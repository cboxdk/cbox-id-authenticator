<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Composer\InstalledVersions;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use RuntimeException;

final class BuildDependencies extends Command
{
    protected $signature = 'authenticator:dependencies {--check : Verify that the locked dependency inventory is current} {--installed : Verify installed production packages against the reviewed inventory}';

    protected $description = 'Build the production Composer inventory and third-party notices used by the native app';

    public function handle(): int
    {
        if ($this->option('installed')) {
            $inventory = json_decode(File::get(resource_path('third-party.json')), true, 512, JSON_THROW_ON_ERROR);
            foreach ($inventory['components'] as $component) {
                $name = $component['name'];
                if (! InstalledVersions::isInstalled($name)
                    || InstalledVersions::getPrettyVersion($name) !== $component['version']
                    || InstalledVersions::getReference($name) !== $component['reference']) {
                    $this->components->error('Installed package differs from the reviewed dependency inventory: '.$name);

                    return self::FAILURE;
                }
            }

            $this->components->info('Installed production packages match the reviewed dependency inventory.');

            return self::SUCCESS;
        }

        $lock = json_decode(File::get(base_path('composer.lock')), true, 512, JSON_THROW_ON_ERROR);
        $components = [];
        foreach ($lock['packages'] as $package) {
            $name = $package['name'];
            $licenses = $package['license'] ?? [];
            $permitted = array_intersect($licenses, ['MIT', 'BSD-2-Clause', 'BSD-3-Clause', 'Apache-2.0']);
            $private = in_array($name, ['cbox/authenticator-security', 'nativephp/mobile-biometrics', 'nativephp/mobile-firebase', 'nativephp/mobile-scanner', 'nativephp/mobile-secure-storage'], true);
            if ($permitted === [] && ! ($private && $licenses === ['proprietary'])) {
                throw new RuntimeException('Review the distribution license for '.$name.' before packaging.');
            }
            $notices = [];
            foreach (File::files(base_path('vendor/'.$name)) as $file) {
                if (preg_match('/^(LICENSE|LICENCE|COPYING|NOTICE)(\..*)?$/i', $file->getFilename()) === 1) {
                    $notices[] = trim(File::get($file->getPathname()));
                }
            }
            $components[] = [
                'type' => 'library', 'name' => $name, 'version' => $package['version'],
                'reference' => $package['source']['reference'] ?? $package['dist']['reference'] ?? null,
                'licenses' => $licenses, 'notice' => implode("\n\n", $notices),
            ];
        }
        usort($components, fn (array $a, array $b): int => strcmp($a['name'], $b['name']));
        $document = json_encode([
            'format' => 'cbox-composer-inventory-v1',
            'scope' => 'Production Composer dependencies. Native Apple/Firebase/Swift packages must also be inventoried from the signed archive.',
            'lock_sha256' => hash_file('sha256', base_path('composer.lock')),
            'components' => $components,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)."\n";
        $path = resource_path('third-party.json');
        if ($this->option('check')) {
            if (! is_file($path) || File::get($path) !== $document) {
                $this->components->error('Dependency inventory is stale. Run php artisan authenticator:dependencies.');

                return self::FAILURE;
            }
        } else {
            File::put($path, $document);
        }
        $this->components->info(count($components).' locked production packages checked. Marketplace entitlements remain a release requirement.');

        return self::SUCCESS;
    }
}
