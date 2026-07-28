<native:column class="w-full h-full bg-theme-background">
    <native:scroll-view class="flex-1">
        <native:column class="w-full p-4 gap-4">
            <native:text class="text-2xl font-bold text-theme-on-background">Settings</native:text>

            <native:column class="w-full gap-2 p-4 rounded-2xl bg-theme-surface">
                <native:text class="text-sm font-semibold text-theme-on-surface">Notifications</native:text>
                <native:text class="text-sm text-theme-on-surface-variant">
                    @if ($permission === 'granted')
                        On. You'll be alerted when an app asks for approval.
                    @elseif ($permission === 'denied')
                        Off. Turn them on in your phone's settings, then re-sync below —
                        approvals still work, but you'll have to open the app to see them.
                    @else
                        Not set up yet.
                    @endif
                </native:text>

                <native:button label="Re-sync notifications" variant="secondary" size="sm" :loading="$working" @press="resync" />

                @if ($notice !== null)
                    <native:text class="text-xs text-theme-on-surface-variant">{{ $notice }}</native:text>
                @endif
            </native:column>

            <native:divider class="border-theme-outline" />

            <native:column class="w-full gap-2">
                <native:button label="Sign out and remove this device" variant="destructive" size="lg" :disabled="$working" @press="signOut" />
                <native:text class="text-xs text-theme-on-surface-variant">
                    This phone will stop receiving approval requests.
                </native:text>
            </native:column>

            <native:button label="Back" variant="ghost" @navigate.back />
        </native:column>
    </native:scroll-view>
</native:column>
