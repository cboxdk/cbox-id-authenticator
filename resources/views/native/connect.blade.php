<column class="w-full h-full bg-theme-background">
    <scroll-view class="flex-1">
        <column class="w-full px-5 pt-5 pb-8 gap-6">
            <column class="w-full items-center gap-4 py-2">
                @include('native.partials.mark', ['symbol' => 'qrcode.viewfinder'])
                <text text-align="center" class="text-3xl font-bold tracking-tight text-theme-on-background">{{ __('One scan. Connected.') }}</text>
                <text text-align="center" class="text-base text-theme-on-surface-variant">{{ __('Open My account → Trusted devices on your Cbox ID host to find your code.') }}</text>
            </column>
            @if ($error !== null)
                <column class="w-full p-4 gap-2 rounded-2xl bg-theme-destructive/5 border border-theme-destructive/20">
                    <text class="text-base font-semibold text-theme-destructive">{{ __('Could not connect') }}</text>
                    <text class="text-sm text-theme-on-surface-variant">{{ __($error) }}</text>
                </column>
            @endif
            @if ($notice !== null)
                <text class="text-sm text-theme-on-surface-variant">{{ __($notice) }}</text>
            @endif
            @if ($working)
                <column class="w-full p-5 gap-3 rounded-2xl bg-theme-surface border border-theme-outline/50">
                    <activity-indicator />
                    <text class="text-lg font-semibold text-theme-on-background">{{ __('Continue signing in') }}</text>
                    <text class="text-base text-theme-primary">{{ $signInHost }}</text>
                    <text class="text-sm text-theme-on-surface-variant">{{ __('Finish in the sign-in window. You will return here automatically.') }}</text>
                </column>
            @else
                <button label="{{ __('Scan the code') }}" variant="primary" size="lg" class="w-full glass:prominent" @press="scan"></button>
                <row class="w-full px-2 gap-3 items-center">
                    <icon name="safari" :size="22" class="text-theme-primary" />
                    <text class="flex-1 text-sm text-theme-on-surface-variant">{{ __('After scanning, sign in as usual. Then you are ready to approve.') }}</text>
                </row>
                <column class="w-full rounded-2xl bg-theme-surface border border-theme-outline/50">
                    <pressable class="w-full p-4" a11y-label="{{ __('Using this phone only?') }}" @press="toggleManual">
                        @include('native.partials.action-content', ['symbol' => 'iphone', 'title' => __('Using this phone only?'), 'subtitle' => __('Connect without scanning'), 'trailing' => $showManual ? 'chevron.up' : 'chevron.down'])
                    </pressable>
                    @if ($showManual)
                        <column class="w-full px-4 pb-4 gap-4">
                            <text class="text-sm text-theme-on-surface-variant">{{ __('Open Trusted devices, then tap the connection link next to the QR code.') }}</text>
                            <bare-text-input native:model="host" keyboard="url" placeholder="your-company.cboxid.com" a11y-label="{{ __('Cbox ID address') }}" class="w-full px-4 py-3 rounded-xl bg-theme-surface-variant"></bare-text-input>
                            <button label="{{ __('Open Trusted devices') }}" variant="primary" class="w-full" @press="openConsole"></button>
                        </column>
                    @endif
                </column>
            @endif
        </column>
    </scroll-view>
</column>
