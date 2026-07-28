<native:column class="w-full h-full p-6 gap-5 justify-center bg-theme-background">
    <native:column class="w-full items-center gap-2">
        <native:icon name="lock.shield" :size="52" class="text-theme-primary" />
        <native:text class="text-2xl font-bold text-theme-on-background">Cbox ID</native:text>
        <native:text text-align="center" class="text-sm text-theme-on-surface-variant">
            Approve sign-ins and agent requests from your phone.
        </native:text>
    </native:column>

    <native:column class="w-full gap-2 p-4 rounded-2xl bg-theme-surface">
        <native:text class="text-sm font-semibold text-theme-on-surface">Connect this phone</native:text>
        <native:text class="text-sm text-theme-on-surface-variant">
            Open Cbox ID on your computer, go to Sign-in → Trusted devices, and scan the
            code shown there.
        </native:text>
    </native:column>

    @if ($error !== null)
        <native:row class="w-full items-center gap-2">
            <native:icon name="exclamationmark.triangle" :size="16" class="text-theme-error" />
            <native:text class="text-sm text-theme-error">{{ $error }}</native:text>
        </native:row>
    @endif

    <x-btn label="Scan the code" variant="primary" size="lg" :loading="$working" press="scan" />

    <native:text text-align="center" class="text-xs text-theme-on-surface-variant">
        You'll sign in with your normal Cbox ID account in your browser.
    </native:text>
</native:column>
