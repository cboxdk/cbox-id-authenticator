<?php

declare(strict_types=1);

namespace App\Accounts;

/**
 * A sign-in that has left for the browser and not yet come back.
 *
 * Everything here is needed to finish the exchange when the callback deep link arrives,
 * and none of it can be recomputed at that point: the PKCE verifier is single-use, the
 * account id already owns the DPoP key the tokens will be bound to, and the issuer says
 * which host to talk to.
 *
 * Deliberately NOT holding the DPoP private key. The key is generated straight into the
 * account's own namespace when the flow starts, so the secret lives in exactly one
 * place; the id below is the handle to it.
 */
final readonly class PendingSignIn
{
    public function __construct(
        public string $state,
        public string $accountId,
        public string $issuer,
        public string $clientId,
        public string $verifier,
        public int $createdAt,
        public ?string $enrolmentToken = null,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'state' => $this->state,
            'account_id' => $this->accountId,
            'issuer' => $this->issuer,
            'client_id' => $this->clientId,
            'verifier' => $this->verifier,
            'created_at' => $this->createdAt,
            'enrolment_token' => $this->enrolmentToken,
        ];
    }

    /**
     * @param  array<string, mixed>  $row
     */
    public static function fromArray(array $row): ?self
    {
        foreach (['state', 'account_id', 'issuer', 'client_id', 'verifier'] as $required) {
            if (! is_string($row[$required] ?? null) || $row[$required] === '') {
                return null;
            }
        }

        return new self(
            $row['state'],
            $row['account_id'],
            $row['issuer'],
            $row['client_id'],
            $row['verifier'],
            is_numeric($row['created_at'] ?? null) ? (int) $row['created_at'] : 0,
            is_string($row['enrolment_token'] ?? null) ? $row['enrolment_token'] : null,
        );
    }
}
