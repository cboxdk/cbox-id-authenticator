<native:column class="w-full h-full items-center justify-center gap-4 p-6 bg-theme-background">
    @if ($error === null)
        <native:activity-indicator size="lg" />
        <native:text class="text-base text-theme-on-surface-variant">Finishing sign-in…</native:text>
    @else
        <native:icon name="exclamationmark.triangle" :size="40" class="text-theme-error" />
        <native:text text-align="center" class="text-base text-theme-on-background">{{ $error }}</native:text>
        <native:button label="Try again" variant="primary" @navigate.replace="/" />
    @endif
</native:column>
