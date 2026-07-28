<native:column class="w-full h-full p-6 gap-5 justify-center bg-theme-background">
    <native:column class="w-full items-center gap-2">
        <native:icon name="lock.shield" :size="52" class="text-theme-primary" />
        <native:text class="text-2xl font-bold text-theme-on-background">Cbox ID</native:text>
        <native:text text-align="center" class="text-sm text-theme-on-surface-variant">
            Approve sign-ins and agent requests from your phone.
        </native:text>
    </native:column>

    {{-- `native:text-input`, not one of the outlined/filled/bare variants the docs index
         describes: 4.0.0-rc.1 registers only `text_input`. The docs are written against
         a later state of the engine than this release candidate ships. --}}
    <native:text-input
        native:model.blur="host"
        label="Your Cbox ID address"
        placeholder="acme.cboxid.com"
        keyboard="url"
        :disabled="$working"
    />

    @if ($error !== null)
        <native:row class="w-full items-center gap-2">
            <native:icon name="exclamationmark.triangle" :size="16" class="text-theme-error" />
            <native:text class="text-sm text-theme-error">{{ $error }}</native:text>
        </native:row>
    @endif

    <native:button label="Continue" variant="primary" size="lg" :loading="$working" @press="connect" />

    <native:text text-align="center" class="text-xs text-theme-on-surface-variant">
        You'll sign in with your normal Cbox ID account in your browser.
    </native:text>
</native:column>
