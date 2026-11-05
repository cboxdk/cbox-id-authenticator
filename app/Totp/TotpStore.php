<?php

declare(strict_types=1);

namespace App\Totp;

use App\Services\DeviceSecurity;
use App\Services\SecureVault;
use RuntimeException;
use Throwable;

/** A single Keychain item makes every import an atomic replacement. No plaintext files. */
final class TotpStore
{
    public const string KEY = 'cbox.totp.v1';

    public const int MAX_ENTRIES = 500;

    public function isEmpty(): bool
    {
        return $this->read() === [];
    }

    /** @return list<TotpEntry> */
    public function all(): array
    {
        $this->requireUnlocked();

        return $this->read();
    }

    public function find(string $id): ?TotpEntry
    {
        foreach ($this->all() as $entry) {
            if ($entry->id === $id) {
                return $entry;
            }
        }

        return null;
    }

    /** @param list<TotpEntry> $incoming
     * @return array{added: int, duplicates: int}
     */
    public function merge(array $incoming, bool $preview = false): array
    {
        $generation = $this->requireUnlocked();
        $entries = $this->read();
        $known = array_fill_keys(array_map(fn (TotpEntry $e): string => $e->fingerprint(), $entries), true);
        $added = 0;
        $duplicates = 0;
        foreach ($incoming as $entry) {
            if (isset($known[$entry->fingerprint()])) {
                $duplicates++;

                continue;
            }
            $known[$entry->fingerprint()] = true;
            $entries[] = new TotpEntry(bin2hex(random_bytes(16)), $entry->issuer, $entry->account, $entry->secret, $entry->algorithm, $entry->digits, $entry->period);
            $added++;
        }
        if (count($entries) > self::MAX_ENTRIES) {
            throw new RuntimeException('You can keep up to 500 codes on this phone.');
        }
        if (! $preview && $added > 0) {
            $this->write($entries, $generation);
        }

        return compact('added', 'duplicates');
    }

    public function rename(string $id, string $issuer, string $account): void
    {
        $generation = $this->requireUnlocked();
        $entries = array_map(fn (TotpEntry $e): TotpEntry => $e->id !== $id ? $e
            : new TotpEntry($id, trim($issuer), trim($account), $e->secret, $e->algorithm, $e->digits, $e->period), $this->read());
        $this->write($entries, $generation);
    }

    public function remove(string $id): void
    {
        $generation = $this->requireUnlocked();
        $this->write(array_values(array_filter($this->read(), fn (TotpEntry $e): bool => $e->id !== $id)), $generation);
    }

    private function requireUnlocked(): int
    {
        return app(DeviceSecurity::class)->generation() ?? throw new RuntimeException('Unlock the app and try again.');
    }

    /** @return list<TotpEntry> */
    private function read(): array
    {
        $raw = SecureVault::get(self::KEY);
        if ($raw === null) {
            return [];
        }
        try {
            $data = json_decode($raw, true, 16, JSON_THROW_ON_ERROR);
            if (! is_array($data) || ($data['version'] ?? null) !== 1 || ! is_array($data['entries'] ?? null)
                || ! array_is_list($data['entries']) || count($data['entries']) > self::MAX_ENTRIES) {
                throw new RuntimeException;
            }
            $entries = array_map(fn (array $row): TotpEntry => TotpEntry::fromArray($row), $data['entries']);
            if (count(array_unique(array_column($data['entries'], 'id'))) !== count($entries)) {
                throw new RuntimeException;
            }

            return $entries;
        } catch (Throwable) {
            throw new RuntimeException('Your saved codes could not be read. Nothing has been changed.');
        }
    }

    /** @param list<TotpEntry> $entries */
    private function write(array $entries, int $generation): void
    {
        $json = json_encode(['version' => 1, 'entries' => array_map(fn (TotpEntry $e): array => $e->toArray(), $entries)], JSON_THROW_ON_ERROR);
        if ($this->requireUnlocked() !== $generation || ! SecureVault::set(self::KEY, $json)) {
            throw new RuntimeException('Could not save your codes. Unlock your phone and try again.');
        }
        app(DeviceSecurity::class)->configure(true);
    }
}
