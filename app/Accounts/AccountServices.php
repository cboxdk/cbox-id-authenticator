<?php

declare(strict_types=1);

namespace App\Accounts;

use App\Services\CboxApi;
use App\Services\DeviceEnrolment;
use App\Services\DpopKey;
use App\Services\DpopProof;
use App\Services\OAuthClient;
use App\Services\ServerClock;
use App\Services\TokenStore;
use App\Support\InstallId;

/**
 * Everything that talks to one account's host, wired to that account.
 *
 * Built by hand rather than resolved from the container, and that is the point: the
 * container has no way to express "this graph, but for account B", so autowiring
 * silently produced one implicit account and every fixed storage key followed from it.
 * Constructing the graph explicitly makes the account a parameter instead of an
 * assumption.
 *
 * {@see ServerClock} is passed in rather than created here — clock drift belongs to the
 * handset, so all accounts share one.
 */
final class AccountServices
{
    public readonly TokenStore $tokens;

    public readonly DpopKey $key;

    public readonly DpopProof $proof;

    public readonly OAuthClient $oauth;

    public readonly CboxApi $api;

    public readonly InstallId $installId;

    public readonly DeviceEnrolment $enrolment;

    public function __construct(
        public readonly Account $account,
        ServerClock $clock,
    ) {
        $prefix = $account->keyPrefix();

        $this->tokens = new TokenStore($prefix);
        $this->key = new DpopKey($prefix);
        $this->proof = new DpopProof($this->key, $clock);
        $this->oauth = new OAuthClient($account, $this->tokens, $this->proof);
        $this->api = new CboxApi($account, $this->proof, $this->oauth);
        $this->installId = new InstallId($prefix);
        $this->enrolment = new DeviceEnrolment($this->api, $this->installId, $prefix);
    }

    public function isSignedIn(): bool
    {
        return $this->tokens->isSignedIn();
    }

    /**
     * Wipe every secret this account owns.
     *
     * The DPoP key goes last and matters most: without it the handset cannot mint a
     * valid proof, so any token that survived elsewhere — in a log, in a crash dump, on
     * a server that has not yet processed the revocation — is inert.
     */
    public function forget(): void
    {
        $this->tokens->forget();
        $this->oauth->forget();
        $this->enrolment->forget();
        $this->installId->forget();
        $this->key->forget();
    }
}
