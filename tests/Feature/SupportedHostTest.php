<?php

declare(strict_types=1);

use App\NativeComponents\Connect;
use App\Services\SupportedHost;
use Illuminate\Support\Facades\Http;
use Native\Mobile\Testing\Native;

it('admits the apex and anything under it', function (string $host): void {
    expect(SupportedHost::allows($host))->toBeTrue();
})->with([
    'apex' => 'cboxid.com',
    'tenant' => 'cbox.cboxid.com',
    'deep subdomain' => 'a.b.cboxid.com',
    'upper case' => 'CBOX.CboxID.com',
    'loopback' => 'localhost:8000',
]);

it('refuses a host that merely ends in the same letters', function (): void {
    // The reason the comparison carries a leading dot. A plain str_ends_with would let
    // an attacker register notcboxid.com and be treated as ours.
    expect(SupportedHost::allows('notcboxid.com'))->toBeFalse()
        ->and(SupportedHost::allows('evilcboxid.com'))->toBeFalse();
});

it('refuses a self-hosted installation', function (): void {
    expect(SupportedHost::allows('id.acme.dk'))->toBeFalse();
});

it('follows the configured suffixes rather than a compiled-in name', function (): void {
    // The one value an operator building their own binary changes.
    config(['cboxid.allowed_host_suffixes' => ['id.acme.dk']]);

    expect(SupportedHost::allows('id.acme.dk'))->toBeTrue()
        ->and(SupportedHost::allows('sso.id.acme.dk'))->toBeTrue()
        ->and(SupportedHost::allows('cboxid.com'))->toBeFalse();
});

it('never contacts a host it will not connect to', function (): void {
    // Discovery first would tell that server a Cbox ID app had scanned it, and would
    // report the wrong reason when it answered.
    fakeSecureStorage();
    Http::fake();

    Native::test(Connect::class)
        ->call('scanned', 'id.acme.dk')
        ->assertSee('connects to cboxid.com accounts only');

    Http::assertNothingSent();
});

it('separates "not an address" from "not a host we serve"', function (): void {
    // Two different places to go looking, so two different messages.
    fakeSecureStorage();
    Http::fake();

    Native::test(Connect::class)
        ->call('scanned', 'just some text')
        ->assertSet('error', "That code isn't a Cbox ID address.");
});
