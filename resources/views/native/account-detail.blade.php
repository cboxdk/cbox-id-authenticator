<list class="w-full h-full" separator>
    @forelse ($accounts as $account)
        <list-section>
            <list-item headline="{{ $account['label'] }}" supporting="{{ $account['issuer'] }}" leadingIcon="person.crop.circle" />
            @if ($notice !== null)
                <text class="px-4 py-3 text-sm text-theme-on-surface-variant">{{ __($notice) }}</text>
            @endif
        </list-section>
        <list-section header="{{ __('Connection') }}" footer="{{ __('To reconnect, scan a new code from My account → Trusted devices for this account.') }}">
            <list-item headline="{{ __('Manage account') }}" leadingIcon="person.crop.circle" trailingIcon="arrow.up.right" @press="manage('{{ $account['id'] }}')" />
            <list-item headline="{{ __('Reconnect account') }}" leadingIcon="qrcode.viewfinder" trailingIcon="chevron.right" @press="reconnect" />
        </list-section>
        @if ($permission !== 'unavailable')
            <list-section header="{{ __('Notifications') }}" footer="{{ $account['notifies'] ? __('Notifications are connected for this account.') : __('Open the app to check requests. Notifications are not connected yet.') }}">
                <list-item headline="{{ __('Notification settings') }}" leadingIcon="bell" trailingIcon="chevron.right" @press="notificationSettings" />
                <list-item headline="{{ __('Re-sync notifications') }}" leadingIcon="arrow.clockwise" @press="resync" />
            </list-section>
        @endif
        <list-section footer="{{ __('Signing out of an account stops approval requests from that host only.') }}">
            <pressable class="w-full px-4 py-4" a11y-label="{{ __('Remove from this phone') }}" @press="requestRemoval('{{ $account['id'] }}')">
                <row class="w-full items-center gap-4">
                    <icon name="trash" :size="24" class="text-theme-destructive" />
                    <text class="flex-1 text-base text-theme-destructive">{{ __('Remove from this phone') }}</text>
                </row>
            </pressable>
        </list-section>
    @empty
        <list-section><text class="p-4 text-base text-theme-on-background">{{ __('This account has been removed from this phone.') }}</text></list-section>
    @endforelse
</list>
