<list class="w-full h-full" separator>
    <list-section footer="{{ __('Scan the setup code supplied by the service, or enter its key below.') }}">
        <list-item headline="{{ __('Scan setup QR code') }}" leadingIcon="qrcode.viewfinder" trailingIcon="chevron.right" @press="scan" />
    </list-section>
    @if ($error !== null)
        <list-section><text class="text-sm text-theme-destructive">{{ __($error) }}</text></list-section>
    @endif
    <list-section header="{{ __('Account') }}">
        <bare-text-input native:model="issuer" placeholder="{{ __('Service') }}" a11y-label="{{ __('Service') }}" :max-length="120" class="w-full py-3"></bare-text-input>
        <bare-text-input native:model="account" placeholder="{{ __('Account name') }}" a11y-label="{{ __('Account name') }}" :max-length="120" class="w-full py-3"></bare-text-input>
        <bare-text-input native:model="secret" placeholder="{{ __('Setup key') }}" a11y-label="{{ __('Setup key') }}" :secure="true" :max-length="512" class="w-full py-3"></bare-text-input>
    </list-section>
    <list-section>
        <list-item headline="{{ __('Advanced settings') }}" trailingIcon="{{ $advanced ? 'chevron.down' : 'chevron.right' }}" @press="toggleAdvanced" />
        @if ($advanced)
            <text class="text-sm text-theme-on-surface-variant">{{ __('Only change these settings if your service specifies them.') }}</text>
            <outlined-text-input native:model="algorithm" label="{{ __('Algorithm (SHA1, SHA256 or SHA512)') }}" :max-length="6" class="w-full"></outlined-text-input>
            <outlined-text-input native:model="digits" label="{{ __('Digits (6 or 8)') }}" keyboard="number" :max-length="1" class="w-full"></outlined-text-input>
            <outlined-text-input native:model="period" label="{{ __('Interval in seconds') }}" keyboard="number" :max-length="3" class="w-full"></outlined-text-input>
        @endif
    </list-section>
    <list-section footer="{{ __('Codes are protected by your iPhone passcode or Face ID. They are not synced automatically.') }}">
        <button label="{{ __('Save code') }}" variant="primary" class="glass:prominent w-full" @press="save"></button>
    </list-section>
</list>
