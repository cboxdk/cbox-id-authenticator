<?php

declare(strict_types=1);

namespace App\Support;

final class ServiceIcon
{
    /** Bundled assets only: no account names or issuers are sent to an icon service. */
    public static function forIssuer(string $issuer): ?string
    {
        return match (mb_strtolower(trim($issuer))) {
            'github', 'github.com' => 'github',
            'google', 'google.com' => 'google',
            'discord', 'discord.com' => 'discord',
            '1password', '1password.com' => '1password',
            'gitlab', 'gitlab.com' => 'gitlab',
            'dropbox', 'dropbox.com' => 'dropbox',
            'proton', 'protonmail', 'proton.me' => 'proton',
            'cloudflare', 'cloudflare.com' => 'cloudflare',
            'paypal', 'paypal.com' => 'paypal',
            'cbox id', 'cboxid' => 'cbox',
            default => null,
        };
    }
}
