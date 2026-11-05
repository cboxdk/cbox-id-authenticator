<column class="w-full h-full bg-theme-background">
    <scroll-view class="flex-1">
        {{-- No heading: the nav bar carries "Settings". --}}
        <column class="w-full p-4 gap-4">
            <column class="w-full gap-2 p-4 rounded-2xl bg-theme-surface">
                <text class="text-sm font-semibold text-theme-on-background">{{ __("Notifications") }}</text>
                <text class="text-sm text-theme-on-surface-variant">
                    @if ($permission === 'granted')
                        {{ __("On. You will be alerted when an app asks for approval.") }}
                    @elseif ($permission === 'denied')
                        {{ __("Off. Enable notifications in iPhone Settings, then re-sync below. You can still open the app to check requests.") }}
                    @else
                        {{ __("Not set up yet.") }}
                    @endif
                </text>

                <button label="{{ __('Notification settings') }}" variant="secondary" @press="notificationSettings"></button>
                <button label="{{ __('Re-sync notifications') }}" variant="secondary" size="md" :loading="$working" @press="resync"></button>

                @if ($notice !== null)
                    <text class="text-xs text-theme-on-surface-variant">{{ __($notice) }}</text>
                @endif
            </column>

            <divider class="border-theme-outline" />

            <text class="text-sm font-semibold text-theme-on-background">{{ __("Accounts") }}</text>

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
                            <text class="text-xs text-theme-on-surface-variant">{{ __("No alerts from this host — open the app to check for requests.") }}</text>
                        </row>
                    @endif

                    <button label="{{ __('Account details') }}" variant="secondary" @press="account('{{ $account['id'] }}')"></button>
                    <button label="{{ __('Manage account') }}" variant="secondary" size="md" @press="manage('{{ $account['id'] }}')"></button>
                    @if ($removing === $account['id'])
                        <text class="text-base text-theme-on-background">{{ __("Remove this account from this phone? You can connect it again with a new code from Trusted devices.") }}</text>
                        <button label="{{ __('Remove account') }}" variant="destructive" :disabled="$working" @press="signOut('{{ $account['id'] }}')"></button>
                        <button label="{{ __('Keep account') }}" variant="secondary" @press="cancelRemoval"></button>
                    @else
                    <button
                        label="{{ __('Sign out and remove this device') }}"
                        variant="destructive"
                        size="md"
                        :disabled="$working"
                        @press="requestRemoval('{{ $account['id'] }}')"
                    ></button>
                    @endif
                </column>
            @endforeach

            <text class="text-xs text-theme-on-surface-variant">{{ __("Signing out of an account stops approval requests from that host only.") }}</text>

            {{-- The only route to the scanner now that the approvals list has no controls
                 of its own. No Back button: this is a tab root, so there is nothing
                 beneath it to go back to — the other tab is one tap away. --}}
            <button label="{{ __('Privacy and help') }}" variant="secondary" @press="about"></button>
            <button label="{{ __('Add another account') }}" variant="secondary" @press="goAddAccount"></button>
        </column>
    </scroll-view>
</column>
