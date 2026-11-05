<column class="w-full h-full bg-theme-background">
    <scroll-view class="flex-1">
        <column class="w-full px-5 pt-4 pb-8 gap-6">
            @if ($error !== null)<text class="text-sm text-theme-destructive">{{ __($error) }}</text>@endif
            <column class="w-full gap-2">
                <text class="text-2xl font-bold tracking-tight text-theme-on-background">{{ __('Make it easy to find.') }}</text>
                <text class="text-base text-theme-on-surface-variant">{{ __('Choose a name you recognize for this code.') }}</text>
            </column>
            <column class="w-full p-4 gap-4 rounded-2xl bg-theme-surface border border-theme-outline/50">
                <column class="w-full gap-2">
                    <text class="text-sm font-medium text-theme-on-background">{{ __('Service') }}</text>
                    <bare-text-input native:model="issuer" a11y-label="{{ __('Service') }}" placeholder="{{ __('Service') }}" :max-length="120" class="w-full p-3 rounded-xl bg-theme-surface-variant"></bare-text-input>
                </column>
                <column class="w-full gap-2">
                    <text class="text-sm font-medium text-theme-on-background">{{ __('Account name') }}</text>
                    <bare-text-input native:model="account" a11y-label="{{ __('Account name') }}" placeholder="{{ __('Account name') }}" :max-length="120" class="w-full p-3 rounded-xl bg-theme-surface-variant"></bare-text-input>
                </column>
                <text class="text-xs font-mono text-theme-on-surface-variant">{{ $configuration }}</text>
            </column>
            <button label="{{ __('Save changes') }}" variant="primary" size="lg" class="glass:prominent w-full" @press="save"></button>
            <column class="w-full gap-3 pt-4">
                @if ($confirmRemoval)
                    <text class="text-base text-theme-on-background">{{ __('Remove this code from this phone? Make sure you have a backup or another way to sign in. Removing it does not turn off two-factor authentication at the service.') }}</text>
                    <button label="{{ __('Remove code') }}" variant="destructive" class="w-full" @press="remove"></button>
                    <button label="{{ __('Keep code') }}" variant="ghost" class="w-full" @press="cancelRemoval"></button>
                @else
                    <pressable class="w-full p-4 rounded-2xl bg-theme-destructive/5 border border-theme-destructive/20" a11y-label="{{ __('Remove code') }}" @press="requestRemoval">
                        <row class="w-full gap-3 items-center">
                            <icon name="trash" :size="22" class="text-theme-destructive" />
                            <text class="flex-1 text-base font-medium text-theme-destructive">{{ __('Remove code') }}</text>
                        </row>
                    </pressable>
                @endif
            </column>
        </column>
    </scroll-view>
</column>
