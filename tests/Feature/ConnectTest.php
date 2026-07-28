<?php

declare(strict_types=1);

use App\NativeComponents\Connect;
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
