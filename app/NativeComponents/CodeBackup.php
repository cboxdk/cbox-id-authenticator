<?php

declare(strict_types=1);

namespace App\NativeComponents;

use App\Events\BackupFileResult;
use App\Services\DeviceSecurity;
use App\Totp\EncryptedBackup;
use App\Totp\NativeBackupFiles;
use App\Totp\TotpEntry;
use App\Totp\TotpStore;
use Illuminate\View\View;
use Native\Mobile\Attributes\On;
use Native\Mobile\Attributes\Poll;
use Native\Mobile\Edge\NativeComponent;
use Throwable;

class CodeBackup extends NativeComponent
{
    public string $password = '';

    public string $confirmation = '';

    public ?string $error = null;

    public ?string $notice = null;

    public bool $hasFile = false;

    public bool $preview = false;

    public int $added = 0;

    public int $duplicates = 0;

    private string $encrypted = '';

    private ?string $operation = null;

    private ?int $generation = null;

    /** @var list<TotpEntry> */
    private array $pending = [];

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
            $this->clearSensitive();
        }
        $this->generation = $current;
    }

    public function export(): void
    {
        $this->guard();
        $this->error = $this->notice = null;
        try {
            if ($this->password !== $this->confirmation) {
                throw new \RuntimeException('The backup passwords do not match.');
            }
            $encrypted = app(EncryptedBackup::class)->encrypt(app(TotpStore::class)->all(), $this->password);
            $this->operation = bin2hex(random_bytes(16));
            if (! app(NativeBackupFiles::class)->save($this->operation, $encrypted)) {
                throw new \RuntimeException('Could not open Files. Unlock the app and try again.');
            }
        } catch (\RuntimeException $e) {
            $this->error = $e->getMessage();
        } catch (Throwable) {
            $this->error = 'Could not create the encrypted backup. Nothing has been changed.';
        } finally {
            $this->password = $this->confirmation = '';
        }
    }

    public function chooseFile(): void
    {
        $this->guard();
        $this->clearSensitive();
        $this->hasFile = false;
        $this->encrypted = '';
        $this->error = $this->notice = null;
        $this->operation = bin2hex(random_bytes(16));
        if (! app(NativeBackupFiles::class)->pick($this->operation)) {
            $this->error = 'Could not open Files. Unlock the app and try again.';
        }
    }

    #[On(BackupFileResult::class)]
    public function fileResult(string $id, string $result, string $contents = ''): void
    {
        if ($this->operation === null || ! hash_equals($this->operation, $id)) {
            return;
        }
        $this->operation = null;
        if ($result === 'picked' && strlen($contents) <= EncryptedBackup::MAX_BYTES && str_starts_with($contents, "CBOX-TOTP-1\n")) {
            $this->encrypted = $contents;
            $this->hasFile = true;
            $this->notice = 'Backup selected. Enter its password to preview the import.';
        } elseif ($result === 'saved') {
            $this->notice = 'Encrypted backup saved. Keep its password somewhere safe.';
        } elseif ($result !== 'cancelled') {
            $this->error = 'Choose a Cbox encrypted backup file smaller than 2 MB.';
        }
    }

    public function inspect(): void
    {
        $this->guard();
        $this->error = $this->notice = null;
        try {
            $generation = $this->generation;
            $entries = app(EncryptedBackup::class)->decrypt($this->encrypted, $this->password);
            $result = app(TotpStore::class)->merge($entries, preview: true);
            if ($generation === null || app(DeviceSecurity::class)->generation() !== $generation) {
                throw new \RuntimeException('Unlock the app and try again.');
            }
            $this->pending = $entries;
            $this->added = $result['added'];
            $this->duplicates = $result['duplicates'];
            $this->preview = true;
        } catch (\RuntimeException $e) {
            $this->error = $e->getMessage();
        } catch (Throwable) {
            $this->error = 'Could not open this backup. Check its password and use a Cbox encrypted backup file.';
        } finally {
            $this->password = $this->confirmation = '';
        }
    }

    public function import(): void
    {
        $this->guard();
        if (! $this->preview) {
            return;
        }
        try {
            $result = app(TotpStore::class)->merge($this->pending);
            $this->notice = __('Imported :added codes. Skipped :duplicates duplicates.', $result);
            $this->encrypted = '';
            $this->hasFile = false;
            $this->error = null;
            $this->clearSensitive();
        } catch (Throwable) {
            $this->error = 'Could not save your codes. Unlock your phone and try again.';
        }
    }

    public function cancelPreview(): void
    {
        $this->clearSensitive();
    }

    private function clearSensitive(): void
    {
        $this->password = $this->confirmation = '';
        $this->pending = [];
        $this->preview = false;
        $this->added = $this->duplicates = 0;
    }

    public function unmount(): void
    {
        $this->clearSensitive();
        $this->encrypted = '';
        $this->operation = null;
    }

    public function render(): View
    {
        $this->guard();

        return view('native.code-backup');
    }
}
