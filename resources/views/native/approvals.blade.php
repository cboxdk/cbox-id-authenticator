<column class="w-full h-full bg-theme-background">
    <refreshable @refresh="load" class="flex-1">
        <column class="w-full px-5 pt-3 pb-8 gap-6">
            @if (! $hasAccounts)
                <column class="w-full p-6 gap-5 rounded-3xl bg-gradient-to-br from-blue-500/10 to-transparent border border-theme-outline/50">
                    @include('native.partials.mark')
                    <column class="w-full gap-2">
                        <text class="text-3xl font-bold tracking-tight text-theme-on-background">{{ __('Your sign-in. Your say.') }}</text>
                        <text class="text-base text-theme-on-surface-variant">{{ __('Approve sign-ins and requests with Face ID or your iPhone passcode.') }}</text>
                    </column>
                    <button label="{{ __('Connect an account') }}" variant="primary" size="lg" class="w-full glass:prominent" @press="addAccount"></button>
                </column>
                <column class="w-full px-1 gap-4">
                    <text class="text-sm font-semibold text-theme-on-surface-variant">{{ __('Start with Cbox ID') }}</text>
                    <row class="w-full gap-3 items-center">
                        <icon name="qrcode.viewfinder" :size="22" class="text-theme-primary" />
                        <text class="flex-1 text-base text-theme-on-background">{{ __('Scan your connection code, then sign in.') }}</text>
                    </row>
                    <row class="w-full gap-3 items-center">
                        <icon name="checkmark.shield" :size="22" class="text-theme-primary" />
                        <text class="flex-1 text-base text-theme-on-background">{{ __('Review every request before you approve.') }}</text>
                    </row>
                </column>
                <pressable class="w-full p-4 rounded-2xl bg-theme-surface border border-theme-outline/50" a11y-label="{{ __('Add verification codes') }}" @press="addCode">
                    @include('native.partials.action-content', ['symbol' => 'key', 'title' => __('Verification codes'), 'subtitle' => __('For your other accounts. Also works offline.')])
                </pressable>
            @else
                @if ($approvals === [])
                    <column class="w-full p-6 gap-5 rounded-3xl bg-gradient-to-br from-blue-500/10 to-transparent border border-theme-outline/50">
                        @include('native.partials.mark', ['symbol' => $degraded !== [] ? 'wifi.exclamationmark' : 'checkmark.shield.fill'])
                        <column class="w-full gap-2">
                            <text class="text-3xl font-bold tracking-tight text-theme-on-background">{{ $degraded !== [] ? __('Could not check all accounts.') : __('You are all set') }}</text>
                            <text class="text-base text-theme-on-surface-variant">{{ $degraded !== [] ? __('Check your connection, then pull to retry.') : __('Nothing needs your approval. New requests appear here.') }}</text>
                        </column>
                    </column>
                @else
                    <row class="w-full items-center gap-3">
                        <text class="flex-1 text-xl font-semibold text-theme-on-background">{{ __('Waiting for you') }}</text>
                        <text class="px-3 py-1 rounded-full bg-theme-primary/10 text-sm font-semibold text-theme-primary">{{ count($approvals) }}</text>
                    </row>
                @endif
                @foreach ($connectionErrors as $connection)
                    <column class="w-full p-4 gap-3 rounded-2xl bg-theme-surface border border-theme-destructive/30">
                        <text class="text-base font-semibold text-theme-on-background">{{ $connection['label'] }}</text>
                        <text class="text-sm text-theme-on-surface-variant">{{ __($connection['error']) }}</text>
                        <button label="{{ __('Account settings') }}" variant="ghost" class="w-full" @press="account('{{ $connection['id'] }}')"></button>
                    </column>
                @endforeach
                @foreach ($approvals as $approval)
                    <pressable class="w-full p-5 rounded-2xl bg-theme-surface border border-theme-outline/50" a11y-label="{{ ($approval['client_name'] ?? __('An application')).', '.$approval['account_label'] }}" @press="open('{{ $approval['row_key'] }}')">
                        <column class="w-full gap-4">
                            @include('native.partials.action-content', ['symbol' => 'app.badge', 'title' => $approval['client_name'] ?? __('An application'), 'subtitle' => $approval['account_label']])
                            @if (! empty($approval['binding_message']))
                                <text :max-lines="3" class="text-xl font-semibold text-theme-on-background">{{ $approval['binding_message'] }}</text>
                            @endif
                            <text class="text-sm font-medium text-theme-primary">{{ ($approval['purpose'] ?? '') === 'action' ? __('Action request') : __('Sign-in request') }} · {{ __('Tap to review') }}</text>
                        </column>
                    </pressable>
                @endforeach
                <column class="w-full gap-3">
                    <text class="px-1 text-sm font-semibold text-theme-on-surface-variant">{{ __('Connected accounts') }}</text>
                    <column class="w-full rounded-2xl bg-theme-surface border border-theme-outline/50">
                        @foreach ($connectedAccounts as $account)
                            <pressable class="w-full p-4" a11y-label="{{ $account['label'] }}" @press="account('{{ $account['id'] }}')">
                                @include('native.partials.action-content', ['symbol' => 'person.crop.circle', 'title' => $account['label'], 'subtitle' => parse_url($account['issuer'], PHP_URL_HOST)])
                            </pressable>
                            @if (! $loop->last)<column class="w-full px-4"><column class="w-full h-[1] bg-theme-outline/40"></column></column>@endif
                        @endforeach
                    </column>
                </column>
            @endif
        </column>
    </refreshable>
</column>
