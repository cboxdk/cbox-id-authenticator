<column class="w-full h-full safe-area p-6 gap-5 justify-center items-center bg-theme-background">
    <column class="w-full items-center gap-2">
        <icon name="lock.shield" :size="52" class="text-theme-primary" />
        <text class="text-2xl font-bold text-theme-on-background">Cbox ID</text>
        <text text-align="center" class="text-sm text-theme-on-surface-variant">
            Approve sign-ins and agent requests from your phone.
        </text>
    </column>

    <column class="w-full gap-2 p-4 rounded-2xl bg-theme-surface">
        <text class="text-sm font-semibold text-theme-on-background">Connect this phone</text>
        <text class="text-sm text-theme-on-surface-variant">
            Open Cbox ID on your computer, go to Sign-in → Trusted devices, and scan the
            code shown there.
        </text>
    </column>

    @if ($error !== null)
        <row class="w-full items-center gap-2">
            <icon name="exclamationmark.triangle" :size="16" class="text-theme-error" />
            <text class="text-sm text-theme-error">{{ $error }}</text>
        </row>
    @endif

    <button label="Scan the code" variant="primary" size="lg" :loading="$working" @press="scan"></button>

    <text text-align="center" class="text-xs text-theme-on-surface-variant">
        You'll sign in with your normal Cbox ID account in your browser.
    </text>

    {{-- Only when there is somewhere to go. Adding an account is a deliberate push from
         the approvals list or Settings, and the scanner is the sole control here — so
         changing your mind, or cancelling the scan, otherwise leaves you stranded on a
         screen with no way out. Absent on first run, where nothing sits beneath. --}}
    @if ($canGoBack)
        <button label="Back" variant="ghost" @press="goBack"></button>
    @endif
</column>
