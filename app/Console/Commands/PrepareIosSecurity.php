<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use RuntimeException;

/** Reproducible app-specific hardening of the generated Firebase bridge. */
final class PrepareIosSecurity extends Command
{
    protected $signature = 'authenticator:prepare-ios {--platform=} {--build-path=} {--plugin-path=} {--app-id=} {--config=} {--plugins=}';

    protected $description = 'Remove credential logging and deliver a fixed foreground refresh event in the generated iOS bridge';

    public function handle(): int
    {
        if ($this->option('platform') !== 'ios') {
            return self::SUCCESS;
        }
        $root = $this->option('build-path');
        if (! is_string($root) || ! is_dir($root)) {
            throw new RuntimeException('The iOS build path does not exist.');
        }
        $tabsPath = $root.'/NativePHP/NativeRender/NativeRootTabsRenderer.swift';
        $tabs = File::get($tabsPath);
        $tabsMarker = '// Cbox: keep account and code tabs available on pushed screens.';
        if (! str_contains($tabs, $tabsMarker)) {
            $original = "if isRoot && !forceHide {\n            content";
            if (substr_count($tabs, $original) !== 1) {
                throw new RuntimeException('Review native tab visibility before building: the upstream renderer changed.');
            }
            $tabs = str_replace($original, "$tabsMarker\n        if !forceHide {\n            content.toolbar(.visible, for: .tabBar)", $tabs);
            File::put($tabsPath, $tabs);
        }
        $scanners = glob($root.'/NativePHP/Bridge/Plugins/*/ScannerViewController.swift') ?: [];
        if (count($scanners) !== 1) {
            throw new RuntimeException('Expected one generated scanner. Review the plugin layout before building.');
        }
        $scanner = File::get($scanners[0]);
        $scannerMarker = '// Cbox: finish camera dismissal before opening authentication.';
        if (! str_contains($scanner, $scannerMarker)) {
            $start = strpos($scanner, '        // Dispatch event to Laravel');
            $end = strpos($scanner, '    private func parseFormats(', $start ?: 0);
            if ($start === false || $end === false || substr_count(substr($scanner, $start, $end - $start), 'dispatchScannedEvent(data: data, format: formatName)') !== 1) {
                throw new RuntimeException('Review scanner completion before building: the upstream implementation changed.');
            }
            $completion = <<<'SWIFT'
        // Cbox: finish camera dismissal before opening authentication.
        if continuous {
            dispatchScannedEvent(data: data, format: formatName)
        } else {
            dismiss(animated: true) {
                self.dispatchScannedEvent(data: data, format: formatName)
            }
        }
    }


SWIFT;
            File::put($scanners[0], substr($scanner, 0, $start).$completion.substr($scanner, $end));
        }
        $podfile = $root.'/Podfile';
        if (is_file($podfile)) {
            $pods = File::get($podfile);
            if (! str_contains($pods, '# Cbox: align dependencies with the app deployment target.')) {
                if (str_contains($pods, 'post_install do')) {
                    throw new RuntimeException('Review the existing CocoaPods post_install hook before building.');
                }
                $pods .= <<<'RUBY'


# Cbox: align dependencies with the app deployment target.
post_install do |installer|
  installer.pods_project.targets.each do |target|
    target.build_configurations.each do |config|
      current = config.build_settings['IPHONEOS_DEPLOYMENT_TARGET'] || '0'
      if Gem::Version.new(current) < Gem::Version.new('18.2')
        config.build_settings['IPHONEOS_DEPLOYMENT_TARGET'] = '18.2'
      end
    end
  end
end

RUBY;
                File::put($podfile, $pods);
            }
        }
        $matches = glob($root.'/NativePHP/Bridge/Plugins/*/PushNotificationFunctions.swift') ?: [];
        if (count($matches) !== 1) {
            throw new RuntimeException('Expected one generated Firebase bridge. Review the plugin layout before building.');
        }
        $path = $matches[0];
        $swift = file_get_contents($path);
        if (! is_string($swift)) {
            throw new RuntimeException('Could not read the generated Firebase bridge.');
        }
        $marker = '// Cbox: notification data is a refresh hint, never a PHP class name.';
        if (! str_contains($swift, $marker)) {
            $start = strpos($swift, '    private func dispatchDataMessageEvent(');
            $end = strpos($swift, '    private func processQueue()', $start ?: 0);
            if ($start === false || $end === false) {
                throw new RuntimeException('The Firebase bridge changed. Review foreground notification handling.');
            }
            $method = <<<'SWIFT'
    // Cbox: notification data is a refresh hint, never a PHP class name.
    private func dispatchDataMessageEvent(_ userInfo: [AnyHashable: Any]) {
        DispatchQueue.main.async {
            LaravelBridge.shared.send?("App\\Events\\PushReceived", [:])
        }
    }

SWIFT;
            $swift = substr($swift, 0, $start).$method.substr($swift, $end);
        }
        if (! str_contains($swift, 'cboxNotificationURL(deepLink)')) {
            $original = 'if let deepLink = deepLink, let url = URL(string: deepLink) {';
            if (! str_contains($swift, $original)) {
                throw new RuntimeException('The Firebase notification routing changed. Review it before building.');
            }
            $swift = str_replace($original, 'if let deepLink = deepLink, let url = cboxNotificationURL(deepLink) {', $swift);
        }
        if (file_put_contents($path, $swift) === false) {
            throw new RuntimeException('Could not harden the Firebase bridge.');
        }

        $security = glob($root.'/NativePHP/Bridge/Plugins/*/AuthenticatorSecurityFunctions.swift') ?: [];
        if (count($security) !== 1) {
            throw new RuntimeException('Expected the authenticator security plugin in the generated iOS project.');
        }
        foreach (File::allFiles($root.'/NativePHP') as $file) {
            if ($file->getExtension() !== 'swift') {
                continue;
            }
            $source = File::get($file->getPathname());
            if ($file->getFilename() === 'AppUpdateManager.swift') {
                $source = str_replace('return Bundle.main.object(forInfoDictionaryKey: "BIFROST_APP_ID") as? String', 'return nil // Cbox: app updates are distributed through the App Store.', $source);
                if (! str_contains($source, '// Cbox: app updates are distributed through the App Store.')) {
                    throw new RuntimeException('The native updater changed. Review it before building.');
                }
            }
            // Keep complete Swift expressions (including multiline arguments) intact.
            // Discard their output rather than attempting to parse nested strings.
            $source = preg_replace('/(?<![a-zA-Z0-9_.])(?:Swift\.|Foundation\.)?(?:print|debugPrint|NSLog)\s*\(/', 'cboxDiscardLog(', $source);
            if (! is_string($source) || file_put_contents($file->getPathname(), $source) === false) {
                throw new RuntimeException('Could not remove native credential logging.');
            }
        }
        $source = File::get($security[0]);
        $source = str_replace('#error("Authenticator iOS preparation did not complete. Run authenticator:prepare-ios before building.")', '// Cbox: source security prepared.', $source);
        if (file_put_contents($security[0], $source) === false) {
            throw new RuntimeException('Could not complete the iOS security build guard.');
        }
        $this->components->info('Native credential logging disabled; fixed push event installed; security build guard satisfied.');

        return self::SUCCESS;
    }
}
