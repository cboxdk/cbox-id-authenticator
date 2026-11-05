<?php

declare(strict_types=1);

namespace App\Accounts;

use App\Services\OAuthClient;
use App\Services\ServerClock;
use Throwable;

/**
 * The way in to any account's services.
 *
 * Every screen goes through here rather than resolving `CboxApi` or `OAuthClient` from
 * the container, because those types can no longer be built without knowing which
 * account they serve. Asking the container for one now means asking "for whom?" — and
 * that question having no answer is exactly the bug this replaces.
 *
 * Service graphs are memoised per account for the life of the request. Two calls for
 * the same account share a `DpopKey`, so its in-memory PEM cache is used rather than
 * re-reading the keychain on every proof.
 */
final class AccountContext
{
    /** @var array<string, AccountServices> */
    private array $cache = [];

    public function __construct(
        private readonly AccountStore $store,
        private readonly PendingSignIns $pending,
        private readonly ServerClock $clock,
    ) {}

    public function for(Account $account): AccountServices
    {
        return $this->cache[$account->id] ??= new AccountServices($account, $this->clock);
    }

    public function forId(string $id): ?AccountServices
    {
        $account = $this->store->find($id);

        return $account === null ? null : $this->for($account);
    }

    /**
     * Services for every connected account, in the order they were added.
     *
     * @return list<AccountServices>
     */
    public function all(): array
    {
        return array_map($this->for(...), $this->store->all());
    }

    public function store(): AccountStore
    {
        return $this->store;
    }

    public function pending(): PendingSignIns
    {
        return $this->pending;
    }

    /**
     * Begin a sign-in: reserve an account id and record the attempt.
     *
     * The id is allocated NOW so the DPoP key and install id are written straight into
     * their final namespace. The alternative — generating them loose and moving them on
     * success — means a window where the private key exists in two places, and a failure
     * mid-move leaves tokens bound to a key nobody can find.
     *
     * The account is not added to the index here. Until the callback completes there is
     * nothing to show the user, and an index entry with no refresh token would render as
     * a signed-out account they never created.
     */
    public function beginSignIn(string $issuer, string $clientId, ?string $enrolmentToken = null, ?string $expectedSubject = null): PendingSignIn
    {
        $this->sweepAbandoned();

        $attempt = OAuthClient::newAttempt();

        $account = new Account(
            $this->store->newId(),
            $issuer,
            Account::labelFor($issuer),
        );

        // Writes the client id under the reserved namespace, and generates the DPoP key
        // the tokens will be bound to.
        $services = $this->for($account);

        $pending = new PendingSignIn(
            $attempt['state'],
            $account->id,
            $issuer,
            $clientId,
            $attempt['verifier'],
            time(),
            $enrolmentToken,
            $expectedSubject,
        );

        $this->pending->put($pending);
        // Index the reservation before creating secrets so interrupted setup can be
        // swept later without leaving an unreachable Keychain namespace.
        $services->oauth->rememberClientId($clientId);
        $services->key->pem();

        return $pending;
    }

    /**
     * The account a pending sign-in reserved, as a value object.
     *
     * Not read from the index — it is not there yet. Reconstructed from the record so
     * the callback can reach the namespace the attempt already wrote into.
     */
    public function reserved(PendingSignIn $pending): AccountServices
    {
        return $this->for(new Account(
            $pending->accountId,
            $pending->issuer,
            Account::labelFor($pending->issuer),
        ));
    }

    public function cancelSignIn(string $state): void
    {
        $this->pending->cancel($state, function (PendingSignIn $pending): void {
            if ($this->store->find($pending->accountId) === null) {
                $this->reserved($pending)->forget();
                unset($this->cache[$pending->accountId]);
            }
        });
    }

    /**
     * Sign out of one account: revoke server-side, wipe locally, drop from the index.
     *
     * Revocation is attempted first and its failure is swallowed. An unreachable host
     * must not strand the user with an account they cannot remove — the local wipe is
     * what actually protects them, and it always runs.
     */
    public function remove(Account $account): void
    {
        $services = $this->for($account);

        try {
            $services->oauth->revoke();
        } catch (Throwable) {
            // Best effort. The refresh token is about to be unusable anyway: the key it
            // is bound to is deleted below, and the server refuses a different `jkt`.
        }

        $services->forget();
        $this->store->forget($account->id);

        unset($this->cache[$account->id]);
    }

    /**
     * Wipe the namespaces of sign-ins that were started and never finished.
     *
     * Without this, tapping "Scan the code" and walking away leaves a DPoP key in the
     * keychain forever, and does so again on every attempt.
     */
    private function sweepAbandoned(): void
    {
        $this->pending->sweep(function (string $accountId): void {
            // Only ever an unfinished attempt: a completed sign-in removes its own
            // pending record, so nothing in the index can appear here.
            if ($this->store->find($accountId) !== null) {
                return;
            }

            (new AccountServices(
                new Account($accountId, '', ''),
                $this->clock,
            ))->forget();
        });
    }
}
