<column class="w-full h-full bg-theme-background">
    <scroll-view class="flex-1">
        <column class="w-full px-5 pt-3 pb-8 gap-6">
            <text class="text-base text-theme-on-surface-variant">{{ __('Your accounts, security and backups.') }}</text>
            <column class="w-full gap-3">
                <text class="px-1 text-sm font-semibold text-theme-on-surface-variant">Cbox ID</text>
                <column class="w-full rounded-2xl bg-theme-surface border border-theme-outline/50">
                    @foreach ($accounts as $account)
                        <pressable class="w-full p-4" a11y-label="{{ $account['label'] }}" a11y-hint="{{ parse_url($account['issuer'], PHP_URL_HOST) }}" @press="account('{{ $account['id'] }}')">
                            <row class="w-full items-center gap-3">
                                <column class="w-11 h-11 rounded-xl bg-theme-primary/10 items-center justify-center">
                                    <text class="text-base font-semibold text-theme-primary">{{ mb_strtoupper(mb_substr($account['label'], 0, 1)) }}</text>
                                </column>
                                <column class="flex-1 gap-1">
                                    <text class="text-base font-semibold text-theme-on-background">{{ $account['label'] }}</text>
                                    <text class="text-sm text-theme-on-surface-variant">{{ parse_url($account['issuer'], PHP_URL_HOST) }}</text>
                                </column>
                                <icon name="chevron.right" :size="16" class="text-theme-on-surface-variant" />
                            </row>
                        </pressable>
                        <column class="w-full px-4"><column class="w-full h-[1] bg-theme-outline/40"></column></column>
                    @endforeach
                    <pressable class="w-full p-4" a11y-label="{{ __('Add another account') }}" @press="goAddAccount">
                        @include('native.partials.action-content', ['symbol' => 'plus', 'title' => __('Add another account'), 'subtitle' => ''])
                    </pressable>
                </column>
            </column>
            <column class="w-full gap-3">
                <text class="px-1 text-sm font-semibold text-theme-on-surface-variant">{{ __('Security and data') }}</text>
                <column class="w-full rounded-2xl bg-theme-surface border border-theme-outline/50">
                    <pressable class="w-full p-4" a11y-label="{{ __('Encrypted backup') }}" a11y-hint="{{ __('Keep a copy of your verification codes') }}" @press="backup">
                        @include('native.partials.action-content', ['symbol' => 'lock.doc', 'title' => __('Encrypted backup'), 'subtitle' => __('Keep a copy of your verification codes')])
                    </pressable>
                    <column class="w-full px-4"><column class="w-full h-[1] bg-theme-outline/40"></column></column>
                    <pressable class="w-full p-4" a11y-label="{{ __('Privacy and help') }}" @press="about">
                        @include('native.partials.action-content', ['symbol' => 'hand.raised', 'title' => __('Privacy and help'), 'subtitle' => ''])
                    </pressable>
                </column>
            </column>
            <column class="w-full gap-3">
                <text class="px-1 text-sm font-semibold text-theme-on-surface-variant">{{ __('Notifications') }}</text>
                <column class="w-full rounded-2xl bg-theme-surface border border-theme-outline/50">
                    @if ($permission === 'unavailable')
                        <row class="w-full p-4 gap-3 items-center">
                            <icon name="bell.slash" :size="22" class="text-theme-on-surface-variant" />
                            <column class="flex-1 gap-1">
                                <text class="text-base font-medium text-theme-on-background">{{ __('Check requests in the app') }}</text>
                                <text class="text-sm text-theme-on-surface-variant">{{ __('Push notifications are not available yet.') }}</text>
                            </column>
                        </row>
                    @else
                        <pressable class="w-full p-4" a11y-label="{{ __('Notification settings') }}" @press="notificationSettings">
                            @include('native.partials.action-content', ['symbol' => 'bell', 'title' => __('Notification settings'), 'subtitle' => $permission === 'granted' ? __('On. You will be alerted when an app asks for approval.') : ($permission === 'denied' ? __('Off. Enable notifications in iPhone Settings.') : __('Not set up yet.'))])
                        </pressable>
                        <column class="w-full px-4"><column class="w-full h-[1] bg-theme-outline/40"></column></column>
                        <pressable class="w-full p-4" a11y-label="{{ __('Re-sync notifications') }}" @press="resync">
                            @include('native.partials.action-content', ['symbol' => 'arrow.clockwise', 'title' => __('Re-sync notifications'), 'subtitle' => ''])
                        </pressable>
                    @endif
                </column>
                @if ($notice !== null)<text class="px-1 text-sm text-theme-on-surface-variant">{{ __($notice) }}</text>@endif
            </column>
            <column class="w-full gap-3">
                <text class="px-1 text-sm font-semibold text-theme-on-surface-variant">{{ __('Language') }}</text>
                <pressable class="w-full p-4 rounded-2xl bg-theme-surface border border-theme-outline/50" a11y-label="{{ __('App language') }}" a11y-hint="{{ __('Change in iPhone Settings') }}" @press="languageSettings">
                    @include('native.partials.action-content', ['symbol' => 'globe', 'title' => \App\Support\AppLanguages::NAMES[app()->getLocale()] ?? 'English', 'subtitle' => __('Change in iPhone Settings')])
                </pressable>
            </column>
            <text text-align="center" class="text-xs text-theme-on-surface-variant">{{ __('Version :version · Build :build', ['version' => config('nativephp.version'), 'build' => config('nativephp.version_code')]) }}</text>
        </column>
    </scroll-view>
</column>
