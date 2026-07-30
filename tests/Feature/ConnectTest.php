<?php

declare(strict_types=1);

use App\NativeComponents\Connect;
use Illuminate\Support\Facades\Http;
use Native\Mobile\Testing\Native;

/**
 * Component tests run in-process — no simulator, no device. They are the only part of
 * this app verifiable here; anything crossing the native bridge (secure storage,
 * biometrics, push, the camera) needs a real build.
 */
it('renders the connect screen', function (): void {
    Native::test(Connect::class)
        ->assertSee('Cbox ID')
        ->assertSee('Scan the code');
});

it('rejects a scanned code that is not an address', function (): void {
    // A QR code is attacker-supplied input. Coercing something unrecognisable into a
    // host is how an app ends up asking a hostile server for its authenticator client.
    Native::test(Connect::class)
        ->call('scanned', 'just some text')
        ->assertSet('error', "That code isn't a Cbox ID address.");
});

it('rejects a code carrying a path or port rather than a bare host', function (): void {
    Native::test(Connect::class)
        ->call('scanned', 'acme.cboxid.com:8443/evil')
        ->assertSet('error', "That code isn't a Cbox ID address.");
});

it('accepts a loopback host over http so the flow can be tested locally', function (): void {
    // Without this the app can only ever be pointed at a deployed environment, which
    // would put the first end-to-end sign-in test behind a deployment.
    Http::fake([
        'http://localhost:8000/.well-known/cbox-authenticator' => Http::response([
            'issuer' => 'http://localhost:8000',
            'client_id' => 'cid_local',
        ]),
    ]);

    // Discovery is as far as this can go in-process: storing the discovered client id
    // needs SecureStorage, which is a native bridge call. So the assertion is on the
    // request that was made, not on reaching a signed-in state.
    Native::test(Connect::class)
        ->call('scanned', 'com.cboxid.authenticator://connect?host=localhost%3A8000');

    // http, not https — the narrow loopback exception. A real hostname must never
    // downgrade, which the sibling spec covers.
    Http::assertSent(fn ($r) => $r->url() === 'http://localhost:8000/.well-known/cbox-authenticator');
});

it('never downgrades a real hostname to http', function (): void {
    Http::fake(['*' => Http::response([], 404)]);

    Native::test(Connect::class)->call('scanned', 'acme.cboxid.com');

    Http::assertSent(fn ($r) => str_starts_with($r->url(), 'https://acme.cboxid.com/'));
});

it('still refuses a hostname that smuggles a path', function (): void {
    Native::test(Connect::class)
        ->call('scanned', 'acme.cboxid.com/../evil')
        ->assertSet('error', "That code isn't a Cbox ID address.");
});
