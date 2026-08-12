<?php

declare(strict_types=1);

use App\Services\EnrolmentCode;

it('reads a host and a token out of an enrolment deep link', function (): void {
    $code = EnrolmentCode::parse('com.cboxid.authenticator://connect?host=acme.cboxid.com&t=abc.def.ghi');

    expect($code?->host)->toBe('acme.cboxid.com')
        ->and($code?->token)->toBe('abc.def.ghi');
});

it('accepts a code with no token, for a first-run scan', function (): void {
    expect(EnrolmentCode::parse('acme.cboxid.com')?->token)->toBeNull()
        ->and(EnrolmentCode::parse('https://acme.cboxid.com')?->host)->toBe('acme.cboxid.com');
});

it('refuses anything that is not an address', function (string $raw): void {
    // A QR code is attacker-supplied input. Coercing something unrecognisable into a
    // host is how an app ends up asking a hostile server for its authenticator client.
    expect(EnrolmentCode::parse($raw))->toBeNull();
})->with([
    'plain text' => 'just some text',
    'empty' => '',
    'path smuggled onto a host' => 'acme.cboxid.com/../evil',
    'port and path' => 'acme.cboxid.com:8443/evil',
    'credentials' => 'user:pass@acme.cboxid.com',
    'no tld' => 'acme',
    'our scheme with no host param' => 'com.cboxid.authenticator://connect?t=abc',
    'http url' => 'http://acme.cboxid.com',
]);

it('allows a loopback host so the flow can be tested locally', function (): void {
    // Without this the app can only ever be pointed at a deployed environment, which
    // would put the first end-to-end sign-in test behind a deployment.
    expect(EnrolmentCode::parse('localhost:8000')?->host)->toBe('localhost:8000')
        ->and(EnrolmentCode::parse('127.0.0.1')?->host)->toBe('127.0.0.1');
});

it('lower-cases the host so two spellings are one account', function (): void {
    expect(EnrolmentCode::parse('ACME.CboxID.com')?->host)->toBe('acme.cboxid.com');
});
