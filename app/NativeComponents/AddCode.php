<?php

declare(strict_types=1);

namespace App\NativeComponents;

use App\Concerns\NavigatesScreens;
use App\Services\DeviceSecurity;
use App\Totp\TotpEntry;
use App\Totp\TotpStore;
use App\Totp\TotpUri;
use Illuminate\View\View;
use Native\Mobile\Attributes\On;
use Native\Mobile\Attributes\Poll;
use Native\Mobile\Edge\NativeComponent;
use Native\Mobile\Events\Scanner\CodeScanned;
use Native\Mobile\Facades\Scanner;
use Throwable;

class AddCode extends NativeComponent
{
    use NavigatesScreens;

    public string $issuer = '';

    public string $account = '';

    public string $secret = '';

    public string $algorithm = 'SHA1';

    public string $digits = '6';

    public string $period = '30';

    public bool $advanced = false;

    public ?string $error = null;

    private ?int $generation = null;

    public function mount(): void
    {
        app(DeviceSecurity::class)->configure(true);
        $this->generation = app(DeviceSecurity::class)->generation();
    }

    #[Poll(1000)]
    public function guard(): void
    {
        $current = app(DeviceSecurity::class)->generation();
        if ($current === null || ($this->generation !== null && $current !== $this->generation)) {
            $this->secret = '';
        }
        $this->generation = $current;
    }

    public function scan(): void
    {
        $this->guard();
        if ($this->generation !== null) {
            Scanner::scan()->id('totp-add')->scan();
        }
    }

    #[On(CodeScanned::class)]
    public function scanned(string $data): void
    {
        $this->guard();
        if ($this->generation === null) {
            return;
        }
        try {
            $entry = TotpUri::parse($data);
            $this->issuer = $entry->issuer;
            $this->account = $entry->account;
            $this->secret = $entry->secret;
            $this->algorithm = $entry->algorithm;
            $this->digits = (string) $entry->digits;
            $this->period = (string) $entry->period;
            $this->advanced = $entry->algorithm !== 'SHA1' || $entry->digits !== 6 || $entry->period !== 30;
            $this->error = null;
        } catch (\InvalidArgumentException $e) {
            $this->error = $e->getMessage();
        }
    }

    public function toggleAdvanced(): void
    {
        $this->advanced = ! $this->advanced;
    }

    public function save(): void
    {
        $this->guard();
        try {
            if (! ctype_digit($this->period) || strlen($this->period) > 3 || ! in_array($this->digits, ['6', '8'], true)) {
                throw new \InvalidArgumentException('This TOTP configuration is not supported.');
            }
            $entry = new TotpEntry(bin2hex(random_bytes(16)), trim($this->issuer), trim($this->account), $this->secret,
                strtoupper(trim($this->algorithm)), (int) $this->digits, (int) $this->period);
            $result = app(TotpStore::class)->merge([$entry]);
            $this->secret = '';
            if ($result['added'] === 0) {
                $this->error = 'This code is already saved on this phone.';

                return;
            }
            $this->replace('/codes');
        } catch (\InvalidArgumentException $e) {
            $this->error = $e->getMessage();
        } catch (Throwable) {
            $this->error = 'Could not save your codes. Unlock your phone and try again.';
        }
    }

    public function unmount(): void
    {
        $this->secret = '';
    }

    public function render(): View
    {
        $this->guard();

        return view('native.add-code');
    }
}
