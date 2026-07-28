<native:column class="w-full h-full items-center justify-center gap-4 p-6 bg-white">
    @if ($error === null)
        <native:text class="text-base text-zinc-600">Finishing sign-in…</native:text>
    @else
        <native:icon name="exclamationmark.triangle" :size="40" class="text-red-600" />
        <native:text text-align="center" class="text-base text-zinc-900">{{ $error }}</native:text>
        <x-btn label="Try again" variant="primary" press="goConnect" />
    @endif
</native:column>
