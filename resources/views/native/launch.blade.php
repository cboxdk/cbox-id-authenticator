<column class="w-full h-full safe-area bg-theme-background p-6 gap-4 justify-center">
    @if (isset($error) && $error !== null)
        <text class="text-lg font-semibold text-theme-on-background">{{ __("Unlock your accounts") }}</text>
        <text class="text-base text-theme-on-surface-variant">{{ __($error) }}</text>
        <button label="{{ __('Try again') }}" @press="mount"></button>
    @endif
</column>
