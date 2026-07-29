<column class="w-full h-full p-6 gap-5 justify-center bg-white">
    <column class="w-full items-center gap-2">
        <icon name="lock.shield" :size="52" class="text-blue-600" />
        <text class="text-2xl font-bold text-zinc-900">Cbox ID</text>
        <text text-align="center" class="text-sm text-zinc-600">
            Approve sign-ins and agent requests from your phone.
        </text>
    </column>

    <column class="w-full gap-2 p-4 rounded-2xl bg-zinc-50">
        <text class="text-sm font-semibold text-zinc-900">Connect this phone</text>
        <text class="text-sm text-zinc-600">
            Open Cbox ID on your computer, go to Sign-in → Trusted devices, and scan the
            code shown there.
        </text>
    </column>

    @if ($error !== null)
        <row class="w-full items-center gap-2">
            <icon name="exclamationmark.triangle" :size="16" class="text-red-600" />
            <text class="text-sm text-red-600">{{ $error }}</text>
        </row>
    @endif

    <button label="Scan the code" variant="primary" size="lg" :loading="$working" @press="scan"></button>

    <text text-align="center" class="text-xs text-zinc-600">
        You'll sign in with your normal Cbox ID account in your browser.
    </text>
</column>
