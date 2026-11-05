<?php

declare(strict_types=1);

namespace App\Services;

use App\Accounts\Account;
use Illuminate\Http\Client\Pool;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * The device and approvals API on ONE account's Cbox ID host.
 *
 * Every call is DPoP-bound, and every response feeds the clock offset back so a
 * drifting handset self-corrects rather than dying on the server's 60-second `iat`
 * window.
 *
 * The base URL comes from the account rather than from a stored "current issuer".
 * With several accounts connected there is no such thing as the current issuer, and a
 * single slot holding one would have sent one tenant's DPoP-bound token to another.
 */
final class CboxApi
{
    public function __construct(
        private readonly Account $account,
        private readonly DpopProof $proof,
        private readonly OAuthClient $oauth,
    ) {}

    /**
     * Enrol this installation, or re-enrol after the push token rotates.
     *
     * The endpoint is idempotent on `install_id`, so this is also the rotation call —
     * there is no separate one to get wrong, and no ordering hazard between rotating
     * and re-reading the device id.
     *
     * `$enrolmentToken` is the short-lived code the QR carried. The server requires it
     * on a first enrolment and binds it to the signed-in subject, which is what stops a
     * code scanned from someone else's screen from attaching this handset to their
     * account. A push-token rotation has no code to present and sends none.
     *
     * @return array<string, mixed>
     */
    public function enrolDevice(
        string $installId,
        ?string $pushToken,
        string $name,
        string $platform,
        ?string $enrolmentToken = null,
    ): array {
        $body = [
            'install_id' => $installId,
            'platform' => $platform,
            'push_token' => $pushToken,
            'name' => $name,
            'app_version' => (string) config('nativephp.version', 'dev'),
        ];

        if ($enrolmentToken !== null && $enrolmentToken !== '') {
            $body['enrolment_token'] = $enrolmentToken;
        }

        return $this->json('POST', '/devices', $body);
    }

    /**
     * @return array{subject: string, label: string}
     */
    public function identity(): array
    {
        $data = $this->json('GET', '/authenticator/me')['data'] ?? null;
        if (! is_array($data) || ! is_string($data['subject'] ?? null) || $data['subject'] === '') {
            throw new ApiFailure(502);
        }

        return ['subject' => $data['subject'], 'label' => is_string($data['label'] ?? null) ? $data['label'] : $data['subject']];
    }

    public function queuePending(Pool $pool): void
    {
        $token = $this->oauth->freshAccessToken();
        $url = $this->base().'/approvals';
        $pool->as($this->account->id)->withHeaders($this->proof->headers('GET', $url, $token))
            ->acceptJson()->withoutRedirecting()->connectTimeout(4)->timeout(8)->get($url);
    }

    /** @return list<array<string, mixed>> */
    public function pendingFromResponse(Response $response): array
    {
        $this->proof->syncTo($response->header('Date'));
        if ($response->status() === 401) {
            // Refresh and retry only this account; the other pooled responses survive.
            $this->oauth->refresh();

            return $this->pendingApprovals();
        }
        if (! $response->successful()) {
            throw new ApiFailure($response->status(), $response->json('error'));
        }
        $body = $response->json();

        return $this->pendingPages(is_array($body) ? $body : []);
    }

    /** @return list<array<string, mixed>> */
    public function pendingApprovals(): array
    {
        return $this->pendingPages($this->json('GET', '/approvals'));
    }

    /** @param array<string, mixed> $page
     * @return list<array<string, mixed>>
     */
    private function pendingPages(array $page): array
    {
        $rows = [];
        $seen = [];
        for ($number = 0; $number < 10; $number++) {
            $data = $page['data'] ?? [];
            if (! is_array($data)) {
                throw new ApiFailure(502);
            }
            array_push($rows, ...array_values(array_filter($data, 'is_array')));
            if (($page['meta']['has_more'] ?? false) !== true) {
                return $rows;
            }
            $cursor = $page['meta']['next_cursor'] ?? null;
            if (! is_string($cursor) || preg_match('/^[0-9A-HJKMNP-TV-Z]{26}$/i', $cursor) !== 1 || isset($seen[$cursor])) {
                throw new ApiFailure(502);
            }
            $seen[$cursor] = true;
            $page = $this->json('GET', '/approvals?cursor='.rawurlencode($cursor));
        }

        // Never present a truncated inbox as complete or follow an unbounded server loop.
        throw new ApiFailure(502);
    }

