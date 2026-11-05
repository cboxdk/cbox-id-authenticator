<list class="w-full h-full" separator>
    @forelse ($accounts as $account)
        <list-section>
            <list-item headline="{{ $account['label'] }}" supporting="{{ $account['issuer'] }}" leadingIcon="person.crop.circle" />
            @if ($notice !== null)
                <text class="text-sm text-theme-on-surface-variant">{{ __($notice) }}</text>
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
            @if ($removing === $account['id'])
                <text class="text-base text-theme-on-background">{{ __('Remove this account from this phone? You can connect it again with a new code from Trusted devices.') }}</text>
                <button label="{{ __('Remove account') }}" variant="destructive" class="glass:prominent" :disabled="$working" @press="signOut('{{ $account['id'] }}')"></button>
                <button label="{{ __('Keep account') }}" variant="ghost" @press="cancelRemoval"></button>
            @else
                <button label="{{ __('Remove from this phone') }}" variant="ghost" class="text-theme-destructive" @press="requestRemoval('{{ $account['id'] }}')"></button>
            @endif
        </list-section>
    @empty
        <list-section><text class="text-base text-theme-on-background">{{ __('This account has been removed from this phone.') }}</text></list-section>
    @endforelse
</list>
