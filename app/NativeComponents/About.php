<?php

declare(strict_types=1);

namespace App\NativeComponents;

use App\Concerns\NavigatesScreens;
use Illuminate\View\View;
use Native\Mobile\Edge\NativeComponent;
use Native\Mobile\Facades\Browser;

class About extends NativeComponent
{
    use NavigatesScreens;

    public ?string $error = null;

    public ?string $expanded = null;

    public function toggle(string $topic): void
    {
        if (in_array($topic, ['codes', 'camera', 'privacy', 'remove', 'lost', 'unexpected'], true)) {
            $this->expanded = $this->expanded === $topic ? null : $topic;
        }
    }

    public function licenses(): void
    {
        $this->navigate('/licenses');
    }

    public function support(): void
    {
        if (! Browser::open('https://cbox.dk/contact')) {
            $this->error = 'Visit cbox.dk/contact in your browser to contact Cbox.';
        }
    }

    public function render(): View
    {
        return view('native.about');
    }
}
