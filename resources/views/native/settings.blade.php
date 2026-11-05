<list class="w-full h-full" separator>
    <list-section header="{{ __('Accounts') }}" footer="{{ __('Each account has its own requests and device connection.') }}">
        @foreach ($accounts as $account)
            <list-item headline="{{ $account['label'] }}" supporting="{{ $account['issuer'] }}" leadingIcon="person.crop.circle" trailingIcon="chevron.right" @press="account('{{ $account['id'] }}')" />
        @endforeach
        <list-item headline="{{ __('Add another account') }}" leadingIcon="plus.circle" trailingIcon="chevron.right" @press="goAddAccount" />
    </list-section>
    <list-section header="{{ __('Notifications') }}">
        @if ($permission === 'unavailable')
            <list-item headline="{{ __('Check requests in the app') }}" supporting="{{ __('Push notifications are not available yet.') }}" leadingIcon="bell.slash" />
        @else
            <list-item headline="{{ __('Notification settings') }}" supporting="{{ $permission === 'granted' ? __('On. You will be alerted when an app asks for approval.') : ($permission === 'denied' ? __('Off. Enable notifications in iPhone Settings.') : __('Not set up yet.')) }}" leadingIcon="bell" trailingIcon="chevron.right" @press="notificationSettings" />
            <list-item headline="{{ __('Re-sync notifications') }}" leadingIcon="arrow.clockwise" @press="resync" />
        @endif
        @if ($notice !== null)
            <text class="px-4 py-3 text-sm text-theme-on-surface-variant">{{ __($notice) }}</text>
        @endif
    </list-section>
    <list-section header="{{ __('Your data') }}">
        <list-item headline="{{ __('Encrypted backup') }}" supporting="{{ __('Import or export your verification codes') }}" leadingIcon="lock.doc" trailingIcon="chevron.right" @press="backup" />
        <list-item headline="{{ __('Privacy and help') }}" leadingIcon="hand.raised" trailingIcon="chevron.right" @press="about" />
    </list-section>
</list>
