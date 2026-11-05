<?php

declare(strict_types=1);

namespace App\NativeComponents;

use App\Accounts\AccountContext;
use Illuminate\View\View;
use Native\Mobile\Facades\System;

class AccountDetail extends Settings
{
    public function mount(): void
    {
        parent::mount();
        $id = (string) $this->param('id');
        $this->accounts = array_values(array_filter($this->accounts, fn (array $account): bool => $account['id'] === $id));
    }

    public function reconnect(): void
    {
        $this->navigate('/settings/connect', ['reconnect' => (string) $this->param('id')]);
    }

    public function notificationSettings(): void
    {
        System::appSettings();
    }

    public function signOut(string $accountId): void
    {
        parent::signOut($accountId);
        if (app(AccountContext::class)->store()->find($accountId) === null) {
            $this->replace('/settings');
        }
    }

    public function render(): View
    {
        return view('native.account-detail');
    }
}
