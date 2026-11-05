<column class="w-full h-full bg-theme-background">
    <scroll-view class="flex-1">
        <column class="w-full p-4 gap-4">
            <row class="w-full gap-3">
                <button label="{{ __('Add code') }}" variant="primary" @press="add"></button>
                <button label="{{ __('Backup') }}" variant="secondary" @press="backup"></button>
            </row>
            <outlined-text-input native:model="search" label="{{ __('Search codes') }}" :max-length="120" class="w-full"></outlined-text-input>
            @if ($error !== null)
                <text class="text-sm text-theme-error">{{ __($error) }}</text>
            @endif
            @if ($notice !== null)
                <text class="text-sm text-theme-on-surface-variant">{{ __($notice) }}</text>
            @endif
            @forelse ($codes as $code)
                <column class="w-full p-4 gap-2 rounded-2xl bg-theme-surface">
                    <text class="text-base font-semibold text-theme-on-background">{{ $code['issuer'] ?: $code['account'] }}</text>
                    @if ($code['issuer'] !== '')
                        <text class="text-sm text-theme-on-surface-variant">{{ $code['account'] }}</text>
                    @endif
                    <text class="text-3xl font-mono font-semibold text-theme-primary">{{ $code['code'] }}</text>
                    <text class="text-sm text-theme-on-surface-variant">{{ __('New code in :seconds seconds', ['seconds' => $code['remaining']]) }}</text>
                    <row class="w-full gap-3">
                        <button label="{{ __('Copy code') }}" variant="secondary" a11y-label="{{ __('Copy code for :account', ['account' => $code['account']]) }}" @press="copy('{{ $code['id'] }}')"></button>
                        <button label="{{ __('Details') }}" variant="ghost" @press="detail('{{ $code['id'] }}')"></button>
                    </row>
                </column>
            @empty
                <column class="w-full py-8 gap-3 items-center">
                    <icon name="key" :size="40" class="text-theme-primary" />
                    <text class="text-lg font-semibold text-theme-on-background">{{ $search !== '' ? __('No matching codes.') : __('Your codes, on this phone.') }}</text>
                    <text text-align="center" class="text-sm text-theme-on-surface-variant">{{ __('Scan a setup QR code or enter a setup key. Codes work offline, without a Cbox account.') }}</text>
                </column>
            @endforelse
            <text class="text-xs text-theme-on-surface-variant">{{ __('Keep automatic date and time enabled on your phone. Create an encrypted backup before changing or resetting your phone.') }}</text>
        </column>
    </scroll-view>
</column>
