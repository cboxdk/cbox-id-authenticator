<?php

declare(strict_types=1);

use App\Accounts\Account;
use App\Accounts\AccountStore;
use App\Accounts\PendingSignIns;
use App\NativeComponents\Connect;
use App\NativeComponents\Launch;
use Illuminate\Support\Facades\Http;
use Native\Mobile\Testing\Native;

/**
 * Component tests run in-process — no simulator, no device. They are the only part of
 * this app verifiable here; anything crossing the native bridge (biometrics, push, the
 * camera) needs a real build. Secure storage is the exception: `fakeSecureStorage()`
 * scripts the bridge, which is what makes the account layer testable at all.
 */
it('renders the connect screen', function (): void {
    fakeSecureStorage();

    Native::test(Connect::class)
        ->assertSee('Cbox ID')
        ->assertSee('Scan the code');
});

it('still renders with accounts already connected', function (): void {
    // The whole point of splitting Launch out. This screen used to bounce to the
    // approvals list the moment a refresh token existed, which made adding a second
    // account impossible — there was no route to the scanner.
    fakeSecureStorage();
    app(AccountStore::class)->put(new Account('01A', 'https://cbox.cboxid.com', 'cbox.cboxid.com'));

    Native::test(Connect::class)->assertSee('Scan the code');
});

it('rejects a scanned code that is not an address', function (): void {
    fakeSecureStorage();

    Native::test(Connect::class)
        ->call('scanned', 'just some text')
        ->assertSet('error', "That code isn't a Cbox ID address.");
});

it('accepts a loopback host over http so the flow can be tested locally', function (): void {
    fakeSecureStorage();

    Http::fake([
        'http://localhost:8000/.well-known/cbox-authenticator' => Http::response([
            'issuer' => 'http://localhost:8000',
            'client_id' => 'cid_local', 'api_version' => 2,
        ]),
    ]);

    Native::test(Connect::class)
        ->call('scanned', 'com.cboxid.authenticator://connect?host=localhost%3A8000');

    // http, not https — the narrow loopback exception. A real hostname must never
    // downgrade, which the sibling spec covers.
    Http::assertSent(fn ($r) => $r->url() === 'http://localhost:8000/.well-known/cbox-authenticator');
});

it('never downgrades a real hostname to http', function (): void {
    fakeSecureStorage();
    Http::fake(['*' => Http::response([], 404)]);

    Native::test(Connect::class)->call('scanned', 'acme.cboxid.com');

    Http::assertSent(fn ($r) => str_starts_with($r->url(), 'https://acme.cboxid.com/'));
});

it('reserves a separate account for each sign-in it starts', function (): void {
    // Two hosts scanned in a row must not share a namespace — that collision is the
    // whole defect this work exists to remove.
    $keychain = fakeSecureStorage();

    Http::fake([
        '*/.well-known/cbox-authenticator' => Http::sequence()
            ->push(['issuer' => 'https://a.cboxid.com', 'client_id' => 'cid_a', 'api_version' => 2])
            ->push(['issuer' => 'https://b.cboxid.com', 'client_id' => 'cid_b', 'api_version' => 2]),
    ]);

    Native::test(Connect::class)->call('scanned', 'a.cboxid.com');
    Native::test(Connect::class)->call('scanned', 'b.cboxid.com');

    $states = array_keys(json_decode($keychain['cboxid.pending'], true));

    // Both attempts live at once; neither overwrote the other.
    expect($states)->toHaveCount(2);

    $pending = app(PendingSignIns::class);
    $issuers = array_map(fn (string $s): ?string => $pending->take($s)?->issuer, $states);
    sort($issuers);

    expect($issuers)->toBe(['https://a.cboxid.com', 'https://b.cboxid.com']);

    // And each reserved its own namespace for the DPoP key the tokens will bind to.
    $keys = array_filter(
        array_keys($keychain->getArrayCopy()),
        fn (string $k): bool => str_ends_with($k, '.dpop.privkey'),
    );

    expect($keys)->toHaveCount(2);
});

it('opens codes with navigation available before connecting a Cbox account', function (): void {
    fakeSecureStorage();

    Native::test(Launch::class)->assertReplacedWith('/codes');

    expect(app(AccountStore::class)->isEmpty())->toBeTrue();
});

it('offers a way back only when there is somewhere to go', function (): void {
    // Adding an account is a deliberate push, and the scanner is the sole control on
    // this screen — so changing your mind, or cancelling the scan, otherwise strands you
    // here with no route to the account list.
    fakeSecureStorage();

    Native::test(Connect::class)
        ->assertSet('canGoBack', false)
        ->assertDontSee('Back');

    app(AccountStore::class)->put(new Account('01A', 'https://cbox.cboxid.com', 'cbox.cboxid.com'));

    Native::test(Connect::class)
        ->assertSet('canGoBack', true)
        ->assertSee('Back');
});
