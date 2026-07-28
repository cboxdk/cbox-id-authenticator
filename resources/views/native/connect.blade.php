<native:column class="w-full h-full p-6 gap-5 justify-center bg-white">
    <native:column class="w-full items-center gap-2">
        <native:icon name="lock.shield" :size="52" class="text-blue-600" />
        <native:text class="text-2xl font-bold text-zinc-900">Cbox ID</native:text>
        <native:text text-align="center" class="text-sm text-zinc-600">
            Approve sign-ins and agent requests from your phone.
        </native:text>
    </native:column>

    <native:column class="w-full gap-2 p-4 rounded-2xl bg-zinc-50">
        <native:text class="text-sm font-semibold text-zinc-900">Connect this phone</native:text>
        <native:text class="text-sm text-zinc-600">
            Open Cbox ID on your computer, go to Sign-in → Trusted devices, and scan the
            code shown there.
        </native:text>
    </native:column>

    @if ($error !== null)
        <native:row class="w-full items-center gap-2">
            <native:icon name="exclamationmark.triangle" :size="16" class="text-red-600" />
            <native:text class="text-sm text-red-600">{{ $error }}</native:text>
        </native:row>
    @endif

    <x-btn label="Scan the code" variant="primary" size="lg" :loading="$working" press="scan" />

    <native:text text-align="center" class="text-xs text-zinc-600">
        You'll sign in with your normal Cbox ID account in your browser.
    </native:text>
</native:column>
