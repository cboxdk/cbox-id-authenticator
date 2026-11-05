<column class="w-full h-full safe-area bg-theme-background">
<scroll-view class="flex-1">
<column class="w-full p-6 gap-5 items-center">
    <column class="w-full items-center gap-2">
        <icon name="lock.shield" :size="52" class="text-theme-primary" />
        <text class="text-2xl font-bold text-theme-on-background">{{ __("Cbox ID") }}</text>
        <text text-align="center" class="text-sm text-theme-on-surface-variant">{{ __("Approve sign-ins and agent requests from your phone.") }}</text>
    </column>

    <column class="w-full gap-2 p-4 rounded-2xl bg-theme-surface">
        <text class="text-sm font-semibold text-theme-on-background">{{ __("Connect this phone") }}</text>
        <text class="text-sm text-theme-on-surface-variant">{{ __("Open Cbox ID on your computer, go to My account → Trusted devices, and scan the code shown there.") }}</text>
    </column>

    @if ($error !== null)
        <row class="w-full items-center gap-2">
            <icon name="exclamationmark.triangle" :size="16" class="text-theme-error" />
            <text class="text-sm text-theme-error">{{ __($error) }}</text>
        </row>
    @endif

    <button label="{{ __('Scan the code') }}" variant="primary" size="lg" :loading="$working" @press="scan"></button>

    <text text-align="center" class="text-xs text-theme-on-surface-variant">{{ __("You'll sign in with your normal Cbox ID account in your browser.") }}</text>

    <divider class="border-theme-outline" />
    <text class="text-base font-semibold text-theme-on-background">{{ __("Using this phone only?") }}</text>
    <text class="text-sm text-theme-on-surface-variant">{{ __("Open Trusted devices in your browser and tap the connection link next to the QR code. You can also use that link if camera access is unavailable.") }}</text>
    <outlined-text-input native:model="host" keyboard="url" placeholder="your-company.cboxid.com" a11y-label="{{ __('Cbox ID address') }}" class="w-full"></outlined-text-input>
    <button label="{{ __('Open Trusted devices') }}" variant="secondary" @press="openConsole"></button>
    <button label="{{ __('Privacy and help') }}" variant="ghost" @press="about"></button>

    {{-- Only when there is somewhere to go. Adding an account is a deliberate push from
         the approvals list or Settings, and the scanner is the sole control here — so
         changing your mind, or cancelling the scan, otherwise leaves you stranded on a
         screen with no way out. Absent on first run, where nothing sits beneath. --}}
    @if ($canGoBack)
        <button label="{{ __('Back') }}" variant="ghost" @press="goBack"></button>
    @endif
</column>
</scroll-view>
</column>
