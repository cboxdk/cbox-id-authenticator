<?php

declare(strict_types=1);

namespace App\NativeComponents;

use App\Concerns\NavigatesScreens;
use App\Services\DeviceSecurity;
use App\Support\ServiceIcon;
use App\Totp\NativeBackupFiles;
use App\Totp\TotpStore;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;
use Native\Mobile\Attributes\Poll;
use Native\Mobile\Edge\NativeComponent;
use Throwable;

class Codes extends NativeComponent
{
    use NavigatesScreens;

    public string $search = '';

    public string $display = 'grid';

    public bool $largeText = false;

    public ?string $selectedId = null;

    /** @var array<string, mixed>|null */
    public ?array $selectedCode = null;

    public ?string $notice = null;

    public ?string $error = null;

    public ?string $copiedId = null;

    private int $copiedUntil = 0;

    /** @var list<array<string, mixed>> */
    public array $codes = [];

    public function mount(): void
    {
        $this->display = Cache::get('codes.display') === 'list' ? 'list' : 'grid';
        $this->refresh();
    }

    public function onResume(): void
    {
        $this->refresh();
    }

    #[Poll(1000)]
    public function refresh(): void
    {
        $this->largeText = app(DeviceSecurity::class)->usesLargeText();
        $this->codes = [];
        $this->selectedCode = null;
        if (time() >= $this->copiedUntil) {
            $this->copiedId = null;
        }
        if (app(DeviceSecurity::class)->generation() === null) {
            $this->notice = null;
            $this->copiedId = null;
            $this->selectedId = null;

            return;
        }
        try {
            $now = time();
            foreach (app(TotpStore::class)->all() as $entry) {
                if (trim($this->search) !== '' && ! str_contains(mb_strtolower($entry->issuer.' '.$entry->account), mb_strtolower(trim($this->search)))) {
                    continue;
                }
                $code = $entry->code($now);
                $this->codes[] = ['id' => $entry->id, 'issuer' => $entry->issuer, 'account' => $entry->account,
                    'icon' => ServiceIcon::forIssuer($entry->issuer),
                    'code' => implode(' ', str_split($code, intdiv($entry->digits, 2))),
                    'remaining' => $entry->period - ($now % $entry->period), 'period' => $entry->period];
            }
            $this->selectedCode = collect($this->codes)->firstWhere('id', $this->selectedId) ?? ($this->codes[0] ?? null);
            $this->selectedId = $this->selectedCode['id'] ?? null;
            $this->error = null;
        } catch (Throwable) {
            $this->codes = [];
            $this->selectedId = null;
            $this->error = 'Your saved codes could not be read. Nothing has been changed.';
        }
    }

    public function select(string $id): void
    {
        $this->refresh();
        if (collect($this->codes)->contains('id', $id)) {
            $this->selectedId = $id;
            $this->refresh();
        }
    }

    public function show(string $display): void
    {
        if (! in_array($display, ['grid', 'list'], true)) {
            return;
        }
        $this->display = $display;
        Cache::forever('codes.display', $display);
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
            $this->copiedId = $id;
            $this->copiedUntil = $now + 2;
            $this->notice = null;
        } catch (Throwable) {
            $this->copiedId = null;
            $this->notice = 'Could not copy the code. Unlock the app and try again.';
        }
    }

    public function render(): View
    {
        $this->refresh();

        return view('native.codes');
    }
}
