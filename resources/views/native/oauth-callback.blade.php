<column class="w-full h-full items-center justify-center gap-4 p-6 bg-white">
    @if ($error === null)
        <text class="text-base text-zinc-600">Finishing sign-in…</text>
    @else
        <icon name="exclamationmark.triangle" :size="40" class="text-red-600" />
        <text text-align="center" class="text-base text-zinc-900">{{ $error }}</text>
        <button label="Try again" variant="primary" @press="goConnect"></button>
    @endif
</column>
