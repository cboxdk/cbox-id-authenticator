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
        // NativePHP's default overwrites this with false after archiving. This app
        // bundles cryptography for encrypted backups, so Apple must assess it.
        $result = Process::run([
            'plutil', '-extract', 'ITSAppUsesNonExemptEncryption', 'raw', '-o', '-', $infoPlistPath,
        ]);

        if (! $result->successful() || trim($result->output()) !== 'true') {
            throw new RuntimeException('The iOS archive must declare its encryption before export or upload. Rebuild with authenticator:prepare-ios.');
        }

        return true;
    }
}
