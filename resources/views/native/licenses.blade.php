<column class="w-full h-full bg-theme-background">
    <scroll-view class="flex-1">
        <column class="w-full p-4 gap-4">
            @if ($selected !== null)
                <text class="text-lg font-semibold text-theme-on-background">{{ $packages[$selected]['name'] }}</text>
                <text class="text-base text-theme-on-surface-variant">{{ implode(' / ', $packages[$selected]['licenses']) }}</text>
                <text class="text-base text-theme-on-background">{{ $packages[$selected]['notice'] ?: __('License terms are provided by the package supplier.') }}</text>
                <button label="{{ __('All libraries') }}" variant="secondary" @press="showAll"></button>
            @else
                <text class="text-base text-theme-on-surface-variant">{{ __('This app includes the following PHP libraries. The iOS app also uses Apple and Firebase SDKs.') }}</text>
                @foreach ($packages as $index => $package)
                    <button label="{{ $package['name'].' · '.$package['version'] }}" variant="secondary" @press="select({{ $index }})"></button>
                @endforeach
            @endif
        </column>
    </scroll-view>
</column>
