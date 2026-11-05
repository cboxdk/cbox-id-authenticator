<column class="w-full h-full bg-theme-background">
    <scroll-view class="flex-1">
        <column class="w-full px-5 py-3 gap-4">
            @if ($codes !== [] || $search !== '')
                <bare-text-input native:model="search" placeholder="{{ __('Search codes') }}" a11y-label="{{ __('Search codes') }}" :max-length="120" class="w-full px-4 py-3 rounded-xl bg-theme-surface-variant"></bare-text-input>
            @endif
            @if ($error !== null)
                <text class="text-sm text-theme-destructive">{{ __($error) }}</text>
            @endif
            @if ($notice !== null)
                <text class="text-sm text-theme-on-surface-variant">{{ __($notice) }}</text>
            @endif
            @forelse ($codes as $code)
                <column class="w-full p-5 gap-3 rounded-2xl bg-theme-surface">
                    <pressable a11y-label="{{ __('Code details').', '.$code['account'] }}" @press="detail('{{ $code['id'] }}')">
                        <row class="w-full items-center gap-3">
                            <column class="flex-1 gap-1">
                                <text class="text-base font-semibold text-theme-on-background">{{ $code['issuer'] ?: $code['account'] }}</text>
                                @if ($code['issuer'] !== '')
                                    <text class="text-sm text-theme-on-surface-variant">{{ $code['account'] }}</text>
                                @endif
                            </column>
                            <icon name="chevron.right" :size="14" class="text-theme-on-surface-variant" />
                        </row>
                    </pressable>
                    <pressable a11y-label="{{ __('Copy code for :account', ['account' => $code['account']]) }}" @press="copy('{{ $code['id'] }}')">
                        <row class="w-full items-center gap-3 py-2">
                            <text class="flex-1 text-3xl font-mono font-semibold text-theme-primary">{{ $code['code'] }}</text>
                            <icon name="doc.on.doc" :size="20" class="text-theme-primary" />
                        </row>
                    </pressable>
                    <text class="text-xs text-theme-on-surface-variant">{{ __('New code in :seconds seconds', ['seconds' => $code['remaining']]) }}</text>
                </column>
            @empty
                <column class="w-full py-16 gap-4 items-center">
                    <icon name="key.fill" :size="48" class="text-theme-primary" />
                    <text text-align="center" class="text-2xl font-semibold text-theme-on-background">{{ $search !== '' ? __('No matching codes.') : __('Your codes, on this phone.') }}</text>
                    <text text-align="center" class="text-base text-theme-on-surface-variant">{{ __('Secure codes for your accounts. Ready even when you are offline.') }}</text>
                    @if ($search === '')
                        <button label="{{ __('Add code') }}" variant="primary" size="lg" class="glass:prominent" @press="add"></button>
                        <button label="{{ __('Import a backup') }}" variant="ghost" @press="backup"></button>
                    @endif
                </column>
            @endforelse
        </column>
    </scroll-view>
</column>
