<column class="w-full h-full safe-area items-center justify-center gap-4 p-6 bg-theme-background">
    @if ($error === null)
        <text class="text-base text-theme-on-surface-variant">{{ __("Finishing sign-in…") }}</text>
    @else
        <icon name="exclamationmark.triangle" :size="40" class="text-theme-destructive" />
        <text text-align="center" class="text-base text-theme-on-background">{{ __($error) }}</text>
        <button label="{{ __('Try again') }}" variant="primary" @press="goConnect"></button>
    @endif
</column>
