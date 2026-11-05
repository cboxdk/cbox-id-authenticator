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
    File::put($root.'/Podfile', "platform :ios, '18.2'\n");
    File::copy(base_path('vendor/nativephp/mobile-firebase/resources/ios/PushNotificationFunctions.swift'), $path);
    File::copy(base_path('vendor/nativephp/mobile-firebase/resources/ios/FirebaseManager.swift'), dirname($path).'/FirebaseManager.swift');
    foreach (['Info.plist', 'NativePHP.entitlements'] as $name) {
        File::put($root.'/NativePHP/'.$name, '<?xml version="1.0"?><plist version="1.0"><dict><key>aps-environment</key><string>development</string><key>UIBackgroundModes</key><array><string>remote-notification</string></array></dict></plist>');
    }
    $security = $root.'/NativePHP/Bridge/Plugins/AuthenticatorSecurity/AuthenticatorSecurityFunctions.swift';
    File::ensureDirectoryExists(dirname($security));
    File::copy(base_path('plugins/authenticator-security/resources/ios/AuthenticatorSecurityFunctions.swift'), $security);
    $scanner = $root.'/NativePHP/Bridge/Plugins/Scanner/ScannerViewController.swift';
    File::ensureDirectoryExists(dirname($scanner));
    File::copy(base_path('vendor/nativephp/mobile-scanner/resources/ios/ScannerViewController.swift'), $scanner);
    $input = $root.'/NativePHP/Bridge/Plugins/UI/NativeUITextInputCore.swift';
    File::ensureDirectoryExists(dirname($input));
    File::copy(base_path('vendor/nativephp/mobile-ui/resources/ios/NativeUITextInputCore.swift'), $input);
    File::copy(base_path('vendor/nativephp/mobile-ui/resources/ios/NativeUISimpleRenderers.swift'), dirname($input).'/NativeUISimpleRenderers.swift');
    foreach (['NativeUIScrollViewRenderer.swift', 'NativeUILazyGridRenderer.swift'] as $renderer) {
        File::copy(base_path('vendor/nativephp/mobile-ui/resources/ios/'.$renderer), dirname($input).'/'.$renderer);
    }
    $biometric = $root.'/NativePHP/Bridge/Plugins/Biometric/BiometricFunctions.swift';
    File::ensureDirectoryExists(dirname($biometric));
    File::copy(base_path('vendor/nativephp/mobile-biometrics/resources/ios/BiometricFunctions.swift'), $biometric);
    File::copy(base_path('vendor/nativephp/mobile/resources/xcode/NativePHP/Bridge/BridgeRouter.swift'), $root.'/NativePHP/Bridge/BridgeRouter.swift');
    File::ensureDirectoryExists($root.'/NativePHP/NativeRender');
    File::copy(base_path('vendor/nativephp/mobile/resources/xcode/NativePHP/NativeRender/NativeRootTabsRenderer.swift'), $root.'/NativePHP/NativeRender/NativeRootTabsRenderer.swift');
    try {
        $this->artisan('authenticator:prepare-ios', ['--platform' => 'ios', '--build-path' => $root])->assertSuccessful();
        expect(File::get($root.'/NativePHP/NativePHP.entitlements'))->not->toContain('aps-environment');
        expect(File::get($root.'/NativePHP/Info.plist'))->not->toContain('remote-notification')
            ->toContain("<key>CboxPushEnabled</key>\n    <false/>")
            ->toContain('ITSAppUsesNonExemptEncryption');
        expect(File::get(dirname($path).'/FirebaseManager.swift'))->toContain('as? Bool == true else { return }');
        expect(File::get($root.'/NativePHP/NativeRender/NativeRootTabsRenderer.swift'))
            ->toContain('if !forceHide {')
            ->toContain('content.toolbar(.visible, for: .tabBar)')
            ->toContain('.modifier(CboxTabContentBackground(node: content))')
            ->toContain('.accessibilityLabel(Text(action.props.getString("a11y_label", default: icon)))')
            ->toContain('.ignoresSafeArea(edges: .bottom)')
            ->not->toContain('if isRoot && !forceHide {');
        expect(File::get($root.'/NativePHP/de.lproj/Localizable.strings'))->toContain('Kamera nicht verfügbar');
        expect(File::get($biometric))->toContain('cboxLocalized("Confirm this Cbox ID request")');
        expect(File::get($scanner))->toContain('UIApplication.openSettingsURLString')->toContain('accessibilityLabel');
        $firstPressable = File::get(dirname($input).'/NativeUISimpleRenderers.swift');
        expect($firstPressable)->toContain('.accessibilityLabel(Text(label), isEnabled: !label.isEmpty)')
            ->toContain('.accessibilityHint(Text(hint), isEnabled: !hint.isEmpty)')
            ->toContain('.accessibilityAddTraits(.isButton)');
        $first = File::get($path);
        $firstScanner = File::get($scanner);
        $firstInput = File::get($input);
        expect($firstInput)->toContain('echoes.acknowledge(newServerValue)')->toContain('echoes.sent(value)');
        expect($firstScanner)->toContain("dismiss(animated: true) {\n                self.dispatchScannedEvent");
        expect($first)->toContain('App\\\\Events\\\\PushReceived')->not->toContain('print(')->not->toContain('let event = userInfo["event"]');
        $this->artisan('authenticator:prepare-ios', ['--platform' => 'ios', '--build-path' => $root])->assertSuccessful();
        expect(File::get($path))->toBe($first);
        expect(File::get($scanner))->toBe($firstScanner);
        expect(File::get($input))->toBe($firstInput);
        expect(File::get(dirname($input).'/NativeUISimpleRenderers.swift'))->toBe($firstPressable);
        expect(substr_count(File::get($root.'/Podfile'), 'post_install do'))->toBe(1);
        expect(File::get($root.'/Podfile'))->toContain("Gem::Version.new('18.2')");
        expect(File::get($root.'/NativePHP/Bridge/BridgeRouter.swift'))->not->toContain('print(')->toContain('cboxDiscardLog(');
        expect(File::get($security))->not->toContain('#error(')->toContain('// Cbox: source security prepared.');
        $this->artisan('authenticator:check', ['--generated' => $root])->assertSuccessful();
        expect(File::get($root.'/Podfile.lock'))->toBe(File::get(resource_path('ios/Podfile.lock')));
        config(['authenticator.push_enabled' => true]);
        $this->artisan('authenticator:prepare-ios', ['--platform' => 'ios', '--build-path' => $root])->assertSuccessful();
        expect(File::get($root.'/NativePHP/Info.plist'))->toContain("<key>CboxPushEnabled</key>\n    <true/>");
        File::put(dirname($path).'/FirebaseManager.swift', 'class FirebaseManager {}');
        $this->artisan('authenticator:check', ['--generated' => $root])->assertFailed();
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
        foreach (['Info.plist', 'NativePHP.entitlements'] as $name) {
            File::put($root.'/NativePHP/'.$name, '<?xml version="1.0"?><plist version="1.0"><dict/></plist>');
        }
        $this->artisan('authenticator:prepare-ios', ['--platform' => 'ios', '--build-path' => $root])->assertSuccessful();
        $files = File::allFiles($root.'/NativePHP');
        foreach ($files as $file) {
            expect(File::get($file->getPathname()))->not->toMatch('/(?<![a-zA-Z0-9_.])(?:Swift\.|Foundation\.)?(?:print|debugPrint|NSLog)\s*\(/');
        }
        if (PHP_OS_FAMILY === 'Darwin') {
            $command = ['xcrun', 'swiftc', '-frontend', '-parse'];
            foreach ($files as $file) {
                if ($file->getExtension() !== 'swift') {
                    continue;
                }
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
