<?php

declare(strict_types=1);

namespace App\Providers;

use App\Accounts\AccountContext;
use App\Accounts\AccountStore;
use App\Accounts\LegacyAccountMigration;
use App\Accounts\PendingSignIns;
use App\Services\DeviceSecurity;
use App\Services\ServerClock;
use App\Support\NativePackageCommand;
use Illuminate\Support\ServiceProvider;
use Native\Mobile\Commands\PackageCommand;
use Throwable;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * Singletons, all four, and not for performance.
     *
     * {@see ServerClock} holds the correction learned from the last response's `Date`
     * header. Resolved fresh each time, every request would re-learn an offset the next
     * one discards — the handset would correct itself endlessly and never benefit.
     *
     * {@see AccountStore} caches the decoded index. Two instances would be two views of
     * it, and a write through one would leave the other reading stale accounts —
     * the kind of bug that shows up as an account vanishing until the app restarts.
     *
     * {@see AccountContext} memoises a service graph per account, which is how a
     * `DpopKey` keeps its in-memory PEM instead of re-reading the keychain for every
     * proof. It must also share the one `AccountStore` above, which it does by holding
     * the same instance.
     */
    public function register(): void
    {
        $this->app->bind(PackageCommand::class, NativePackageCommand::class);
        $this->app->singleton(ServerClock::class);
        $this->app->singleton(AccountStore::class);
        $this->app->singleton(PendingSignIns::class);
        $this->app->singleton(AccountContext::class);
    }

    /**
     * Bootstrap any application services.
     *
     * The migration runs here rather than on a screen, because a cold start can land
     * anywhere: a notification tap opens `/approvals/…` directly, and a callback deep
     * link opens `/oauth/callback`. Hanging it off the first screen would mean the one
     * path that skipped that screen silently signed the user out.
     */
    public function boot(): void
    {
        $this->app->setLocale($this->app->make(DeviceSecurity::class)->locale());
        try {
            $this->app->make(LegacyAccountMigration::class)->run();
        } catch (Throwable) {
            // Launch shows a retry state when storage is unavailable. Never replace
            // the saved account index with an empty one after a failed read.
        }
    }
}