    /**
     * One approval, in full.
     *
     * This is where `binding_message` comes from — the transaction description the user
     * compares against what the requesting app showed them. It is deliberately absent
     * from the push payload, because a lock screen is readable by anyone holding the
     * handset, so a notification tap MUST land here before anything is rendered. The
     * push carries routing only and is treated as untrusted.
     *
     * @return array<string, mixed>|null
     */
    public function approval(string $id): ?array
    {
        try {
            $data = $this->json('GET', '/approvals/'.rawurlencode($id))['data'] ?? null;

            return is_array($data) ? $data : null;
        } catch (ApiFailure $e) {
            if ($e->status === 404) {
                return null;
            }
            throw $e;
        }
    }

    public function approve(string $id): bool
    {
        return $this->decide($id, 'approve');
    }

    public function deny(string $id, ?string $reason = null): bool
    {
        return $this->decide($id, 'deny', $reason);
    }

    public function deregisterDevice(string $deviceId): void
    {
        $response = $this->request('DELETE', '/devices/'.rawurlencode($deviceId));
        if (! $response->successful() && $response->status() !== 404) {
            throw new ApiFailure($response->status(), $response->json('error'));
        }
    }

    private function decide(string $id, string $action, ?string $reason = null): bool
    {
        $id = rawurlencode($id);
        $response = $this->request('POST', "/approvals/{$id}/{$action}", $reason === null ? [] : ['reason' => $reason]);
        if (! $response->successful() && $response->status() !== 409) {
            throw new ApiFailure($response->status(), $response->json('error'));
        }

        // 409 means no longer actionable — expired, already answered, or never the
        // caller's. The server collapses those on purpose so a stolen handset cannot
        // learn which request ids are real, and the app must not try to tell them apart
        // either. It re-fetches and shows "already handled".
        return $response->successful();
    }

    /**
     * @param  array<string, mixed>  $body
     * @return array<string, mixed>
     */
    private function json(string $method, string $path, array $body = []): array
    {
        $response = $this->request($method, $path, $body);

        if (! $response->successful()) {
            throw new ApiFailure($response->status(), $response->json('error'));
        }

        $decoded = $response->json();

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * @param  array<string, mixed>  $body
     */
    private function request(string $method, string $path, array $body = []): Response
    {
        $token = $this->oauth->freshAccessToken();
        $url = $this->base().$path;

        $response = Http::withHeaders($this->proof->headers($method, $url, $token))
            ->withoutRedirecting()->connectTimeout(5)->acceptJson()
            ->timeout(15)
            ->send($method, $url, $body === [] ? [] : ['json' => $body]);

        $this->proof->syncTo($response->header('Date'));

        // One retry on 401, and only one. A proof rejected for clock drift becomes
        // valid the moment syncTo() above has corrected the offset, so the retry is
        // what turns an unexplainable dead app into a self-healing one. Looping would
        // hide a genuine auth failure instead.
        if ($response->status() === 401) {
            $token = $this->oauth->refresh();

            $response = Http::withHeaders($this->proof->headers($method, $url, $token))
                ->withoutRedirecting()->connectTimeout(5)->acceptJson()
                ->timeout(15)
                ->send($method, $url, $body === [] ? [] : ['json' => $body]);

            $this->proof->syncTo($response->header('Date'));
        }

        return $response;
    }

    private function base(): string
    {
        return rtrim($this->account->issuer, '/').'/api/v1';
    }
}
