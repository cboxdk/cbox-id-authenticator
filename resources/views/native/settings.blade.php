<native:column class="w-full h-full bg-white">
    <native:scroll-view class="flex-1">
        <native:column class="w-full p-4 gap-4">
            <native:text class="text-2xl font-bold text-zinc-900">Settings</native:text>

            <native:column class="w-full gap-2 p-4 rounded-2xl bg-zinc-50">
                <native:text class="text-sm font-semibold text-zinc-900">Notifications</native:text>
                <native:text class="text-sm text-zinc-600">
                    @if ($permission === 'granted')
                        On. You'll be alerted when an app asks for approval.
                    @elseif ($permission === 'denied')
                        Off. Turn them on in your phone's settings, then re-sync below —
                        approvals still work, but you'll have to open the app to see them.
                    @else
                        Not set up yet.
                    @endif
                </native:text>

                <x-btn label="Re-sync notifications" variant="secondary" size="sm" :loading="$working" press="resync" />

                @if ($notice !== null)
                    <native:text class="text-xs text-zinc-600">{{ $notice }}</native:text>
                @endif
            </native:column>

            <native:divider class="border-zinc-200" />

            <native:column class="w-full gap-2">
                <x-btn label="Sign out and remove this device" variant="destructive" size="lg" :disabled="$working" press="signOut" />
                <native:text class="text-xs text-zinc-600">
                    This phone will stop receiving approval requests.
                </native:text>
            </native:column>

            <x-btn label="Back" variant="ghost" press="goBack" />
        </native:column>
    </native:scroll-view>
</native:column>
