<?php

declare(strict_types=1);

namespace App\Totp;

use InvalidArgumentException;

final readonly class TotpEntry
{
    public string $secret;

    public function __construct(
        public string $id,
        public string $issuer,
        public string $account,
        #[\SensitiveParameter] string $secret,
        public string $algorithm = 'SHA1',
        public int $digits = 6,
        public int $period = 30,
    ) {
        if (! preg_match('/^[a-f0-9]{32}$/D', $id)
            || ! in_array($algorithm, ['SHA1', 'SHA256', 'SHA512'], true)
            || ! in_array($digits, [6, 8], true) || $period < 1 || $period > 300) {
            throw new InvalidArgumentException('This TOTP configuration is not supported.');
        }
        foreach ([$issuer, $account] as $label) {
            if (! mb_check_encoding($label, 'UTF-8') || mb_strlen($label) > 120 || preg_match('/[\x00-\x1f\x7f]/', $label)) {
                throw new InvalidArgumentException('Use a name of at most 120 characters.');
            }
        }
        if (trim($account) === '') {
            throw new InvalidArgumentException('Enter an account name.');
        }
        $this->secret = Base32::normalize($secret);
    }

    public function code(int $timestamp): string
    {
        if ($timestamp < 0) {
            throw new InvalidArgumentException('Check the date and time on your phone.');
        }
        $counter = intdiv($timestamp, $this->period);
        $message = pack('N2', intdiv($counter, 4294967296), $counter & 0xFFFFFFFF);
        $hash = hash_hmac(strtolower($this->algorithm), $message, Base32::decode($this->secret), true);
        $offset = ord($hash[strlen($hash) - 1]) & 15;
        $number = ((ord($hash[$offset]) & 127) << 24) | (ord($hash[$offset + 1]) << 16)
            | (ord($hash[$offset + 2]) << 8) | ord($hash[$offset + 3]);

        return str_pad((string) ($number % (10 ** $this->digits)), $this->digits, '0', STR_PAD_LEFT);
    }

    public function fingerprint(): string
    {
        return hash('sha256', implode(':', [$this->secret, $this->algorithm, $this->digits, $this->period]));
    }

    /** @return array<string, string|int> */
    public function toArray(): array
    {
        return get_object_vars($this);
    }

    /** @param array<string, mixed> $data */
    public static function fromArray(#[\SensitiveParameter] array $data): self
    {
        foreach (['id', 'issuer', 'account', 'secret', 'algorithm'] as $key) {
            if (! isset($data[$key]) || ! is_string($data[$key])) {
                throw new InvalidArgumentException('Invalid code record.');
            }
        }
        if (! is_int($data['digits'] ?? null) || ! is_int($data['period'] ?? null)) {
            throw new InvalidArgumentException('Invalid code record.');
        }

        return new self($data['id'], $data['issuer'], $data['account'], $data['secret'], $data['algorithm'], $data['digits'], $data['period']);
    }
}
