<?php

declare(strict_types=1);

namespace App\NativeComponents;

use App\Concerns\NavigatesScreens;
use App\Services\DeviceSecurity;
use App\Totp\NativeBackupFiles;
use App\Totp\TotpStore;
use Illuminate\View\View;
use Native\Mobile\Attributes\Poll;
use Native\Mobile\Edge\NativeComponent;
use Throwable;

class Codes extends NativeComponent
{
    use NavigatesScreens;

    public string $search = '';

    public ?string $notice = null;

    public ?string $error = null;

    /** @var list<array<string, mixed>> */
    public array $codes = [];

    public function mount(): void
    {
        $this->refresh();
    }

    public function onResume(): void
    {
        $this->refresh();
    }

    #[Poll(1000)]
    public function refresh(): void
    {
        $this->codes = [];
        if (app(DeviceSecurity::class)->generation() === null) {
            $this->notice = null;

            return;
        }
        try {
            $now = time();
            foreach (app(TotpStore::class)->all() as $entry) {
                if ($this->search !== '' && ! str_contains(mb_strtolower($entry->issuer.' '.$entry->account), mb_strtolower($this->search))) {
                    continue;
                }
                $code = $entry->code($now);
                $this->codes[] = ['id' => $entry->id, 'issuer' => $entry->issuer, 'account' => $entry->account,
                    'code' => implode(' ', str_split($code, intdiv($entry->digits, 2))),
                    'remaining' => $entry->period - ($now % $entry->period)];
            }
            $this->error = null;
        } catch (Throwable) {
            $this->error = 'Your saved codes could not be read. Nothing has been changed.';
        }
    }

    public function updatedSearch(): void
    {
        $this->refresh();
    }

    public function add(): void
    {
        $this->navigate('/codes/add');
    }

    public function backup(): void
    {
        $this->navigate('/codes/backup');
    }

    public function detail(string $id): void
    {
        $this->navigate('/codes/detail/'.$id);
    }

    public function copy(string $id): void
    {
        try {
            $entry = app(TotpStore::class)->find($id);
            $now = time();
            if ($entry === null || ! app(NativeBackupFiles::class)->copyCode($entry->code($now), $now + $entry->period - $now % $entry->period)) {
                throw new \RuntimeException;
            }
            $this->notice = 'Code copied. The clipboard expires when the code does.';
        } catch (Throwable) {
            $this->notice = 'Could not copy the code. Unlock the app and try again.';
        }
    }

    public function render(): View
    {
        $this->refresh();

        return view('native.codes');
    }
}
