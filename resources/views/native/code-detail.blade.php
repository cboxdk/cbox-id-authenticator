<column class="w-full h-full bg-theme-background">
    <scroll-view class="flex-1">
        <column class="w-full p-4 gap-4">
            @if ($error !== null)
                <text class="text-sm text-theme-destructive">{{ __($error) }}</text>
            @endif
            <bare-text-input native:model="issuer" a11y-label="{{ __('Service') }}" placeholder="{{ __('Service') }}" :max-length="120" class="w-full p-4 rounded-xl bg-theme-surface"></bare-text-input>
            <bare-text-input native:model="account" a11y-label="{{ __('Account name') }}" placeholder="{{ __('Account name') }}" :max-length="120" class="w-full p-4 rounded-xl bg-theme-surface"></bare-text-input>
            <text class="text-sm text-theme-on-surface-variant">{{ $configuration }}</text>
            <button label="{{ __('Save changes') }}" variant="primary" class="glass:prominent w-full" @press="save"></button>
            <divider class="border-theme-outline" />
            @if ($confirmRemoval)
                <text class="text-base text-theme-on-background">{{ __('Remove this code from this phone? Make sure you have a backup or another way to sign in. Removing it does not turn off two-factor authentication at the service.') }}</text>
                <button label="{{ __('Remove code') }}" variant="destructive" @press="remove"></button>
                <button label="{{ __('Keep code') }}" variant="secondary" @press="cancelRemoval"></button>
            @else
                <button label="{{ __('Remove code') }}" variant="destructive" @press="requestRemoval"></button>
            @endif
        </column>
    </scroll-view>
</column>
