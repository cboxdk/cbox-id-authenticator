<?php

declare(strict_types=1);

namespace App\NativeComponents;

use App\Concerns\NavigatesScreens;
use App\Totp\TotpStore;
use Illuminate\View\View;
use Native\Mobile\Edge\NativeComponent;
use Throwable;

class CodeDetail extends NativeComponent
{
    use NavigatesScreens;

    public string $id = '';

    public string $issuer = '';

    public string $account = '';

    public string $configuration = '';

    public bool $confirmRemoval = false;

    public ?string $error = null;

    public function mount(): void
    {
        $this->id = (string) $this->param('id');
        try {
            $entry = app(TotpStore::class)->find($this->id);
            if ($entry === null) {
                $this->replace('/codes');

                return;
            }
            $this->issuer = $entry->issuer;
            $this->account = $entry->account;
            $this->configuration = $entry->algorithm.' · '.$entry->digits.' · '.$entry->period.'s';
        } catch (Throwable) {
            $this->error = 'Unlock the app and try again.';
        }
    }

    public function save(): void
    {
        try {
            app(TotpStore::class)->rename($this->id, $this->issuer, $this->account);
            $this->replace('/codes');
        } catch (\InvalidArgumentException $e) {
            $this->error = $e->getMessage();
        } catch (Throwable) {
            $this->error = 'Could not save your codes. Unlock your phone and try again.';
        }
    }

    public function requestRemoval(): void
    {
        $this->confirmRemoval = true;
    }

    public function cancelRemoval(): void
    {
        $this->confirmRemoval = false;
    }

    public function remove(): void
    {
        if (! $this->confirmRemoval) {
            return;
        }
        try {
            app(TotpStore::class)->remove($this->id);
            $this->replace('/codes');
        } catch (Throwable) {
            $this->error = 'Could not remove the code. Unlock your phone and try again.';
        }
    }

    public function render(): View
    {
        return view('native.code-detail');
    }
}
