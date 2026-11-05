<?php

declare(strict_types=1);

namespace App\Accounts;

use App\Services\SecureVault as SecureStorage;
use Illuminate\Support\Str;
use JsonException;

/**
 * The index of connected accounts.
 *
 * SecureStorage holds STRINGS and nothing else — no lists, no iteration, no way to ask
 * "what keys exist". So the index is a single JSON document under one key, and every
 * secret hangs off {@see Account::keyPrefix()} instead of being enumerable. Losing the
 * index would therefore orphan the secrets rather than merely forget the accounts,
 * which is why {@see put()} refuses to carry on when the write fails.
 *
 * The index itself holds NO secrets — issuer, label, subject. It is in the keychain
 * because that is the only storage this app has, not because a hostname is sensitive.
 */
final class AccountStore
{
    private const INDEX = 'cboxid.accounts';

    /** @var list<Account>|null */
    private ?array $cache = null;

    /**
     * @return list<Account>
     */
    public function all(): array
    {
        if ($this->cache !== null) {
            return $this->cache;
        }

        $raw = SecureStorage::get(self::INDEX);

        if (! is_string($raw) || $raw === '') {
            return $this->cache = [];
        }

        try {
            $rows = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw new AccountStorageFailed('The saved accounts could not be read. Your accounts have not been overwritten.', previous: $e);
        }

        if (! is_array($rows) || ! array_is_list($rows)) {
            throw new AccountStorageFailed('The saved accounts could not be read.');
        }

        $accounts = [];

        foreach ($rows as $row) {
            if (is_array($row) && ($account = Account::fromArray($row)) !== null) {
                $accounts[] = $account;
            } else {
                throw new AccountStorageFailed('A saved account could not be read.');
            }
        }

        return $this->cache = $accounts;
    }

    public function find(string $id): ?Account
    {
        foreach ($this->all() as $account) {
            if ($account->id === $id) {
                return $account;
            }
        }

        return null;
    }

    /**
     * Accounts on one issuer — how a push deep link finds its way home.
     *
     * A list rather than a single account, because two people on the same tenant is
     * exactly the case {@see Account} exists to support. The caller disambiguates.
     *
     * @return list<Account>
     */
    public function forIssuer(string $issuer): array
    {
        $needle = rtrim($issuer, '/');

        return array_values(array_filter(
            $this->all(),
            fn (Account $account): bool => rtrim($account->issuer, '/') === $needle,
        ));
    }

    public function isEmpty(): bool
    {
        return $this->all() === [];
    }

    /**
     * A fresh account id, allocated before sign-in so the DPoP key and install id can
     * be written into their final namespace rather than moved into it afterwards.
     */
    public function newId(): string
    {
        return (string) Str::ulid();
    }

    /**
     * Add or replace an account. Upserts on id, so completing a re-authentication for
     * an account already present updates it rather than listing it twice.
     */
    public function put(Account $account): void
    {
        $accounts = array_values(array_filter(
            $this->all(),
            fn (Account $existing): bool => $existing->id !== $account->id,
        ));

        $accounts[] = $account;

        $this->write($accounts);
    }

    /**
     * Drop an account from the index.
     *
     * Only the index — the secrets under its prefix are the caller's to wipe, because
     * only {@see AccountContext} knows what keys the services own. Removing the index
     * entry first would strand them.
     */
    public function forget(string $id): void
    {
        $this->write(array_values(array_filter(
            $this->all(),
            fn (Account $account): bool => $account->id !== $id,
        )));
    }

    /**
     * @param  list<Account>  $accounts
     */
    private function write(array $accounts): void
    {
        $json = json_encode(
            array_map(fn (Account $account): array => $account->toArray(), $accounts),
            JSON_THROW_ON_ERROR,
        );

        if (! SecureStorage::set(self::INDEX, $json)) {
            // Carrying on would leave the just-written secrets unreachable: they live
            // under a prefix only the index records. Failing here surfaces as "couldn't
            // save this account", which is true and actionable.
            throw new AccountStorageFailed('Could not save the account list on this device.');
        }

        $this->cache = $accounts;
    }
}
