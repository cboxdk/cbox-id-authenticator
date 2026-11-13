<?php

declare(strict_types=1);

use App\Accounts\Account;
use App\NativeComponents\ApprovalDetail;
use Illuminate\Support\Facades\Http;
use Native\Mobile\Events\Biometric\Completed;
use Native\Mobile\Testing\Native;

it('renders the decision screen for a live request', function (): void {
    // The screen's whole purpose, and it had never been rendered by a test. <x-btn>
    // named a component that does not exist, so Blade threw while compiling the
    // template — every actionable approval, on every account.
    fakeSecureStorage();
    anAccount();

    Http::fake(['*/api/v1/approvals/*' => Http::response(['data' => [
        'id' => '01APPROVAL',
        'client_name' => 'Payroll',
        'binding_message' => 'Transfer 4.000 kr to Acme ApS',
        'expires_at' => gmdate('c', time() + 120),
        'scopes' => [['label' => 'Read your profile']],
    ]])]);

    Native::test(ApprovalDetail::class, ['account' => '01ACC', 'id' => '01APPROVAL'])
        ->assertSee('Payroll wants access')
        // Approving is a slide, never a tap: the only approve control is the slider.
        ->assertElement('slide_confirm', fn (array $n): bool => ($n['props']['label'] ?? null) === 'Slide to approve'
            && ($n['props']['a11y_label'] ?? null) === 'Approve' && ($n['props']['on_change'] ?? 0) !== 0)
        ->assertSee('Deny')
        // The binding message is the anti-phishing primitive; it must be on screen
        // before any button is.
        ->assertSee('Transfer 4.000 kr to Acme ApS')
        ->assertSee('alice');
});

it('strips a query string the router left on the approval id', function (): void {
    // A notification deep link carries ?iss=…, and `[^/]+` does not stop at a `?`.
    // Asking the server for `01APPROVAL?iss=…` matches nothing, so a live request
    // would render as gone.
    fakeSecureStorage();
    anAccount();

    Http::fake(['*' => Http::response(['data' => ['id' => '01APPROVAL']])]);

    Native::test(ApprovalDetail::class, [
        'account' => '01ACC',
        'id' => '01APPROVAL?iss=https://cbox.cboxid.com',
    ]);

    Http::assertSent(fn ($r): bool => str_ends_with($r->url(), '/approvals/01APPROVAL'));
});

it('reads as gone when the account was signed out on another screen', function (): void {
    fakeSecureStorage();

    Native::test(ApprovalDetail::class, ['account' => '01GONE', 'id' => '01APPROVAL'])
        ->assertSet('missing', true)
        ->assertSee('no longer available');
});

it('says sign-in, puts the comparison code first and shows where the request came from', function (): void {
    fakeSecureStorage();
    anAccount();
    Http::fake(['*/api/v1/approvals/*' => Http::response(['data' => [
        'id' => '01APPROVAL', 'purpose' => 'action', 'kind' => 'sign_in', 'client_name' => 'Cbox Billing',
        'binding_message' => 'Confirm sign-in · login.cboxid.com · 482913',
        'origin' => ['city' => 'Copenhagen', 'country' => 'Denmark', 'country_code' => 'DK', 'device' => 'Safari on iOS', 'host' => 'login.cboxid.com'],
        'requested_at' => gmdate('c', time() - 5), 'expires_at' => gmdate('c', time() + 272),
    ]])]);

    Native::test(ApprovalDetail::class, ['account' => '01ACC', 'id' => '01APPROVAL'])
        ->assertSee('Sign in to Cbox Billing?')
        ->assertSee('SIGN-IN REQUEST')
        ->assertSee('482 913')
        ->assertSee('Copenhagen, Denmark')
        ->assertSee('Safari on iOS')
        ->assertSee('login.cboxid.com')
        ->assertSee('Just now')
        ->assertSee('4:32')
        // The raw binding message is replaced by its parts, not shown twice.
        ->assertDontSee('Confirm sign-in · login.cboxid.com · 482913');
});

