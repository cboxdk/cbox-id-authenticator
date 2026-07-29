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

            <column class="w-full gap-2">
                <button label="Sign out and remove this device" variant="destructive" size="lg" :disabled="$working" @press="signOut"></button>
                <text class="text-xs text-theme-on-surface-variant">
                    This phone will stop receiving approval requests.
                </text>
            </column>

            <button label="Back" variant="ghost" @press="goBack"></button>
        </column>
    </scroll-view>
</column>
