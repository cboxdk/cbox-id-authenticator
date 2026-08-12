<?php

declare(strict_types=1);

namespace App\Accounts;

use JsonException;
use Native\Mobile\Facades\SecureStorage;

/**
 * Sign-ins in flight, keyed by their OAuth `state`.
 *
 * WHY THIS EXISTS AT ALL
 * ----------------------
 * The PKCE verifier and state used to live under two fixed keys. That is a bug even
 * with a single account — tap "Scan the code", abandon it, start again, and the second
 * attempt overwrites the first's verifier; whichever callback arrives first then fails
 * the exchange with "this sign-in attempt has expired". Keying on `state` is what makes
 * concurrent attempts independent, and multi-account makes concurrency ordinary rather
 * than an edge case.
 *
 * All records live in ONE JSON document rather than a key each, because SecureStorage
 * cannot enumerate keys — without a single document there would be no way to find, and
 * therefore no way to expire, an abandoned attempt.
 */
final class PendingSignIns
{
    private const KEY = 'cboxid.pending';

    /**
     * Ten minutes. Long enough for a slow sign-in with an MFA challenge and a password
     * manager; short enough that an abandoned attempt does not keep a DPoP key alive in
     * the keychain indefinitely.
     */
    private const TTL = 600;

    public function put(PendingSignIn $pending): void
    {
        $records = $this->records();
        $records[$pending->state] = $pending->toArray();

        $this->write($records);
    }

    /**
     * Read and remove in one step — a `state` answers exactly once.
     *
     * Single-use is not tidiness: it is what stops a callback URL, which lands in the
     * OS's deep-link handling and may be replayed, from being exchanged twice.
     */
    public function take(string $state): ?PendingSignIn
    {
        $records = $this->records();
        $row = $records[$state] ?? null;

        unset($records[$state]);
        $this->write($records);

        if (! is_array($row)) {
            return null;
        }

        $pending = PendingSignIn::fromArray($row);

        if ($pending === null || $this->hasExpired($pending)) {
            return null;
        }

        return $pending;
    }

    /**
     * Drop attempts that were never completed.
     *
     * Returns the account ids they had reserved so the caller can wipe the DPoP key and
     * install id that were written into those namespaces when the flow started.
     *
     * @return list<string>
     */
    public function sweep(): array
    {
        $records = $this->records();
        $abandoned = [];

        foreach ($records as $state => $row) {
            $pending = is_array($row) ? PendingSignIn::fromArray($row) : null;

            if ($pending === null || $this->hasExpired($pending)) {
                $abandoned[] = is_array($row) && is_string($row['account_id'] ?? null)
                    ? $row['account_id']
                    : null;

                unset($records[$state]);
            }
        }

        $this->write($records);

        return array_values(array_filter($abandoned, is_string(...)));
    }

    private function hasExpired(PendingSignIn $pending): bool
    {
        return $pending->createdAt <= 0 || $pending->createdAt + self::TTL < time();
    }

    /**
     * @return array<string, mixed>
     */
    private function records(): array
    {
        $raw = SecureStorage::get(self::KEY);

        if (! is_string($raw) || $raw === '') {
            return [];
        }

        try {
            $decoded = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return [];
        }

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * @param  array<string, mixed>  $records
     */
    private function write(array $records): void
    {
        if ($records === []) {
            SecureStorage::delete(self::KEY);

            return;
        }

        SecureStorage::set(self::KEY, json_encode($records, JSON_THROW_ON_ERROR));
    }
}
