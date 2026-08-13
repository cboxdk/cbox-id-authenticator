<column class="w-full h-full bg-theme-background">
    <scroll-view class="flex-1">
        {{-- No heading: the nav bar carries "Settings". --}}
        <column class="w-full p-4 gap-4">
            <column class="w-full gap-2 p-4 rounded-2xl bg-theme-surface">
                <text class="text-sm font-semibold text-theme-on-background">Notifications</text>
                <text class="text-sm text-theme-on-surface-variant">
                    @if ($permission === 'granted')
                        On. You'll be alerted when an app asks for approval.
                    @elseif ($permission === 'denied')
                        Off. Turn them on in your phone's settings, then re-sync below —
                        approvals still work, but you'll have to open the app to see them.
                    @else
                        Not set up yet.
                    @endif
                </text>

                <button label="Re-sync notifications" variant="secondary" size="sm" :loading="$working" @press="resync"></button>

                @if ($notice !== null)
                    <text class="text-xs text-theme-on-surface-variant">{{ $notice }}</text>
                @endif
            </column>

            <divider class="border-theme-outline" />

            <text class="text-sm font-semibold text-theme-on-background">Accounts</text>

            {{-- One card per account, each with its own sign-out. A single global
                 "sign out" would remove every tenant at once, which is never what
                 someone leaving one job and keeping another intends. --}}
            @foreach ($accounts as $account)
                <column class="w-full gap-2 p-4 rounded-2xl bg-theme-surface">
                    <text class="text-base font-semibold text-theme-on-background">{{ $account['label'] }}</text>
                    <text class="text-xs text-theme-on-surface-variant">{{ $account['issuer'] }}</text>

                    {{-- Per account, because the answer is per host. A self-hosted
                         installation with no push transport cannot reach this phone at
                         all, and telling someone "you'll be alerted" for an account that
                         will never alert them is how a missed approval happens. --}}
                    @if (! $account['notifies'])
                        <row class="w-full items-center gap-2">
                            <icon name="bell.slash" :size="14" class="text-theme-on-surface-variant" />
                            <text class="text-xs text-theme-on-surface-variant">
                                No alerts from this host — open the app to check for requests.
                            </text>
                        </row>
                    @endif

                    <button
                        label="Sign out and remove this device"
                        variant="destructive"
                        size="sm"
                        :disabled="$working"
                        @press="signOut('{{ $account['id'] }}')"
                    ></button>
                </column>
            @endforeach

            <text class="text-xs text-theme-on-surface-variant">
                Signing out of an account stops approval requests from that host only.
            </text>

            {{-- The only route to the scanner now that the approvals list has no controls
                 of its own. No Back button: this is a tab root, so there is nothing
                 beneath it to go back to — the other tab is one tap away. --}}
            <button label="Add another account" variant="secondary" @press="goAddAccount"></button>
        </column>
    </scroll-view>
</column>
