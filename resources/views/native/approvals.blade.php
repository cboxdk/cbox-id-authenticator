<column class="w-full h-full safe-area bg-theme-background">
    <refreshable @refresh="load" class="flex-1">
        <column class="w-full p-4 gap-3">
            <text class="text-2xl font-bold text-theme-on-background">Pending requests</text>

            {{-- Named, not counted. "One account is unreachable" leaves the user
                 guessing which of their tenants is not being watched. --}}
            @foreach ($degraded as $label)
                <row class="w-full items-center gap-2 p-3 rounded-xl bg-theme-surface-variant">
                    <icon name="wifi.slash" :size="18" class="text-theme-on-surface-variant" />
                    <text class="text-sm text-theme-on-surface-variant">
                        Can't reach {{ $label }}. Pull to retry.
                    </text>
                </row>
            @endforeach

            @forelse ($approvals as $approval)
                <pressable
                    class="w-full p-4 gap-1 rounded-2xl bg-theme-surface"
                    @press="open('{{ $approval['row_key'] }}')"
                >
                    <row class="w-full items-center gap-2">
                        <icon name="shield" :size="20" class="text-theme-primary" />
                        <text class="text-base font-semibold text-theme-on-background">
                            {{ $approval['client_name'] ?? 'An application' }}
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

                    <text class="text-xs text-theme-on-surface-variant">Tap to review</text>
                </pressable>
            @empty
                <column class="w-full items-center justify-center gap-2 py-16">
                    <icon name="checkmark.circle" :size="40" class="text-theme-on-surface-variant" />
                    <text class="text-base text-theme-on-surface-variant">Nothing waiting.</text>
                    <text text-align="center" class="text-sm text-theme-on-surface-variant">
                        You'll get a notification when an app asks for approval.
                    </text>
                </column>
            @endforelse
        </column>
    </refreshable>

    {{-- A plain row, and it has to be.
         `<native:bottom-bar>` renders ONLY under native chrome: both branches that call
         NativeComponent::resolveBottomBar() are gated on a tab bar or a nav bar, and
         these screens have no NativeLayout at all. Used here the node is hoisted out of
         the tree and then attached to nothing — the bar simply vanishes, taking every
         navigation control with it. It looked right in the published tree, which is
         exactly why asserting on the tree proved nothing.

         Padding is deliberately light: `safe-area` on the root already reserves the
         home-indicator inset, and the p-4 that used to be here stacked on top of it. --}}
    <row class="w-full px-4 py-2 items-center">
        <button label="Add account" variant="ghost" size="sm" @press="goAddAccount"></button>
        <spacer />
        <button label="Settings" variant="ghost" size="sm" @press="goSettings"></button>
    </row>
</column>
