<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use RuntimeException;
use Symfony\Component\Process\Process;

final class BuildIosNotices extends Command
{
    protected $signature = 'authenticator:ios-notices {--generated=nativephp/ios} {--check}';

    protected $description = 'Bundle notices from the resolved CocoaPods and Swift packages';

    public function handle(): int
    {
        $root = rtrim((string) $this->option('generated'), '/');
        $lockPath = $root.'/Podfile.lock';
        $swiftPath = $root.'/NativePHP.xcworkspace/xcshareddata/swiftpm/Package.resolved';
        $lock = File::get($lockPath);
        $process = new Process(['plutil', '-convert', 'json', '-o', '-', $root.'/Pods/Target Support Files/Pods-NativePHP/Pods-NativePHP-acknowledgements.plist']);
        $process->mustRun();
        $notices = json_decode($process->getOutput(), true, flags: JSON_THROW_ON_ERROR);
        $components = [];
        foreach ($notices['PreferenceSpecifiers'] as $notice) {
            $name = $notice['Title'] ?? '';
            if ($name === '' || $name === 'Acknowledgements') {
                continue;
            }
            if (preg_match('/^  - "?'.preg_quote($name, '/').'(?:\/[^ (]+)? \(([^)]+)\)/m', $lock, $match) !== 1) {
                throw new RuntimeException('No locked version for '.$name);
            }
            $text = trim($notice['FooterText'] ?? '');
            $licenses = [];
            if (str_contains($text, 'Apache License')) {
                $licenses[] = 'Apache-2.0';
            }
            if (str_contains($text, 'Permission is hereby granted, free of charge')) {
                $licenses[] = 'MIT';
            }
            if (str_contains($text, 'The origin of this software must not be misrepresented')) {
                $licenses[] = 'Zlib';
            }
            if ($licenses === []) {
                throw new RuntimeException('Review the native license for '.$name);
            }
            $components[] = ['name' => $name, 'version' => $match[1], 'licenses' => $licenses, 'notice' => $text];
        }
        $swift = json_decode(File::get($swiftPath), true, flags: JSON_THROW_ON_ERROR);
        foreach ($swift['pins'] as $pin) {
            if ($pin['identity'] !== 'zipfoundation') {
                throw new RuntimeException('Review the added Swift package '.$pin['identity']);
            }
            $checkout = $root.'/build/SourcePackages/checkouts/ZIPFoundation';
            $revision = new Process(['git', '-C', $checkout, 'rev-parse', 'HEAD']);
            $revision->mustRun();
            if (trim($revision->getOutput()) !== $pin['state']['revision']) {
                throw new RuntimeException('ZIPFoundation checkout does not match Package.resolved.');
            }
            $components[] = [
                'name' => 'ZIPFoundation', 'version' => $pin['state']['version'], 'licenses' => ['MIT'],
                'notice' => trim(File::get($checkout.'/LICENSE')),
            ];
        }
        usort($components, fn (array $a, array $b): int => strcmp($a['name'], $b['name']));
        $document = json_encode([
            'format' => 'cbox-ios-notices-v1',
            'pods_lock_sha256' => hash_file('sha256', $lockPath),
            'swift_lock_sha256' => hash_file('sha256', $swiftPath),
            'components' => $components,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)."\n";
        $path = resource_path('ios-third-party.json');
        if ($this->option('check')) {
            if (! is_file($path) || File::get($path) !== $document) {
                $this->components->error('Native notices changed. Run authenticator:ios-notices before packaging.');

                return self::FAILURE;
            }
        } else {
            File::put($path, $document);
        }
        $this->components->info(count($components).' resolved native dependencies and notices checked.');

        return self::SUCCESS;
    }
}
