<column class="w-full h-full bg-theme-background">
    <scroll-view class="flex-1">
        <column class="w-full px-6 py-8 gap-6">
            <column class="w-full items-center gap-3 py-4">
                <icon name="qrcode.viewfinder" :size="56" class="text-theme-primary" />
                <text text-align="center" class="text-2xl font-bold text-theme-on-background">{{ __('Connect to Cbox ID') }}</text>
                <text text-align="center" class="text-base text-theme-on-surface-variant">{{ __('Scan the QR code in My account → Trusted devices on your Cbox ID host.') }}</text>
            </column>
            @if ($error !== null)
                <column class="w-full p-4 gap-2 rounded-2xl bg-theme-surface">
                    <text class="text-sm font-semibold text-theme-destructive">{{ __('Could not connect') }}</text>
                    <text class="text-sm text-theme-on-surface-variant">{{ __($error) }}</text>
                </column>
            @endif
            <button label="{{ __('Scan the code') }}" variant="primary" size="lg" class="w-full glass:prominent" :loading="$working" @press="scan"></button>
            <text text-align="center" class="text-sm text-theme-on-surface-variant">{{ __('The QR code selects the server and account.') }}</text>
            <button label="{{ __('Using this phone only?') }}" variant="ghost" @press="toggleManual"></button>
            @if ($showManual)
                <column class="w-full p-4 gap-4 rounded-2xl bg-theme-surface">
                    <text class="text-sm text-theme-on-surface-variant">{{ __('Open Trusted devices, then tap the connection link next to the QR code.') }}</text>
                    <outlined-text-input native:model="host" keyboard="url" placeholder="your-company.cboxid.com" a11y-label="{{ __('Cbox ID address') }}" class="w-full"></outlined-text-input>
                    <button label="{{ __('Open Trusted devices') }}" variant="secondary" class="glass" @press="openConsole"></button>
                </column>
            @endif
        </column>
    </scroll-view>
</column>
