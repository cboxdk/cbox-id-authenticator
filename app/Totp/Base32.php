<?php

declare(strict_types=1);

namespace App\Totp;

use InvalidArgumentException;

final class Base32
{
    private const string ALPHABET = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

    public static function normalize(#[\SensitiveParameter] string $value): string
    {
        $value = strtoupper(preg_replace('/\s+/', '', $value) ?? '');
        if (strlen($value) > 512 || ! preg_match('/^[A-Z2-7]+={0,6}$/D', $value)) {
            throw new InvalidArgumentException('Enter a valid Base32 setup key.');
        }
        $key = rtrim($value, '=');
        if (! in_array(strlen($key) % 8, [0, 2, 4, 5, 7], true)
            || (str_contains($value, '=') && strlen($value) % 8 !== 0)) {
            throw new InvalidArgumentException('Enter a valid Base32 setup key.');
        }
        $bytes = self::decode($key);
        if (strlen($bytes) < 10 || self::encode($bytes) !== $key) {
            throw new InvalidArgumentException('The setup key is incomplete or invalid.');
        }

        return $key;
    }

    public static function decode(#[\SensitiveParameter] string $key): string
    {
        $buffer = 0;
        $bits = 0;
        $bytes = '';
        foreach (str_split($key) as $char) {
            $value = strpos(self::ALPHABET, $char);
            if ($value === false) {
                throw new InvalidArgumentException('Enter a valid Base32 setup key.');
            }
            $buffer = ($buffer << 5) | $value;
            $bits += 5;
            if ($bits >= 8) {
                $bits -= 8;
                $bytes .= chr(($buffer >> $bits) & 255);
                $buffer &= (1 << $bits) - 1;
            }
        }

        return $bytes;
    }

    public static function encode(#[\SensitiveParameter] string $bytes): string
    {
        $buffer = 0;
        $bits = 0;
        $result = '';
        foreach (str_split($bytes) as $char) {
            $buffer = ($buffer << 8) | ord($char);
            $bits += 8;
            while ($bits >= 5) {
                $bits -= 5;
                $result .= self::ALPHABET[($buffer >> $bits) & 31];
            }
            $buffer &= (1 << $bits) - 1;
        }
        if ($bits > 0) {
            $result .= self::ALPHABET[($buffer << (5 - $bits)) & 31];
        }

        return $result;
    }
}
