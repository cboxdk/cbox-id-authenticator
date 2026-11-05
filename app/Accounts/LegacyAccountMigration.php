<?php

declare(strict_types=1);

namespace App\Accounts;

use App\Services\SecureVault as SecureStorage;

/**
 * Carries a single-account install into the account-keyed layout.
 *
 * Before accounts existed, every secret sat under a fixed key. An upgrade that simply
 * started reading the new namespaces would find nothing, and the user would open the app
 * to a connect screen with no explanation — signed out of an account they never left,
 * and with the old keys still occupying the keychain.
 *
 * IDEMPOTENT AND ORDERED. The old keys are removed only after the new ones are written,
 * so a process killed half-way re-runs harmlessly on the next launch: the refresh token
 * is still where this looks for it, and the copy simply happens again. The index entry
 * is written LAST, for the same reason the sign-in flow writes it last — an account
 * listed before its secrets exist is an account that renders as broken.
 */
final class LegacyAccountMigration
{
    /**
     * Old key => new key, relative to the account prefix.
     */
    private const MOVES = [
        'cboxid.token.access' => 'token.access',
        'cboxid.token.refresh' => 'token.refresh',
        'cboxid.token.expires' => 'token.expires',
        'cboxid.client_id' => 'client_id',
        'cboxid.dpop.privkey' => 'dpop.privkey',
        'cboxid.install_id' => 'install_id',
    ];

    /**
     * Keys from the old layout that have no home in the new one.
     *
     * The PKCE pair belonged to one in-flight attempt. Any attempt still outstanding
     * across an upgrade is unfinishable anyway — the verifier's `state` is gone with the
     * code that would have matched it — so these are dropped rather than moved.
     */
    private const DISCARD = [
        'cboxid.pkce.verifier',
        'cboxid.pkce.state',
    ];

    public function __construct(private readonly AccountStore $store) {}

    public function run(): void
    {
        // Once any account exists, the migration has either run or was never needed.
        // Reading the index is free — it is cached on the shared store — and it keeps
        // this from touching the keychain on every launch for the rest of the app's life.
        if (! $this->store->isEmpty()) {
            return;
        }

        $refresh = SecureStorage::get('cboxid.token.refresh');
        $issuer = SecureStorage::get('cboxid.issuer');

        // No old install, or one that never completed a sign-in. Either way there is
        // nothing worth carrying: without an issuer the tokens cannot be aimed anywhere.
        if (! is_string($refresh) || $refresh === '' || ! is_string($issuer) || $issuer === '') {
            $this->discard();

            return;
        }

        $account = new Account(
            $this->store->newId(),
            rtrim($issuer, '/'),
            Account::labelFor($issuer),
        );

        foreach (self::MOVES as $old => $new) {
            $value = SecureStorage::get($old);

            if (is_string($value) && $value !== '') {
                SecureStorage::set($account->keyPrefix().$new, $value);
            }
        }

        $this->store->put($account);

        // Only now. Up to this point a crash simply means the migration runs again.
        foreach (array_keys(self::MOVES) as $old) {
            SecureStorage::delete($old);
        }

        SecureStorage::delete('cboxid.issuer');

        $this->discard();
    }

    private function discard(): void
    {
        foreach (self::DISCARD as $key) {
            SecureStorage::delete($key);
        }
    }
}
