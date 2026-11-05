<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Support\AppLanguages;
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
        $this->prepareLocalizations($root);
        $pressables = glob($root.'/NativePHP/Bridge/Plugins/*/NativeUISimpleRenderers.swift') ?: [];
        if (count($pressables) !== 1) {
            throw new RuntimeException('Expected one native pressable renderer.');
        }
        $pressable = File::get($pressables[0]);
        $pressableMarker = '// Cbox: action rows expose their labels and button traits.';
        if (! str_contains($pressable, $pressableMarker)) {
            $original = "        } else {\n            NativeUIColumnRenderer(node: node)\n        }";
            $previous = "        } else {\n            // Cbox: action rows are accessible buttons.\n            NativeUIColumnRenderer(node: node)\n                .accessibilityElement(children: .combine)\n                .accessibilityAddTraits(.isButton)\n        }";
            $pressable = str_replace($previous, $original, $pressable);
            if (substr_count($pressable, $original) !== 1) {
                throw new RuntimeException('Review pressable accessibility: upstream changed.');
            }
            $accessible = <<<'SWIFT'
        } else {
            // Cbox: action rows expose their labels and button traits.
            let label = node.props.getString("a11y_label")
            let hint = node.props.getString("a11y_hint")
            NativeUIColumnRenderer(node: node)
                .accessibilityElement(children: .combine)
                .accessibilityLabel(Text(label), isEnabled: !label.isEmpty)
                .accessibilityHint(Text(hint), isEnabled: !hint.isEmpty)
                .accessibilityAddTraits(.isButton)
        }
SWIFT;
            File::put($pressables[0], str_replace($original, $accessible, $pressable));
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
        $inputs = glob($root.'/NativePHP/Bridge/Plugins/*/NativeUITextInputCore.swift') ?: [];
        if (count($inputs) !== 1) {
            throw new RuntimeException('Expected one native text input core. Review the plugin layout before building.');
        }
        $input = File::get($inputs[0]);
        $inputMarker = '// Cbox: acknowledge every in-flight edit, not only the latest one.';
        if (! str_contains($input, $inputMarker)) {
            $replacements = [
                '.submitLabel(onSubmitCb != 0 ? .done : .return)' => '.submitLabel(multiline ? .return : .done)',
                '            // Submit also acts as a commit point — flush pending, then dispatch.' => "            if !keepFocus { isFocused = false }\n            // Submit also acts as a commit point — flush pending, then dispatch.",
                '    @State private var lastSentValue: String = ""' => "    $inputMarker\n    @State private var echoes = CboxTextInputEchoes()\n    @State private var lastSentValue: String = \"\"",
                '        .onChange(of: serverValue) { _, newServerValue in' => <<<'SWIFT'
        .onReceive(NotificationCenter.default.publisher(for: UIApplication.didEnterBackgroundNotification)) { _ in
            debounceTask?.cancel()
            debounceTask = nil
            echoes.reset()
            if secure {
                text = ""
                lastSentValue = ""
                isFocused = false
            }
        }
        .onChange(of: serverValue) { _, newServerValue in
            if echoes.acknowledge(newServerValue) { return }
SWIFT,
                '            if newServerValue != lastSentValue {' => "            if newServerValue != lastSentValue {\n                echoes.reset()",
                '    private func commit(_ value: String, onChangeCb: Int) {' => "    private func commit(_ value: String, onChangeCb: Int) {\n        guard value != lastSentValue else { return }",
                '            NativeElementBridge.sendTextChangeEvent(onChangeCb, nodeId: node.id, text: value)' => "            echoes.sent(value)\n            NativeElementBridge.sendTextChangeEvent(onChangeCb, nodeId: node.id, text: value)",
            ];
            foreach ($replacements as $original => $replacement) {
                if (substr_count($input, $original) !== 1) {
                    throw new RuntimeException('Review native input synchronization before building: upstream changed.');
                }
                $input = str_replace($original, $replacement, $input);
            }
            File::put($inputs[0], $input);
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

    private function prepareLocalizations(string $root): void
    {
        foreach (array_keys(AppLanguages::NAMES) as $locale) {
            $translations = json_decode(File::get(lang_path($locale.'.json')), true, flags: JSON_THROW_ON_ERROR);
            $lines = [];
            foreach ($translations as $key => $value) {
                $lines[] = json_encode($key, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES).' = '.json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES).';';
            }
            File::ensureDirectoryExists($root.'/NativePHP/'.$locale.'.lproj');
            File::put($root.'/NativePHP/'.$locale.'.lproj/Localizable.strings', implode("\n", $lines)."\n");
        }

        foreach (['ScannerViewController.swift', 'BiometricFunctions.swift'] as $filename) {
            $paths = glob($root.'/NativePHP/Bridge/Plugins/*/'.$filename) ?: [];
            if (count($paths) !== 1) {
                throw new RuntimeException('Expected one native source for '.$filename);
            }
            $source = File::get($paths[0]);
            $marker = '// Cbox: localized native permission and authentication flows.';
            if (str_contains($source, $marker)) {
                continue;
            }
            if ($filename === 'BiometricFunctions.swift') {
                $original = 'localizedReason: "Authenticate"';
                if (substr_count($source, $original) !== 1) {
                    throw new RuntimeException('Review the native authentication reason: upstream changed.');
                }
                $source = str_replace($original, 'localizedReason: cboxLocalized("Confirm this Cbox ID request")', $source);
            } else {
                $strings = [
                    'Camera not available', 'Could not access camera', 'Could not add camera input',
                    'Could not add metadata output', 'Camera Permission Required',
                    'Please enable camera access in Settings to scan QR codes.', 'OK', 'Error', 'Recent Scans',
                ];
                foreach ($strings as $string) {
                    if (! str_contains($source, '"'.$string.'"')) {
                        throw new RuntimeException('Review scanner localization: missing '.$string);
                    }
                    $source = str_replace('"'.$string.'"', 'cboxLocalized("'.$string.'")', $source);
                }
                $source = str_replace('closeBtn.tintColor = .white', 'closeBtn.accessibilityLabel = cboxLocalized("Close scanner")'."\n        closeBtn.tintColor = .white", $source);
                $source = str_replace('clearBtn.tintColor = .white', 'clearBtn.accessibilityLabel = cboxLocalized("Clear scans")'."\n        clearBtn.tintColor = .white", $source);
                $original = 'message: cboxLocalized("Please enable camera access in Settings to scan QR codes."),';
                $start = strpos($source, $original);
                $insertion = $start === false ? false : strpos($source, '        present(alert, animated: true)', $start);
                if ($insertion === false) {
                    throw new RuntimeException('Review camera permission recovery: upstream changed.');
                }
                $action = <<<'SWIFT'
        alert.addAction(UIAlertAction(title: cboxLocalized("Open Settings"), style: .default) { [weak self] _ in
            self?.dismiss(animated: true) {
                if let url = URL(string: UIApplication.openSettingsURLString) { UIApplication.shared.open(url) }
            }
        })

SWIFT;
                $source = substr_replace($source, $action, $insertion, 0);
            }
            File::put($paths[0], $marker."\n".$source);
        }
    }
}
