<column class="w-full h-full safe-area bg-theme-background p-6 gap-4 justify-center">
    @if ($notFound)
        <column class="w-16 h-16 items-center justify-center rounded-2xl bg-theme-surface-variant">
            <icon name="questionmark.circle" :size="32" class="text-theme-on-surface-variant" />
        </column>
        <text class="text-2xl font-bold text-theme-on-background">{{ __("No request for this phone") }}</text>
        @if ($unreachable)
            <text class="text-base text-theme-on-surface-variant">{{ __("Couldn't reach every Cbox ID host. Check your connection and try again.") }}</text>
            <button label="{{ __('Try again') }}" variant="primary" class="w-full" @press="mount"></button>
        @else
            <text class="text-base text-theme-on-surface-variant">{{ __("It may have expired or been answered, or it belongs to an account that isn't on this phone.") }}</text>
        @endif
        <button label="{{ __('Open approvals') }}" variant="secondary" class="w-full" @press="goApprovals"></button>
    @else
        <activity-indicator />
    @endif
</column>
