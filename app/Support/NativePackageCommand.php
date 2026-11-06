<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Facades\Process;
use Native\Mobile\Commands\PackageCommand;
use RuntimeException;

/** Preserve this app's encryption declaration when NativePHP exports the archive. */
final class NativePackageCommand extends PackageCommand
{
    protected function setExportCompliance(string $infoPlistPath): bool
    {
        // The owner approved the authentication-only exemption on 2026-11-06.
        // TOTP backups protect authentication secrets; this is not a general file
        // encryption app. Preserve that explicit policy, not an upstream default.
        $result = Process::run([
            'plutil', '-extract', 'ITSAppUsesNonExemptEncryption', 'raw', '-o', '-', $infoPlistPath,
        ]);

        if (! $result->successful() || trim($result->output()) !== 'false') {
            throw new RuntimeException('The iOS archive must declare the approved authentication-only encryption exemption before export or upload. Rebuild with authenticator:prepare-ios.');
        }

        return true;
    }
}
