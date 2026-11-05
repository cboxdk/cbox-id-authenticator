<?php

declare(strict_types=1);

use App\Events\PushReceived;
use App\NativeComponents\Approvals;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Native\Mobile\Testing\Native;
use Symfony\Component\Process\Process;

it('hardens the installed Firebase bridge reproducibly without changing vendor sources', function (): void {
    $root = sys_get_temp_dir().'/cbox-ios-'.bin2hex(random_bytes(8));
    $path = $root.'/NativePHP/Bridge/Plugins/Firebase/PushNotificationFunctions.swift';
    File::ensureDirectoryExists(dirname($path));
    File::copy(base_path('vendor/nativephp/mobile-firebase/resources/ios/PushNotificationFunctions.swift'), $path);
    $security = $root.'/NativePHP/Bridge/Plugins/AuthenticatorSecurity/AuthenticatorSecurityFunctions.swift';
    File::ensureDirectoryExists(dirname($security));
    File::copy(base_path('plugins/authenticator-security/resources/ios/AuthenticatorSecurityFunctions.swift'), $security);
    File::copy(base_path('vendor/nativephp/mobile/resources/xcode/NativePHP/Bridge/BridgeRouter.swift'), $root.'/NativePHP/Bridge/BridgeRouter.swift');
    try {
        $this->artisan('authenticator:prepare-ios', ['--platform' => 'ios', '--build-path' => $root])->assertSuccessful();
        $first = File::get($path);
        expect($first)->toContain('App\\\\Events\\\\PushReceived')->not->toContain('print(')->not->toContain('let event = userInfo["event"]');
        $this->artisan('authenticator:prepare-ios', ['--platform' => 'ios', '--build-path' => $root])->assertSuccessful();
        expect(File::get($path))->toBe($first);
        expect(File::get($root.'/NativePHP/Bridge/BridgeRouter.swift'))->not->toContain('print(')->toContain('cboxDiscardLog(');
        expect(File::get($security))->not->toContain('#error(')->toContain('// Cbox: source security prepared.');
        $this->artisan('authenticator:check', ['--generated' => $root])->assertSuccessful();
    } finally {
        File::deleteDirectory($root);
    }
});

it('refreshes the native inbox when the fixed push event arrives', function (): void {
    fakeSecureStorage();
    anAccount();
    Http::fake(['*/approvals' => Http::sequence()->push(['data' => []])->push(['data' => [['id' => 'new-request']]])]);
    $screen = Native::test(Approvals::class)->assertSet('approvals', []);
    $screen->emitNative(PushReceived::class);
    expect($screen->get('approvals'))->toHaveCount(1);
});

it('includes the security plugin and privacy declaration in reproducible packaging', function (): void {
    $manifest = json_decode(File::get(base_path('plugins/authenticator-security/nativephp.json')), true, 512, JSON_THROW_ON_ERROR);
    expect($manifest['ios']['init_function'])->toBe('CboxAuthenticatorSecurityInit')
        ->and($manifest['assets']['ios']['ios/PrivacyInfo.xcprivacy'])->toBe('Resources/PrivacyInfo.xcprivacy')
        ->and($manifest['hooks']['post_compile'])->toBe('authenticator:prepare-ios');
    $this->artisan('authenticator:check')->assertSuccessful();
});

it('prepares the complete locked iOS source tree without logging credentials', function (): void {
    $root = sys_get_temp_dir().'/cbox-ios-source-'.bin2hex(random_bytes(8));
    $sources = [base_path('vendor/nativephp/mobile/resources/xcode/NativePHP') => $root.'/NativePHP'];
    foreach (['mobile-biometrics', 'mobile-browser', 'mobile-firebase', 'mobile-scanner', 'mobile-secure-storage', 'mobile-ui'] as $plugin) {
        $sources[base_path('vendor/nativephp/'.$plugin.'/resources/ios')] = $root.'/NativePHP/Bridge/Plugins/'.$plugin;
    }
    $sources[base_path('plugins/authenticator-security/resources/ios')] = $root.'/NativePHP/Bridge/Plugins/AuthenticatorSecurity';
    try {
        foreach ($sources as $from => $to) {
            foreach (File::allFiles($from) as $file) {
                if ($file->getExtension() !== 'swift') {
                    continue;
                }
                $target = $to.'/'.$file->getRelativePathname();
                File::ensureDirectoryExists(dirname($target));
                File::copy($file->getPathname(), $target);
            }
        }
        $this->artisan('authenticator:prepare-ios', ['--platform' => 'ios', '--build-path' => $root])->assertSuccessful();
        $files = File::allFiles($root.'/NativePHP');
        foreach ($files as $file) {
            expect(File::get($file->getPathname()))->not->toMatch('/(?<![a-zA-Z0-9_.])(?:Swift\.|Foundation\.)?(?:print|debugPrint|NSLog)\s*\(/');
        }
        if (PHP_OS_FAMILY === 'Darwin') {
            $command = ['xcrun', 'swiftc', '-frontend', '-parse'];
            foreach ($files as $file) {
                $command[] = $file->getPathname();
            }
            $process = new Process($command);
            $process->setTimeout(60)->run();
            expect($process->getExitCode())->toBe(0, $process->getErrorOutput());
        }
    } finally {
        File::deleteDirectory($root);
    }
});
