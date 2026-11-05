<column class="w-full h-full bg-theme-background">
    <refreshable @refresh="load" class="flex-1">
        {{-- No heading: the nav bar carries "Pending requests" now, and repeating it
             below would be the same words twice on one screen. --}}
        <column class="w-full p-4 gap-3">
            @if (! $hasAccounts)
                <column class="w-full items-center py-16 gap-4">
                    <icon name="checkmark.shield" :size="48" class="text-theme-primary" />
                    <text text-align="center" class="text-2xl font-semibold text-theme-on-background">{{ __('Approve with your iPhone') }}</text>
                    <text text-align="center" class="text-base text-theme-on-surface-variant">{{ __('Connect a Cbox ID account to approve sign-ins and requests.') }}</text>
                    <button label="{{ __('Connect an account') }}" variant="primary" size="lg" class="glass:prominent" @press="addAccount"></button>
                </column>
            @endif
            {{-- Named, not counted. "One account is unreachable" leaves the user
                 guessing which of their tenants is not being watched. --}}
            @foreach ($connectionErrors as $connection)
                <column class="w-full gap-2 p-3 rounded-xl bg-theme-surface-variant">
                    <text class="text-base font-semibold text-theme-on-background">{{ $connection['label'] }}</text>
                    <text class="text-sm text-theme-on-surface-variant">{{ __($connection['error']) }}</text>
                    <button label="{{ __('Account settings') }}" variant="secondary" @press="account('{{ $connection['id'] }}')"></button>
                </column>
            @endforeach

            @forelse ($approvals as $approval)
                <pressable
                    class="w-full p-4 gap-1 rounded-2xl bg-theme-surface"
                    a11y-label="{{ ($approval['client_name'] ?? __('An application')).', '.$approval['account_label'] }}"
                    @press="open('{{ $approval['row_key'] }}')"
                >
                    <row class="w-full items-center gap-2">
                        <icon name="shield" :size="20" class="text-theme-primary" />
                        <text class="text-base font-semibold text-theme-on-background">
                            {{ $approval['client_name'] ?? __('An application') }}
                        </text>
                    </row>

                    {{-- Which account this is for. With one account it is noise; with two
                         it is the difference between approving your own sign-in and
                         approving someone else's. --}}
                    <text class="text-xs text-theme-primary">{{ $approval['account_label'] }}</text>

                    {{-- The binding message is previewed, but the DECISION is only taken
                         on the detail screen, where the user has had to read it. --}}
                    @if (! empty($approval['binding_message']))
                        <text :max-lines="2" class="text-sm text-theme-on-surface-variant">
                            {{ $approval['binding_message'] }}
                        </text>
                    @endif

                    <text class="text-sm text-theme-on-surface-variant">{{ ($approval['purpose'] ?? '') === 'action' ? __('Action request') : __('Sign-in request') }}</text>
                    <text class="text-sm text-theme-on-surface-variant">{{ __("Tap to review") }}</text>
                </pressable>
            @empty
                @if ($hasAccounts)
                <column class="w-full items-center justify-center gap-2 py-16">
                    <icon name="checkmark.circle" :size="40" class="text-theme-on-surface-variant" />
                    <text class="text-base text-theme-on-surface-variant">{{ $degraded !== [] ? __('Could not check all accounts.') : __('Nothing waiting.') }}</text>
                    <text text-align="center" class="text-sm text-theme-on-surface-variant">
                        {{ $degraded !== [] ? __('Check your connection, then pull to retry.') : __('Requests appear here automatically. Notifications are optional.') }}
                    </text>
                </column>
                @endif
            @endforelse
        </column>
    </refreshable>

    {{-- Nothing pinned here any more. AppLayout's tab bar carries Settings, and it stays
         put while this list scrolls — which is what the hand-rolled row never did. --}}
</column>
