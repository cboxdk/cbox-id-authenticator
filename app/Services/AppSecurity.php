<?php

declare(strict_types=1);

namespace App\Services;

use App\Accounts\AccountStore;
use App\Totp\TotpStore;

final class AppSecurity
{
    public function hasSecrets(): bool
    {
        return ! app(AccountStore::class)->isEmpty() || ! app(TotpStore::class)->isEmpty();
    }

    public function refresh(): void
    {
        app(DeviceSecurity::class)->configure($this->hasSecrets());
    }
}