it('reads an older host that sends neither kind nor origin', function (): void {
    fakeSecureStorage();
    anAccount();
    Http::fake(['*/api/v1/approvals/*' => Http::response(['data' => [
        'id' => '01APPROVAL', 'purpose' => 'action', 'client_name' => 'Cbox step-up',
        'binding_message' => 'Confirm security access · app.cboxid.com · 120034',
        'expires_at' => gmdate('c', time() + 60),
    ]])]);

    Native::test(ApprovalDetail::class, ['account' => '01ACC', 'id' => '01APPROVAL'])
        ->assertSee("Confirm it's you")
        ->assertSee('120 034')
        ->assertSee('app.cboxid.com')
        ->assertDontSee('Near');
});

it('asks before denying, and offers the report beside it', function (): void {
    fakeSecureStorage();
    anAccount();
    Http::fake(['*/deny' => Http::response(['status' => 'denied']), '*/api/v1/approvals/*' => Http::response(['data' => [
        'id' => '01APPROVAL', 'client_name' => 'Payroll', 'binding_message' => 'Pay 10 kr', 'expires_at' => gmdate('c', time() + 60),
    ]])]);

    $screen = Native::test(ApprovalDetail::class, ['account' => '01ACC', 'id' => '01APPROVAL'])
        ->assertDontSee("This wasn't me")
        ->call('askDeny')
        ->assertSet('confirmingDeny', true)
        ->assertSee('Deny this request?')
        ->assertSee("This wasn't me")
        ->assertMissingElement('slide_confirm');
    Http::assertNotSent(fn ($request) => $request->method() === 'POST');
    $screen->call('keepRequest')->assertSet('confirmingDeny', false)->assertElement('slide_confirm');
    $screen->call('askDeny')->call('deny')->assertSet('outcome', 'denied');
    Http::assertSent(fn ($request) => str_ends_with($request->url(), '/deny'));
});

it('sends the person back to the browser after approving from the sign-in page link', function (): void {
    fakeSecureStorage();
    anAccount();
    Native::fakeBridge()->respondTo('AuthenticatorSecurity.State', [
        'unlocked' => true, 'foreground' => true, 'generation' => 0, 'recentAuthentication' => true,
    ]);
    Http::fake(['*/approve' => Http::response(['status' => 'approved']), '*/api/v1/approvals/*' => Http::response(['data' => [
        'id' => '01APPROVAL', 'kind' => 'sign_in', 'client_name' => 'Cbox Billing',
        'binding_message' => 'Confirm sign-in · login.cboxid.com · 482913', 'expires_at' => gmdate('c', time() + 60),
    ]])]);

    Native::test(ApprovalDetail::class, ['account' => '01ACC', 'id' => '01APPROVAL', 'via' => 'link'])
        ->assertSet('fromLink', true)
        ->call('slid', true)
        ->assertSet('outcome', 'approved')
        ->assertSee('Go back to your browser to finish signing in.')
        ->assertSee('Tap ◀ in the top-left corner. The page continues by itself.');
});

it('returns the slider to the start when Face ID is cancelled, and approves nothing', function (): void {
    fakeSecureStorage();
    anAccount();
    Native::fakeBridge()->respondTo('AuthenticatorSecurity.Authenticate', ['started' => true]);
    Http::fake(['*/api/v1/approvals/*' => Http::response(['data' => [
        'id' => '01APPROVAL', 'client_name' => 'Payroll', 'binding_message' => 'Pay 10 kr', 'expires_at' => gmdate('c', time() + 60),
    ]])]);

    $screen = Native::test(ApprovalDetail::class, ['account' => '01ACC', 'id' => '01APPROVAL'])
        ->call('slid', true)
        ->assertElement('slide_confirm', fn (array $n): bool => ($n['props']['busy'] ?? false) === true);
    $screen->emitNative(Completed::class, ['success' => false, 'id' => $screen->get('biometricAttempt')])
        ->assertSet('slideReset', 1)
        ->assertElement('slide_confirm', fn (array $n): bool => ($n['props']['reset'] ?? null) === 1 && ($n['props']['busy'] ?? true) === false);
    Http::assertNotSent(fn ($request) => $request->method() === 'POST');
});
