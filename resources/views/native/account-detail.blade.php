<column class="w-full h-full bg-theme-background">
    <scroll-view class="flex-1">
        <column class="w-full p-4 gap-4">
            @forelse ($accounts as $account)
                <text class="text-2xl font-bold text-theme-on-background">{{ $account['label'] }}</text>
                <text class="text-base text-theme-on-surface-variant">{{ $account['issuer'] }}</text>
                <text class="text-base text-theme-on-background">{{ $account['notifies'] ? __('Notifications are connected for this account.') : __('Open the app to check requests. Notifications are not connected yet.') }}</text>
                @if ($notice !== null)
                    <text class="text-base text-theme-on-surface-variant">{{ __($notice) }}</text>
                @endif
                <button label="{{ __('Notification settings') }}" variant="secondary" @press="notificationSettings"></button>
                <button label="{{ __('Re-sync notifications') }}" variant="secondary" :loading="$working" @press="resync"></button>
                <button label="{{ __('Manage account') }}" variant="secondary" @press="manage('{{ $account['id'] }}')"></button>
                <button label="{{ __('Reconnect account') }}" variant="secondary" @press="reconnect"></button>
                <text class="text-sm text-theme-on-surface-variant">{{ __("To reconnect, scan a new code from My account → Trusted devices for this account.") }}</text>
                @if ($removing === $account['id'])
                    <text class="text-base text-theme-on-background">{{ __("Remove this account from this phone? You can connect it again with a new code from Trusted devices.") }}</text>
                    <button label="{{ __('Remove account') }}" variant="destructive" :disabled="$working" @press="signOut('{{ $account['id'] }}')"></button>
                    <button label="{{ __('Keep account') }}" variant="secondary" @press="cancelRemoval"></button>
                @else
                    <button label="{{ __('Remove from this phone') }}" variant="destructive" @press="requestRemoval('{{ $account['id'] }}')"></button>
                @endif
            @empty
                <text class="text-base text-theme-on-background">{{ __("This account has been removed from this phone.") }}</text>
                <button label="{{ __('Back') }}" @press="goConnect"></button>
            @endforelse
        </column>
    </scroll-view>
</column>
