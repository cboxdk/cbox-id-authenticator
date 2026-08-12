<?php

declare(strict_types=1);

use App\Accounts\Account;
use App\Accounts\AccountContext;
use App\Accounts\AccountStore;
use App\NativeComponents\ApprovalDetail;
use Illuminate\Support\Facades\Http;
use Native\Mobile\Testing\Native;

function anAccount(): Account
{
    $account = new Account('01ACC', 'https://cbox.cboxid.com', 'alice');

    app(AccountStore::class)->put($account);

    $services = app(AccountContext::class)->for($account);
    $services->oauth->rememberClientId('cid_a');
    $services->tokens->put('access', 'refresh', 900);

    return $account;
}

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
        ->assertSee('Approve this request?')
        ->assertSee('Approve')
        ->assertSee('Deny')
        ->assertSee("This wasn't me")
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
