<column class="w-full h-full bg-theme-background">
    <scroll-view class="flex-1">
        <column class="w-full px-5 pt-4 pb-8 gap-6">
            @forelse ($accounts as $account)
                <column class="w-full p-6 items-center gap-3 rounded-3xl bg-gradient-to-br from-blue-500/10 to-transparent border border-theme-outline/50">
                    <column class="w-16 h-16 rounded-full bg-theme-primary/10 items-center justify-center">
                        <text class="text-2xl font-semibold text-theme-primary">{{ mb_strtoupper(mb_substr($account['label'], 0, 1)) }}</text>
                    </column>
                    <text text-align="center" class="text-2xl font-bold text-theme-on-background">{{ $account['label'] }}</text>
                    <text text-align="center" class="text-sm text-theme-on-surface-variant">{{ parse_url($account['issuer'], PHP_URL_HOST) }}</text>
                </column>
                @if ($notice !== null)<text class="text-sm text-theme-on-surface-variant">{{ __($notice) }}</text>@endif
                <column class="w-full gap-3">
                    <text class="px-1 text-sm font-semibold text-theme-on-surface-variant">{{ __('Connection') }}</text>
                    <column class="w-full rounded-2xl bg-theme-surface border border-theme-outline/50">
                        <pressable class="w-full p-4" a11y-label="{{ __('Manage account') }}" @press="manage('{{ $account['id'] }}')">
                            @include('native.partials.action-content', ['symbol' => 'person.crop.circle', 'title' => __('Manage account'), 'subtitle' => __('Opens your Cbox ID host'), 'trailing' => 'arrow.up.right'])
                        </pressable>
                        <column class="w-full px-4"><column class="w-full h-[1] bg-theme-outline/40"></column></column>
                        <pressable class="w-full p-4" a11y-label="{{ __('Reconnect account') }}" @press="reconnect">
                            @include('native.partials.action-content', ['symbol' => 'qrcode.viewfinder', 'title' => __('Reconnect account'), 'subtitle' => __('Use a new connection code')])
                        </pressable>
                    </column>
                </column>
                @if ($permission !== 'unavailable')
                    <column class="w-full rounded-2xl bg-theme-surface border border-theme-outline/50">
                        <pressable class="w-full p-4" a11y-label="{{ __('Notification settings') }}" @press="notificationSettings">
                            @include('native.partials.action-content', ['symbol' => 'bell', 'title' => __('Notification settings'), 'subtitle' => $account['notifies'] ? __('Notifications are connected for this account.') : __('Open the app to check requests. Notifications are not connected yet.')])
                        </pressable>
                        <column class="w-full px-4"><column class="w-full h-[1] bg-theme-outline/40"></column></column>
                        <pressable class="w-full p-4" a11y-label="{{ __('Re-sync notifications') }}" @press="resync">
                            @include('native.partials.action-content', ['symbol' => 'arrow.clockwise', 'title' => __('Re-sync notifications'), 'subtitle' => ''])
                        </pressable>
                    </column>
                @endif
                <column class="w-full gap-3 pt-2">
                    <pressable class="w-full p-4 rounded-2xl bg-theme-destructive/5 border border-theme-destructive/20" a11y-label="{{ __('Remove from this phone') }}" @press="requestRemoval('{{ $account['id'] }}')">
                        <row class="w-full gap-3 items-center">
                            <icon name="trash" :size="22" class="text-theme-destructive" />
                            <text class="flex-1 text-base font-medium text-theme-destructive">{{ __('Remove from this phone') }}</text>
                        </row>
                    </pressable>
                    <text class="px-1 text-sm text-theme-on-surface-variant">{{ __('This only disconnects this account on this iPhone. Your other accounts and codes stay here.') }}</text>
                </column>
            @empty
                <text class="text-base text-theme-on-background">{{ __('This account has been removed from this phone.') }}</text>
            @endforelse
        </column>
    </scroll-view>
</column>
