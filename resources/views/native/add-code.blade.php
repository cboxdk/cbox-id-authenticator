<column class="w-full h-full bg-theme-background">
    <scroll-view class="flex-1">
        <column class="w-full p-4 gap-4">
            <button label="{{ __('Scan setup QR code') }}" variant="primary" @press="scan"></button>
            <text class="text-sm text-theme-on-surface-variant">{{ __('Or enter the setup key supplied by the service. Keep its recovery codes separately.') }}</text>
            @if ($error !== null)
                <text class="text-sm text-theme-error">{{ __($error) }}</text>
            @endif
            <outlined-text-input native:model="issuer" label="{{ __('Service') }}" :max-length="120" class="w-full"></outlined-text-input>
            <outlined-text-input native:model="account" label="{{ __('Account name') }}" :max-length="120" class="w-full"></outlined-text-input>
            <outlined-text-input native:model="secret" label="{{ __('Setup key') }}" :secure="true" :max-length="512" class="w-full"></outlined-text-input>
            <button label="{{ __('Advanced settings') }}" variant="ghost" @press="toggleAdvanced"></button>
            @if ($advanced)
                <text class="text-sm text-theme-on-surface-variant">{{ __('Only change these settings if your service specifies them.') }}</text>
                <outlined-text-input native:model="algorithm" label="{{ __('Algorithm (SHA1, SHA256 or SHA512)') }}" :max-length="6" class="w-full"></outlined-text-input>
                <outlined-text-input native:model="digits" label="{{ __('Digits (6 or 8)') }}" keyboard="number" :max-length="1" class="w-full"></outlined-text-input>
                <outlined-text-input native:model="period" label="{{ __('Interval in seconds') }}" keyboard="number" :max-length="3" class="w-full"></outlined-text-input>
            @endif
            <button label="{{ __('Save code') }}" variant="primary" @press="save"></button>
            <text class="text-xs text-theme-on-surface-variant">{{ __('Codes are protected by your iPhone passcode or Face ID. They are not synced automatically.') }}</text>
        </column>
    </scroll-view>
</column>
