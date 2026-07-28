<column class="w-full h-full bg-white">
    <scroll-view class="flex-1">
        <column class="w-full p-4 gap-4">
            <text class="text-2xl font-bold text-zinc-900">Settings</text>

            <column class="w-full gap-2 p-4 rounded-2xl bg-zinc-50">
                <text class="text-sm font-semibold text-zinc-900">Notifications</text>
                <text class="text-sm text-zinc-600">
                    @if ($permission === 'granted')
                        On. You'll be alerted when an app asks for approval.
                    @elseif ($permission === 'denied')
                        Off. Turn them on in your phone's settings, then re-sync below —
                        approvals still work, but you'll have to open the app to see them.
                    @else
                        Not set up yet.
                    @endif
                </text>

                <x-btn label="Re-sync notifications" variant="secondary" size="sm" :loading="$working" press="resync" />

                @if ($notice !== null)
                    <text class="text-xs text-zinc-600">{{ $notice }}</text>
                @endif
            </column>

            <divider class="border-zinc-200" />

            <column class="w-full gap-2">
                <x-btn label="Sign out and remove this device" variant="destructive" size="lg" :disabled="$working" press="signOut" />
                <text class="text-xs text-zinc-600">
                    This phone will stop receiving approval requests.
                </text>
            </column>

            <x-btn label="Back" variant="ghost" press="goBack" />
        </column>
    </scroll-view>
</column>
