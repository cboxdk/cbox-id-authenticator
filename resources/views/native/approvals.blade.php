<column class="w-full h-full bg-theme-background">
    <refreshable @refresh="load" class="flex-1">
        <column class="w-full px-5 py-4 gap-4">
            @if ($connected !== null)
                <column class="w-full p-4 gap-2 rounded-2xl bg-theme-surface">
                    <row class="w-full gap-3 items-center">
                        <icon name="checkmark.circle.fill" :size="24" class="text-theme-primary" />
                        <text class="flex-1 text-base font-semibold text-theme-on-background">{{ __('Account connected') }}</text>
                    </row>
                    <text class="text-sm text-theme-on-surface-variant">{{ $connected }}</text>
                    <button label="{{ __('Got it') }}" variant="ghost" @press="dismissConnected"></button>
                </column>
            @endif
            @if (! $hasAccounts)
                <column class="w-full gap-4 pt-4 pb-2">
                    <icon name="checkmark.shield.fill" :size="44" class="text-theme-primary" />
                    <text class="text-3xl font-semibold text-theme-on-background">{{ __('Approve with your iPhone') }}</text>
                    <text class="text-base text-theme-on-surface-variant">{{ __('Connect your Cbox ID account. Review each request and approve it with Face ID or your passcode.') }}</text>
                </column>
                <column class="w-full p-5 gap-4 rounded-2xl bg-theme-surface">
                    <text class="text-lg font-semibold text-theme-on-background">{{ __('Start with Cbox ID') }}</text>
                    <text class="text-base text-theme-on-surface-variant">{{ __('Have your connection QR code ready. We will guide you through sign-in.') }}</text>
                    <button label="{{ __('Connect an account') }}" variant="primary" size="lg" class="w-full glass:prominent" @press="addAccount"></button>
                </column>
                <pressable class="w-full p-4" a11y-label="{{ __('Add verification codes') }}" @press="addCode">
                    <row class="w-full gap-3 items-center">
                        <icon name="key" :size="24" class="text-theme-primary" />
                        <column class="flex-1 gap-1">
                            <text class="text-base font-semibold text-theme-on-background">{{ __('Also need verification codes?') }}</text>
                            <text class="text-sm text-theme-on-surface-variant">{{ __('Add codes for your other accounts in the Codes tab.') }}</text>
                        </column>
                        <icon name="chevron.right" :size="14" class="text-theme-on-surface-variant" />
                    </row>
                </pressable>
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
                <column class="w-full items-center gap-3 px-4 py-8">
                    <icon name="checkmark.circle" :size="40" class="text-theme-on-surface-variant" />
                    <text class="text-xl font-semibold text-theme-on-background">{{ $degraded !== [] ? __('Could not check all accounts.') : __('You are all set') }}</text>
                    <text text-align="center" class="text-sm text-theme-on-surface-variant">
                        {{ $degraded !== [] ? __('Check your connection, then pull to retry.') : __('When you sign in or need to approve an action, the request appears here.') }}
                    </text>
                </column>
                @endif
            @endforelse
        </column>
    </refreshable>

    {{-- Nothing pinned here any more. AppLayout's tab bar carries Settings, and it stays
         put while this list scrolls — which is what the hand-rolled row never did. --}}
</column>
