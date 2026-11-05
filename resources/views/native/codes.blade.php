<column class="w-full h-full bg-theme-background">
    <scroll-view class="flex-1">
        <column class="w-full px-5 pt-3 pb-8 gap-4">
            @if ($codes !== [] || $search !== '')
                <row class="w-full px-4 py-1 gap-2 items-center rounded-2xl bg-theme-surface border border-theme-outline/50">
                    <icon name="magnifyingglass" :size="18" class="text-theme-on-surface-variant" />
                    <bare-text-input native:model="search" placeholder="{{ __('Search codes') }}" a11y-label="{{ __('Search codes') }}" :max-length="120" class="flex-1 py-3"></bare-text-input>
                </row>
            @endif
            @if ($error !== null)<text class="text-sm text-theme-destructive">{{ __($error) }}</text>@endif
            @if ($notice !== null && $copiedId === null)<text class="text-sm text-theme-on-surface-variant">{{ __($notice) }}</text>@endif
            @forelse ($codes as $code)
                <column class="w-full p-5 gap-4 rounded-2xl bg-theme-surface border border-theme-outline/50">
                    <pressable class="w-full" a11y-label="{{ __('Code details').', '.$code['issuer'].', '.$code['account'] }}" @press="detail('{{ $code['id'] }}')">
                        <row class="w-full items-center gap-3">
                            <column class="w-10 h-10 rounded-xl bg-theme-primary/10 items-center justify-center">
                                <text class="text-lg font-semibold text-theme-primary">{{ mb_strtoupper(mb_substr($code['issuer'] ?: $code['account'], 0, 1)) }}</text>
                            </column>
                            <column class="flex-1 gap-1">
                                <text class="text-base font-semibold text-theme-on-background">{{ $code['issuer'] ?: $code['account'] }}</text>
                                @if ($code['issuer'] !== '')<text class="text-sm text-theme-on-surface-variant">{{ $code['account'] }}</text>@endif
                            </column>
                            <icon name="ellipsis" :size="18" class="text-theme-on-surface-variant" />
                        </row>
                    </pressable>
                    <pressable class="w-full py-1" a11y-label="{{ $code['issuer'].', '.__('Copy code for :account', ['account' => $code['account']]).', '.implode(' ', str_split(str_replace(' ', '', $code['code']))) }}" @press="copy('{{ $code['id'] }}')">
                        <row class="w-full items-center gap-2">
                            <text class="flex-1 text-3xl font-mono font-semibold tracking-wide text-theme-primary">{{ $code['code'] }}</text>
                            <column class="w-10 h-10 items-center justify-center rounded-full bg-theme-primary/10">
                                <icon name="{{ $copiedId === $code['id'] ? 'checkmark' : 'doc.on.doc' }}" :size="18" class="text-theme-primary" />
                            </column>
                        </row>
                    </pressable>
                    <progress-bar :value="$code['remaining'] / $code['period']" a11y-label="{{ __('New code in :seconds seconds', ['seconds' => $code['remaining']]) }}" class="w-full" />
                    <row class="w-full items-center gap-2">
                        <text class="flex-1 text-xs text-theme-on-surface-variant">{{ $copiedId === $code['id'] ? __('Copied') : __('Tap the code to copy') }}</text>
                        <text class="text-xs font-mono text-theme-on-surface-variant">{{ $code['remaining'] }} s</text>
                    </row>
                </column>
            @empty
                <column class="w-full p-6 gap-5 rounded-3xl bg-gradient-to-br from-blue-500/10 to-transparent border border-theme-outline/50">
                    @include('native.partials.mark', ['symbol' => $search !== '' ? 'magnifyingglass' : 'key.fill'])
                    <column class="w-full gap-2">
                        <text class="text-3xl font-bold tracking-tight text-theme-on-background">{{ $search !== '' ? __('No matching codes.') : __('Your codes, on this phone.') }}</text>
                        <text class="text-base text-theme-on-surface-variant">{{ $search !== '' ? __('Try a service or account name.') : __('Secure codes for your accounts. Ready even when you are offline.') }}</text>
                    </column>
                    @if ($search === '')
                        <button label="{{ __('Add code') }}" variant="primary" size="lg" class="w-full glass:prominent" @press="add"></button>
                    @endif
                </column>
                @if ($search === '')
                    <pressable class="w-full p-4 rounded-2xl bg-theme-surface border border-theme-outline/50" a11y-label="{{ __('Import a backup') }}" @press="backup">
                        @include('native.partials.action-content', ['symbol' => 'square.and.arrow.down', 'title' => __('Import a backup'), 'subtitle' => __('Bring your saved codes to this iPhone')])
                    </pressable>
                @endif
            @endforelse
        </column>
    </scroll-view>
</column>
