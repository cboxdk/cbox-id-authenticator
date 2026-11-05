<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Providers\NativeServiceProvider;
use App\Support\AppLanguages;
use Cbox\AuthenticatorSecurity\SecurityServiceProvider;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

final class CheckRelease extends Command
{
    protected $signature = 'authenticator:check {--store : Check production settings and push configuration} {--generated= : Validate the generated iOS project after native:install}';

    protected $description = 'Validate authenticator packaging before an iPhone build or App Store archive';

    public function handle(): int
    {
        $errors = [];
        $check = function (bool $valid, string $message) use (&$errors): void {
            if (! $valid) {
                $errors[] = $message;
            }
        };
        $check(config('nativephp.app_id') === 'com.cboxid.authenticator', 'Set NATIVEPHP_APP_ID=com.cboxid.authenticator.');
        $check(config('nativephp.deeplink_scheme') === 'com.cboxid.authenticator', 'Set the authenticator deep-link scheme.');
        $check(preg_match('/^\d+\.\d+\.\d+$/', (string) config('nativephp.version')) === 1, 'Set a numeric x.y.z app version.');
        $check((int) config('nativephp.version_code') > 0, 'Set a positive app build number.');
        foreach (['openssl', 'sodium', 'pdo_sqlite', 'mbstring'] as $extension) {
            $check(extension_loaded($extension), 'Missing PHP extension: '.$extension);
        }
        foreach (['resources/openssl.cnf', 'public/icon.png', 'plugins/authenticator-security/resources/ios/PrivacyInfo.xcprivacy'] as $file) {
            $check(is_file(base_path($file)), 'Missing packaging asset: '.$file);
        }
        $check(class_exists(SecurityServiceProvider::class), 'Install the local authenticator security plugin with Composer.');
        $check(in_array(SecurityServiceProvider::class,
            (new NativeServiceProvider(app()))->plugins(), true), 'Register the authenticator security plugin.');

        $generated = $this->option('generated');
        if (is_string($generated) && $generated !== '') {
            $files = glob(rtrim($generated, '/').'/NativePHP/Bridge/Plugins/*/PushNotificationFunctions.swift') ?: [];
            $swift = count($files) === 1 ? file_get_contents($files[0]) : false;
            $check(is_string($swift) && str_contains($swift, '// Cbox: notification data is a refresh hint') && str_contains($swift, 'cboxNotificationURL(deepLink)')
                && ! str_contains($swift, 'print(') && ! str_contains($swift, 'let event = userInfo["event"]'),
                'Generated Firebase bridge has not passed authenticator hardening. Do not archive this project.');
            $security = glob(rtrim($generated, '/').'/NativePHP/Bridge/Plugins/*/AuthenticatorSecurityFunctions.swift') ?: [];
            $source = count($security) === 1 ? file_get_contents($security[0]) : false;
            $check(is_string($source) && str_contains($source, '// Cbox: source security prepared.'), 'The native security build guard has not passed.');
            if (is_dir(rtrim($generated, '/').'/NativePHP')) {
                foreach (File::allFiles(rtrim($generated, '/').'/NativePHP') as $file) {
                    if ($file->getExtension() !== 'swift') {
                        continue;
                    }
                    $contents = File::get($file->getPathname());
                    $check(preg_match('/(?<![a-zA-Z0-9_.])(?:Swift\.|Foundation\.)?(?:print|debugPrint|NSLog)\s*\(/', $contents) !== 1,
                        'Native logging remains in '.$file->getRelativePathname());
                    if ($file->getFilename() === 'AppUpdateManager.swift') {
                        $check(str_contains($contents, '// Cbox: app updates are distributed through the App Store.'), 'Disable native OTA updates before archiving.');
                    }
                }
            }
        }

        if ($this->option('store')) {
            foreach (array_keys(AppLanguages::NAMES) as $locale) {
                foreach (['InfoPlist.strings', 'Localizable.strings'] as $name) {
                    $check(is_string($generated) && is_file(rtrim($generated, '/').'/NativePHP/'.$locale.'.lproj/'.$name), 'Missing bundled iOS translation: '.$locale.'/'.$name);
                }
            }
            $inventoryPath = resource_path('ios-third-party.json');
            $inventory = is_file($inventoryPath) ? json_decode(File::get($inventoryPath), true, flags: JSON_THROW_ON_ERROR) : [];
            foreach (['pods_lock_sha256' => '/Podfile.lock', 'swift_lock_sha256' => '/NativePHP.xcworkspace/xcshareddata/swiftpm/Package.resolved'] as $key => $file) {
                $lock = is_string($generated) ? rtrim($generated, '/').$file : '';
                $check(is_file($lock) && ($inventory[$key] ?? null) === hash_file('sha256', $lock), 'Refresh the bundled iOS dependency notices: '.$file);
            }
            $check(is_string($generated) && is_file(rtrim($generated, '/').'/NativePHP/Resources/PrivacyInfo.xcprivacy'), 'Pass --generated with the iOS project containing the bundled privacy manifest.');
            $check(app()->environment('production'), 'Use APP_ENV=production for the store archive.');
            $check(config('app.debug') === false, 'Use APP_DEBUG=false for the store archive.');
            $check(config('nativephp.fps_overlay') === false, 'Disable the FPS overlay.');
            $check(config('logging.default') === 'silent', 'Use LOG_CHANNEL=silent in the store archive.');
            $firebase = base_path('GoogleService-Info.plist');
            $check(is_file($firebase), 'Add the iOS GoogleService-Info.plist for push notifications.');
            if (is_file($firebase)) {
                $contents = file_get_contents($firebase);
                $check(is_string($contents) && preg_match('/<key>BUNDLE_ID<\/key>\s*<string>com\.cboxid\.authenticator<\/string>/', $contents) === 1,
                    'The Firebase plist must belong to com.cboxid.authenticator.');
            }
            $check(is_string(config('nativephp.development_team')) && config('nativephp.development_team') !== '', 'Set your Apple development team.');
        }

        foreach ($errors as $error) {
            $this->components->error($error);
        }
        if ($errors !== []) {
            return self::FAILURE;
        }
        $this->components->info($this->option('store')
            ? 'Packaging checks passed. A signed archive and device testing are still required.'
            : 'Source packaging checks passed. Ready for an iPhone build.');

        return self::SUCCESS;
    }
}
