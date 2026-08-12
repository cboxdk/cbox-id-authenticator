<?php

declare(strict_types=1);

namespace App\Services;

use Throwable;

/**
 * A glance at the enrolment code before the browser opens.
 *
 * THE APP DOES NOT VERIFY THIS CODE, AND MUST NOT TRY
 * ---------------------------------------------------
 * An earlier version fetched the host's JWKS and checked the signature. That was wrong
 * twice over.
 *
 * Wrong on security: the verifying key comes from the very host the code names, so a
 * signature can only ever say "this host said this" — never "this host is trustworthy".
 * It bought nothing a client could rely on.
 *
 * Wrong on architecture: it assumed every host serving the authenticator is also an
 * OIDC issuer publishing a JWKS. The platform root deliberately is not — see
 * `PlaneResolver::servesIssuer()`, which refuses discovery and JWKS there on the
 * grounds that half an IdP is worse than none — while still legitimately offering
 * trusted devices to its own subjects over first-party token endpoints. So the check
 * could never pass on the platform root no matter what was deployed.
 *
 * The code is VERIFIED BY THE SERVER at `POST /api/v1/devices`, which is the only place
 * that can do it meaningfully: it holds the signing key, it knows which subject actually
 * signed in, and it owns the single-use record. Everything below is a courtesy — reading
 * an unauthenticated `exp` so an obviously stale code fails while the user is still
 * looking at the scanner, rather than after a full sign-in.
 *
 * Nothing here is a security control. If this class let a bad code through, the server
 * would still refuse it.
 */
final class EnrolmentToken
{
    /**
     * Generous on purpose.
     *
     * This is a UX shortcut, not the expiry check — the server's is authoritative and
     * far tighter. Being strict here would mean refusing a code the server would happily
     * have accepted, which is the one way a courtesy check can do harm.
     */
    private const GRACE = 120;

    public function __construct(private readonly ServerClock $clock) {}

    /**
     * @throws EnrolmentCodeRejected when the code is unreadable or plainly stale
     */
    public function precheck(string $token): void
    {
        $claims = self::payload($token);

        if ($claims === null) {
            throw new EnrolmentCodeRejected("That code isn't a Cbox ID enrolment code.");
        }

        $exp = $claims['exp'] ?? null;

        if (is_int($exp) && $exp + self::GRACE < $this->clock->now()) {
            throw new EnrolmentCodeRejected(
                'That code has expired. Open Trusted devices again for a fresh one.'
            );
        }
    }

    /**
     * The claims, WITHOUT verifying anything.
     *
     * Named to make that impossible to miss at a call site. A JWT payload is plain
     * base64url — readable by anyone, guaranteeing nothing.
     *
     * @return array<string, mixed>|null
     */
    private static function payload(string $token): ?array
    {
        $segments = explode('.', $token);

        if (count($segments) !== 3) {
            return null;
        }

        try {
            $decoded = json_decode(
                (string) base64_decode(strtr($segments[1], '-_', '+/'), true),
                true,
                512,
                JSON_THROW_ON_ERROR,
            );
        } catch (Throwable) {
            return null;
        }

        return is_array($decoded) ? $decoded : null;
    }
}
