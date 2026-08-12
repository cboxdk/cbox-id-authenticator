<column class="w-full h-full bg-theme-background">
    <scroll-view class="flex-1">
        <column class="w-full p-4 gap-4">
            <text class="text-2xl font-bold text-theme-on-background">Settings</text>

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

            <button label="Add another account" variant="secondary" @press="goAddAccount"></button>

            <button label="Back" variant="ghost" @press="goBack"></button>
        </column>
    </scroll-view>
</column>
