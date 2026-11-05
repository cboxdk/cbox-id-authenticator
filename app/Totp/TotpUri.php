<?php

declare(strict_types=1);

namespace App\Totp;

use InvalidArgumentException;

final class TotpUri
{
    public static function parse(#[\SensitiveParameter] string $uri): TotpEntry
    {
        if (strlen($uri) > 4096 || preg_match('/%(?![a-f0-9]{2})/i', $uri)) {
            throw new InvalidArgumentException('That QR code is not a supported TOTP setup code.');
        }
        $parts = parse_url($uri);
        if (! is_array($parts) || ($parts['scheme'] ?? '') !== 'otpauth' || ($parts['host'] ?? '') !== 'totp'
            || array_intersect(['user', 'pass', 'port', 'fragment'], array_keys($parts)) !== []) {
            throw new InvalidArgumentException('Scan a TOTP setup QR code. HOTP and migration QR codes are not supported.');
        }
        $query = [];
        foreach (explode('&', $parts['query'] ?? '') as $pair) {
            $pieces = explode('=', $pair, 2);
            $key = rawurldecode($pieces[0]);
            if (count($pieces) !== 2 || isset($query[$key])) {
                throw new InvalidArgumentException('That QR code contains conflicting settings.');
            }
            $query[$key] = rawurldecode($pieces[1]);
        }
        $label = rawurldecode(ltrim($parts['path'] ?? '', '/'));
        $labels = explode(':', $label, 2);
        $issuer = count($labels) === 2 ? trim($labels[0]) : '';
        $account = trim($labels[count($labels) - 1]);
        if (isset($query['issuer'])) {
            if ($issuer !== '' && $issuer !== trim($query['issuer'])) {
                throw new InvalidArgumentException('The issuer names in this QR code do not match.');
            }
            $issuer = trim($query['issuer']);
        }
        $digits = $query['digits'] ?? '6';
        $period = $query['period'] ?? '30';
        if (! ctype_digit($digits) || strlen($digits) > 1 || ! ctype_digit($period) || strlen($period) > 3) {
            throw new InvalidArgumentException('This TOTP configuration is not supported.');
        }

        return new TotpEntry(bin2hex(random_bytes(16)), $issuer, $account, $query['secret'] ?? '', strtoupper($query['algorithm'] ?? 'SHA1'), (int) $digits, (int) $period);
    }
}
