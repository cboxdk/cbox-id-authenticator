<?php

declare(strict_types=1);

namespace App\NativeComponents;

use App\Accounts\AccountContext;
use App\Accounts\AccountServices;
use App\Concerns\NavigatesScreens;
use App\Services\ApiFailure;
use App\Services\DeviceSecurity;
use App\Services\ServerClock;
use App\Support\DeepLinkQuery;
use Illuminate\View\View;
use Native\Mobile\Attributes\Computed;
use Native\Mobile\Attributes\On;
use Native\Mobile\Attributes\Poll;
use Native\Mobile\Edge\NativeComponent;
use Native\Mobile\Events\Biometric\Completed;
use Throwable;

class ApprovalDetail extends NativeComponent
{
    use NavigatesScreens;

    /** @var array<string, mixed>|null */
    public ?array $approval = null;

    public bool $missing = false;

    public bool $working = false;

    public ?string $outcome = null;

    public ?string $accountLabel = null;

    public ?string $error = null;

    public ?string $biometricAttempt = null;

    /** Bumped to send the slide's thumb back to the start (a cancelled Face ID, an error). */
    public int $slideReset = 0;

    /** Deny asks once more, so a stray tap beside the slider cannot decline either. */
    public bool $confirmingDeny = false;

    /**
     * Opened from the sign-in page's "Open Cbox ID app" link, on this same phone. After
     * approving, the person goes back to that browser; see {@see self::render()}.
     */
    public bool $fromLink = false;

    private ?string $attemptRequest = null;

    private ?int $attemptGeneration = null;

    private bool $biometricSucceeded = false;

    public function mount(): void
    {
        $this->fromLink = $this->param('via') === 'link';
        $this->load();
    }

    public function onResume(): void
    {
        $this->cancelAuthentication();
        $this->load();
    }

    public function unmount(): void
    {
        $this->cancelAuthentication();
        parent::unmount();
    }

    private function services(): ?AccountServices
    {
        return app(AccountContext::class)->forId((string) $this->param('account'));
    }

    private function approvalId(): string
    {
        return DeepLinkQuery::cleanParam((string) $this->param('id'));
    }

    #[Poll(1000)]
    public function tick(): void
    {
        if ($this->approval !== null && $this->outcome === null && $this->secondsLeft() <= 0) {
            $this->cancelAuthentication();
            $this->outcome = 'expired';

            return;
        }

        $this->finishAuthentication();
    }

    #[Poll(5000)]
    public function refresh(): void
    {
        if (! $this->working && $this->biometricAttempt === null && $this->outcome === null && app(DeviceSecurity::class)->generation() !== null) {
            $this->load();
        }
    }

    #[Computed]
    public function secondsLeft(): int
    {
        $expires = $this->approval['expires_at'] ?? null;

        return is_string($expires) ? max(0, (strtotime($expires) ?: 0) - app(ServerClock::class)->now()) : 0;
    }

    public function approve(): void
    {
        if (! $this->actionable() || $this->biometricAttempt !== null) {
            return;
        }

        $this->attemptGeneration = app(DeviceSecurity::class)->generation();
        if ($this->attemptGeneration === null) {
            $this->error = 'Unlock the app before approving a request.';

            return;
        }
        $this->error = null;
        $this->biometricAttempt = bin2hex(random_bytes(24));
        $this->attemptRequest = (string) $this->param('account').'/'.$this->approvalId();

        try {
            if (app(DeviceSecurity::class)->canReuseAuthentication($this->attemptGeneration)) {
                $this->biometricSucceeded = true;
                $this->finishAuthentication();

                return;
            }
            // Dispatch only acknowledges the prompt. Completed confirms a fresh
            // authentication when the native gate has no recent one to reuse.
            if (! app(DeviceSecurity::class)->authenticate($this->biometricAttempt)) {
                $this->cancelAuthentication();
                $this->error = 'Could not start device authentication. Try again.';
            }
        } catch (Throwable) {
            $this->cancelAuthentication();
            $this->error = 'Could not start device authentication. Try again.';
        }
    }

    /**
     * The slider reached the end. The same path as a tap on Approve used to take: device
     * authentication still follows, and nothing is sent until it succeeds.
     */
    public function slid(bool $completed = true): void
    {
        $this->confirmingDeny = false;
        if ($completed) {
            $this->approve();
        }
        if ($this->biometricAttempt === null && $this->outcome === null && ! $this->working) {
            $this->slideReset++;
        }
    }

    public function askDeny(): void
    {
        if ($this->actionable()) {
            $this->confirmingDeny = true;
        }
    }

    public function keepRequest(): void
    {
        $this->confirmingDeny = false;
    }

    #[On(Completed::class)]
    public function biometricCompleted(bool $success, ?string $id = null): void
    {
        if ($id === null || $this->biometricAttempt === null || ! hash_equals($this->biometricAttempt, $id)) {
            return;
        }

        if (! $success) {
            $this->cancelAuthentication();
            $this->slideReset++;
            $this->error = 'Authentication cancelled. No approval was sent.';

            return;
        }

        $this->biometricSucceeded = true;
        $this->finishAuthentication();
    }

