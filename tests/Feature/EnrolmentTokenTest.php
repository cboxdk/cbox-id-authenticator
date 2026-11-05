<?php

declare(strict_types=1);

use App\Services\EnrolmentCodeRejected;
use App\Services\EnrolmentToken;
use App\Services\ServerClock;

/**
 * The app does NOT verify enrolment codes — see EnrolmentToken for why that was both
 * unsound (the verifying key comes from the host being judged) and unworkable (the
 * platform root deliberately publishes no JWKS). These cover the courtesy glance only:
 * catch an obviously stale code before the user is sent through a whole sign-in.
 *
 * The real checks live in the platform's own suite, tests/Feature/Devices/EnrolmentCodeTest.php.
 */
function code(array $claims = []): string
{
    $b64 = fn (array $part): string => rtrim(strtr(base64_encode(json_encode($part)), '+/', '-_'), '=');

    return $b64(['alg' => 'RS256', 'typ' => 'cbox-enrolment+jwt'])
        .'.'.$b64($claims + ['sub' => 'sub_01ALICE', 'exp' => time() + 120])
        .'.signature-not-checked-here';
}

it('lets a fresh code through', function (): void {
    (new EnrolmentToken(new ServerClock))->precheck(code());
})->throwsNoExceptions();

it('does not care whether the signature is real', function (): void {
    // Stated as a test so nobody "fixes" this later by reaching for a JWKS again. The
    // server holds the signing key and is the only thing that can judge it.
    (new EnrolmentToken(new ServerClock))->precheck(code().'-tampered');
})->throwsNoExceptions();

it('catches a code that is plainly stale', function (): void {
    expect(fn () => (new EnrolmentToken(new ServerClock))->precheck(code(['exp' => time() - 600])))
        ->toThrow(EnrolmentCodeRejected::class, 'expired');
});

it('measures staleness against the server clock', function (): void {
    // A handset running slow must not be able to keep a stale code alive.
    $clock = new ServerClock;
    $clock->syncTo(gmdate('D, d M Y H:i:s \G\M\T', time() + 600));

    expect(fn () => (new EnrolmentToken($clock))->precheck(code(['exp' => time() + 120])))
        ->toThrow(EnrolmentCodeRejected::class, 'expired');
});

it('gives a code near the boundary the benefit of the doubt', function (): void {
    // Being strict here would refuse a code the server would have accepted, which is the
    // one way a courtesy check can do actual harm.
    (new EnrolmentToken(new ServerClock))->precheck(code(['exp' => time() - 30]));
})->throwsNoExceptions();

it('rejects something that is not a token at all', function (string $raw): void {
    expect(fn () => (new EnrolmentToken(new ServerClock))->precheck($raw))
        ->toThrow(EnrolmentCodeRejected::class, "isn't a Cbox ID enrolment code");
})->with([
    'empty' => '',
    'not a jwt' => 'hello',
    'two segments' => 'a.b',
    'payload is not json' => 'aGVhZGVy.bm90LWpzb24!.sig',
]);

it('accepts a code with no expiry rather than inventing one', function (): void {
    // The server decides. A code the app cannot read an `exp` from is the server's
    // problem to refuse, not the app's to guess at.
    (new EnrolmentToken(new ServerClock))->precheck(code(['exp' => null]));
})->throwsNoExceptions();
