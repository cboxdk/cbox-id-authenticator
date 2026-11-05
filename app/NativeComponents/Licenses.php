<?php

declare(strict_types=1);

namespace App\NativeComponents;

use App\Concerns\NavigatesScreens;
use Illuminate\Support\Facades\File;
use Illuminate\View\View;
use Native\Mobile\Edge\NativeComponent;

class Licenses extends NativeComponent
{
    use NavigatesScreens;

    /** @var list<array{name: string, version: string, licenses: list<string>, notice: string}> */
    public array $packages = [];

    public ?int $selected = null;

    public function mount(): void
    {
        $data = json_decode(File::get(resource_path('third-party.json')), true, 512, JSON_THROW_ON_ERROR);
        $native = json_decode(File::get(resource_path('ios-third-party.json')), true, 512, JSON_THROW_ON_ERROR);
        $this->packages = array_merge($native['components'], $data['components']);
        $this->packages[] = ['name' => 'Simple Icons', 'version' => '16.0.0', 'licenses' => ['CC0-1.0'],
            'notice' => File::get(resource_path('brand/services/LICENSE.txt'))."\n\nhttps://github.com/simple-icons/simple-icons/tree/16.0.0\nBrand names and logos belong to their respective owners."];
    }

    public function select(int $index): void
    {
        if (isset($this->packages[$index])) {
            $this->selected = $index;
        }
    }

    public function showAll(): void
    {
        $this->selected = null;
    }

    public function render(): View
    {
        return view('native.licenses');
    }
}