    private function finishAuthentication(): void
    {
        if (! $this->biometricSucceeded || $this->biometricAttempt === null) {
            return;
        }

        // LAContext may finish before iOS becomes active again. Keep the result
        // until the native gate permits access; a real background transition still
        // changes the generation and invalidates this attempt on return.
        $currentGeneration = app(DeviceSecurity::class)->generation();
        if ($currentGeneration === null) {
            return;
        }

        $request = $this->attemptRequest;
        $generation = $this->attemptGeneration;
        $this->cancelAuthentication(); // Consume before any network call; an event cannot approve twice.

        if ($generation === null || $generation !== $currentGeneration) {
            $this->slideReset++;
            $this->error = 'Authentication expired when the app was locked. Try again.';

            return;
        }

        if ($request !== (string) $this->param('account').'/'.$this->approvalId()) {
            return;
        }

        $this->decide(true);
    }

    public function cancelAuthentication(): void
    {
        $this->biometricAttempt = null;
        $this->attemptRequest = null;
        $this->attemptGeneration = null;
        $this->biometricSucceeded = false;
    }

    public function deny(): void
    {
        $this->confirmingDeny = false;
        $this->cancelAuthentication();
        $this->decide(false);
    }

    public function wasNotMe(): void
    {
        $this->confirmingDeny = false;
        $this->cancelAuthentication();
        $this->decide(false, 'not_me');
    }

    private function actionable(): bool
    {
        return ! $this->working && ! $this->missing && $this->approval !== null
            && $this->outcome === null && $this->secondsLeft() > 0;
    }

    private function decide(bool $approve, ?string $reason = null): void
    {
        if (! $this->actionable()) {
            $this->tick();

            return;
        }

        $services = $this->services();
        if ($services === null) {
            $this->missing = true;

            return;
        }

        $this->working = true;
        $this->error = null;

        try {
            $settled = $approve
                ? $services->api->approve($this->approvalId())
                : $services->api->deny($this->approvalId(), $reason);
            if ($settled) {
                $this->outcome = $approve ? 'approved' : ($reason === 'not_me' ? 'reported' : 'denied');
            } else {
                $this->load();
                $this->outcome ??= 'stale';
            }
        } catch (Throwable $e) {
            // A lost response is not a rejection. Reconcile once with the server,
            // without ever repeating a decision automatically.
            $this->load();
            if ($this->outcome === null) {
                $this->error = $e instanceof ApiFailure ? $e->getMessage()
                    : 'Could not confirm the result. Refresh before trying again.';
            }
            $this->slideReset++;
        } finally {
            $this->working = false;
        }
    }

    public function load(): void
    {
        $services = $this->services();
        if ($services === null) {
            $this->missing = true;

            return;
        }

        $this->accountLabel = $services->account->label;
        try {
            $this->approval = $services->api->approval($this->approvalId());
            $this->missing = $this->approval === null;
            $this->error = null;
            $status = $this->approval['status'] ?? 'pending';
            if (in_array($status, ['approved', 'denied', 'expired', 'reported'], true)) {
                $this->outcome = $status;
                $this->cancelAuthentication();
            }
        } catch (Throwable $e) {
            $this->error = $e instanceof ApiFailure ? $e->getMessage()
                : 'Could not reach your Cbox ID host. Check your connection and try again.';
        }
    }

    /**
     * What the screen says about the request, from the server's answer only.
     *
     * `kind` and `origin` are newer fields; an older host sends neither, and the browser
     * step-up's binding message ("Confirm sign-in · host · 123456") still says which it is.
     * The comparison code is lifted out so it can be the largest thing on the screen.
     *
     * @return array{kind: string, app: string, code: ?string, message: ?string, host: ?string, location: ?string, device: ?string, requested: ?int}
     */
    #[Computed]
    public function details(): array
    {
        $approval = $this->approval ?? [];
        $message = is_string($approval['binding_message'] ?? null) && $approval['binding_message'] !== '' ? $approval['binding_message'] : null;
        $origin = is_array($approval['origin'] ?? null) ? $approval['origin'] : [];
        $text = static fn (mixed $value): ?string => is_string($value) && trim($value) !== '' ? mb_substr(trim($value), 0, 120) : null;

        $kind = $text($approval['kind'] ?? null);
        $code = null;
        $host = $text($origin['host'] ?? null);
        if ($message !== null && preg_match('/^(Confirm sign-in|Confirm security access) · (.{1,210}) · (\d{6})$/u', $message, $parts) === 1) {
            $kind ??= $parts[1] === 'Confirm sign-in' ? 'sign_in' : 'step_up';
            $host ??= $parts[2];
            $code = $parts[3];
            $message = null;
        }
        $kind ??= ($approval['purpose'] ?? null) === 'action' ? 'action' : 'authentication';

        $place = array_values(array_filter([$text($origin['city'] ?? null), $text($origin['country'] ?? null)]));
        $requested = is_string($approval['requested_at'] ?? null) ? strtotime($approval['requested_at']) : false;

        return [
            'kind' => in_array($kind, ['sign_in', 'step_up', 'action', 'authentication'], true) ? $kind : 'authentication',
            // For a sign-in, the app being signed in to; the requesting client is Cbox ID's own.
            'app' => $text($origin['app'] ?? null) ?? $text($approval['client_name'] ?? null) ?? __('An application'),
            'code' => $code,
            'message' => $message,
            'host' => $host,
            'location' => $place === [] ? null : implode(', ', $place),
            'device' => $text($origin['device'] ?? null),
            'requested' => $requested === false ? null : max(0, app(ServerClock::class)->now() - $requested),
        ];
    }

    public function render(): View
    {
        return view('native.approval-detail');
    }
}
