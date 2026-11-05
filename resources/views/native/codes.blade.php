<column class="w-full h-full bg-theme-background">
    @if ($codes !== [] || $search !== '')
        <row class="w-full px-5 pt-2 pb-3 gap-3 items-center">
            <row class="flex-1 px-3 gap-2 items-center rounded-2xl bg-theme-surface border border-theme-outline/50">
                <icon name="magnifyingglass" :size="17" class="text-theme-on-surface-variant" />
                <bare-text-input native:model="search" placeholder="{{ __('Search codes') }}" a11y-label="{{ __('Search codes') }}" :max-length="120" class="flex-1 py-3"></bare-text-input>
            </row>
            @if (! $largeText)
            <row class="p-1 rounded-full bg-theme-surface-variant">
                @foreach (['list' => 'list.bullet', 'grid' => 'square.grid.2x2'] as $mode => $symbol)
                    <pressable class="w-11 h-11 rounded-full items-center justify-center {{ $display === $mode ? 'bg-theme-primary' : '' }}" a11y-label="{{ $mode === 'list' ? __('List view') : __('Grid view') }}" a11y-value="{{ $display === $mode ? __('Selected') : '' }}" @press="show('{{ $mode }}')">
                        <icon name="{{ $symbol }}" :size="18" class="{{ $display === $mode ? 'text-theme-on-primary' : 'text-theme-on-surface-variant' }}" />
                    </pressable>
                @endforeach
            </row>
            @endif
        </row>
    @endif
    @if ($error !== null)<text class="px-5 pb-3 text-sm text-theme-destructive">{{ __($error) }}</text>@endif
    @if ($notice !== null && $copiedId === null)<text class="px-5 pb-3 text-sm text-theme-on-surface-variant">{{ __($notice) }}</text>@endif
    @if ($codes !== [] && $display === 'grid' && $selectedCode !== null && trim($search) === '' && ! $largeText)
        @php($code = $selectedCode)
        <column class="w-full px-5 pb-4 gap-3">
            <column class="w-full p-5 gap-3 rounded-3xl bg-theme-surface border border-theme-outline/50">
                <row class="w-full items-center gap-3">
                    @include('native.partials.service-icon', ['code' => $code, 'large' => true])
                    <column class="flex-1 gap-1">
                        <text class="text-lg font-semibold text-theme-on-background">{{ $code['issuer'] ?: $code['account'] }}</text>
                        @if ($code['issuer'] !== '')<text class="text-sm text-theme-on-surface-variant">{{ $code['account'] }}</text>@endif
                    </column>
                    <pressable class="w-11 h-11 items-center justify-center" a11y-label="{{ __('Code details').', '.$code['issuer'].', '.$code['account'] }}" @press="detail('{{ $code['id'] }}')">
                        <icon name="ellipsis" :size="22" class="text-theme-on-surface-variant" />
                    </pressable>
                </row>
                <pressable class="w-full py-2" a11y-label="{{ $code['issuer'].', '.__('Copy code for :account', ['account' => $code['account']]).', '.implode(' ', str_split(str_replace(' ', '', $code['code']))) }}" @press="copy('{{ $code['id'] }}')">
                    <column class="w-full items-center gap-2">
                        <text text-align="center" class="w-full {{ strlen($code['code']) > 7 ? 'text-4xl' : 'text-5xl' }} font-mono font-semibold tracking-tight text-theme-on-background">{{ $code['code'] }}</text>
                        <row class="items-center justify-center gap-2">
                            <icon name="{{ $copiedId === $code['id'] ? 'checkmark' : 'doc.on.doc' }}" :size="14" class="text-theme-primary" />
                            <text class="text-sm font-medium text-theme-primary">{{ $copiedId === $code['id'] ? __('Copied') : __('Tap the code to copy') }}</text>
                        </row>
                    </column>
                </pressable>
                <progress-bar :value="$code['remaining'] / $code['period']" a11y-label="{{ __('New code in :seconds seconds', ['seconds' => $code['remaining']]) }}" class="w-full" />
                <text text-align="center" class="w-full text-xs text-theme-on-surface-variant">{{ __('New code in :seconds seconds', ['seconds' => $code['remaining']]) }}</text>
            </column>
            <text class="px-1 text-sm font-semibold text-theme-on-surface-variant">{{ __('Your services') }}</text>
        </column>
        <lazy-grid :columns="2" :gap="12" class="flex-1 w-full px-5">
            @foreach ($codes as $code)
                <pressable class="w-full p-3 rounded-2xl border {{ $selectedId === $code['id'] ? 'bg-theme-primary/10 border-theme-primary' : 'bg-theme-surface border-theme-outline/50' }}" a11y-label="{{ __('Show code for :account', ['account' => $code['account']]).', '.$code['issuer'] }}" a11y-value="{{ $selectedId === $code['id'] ? __('Selected') : '' }}" @press="select('{{ $code['id'] }}')">
                    <column class="w-full gap-2">
                        <row class="w-full items-center">
                            @include('native.partials.service-icon', ['code' => $code, 'large' => false])
                            <spacer />
                            @if ($selectedId === $code['id'])<icon name="checkmark.circle.fill" :size="18" class="text-theme-primary" />@endif
                        </row>
                        <column class="w-full gap-1">
                            <text class="text-sm font-semibold text-theme-on-background">{{ $code['issuer'] ?: $code['account'] }}</text>
                            @if ($code['issuer'] !== '')<text class="text-xs text-theme-on-surface-variant">{{ $code['account'] }}</text>@endif
                        </column>
                    </column>
                </pressable>
            @endforeach
        </lazy-grid>
    @else
        <scroll-view class="flex-1">
            <column class="w-full px-5 pt-1 pb-8 gap-3">
                @forelse ($codes as $code)
                    <column class="w-full p-4 gap-2 rounded-2xl bg-theme-surface border border-theme-outline/50">
                        <row class="w-full items-center gap-3">
                            @include('native.partials.service-icon', ['code' => $code, 'large' => false])
                            <column class="flex-1 gap-1">
                                <text class="text-base font-semibold text-theme-on-background">{{ $code['issuer'] ?: $code['account'] }}</text>
                                @if ($code['issuer'] !== '')<text class="text-sm text-theme-on-surface-variant">{{ $code['account'] }}</text>@endif
                            </column>
                            <pressable class="w-11 h-11 items-center justify-center" a11y-label="{{ __('Code details').', '.$code['issuer'].', '.$code['account'] }}" @press="detail('{{ $code['id'] }}')">
                                <icon name="ellipsis" :size="20" class="text-theme-on-surface-variant" />
                            </pressable>
                        </row>
                        <pressable class="w-full py-1" a11y-label="{{ $code['issuer'].', '.__('Copy code for :account', ['account' => $code['account']]).', '.implode(' ', str_split(str_replace(' ', '', $code['code']))) }}" @press="copy('{{ $code['id'] }}')">
                            <row class="w-full items-center gap-2">
                                <text class="flex-1 text-3xl font-mono font-semibold text-theme-on-background">{{ $code['code'] }}</text>
                                <text class="text-xs text-theme-on-surface-variant">{{ $copiedId === $code['id'] ? __('Copied') : $code['remaining'].' s' }}</text>
                                <icon name="{{ $copiedId === $code['id'] ? 'checkmark' : 'doc.on.doc' }}" :size="18" class="text-theme-primary" />
                            </row>
                        </pressable>
                        <progress-bar :value="$code['remaining'] / $code['period']" a11y-label="{{ __('New code in :seconds seconds', ['seconds' => $code['remaining']]) }}" class="w-full" />
                    </column>
                @empty
                    <column class="w-full p-6 gap-5 rounded-3xl bg-gradient-to-br from-blue-500/10 to-transparent border border-theme-outline/50">
                        @include('native.partials.mark', ['symbol' => $search !== '' ? 'magnifyingglass' : 'key.fill'])
                        <column class="w-full gap-2">
                            <text class="text-3xl font-bold tracking-tight text-theme-on-background">{{ $search !== '' ? __('No matching codes.') : __('Your codes, on this phone.') }}</text>
                            <text class="text-base text-theme-on-surface-variant">{{ $search !== '' ? __('Try a service or account name.') : __('Secure codes for your accounts. Ready even when you are offline.') }}</text>
                        </column>
                        @if ($search === '')<button label="{{ __('Add code') }}" variant="primary" size="lg" class="w-full glass:prominent" @press="add"></button>@endif
                    </column>
                    @if ($search === '')
                        <pressable class="w-full p-4 rounded-2xl bg-theme-surface border border-theme-outline/50" a11y-label="{{ __('Import a backup') }}" @press="backup">
                            @include('native.partials.action-content', ['symbol' => 'square.and.arrow.down', 'title' => __('Import a backup'), 'subtitle' => __('Bring your saved codes to this iPhone')])
                        </pressable>
                    @endif
                @endforelse
            </column>
        </scroll-view>
    @endif
</column>
