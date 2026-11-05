<?php

declare(strict_types=1);

use App\Accounts\Account;
use App\Accounts\AccountContext;
use App\Accounts\AccountStore;
use App\NativeComponents\Approvals;
use Illuminate\Support\Facades\Http;
use Native\Mobile\Testing\Native;

/**
 * Two accounts, both signed in, with tokens already in place so the API layer does not
 * try to refresh.
 */
function twoAccounts(): void
{
    $store = app(AccountStore::class);

    foreach ([['01A', 'https://a.cboxid.com', 'alice'], ['01B', 'https://b.cboxid.com', 'bob']] as [$id, $issuer, $label]) {
        $account = new Account($id, $issuer, $label);
        $store->put($account);

        $services = app(AccountContext::class)->for($account);
        $services->oauth->rememberClientId('cid_'.$id);
        $services->tokens->put('access-'.$id, 'refresh-'.$id, 900);
    }
}

it('merges pending requests from every account into one list', function (): void {
    // A picker would be less code, but a prompt on whichever account is not selected
    // would sit unseen — and a CIBA request lives 300 seconds.
    fakeSecureStorage();
    twoAccounts();

    Http::fake([
        'https://a.cboxid.com/api/v1/approvals' => Http::response(['data' => [
            ['id' => 'ap1', 'client_name' => 'Payroll', 'expires_at' => gmdate('c', time() + 200)],
        ]]),
        'https://b.cboxid.com/api/v1/approvals' => Http::response(['data' => [
            ['id' => 'ap2', 'client_name' => 'Console', 'expires_at' => gmdate('c', time() + 60)],
        ]]),
    ]);

    $screen = Native::test(Approvals::class);

    $rows = $screen->get('approvals');

    expect($rows)->toHaveCount(2)
        // Soonest to expire first — across hosts that is the only ordering the user is
        // actually deciding on.
        ->and($rows[0]['client_name'])->toBe('Console')
        ->and($rows[0]['account_label'])->toBe('bob')
        ->and($rows[1]['account_label'])->toBe('alice');
});

it('keys each row by account so two hosts can mint the same approval id', function (): void {
    fakeSecureStorage();
    twoAccounts();

    Http::fake([
        'https://a.cboxid.com/api/v1/approvals' => Http::response(['data' => [['id' => 'same']]]),
        'https://b.cboxid.com/api/v1/approvals' => Http::response(['data' => [['id' => 'same']]]),
    ]);

    $rows = Native::test(Approvals::class)->get('approvals');

    expect(array_column($rows, 'row_key'))->toBe(['01A:same', '01B:same']);
});

it('shows the accounts it could still reach when one host is down', function (): void {
    // Letting one failure empty the list would tell someone waiting on an approval that
    // nothing is pending, which is the single worst thing this screen can say.
    fakeSecureStorage();
    twoAccounts();

    Http::fake([
        'https://a.cboxid.com/api/v1/approvals' => Http::response([], 500),
        'https://b.cboxid.com/api/v1/approvals' => Http::response(['data' => [['id' => 'ap2']]]),
    ]);

    $screen = Native::test(Approvals::class);

    expect($screen->get('approvals'))->toHaveCount(1)
        ->and($screen->get('degraded'))->toBe(['alice']);
});

it('keeps what is on screen when every host is unreachable', function (): void {
    fakeSecureStorage();
    twoAccounts();

    // One stub, sequenced: two accounts means two requests per load, so the first pass
    // succeeds for both and the second fails for both. Re-calling Http::fake() would not
    // work — it appends stubs rather than replacing them, and the first match wins.
    Http::fake(['*/api/v1/approvals' => Http::sequence()
        ->push(['data' => [['id' => 'ap1']]])
        ->push(['data' => [['id' => 'ap2']]])
        ->pushStatus(500)
        ->pushStatus(500),
    ]);

    $screen = Native::test(Approvals::class);
    expect($screen->get('approvals'))->toHaveCount(2);

    // Now the network goes away entirely.
    $screen->call('load');

    expect($screen->get('approvals'))->toHaveCount(2)
        ->and($screen->get('degraded'))->toHaveCount(2);
});

it('drops a row the server sent without an id', function (): void {
    fakeSecureStorage();
    twoAccounts();

    Http::fake(['*/api/v1/approvals' => Http::response(['data' => [['client_name' => 'Nameless']]])]);

    expect(Native::test(Approvals::class)->get('approvals'))->toBe([]);
});

it('completes sign-in on a handset that will never receive a push', function (): void {
    // Someone who tapped "Don't Allow", or an account on a self-hosted host with no push
    // transport. Neither is a broken account — the list is fetched with the OAuth token
    // and refreshes on its own — and discarding the sign-in over a lost banner is far
    // the worse outcome. enrol() used to throw here, which did exactly that.
    fakeSecureStorage();

    $account = new Account('01A', 'https://cbox.cboxid.com', 'alice');
    app(AccountStore::class)->put($account);

    $services = app(AccountContext::class)->for($account);

    // No push token: the bridge answers null, as it does when permission was refused.
    $services->oauth->rememberClientId('cid');
    $services->tokens->put('access', 'refresh', 900);
    Http::fake(['*/api/v1/devices' => Http::response(['data' => ['id' => 'device-1']], 201)]);
    expect($services->enrolment->enrol('a-code'))->toBeTrue()
        ->and($services->enrolment->deviceId())->toBe('device-1');
    Http::assertSent(fn ($request) => $request['push_token'] === null && $request['enrolment_token'] === 'a-code');

    // And the account is untouched and usable.
    expect(app(AccountStore::class)->all())->toHaveCount(1);
});
