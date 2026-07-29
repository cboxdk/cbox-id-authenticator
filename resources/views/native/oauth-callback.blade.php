<column class="w-full h-full items-center justify-center gap-4 p-6 bg-theme-background">
    @if ($error === null)
        <text class="text-base text-theme-on-surface-variant">Finishing sign-in…</text>
    @else
        <icon name="exclamationmark.triangle" :size="40" class="text-theme-error" />
        <text text-align="center" class="text-base text-theme-on-background">{{ $error }}</text>
        <button label="Try again" variant="primary" @press="goConnect"></button>
    @endif
</column>
