<?php

declare(strict_types=1);

use App\NativeComponents\Connect;
use Native\Mobile\Testing\Native;

/**
 * Component tests run in-process — no simulator, no device. They are the only part of
 * this app that can be verified here; anything touching the native bridge (secure
 * storage, biometrics, push) needs a real build, which is Spike A's job.
 */
it('renders the connect screen', function (): void {
    Native::test(Connect::class)
        ->assertSee('Cbox ID')
        ->assertSee('Your Cbox ID address');
});

it('refuses to continue without a host', function (): void {
    Native::test(Connect::class)
        ->set('host', '  ')
        ->call('connect')
        ->assertSet('error', 'Enter the address your organisation gave you.');
});
