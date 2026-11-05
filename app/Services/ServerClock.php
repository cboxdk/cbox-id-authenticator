<?php

declare(strict_types=1);

namespace App\Services;

/**
 * How far this handset's clock is from the servers it talks to.
 *
 * THE CLOCK PROBLEM
 * -----------------
 * A DPoP proof carries an `iat` the server accepts only within 60 seconds, and there is
 * no recovery path on this platform: laravel-id implements no `DPoP-Nonce` challenge,
 * which is the mechanism RFC 9449 §8 provides precisely so a server can hand a drifting
 * client a fresh reference point. Without it, a handset whose clock is off by more than
 * a minute gets a hard `invalid_dpop_proof` on every call and the app is simply,
 * inexplicably dead.
 *
 * So the client corrects itself from the `Date` header every response carries. This is
 * a mitigation, not a fix — the real fix is server-side nonce support.
 *
 * DEVICE-WIDE, NOT PER ACCOUNT. Drift is a property of the handset, and the offset is
 * held here rather than inside a proof minter so that a correction learned during
 * discovery — before any account exists — still applies to the token exchange that
 * follows. Held as a container singleton for the same reason: an offset learned by one
 * resolved instance and lost by the next would leave the app correcting itself over and
 * over without ever benefiting.
 *
 * One caveat worth stating: the `Date` header is not authenticated. A network attacker
 * can skew it. They gain only the ability to shift our `iat` within a window the server
 * itself polices, so the exposure is small — but it is the reason this is described as
 * a mitigation.
 */
final class ServerClock
{
    /** Seconds to add to local time to match the server. */
    private int $offset = 0;

    /**
     * Learn the server's clock from a response `Date` header.
     *
     * Cheap and worth doing on every response: drift is not constant, and correcting
     * once per launch would leave a slow-running clock wrong again by evening.
     */
    public function syncTo(?string $serverDate): void
    {
        if ($serverDate === null || $serverDate === '') {
            return;
        }

        $parsed = strtotime($serverDate);

        if ($parsed === false) {
            return;
        }

        $this->offset = $parsed - time();
    }

    public function offset(): int
    {
        return $this->offset;
    }

    public function now(): int
    {
        return time() + $this->offset;
    }
}
