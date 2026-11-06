<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Facades\Process;
use Native\Mobile\Commands\PackageCommand;
use RuntimeException;

/** Preserve the app's identity and encryption declaration in the exported archive. */
final class NativePackageCommand extends PackageCommand
{
    protected function configureAppSettings(string $archivePath): bool
    {
        if (! parent::configureAppSettings($archivePath)) {
            return false;
        }

        // Xcode derives CFBundleName from the NativePHP target. Apply the product
        // name before export signs the final IPA, including system login dialogs.
        $plist = $archivePath.'/Products/Applications/NativePHP.app/Info.plist';
        foreach (['CFBundleName', 'CFBundleDisplayName'] as $key) {
            $result = Process::run(['plutil', '-replace', $key, '-string', 'Cbox ID', $plist]);
            if (! $result->successful()) {
                throw new RuntimeException('Could not set the Cbox ID name in the iOS archive.');
            }
        }

        return true;
    }

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
