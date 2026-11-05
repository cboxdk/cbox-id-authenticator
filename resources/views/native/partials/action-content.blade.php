<row class="w-full items-center gap-3">
    <column class="w-10 h-10 items-center justify-center rounded-xl bg-theme-primary/10">
        <icon name="{{ $symbol }}" :size="20" class="text-theme-primary" />
    </column>
    <column class="flex-1 gap-1">
        <text class="text-base font-medium text-theme-on-background">{{ $title }}</text>
        @if (! empty($subtitle))
            <text class="text-sm text-theme-on-surface-variant">{{ $subtitle }}</text>
        @endif
    </column>
    <icon name="{{ $trailing ?? 'chevron.right' }}" :size="14" class="text-theme-on-surface-variant" />
</row>
