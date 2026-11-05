<column class="w-full h-full bg-theme-background">
    <scroll-view class="flex-1">
        <column class="w-full px-5 py-4 gap-6">
            <column class="w-full gap-3">
                <text class="text-2xl font-semibold text-theme-on-background">{{ __('Your account, connected.') }}</text>
                <text class="text-base text-theme-on-surface-variant">{{ __('Use this iPhone to approve Cbox ID sign-ins and requests.') }}</text>
            </column>
            @if ($error !== null)
                <column class="w-full p-4 gap-2 rounded-2xl bg-theme-surface">
                    <text class="text-base font-semibold text-theme-destructive">{{ __('Could not connect') }}</text>
                    <text class="text-sm text-theme-on-surface-variant">{{ __($error) }}</text>
                </column>
            @endif
            @if ($notice !== null)
                <text class="text-sm text-theme-on-surface-variant">{{ __($notice) }}</text>
            @endif
            @if ($working)
                <column class="w-full p-5 gap-3 rounded-2xl bg-theme-surface">
                    <icon name="person.crop.circle.badge.checkmark" :size="28" class="text-theme-primary" />
                    <text class="text-lg font-semibold text-theme-on-background">{{ __('Continue signing in') }}</text>
                    <text class="text-base text-theme-on-surface-variant">{{ $signInHost }}</text>
                    <text class="text-sm text-theme-on-surface-variant">{{ __('Finish in the sign-in window. You will return here automatically.') }}</text>
                </column>
            @else
                <column class="w-full p-5 gap-5 rounded-2xl bg-theme-surface">
                    <row class="w-full gap-4 items-center">
                        <icon name="qrcode.viewfinder" :size="24" class="text-theme-primary" />
                        <column class="flex-1 gap-1">
                            <text class="text-base font-semibold text-theme-on-background">{{ __('1. Scan your connection code') }}</text>
                            <text class="text-sm text-theme-on-surface-variant">{{ __('Find it in My account → Trusted devices on your Cbox ID host.') }}</text>
                        </column>
                    </row>
                    <divider />
                    <row class="w-full gap-4 items-center">
                        <icon name="person.crop.circle" :size="24" class="text-theme-primary" />
                        <column class="flex-1 gap-1">
                            <text class="text-base font-semibold text-theme-on-background">{{ __('2. Sign in to your account') }}</text>
                            <text class="text-sm text-theme-on-surface-variant">{{ __('A secure sign-in window opens. Use your usual login, including Google.') }}</text>
                        </column>
                    </row>
                    <divider />
                    <row class="w-full gap-4 items-center">
                        <icon name="checkmark.shield" :size="24" class="text-theme-primary" />
                        <column class="flex-1 gap-1">
                            <text class="text-base font-semibold text-theme-on-background">{{ __('3. Ready to approve') }}</text>
                            <text class="text-sm text-theme-on-surface-variant">{{ __('Your requests will appear in the Cbox ID tab.') }}</text>
                        </column>
                    </row>
                </column>
                <button label="{{ __('Scan the code') }}" variant="primary" size="lg" class="w-full glass:prominent" @press="scan"></button>
                <button label="{{ __('Using this phone only?') }}" variant="ghost" @press="toggleManual"></button>
                @if ($showManual)
                    <column class="w-full p-4 gap-4 rounded-2xl bg-theme-surface">
                        <text class="text-sm text-theme-on-surface-variant">{{ __('Open Trusted devices, then tap the connection link next to the QR code.') }}</text>
                        <bare-text-input native:model="host" keyboard="url" placeholder="your-company.cboxid.com" a11y-label="{{ __('Cbox ID address') }}" class="w-full px-4 py-3 rounded-xl bg-theme-surface-variant"></bare-text-input>
                        <button label="{{ __('Open Trusted devices') }}" variant="secondary" @press="openConsole"></button>
                    </column>
                @endif
            @endif
        </column>
    </scroll-view>
</column>
