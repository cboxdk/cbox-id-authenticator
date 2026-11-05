<column class="w-full h-full bg-theme-background">
    <scroll-view class="flex-1">
        <column class="w-full px-5 pt-4 pb-8 gap-5">
            <pressable class="w-full p-5 rounded-2xl bg-gradient-to-br from-blue-500/15 to-transparent border border-theme-primary/20" a11y-label="{{ __('Scan setup QR code') }}" @press="scan">
                @include('native.partials.action-content', ['symbol' => 'qrcode.viewfinder', 'title' => __('Scan setup QR code'), 'subtitle' => __('The quickest way to add a code')])
            </pressable>
            @if ($error !== null)<text class="text-sm text-theme-destructive">{{ __($error) }}</text>@endif
            <column class="w-full gap-3">
                <text class="px-1 text-sm font-semibold text-theme-on-surface-variant">{{ __('Or enter the setup key') }}</text>
                <column class="w-full p-4 gap-4 rounded-2xl bg-theme-surface border border-theme-outline/50">
                    <column class="w-full gap-2">
                        <text class="text-sm font-medium text-theme-on-background">{{ __('Service') }}</text>
                        <bare-text-input native:model="issuer" placeholder="{{ __('For example, GitHub') }}" a11y-label="{{ __('Service') }}" :max-length="120" class="w-full p-3 rounded-xl bg-theme-surface-variant"></bare-text-input>
                    </column>
                    <column class="w-full gap-2">
                        <text class="text-sm font-medium text-theme-on-background">{{ __('Account name') }}</text>
                        <bare-text-input native:model="account" placeholder="name@example.com" a11y-label="{{ __('Account name') }}" :max-length="120" class="w-full p-3 rounded-xl bg-theme-surface-variant"></bare-text-input>
                    </column>
                    <column class="w-full gap-2">
                        <text class="text-sm font-medium text-theme-on-background">{{ __('Setup key') }}</text>
                        <bare-text-input native:model="secret" placeholder="{{ __('Key provided by the service') }}" a11y-label="{{ __('Setup key') }}" :secure="true" :max-length="512" class="w-full p-3 rounded-xl bg-theme-surface-variant"></bare-text-input>
                    </column>
                </column>
            </column>
            <column class="w-full rounded-2xl bg-theme-surface border border-theme-outline/50">
                <pressable class="w-full p-4" a11y-label="{{ __('Advanced settings') }}" @press="toggleAdvanced">
                    <row class="w-full items-center gap-3">
                        <text class="flex-1 text-base text-theme-on-background">{{ __('Advanced settings') }}</text>
                        <icon name="{{ $advanced ? 'chevron.up' : 'chevron.down' }}" :size="14" class="text-theme-on-surface-variant" />
                    </row>
                </pressable>
                @if ($advanced)
                    <column class="w-full px-4 pb-4 gap-4">
                        <text class="text-sm text-theme-on-surface-variant">{{ __('Only change these settings if your service specifies them.') }}</text>
                        <outlined-text-input native:model="algorithm" label="{{ __('Algorithm (SHA1, SHA256 or SHA512)') }}" :max-length="6" class="w-full"></outlined-text-input>
                        <outlined-text-input native:model="digits" label="{{ __('Digits (6 or 8)') }}" keyboard="number" :max-length="1" class="w-full"></outlined-text-input>
                        <outlined-text-input native:model="period" label="{{ __('Interval in seconds') }}" keyboard="number" :max-length="3" class="w-full"></outlined-text-input>
                    </column>
                @endif
            </column>
            <button label="{{ __('Save code') }}" variant="primary" size="lg" class="glass:prominent w-full" @press="save"></button>
            <row class="w-full px-1 gap-2 items-center">
                <icon name="lock" :size="16" class="text-theme-on-surface-variant" />
                <text class="flex-1 text-sm text-theme-on-surface-variant">{{ __('Saved on this iPhone. Protected by Face ID or your passcode.') }}</text>
            </row>
        </column>
    </scroll-view>
</column>
