<column class="w-full h-full bg-theme-background">
    <scroll-view class="flex-1">
        <column class="w-full px-5 pt-5 pb-8 gap-6">
            <column class="w-full p-6 gap-4 rounded-3xl bg-gradient-to-br from-blue-500/10 to-transparent border border-theme-outline/50">
                @include('native.partials.mark', ['symbol' => 'hand.raised.fill'])
                <text class="text-2xl font-bold text-theme-on-background">{{ __('Your accounts and privacy') }}</text>
                <text class="text-base text-theme-on-surface-variant">{{ __('Know what stays on your phone, and what to do when you need help.') }}</text>
            </column>
            <column class="w-full gap-3">
                <column class="w-full rounded-2xl bg-theme-surface border border-theme-outline/50">
                    <pressable class="w-full p-4" a11y-label="{{ __('Codes not working?') }}" a11y-hint="{{ $expanded === 'codes' ? __('Collapse explanation') : __('Read explanation') }}" @press="toggle('codes')">
                        @include('native.partials.action-content', ['symbol' => 'key', 'title' => __('Codes not working?'), 'subtitle' => '', 'trailing' => $expanded === 'codes' ? 'chevron.up' : 'chevron.down'])
                    </pressable>
                    @if ($expanded === 'codes')
                        <column class="w-full px-4 pb-5 gap-4">
                            <text class="text-base text-theme-on-surface-variant">{{ __("Check that automatic date and time is enabled in iPhone Settings. Use the newest code before its timer runs out.") }}</text>
                        </column>
                    @endif
                </column>
                <column class="w-full rounded-2xl bg-theme-surface border border-theme-outline/50">
                    <pressable class="w-full p-4" a11y-label="{{ __('Cannot scan a code?') }}" a11y-hint="{{ $expanded === 'camera' ? __('Collapse explanation') : __('Read explanation') }}" @press="toggle('camera')">
                        @include('native.partials.action-content', ['symbol' => 'qrcode.viewfinder', 'title' => __('Cannot scan a code?'), 'subtitle' => '', 'trailing' => $expanded === 'camera' ? 'chevron.up' : 'chevron.down'])
                    </pressable>
                    @if ($expanded === 'camera')
                        <column class="w-full px-4 pb-5 gap-4">
                            <text class="text-base text-theme-on-surface-variant">{{ __("Allow camera access in iPhone Settings. For Cbox ID, you can also use the connection link in Trusted devices. For verification codes, enter the setup key from the service.") }}</text>
                        </column>
                    @endif
                </column>
                <column class="w-full rounded-2xl bg-theme-surface border border-theme-outline/50">
                    <pressable class="w-full p-4" a11y-label="{{ __('Your accounts and privacy') }}" a11y-hint="{{ $expanded === 'privacy' ? __('Collapse explanation') : __('Read explanation') }}" @press="toggle('privacy')">
                        @include('native.partials.action-content', ['symbol' => 'hand.raised', 'title' => __('Your accounts and privacy'), 'subtitle' => '', 'trailing' => $expanded === 'privacy' ? 'chevron.up' : 'chevron.down'])
                    </pressable>
                    @if ($expanded === 'privacy')
                        <column class="w-full px-4 pb-5 gap-4">
                            <text class="text-base text-theme-on-surface-variant">{{ __("Verification code setup keys stay in this iPhone's Keychain. They are never sent to Cbox or the service. Encrypted backups contain your verification codes only, and are saved wherever you choose in Files. Keep your backup password safe; Cbox cannot recover it. No automatic synchronization is enabled.") }}</text>
                            <text class="text-base text-theme-on-surface-variant">{{ __("Your account names, sign-in tokens and device keys are stored in this iPhone's Keychain. Passwords are entered in the system sign-in browser and are not stored by this app.") }}</text>
                            <text class="text-base text-theme-on-surface-variant">{{ __("Your Cbox ID host receives your device name, platform, app version and approval decisions. If notifications are enabled, Firebase and Apple deliver a notification using a push token. Request details are fetched from your host when you open the app.") }}</text>
                            <text class="text-base text-theme-on-surface-variant">{{ __("Face ID and the device passcode are handled by iOS. The app receives the authentication result, not your biometric data. Camera access is used to scan a connection code.") }}</text>
                        </column>
                    @endif
                </column>
                <column class="w-full rounded-2xl bg-theme-surface border border-theme-outline/50">
                    <pressable class="w-full p-4" a11y-label="{{ __('Remove an account') }}" a11y-hint="{{ $expanded === 'remove' ? __('Collapse explanation') : __('Read explanation') }}" @press="toggle('remove')">
                        @include('native.partials.action-content', ['symbol' => 'person.crop.circle.badge.minus', 'title' => __('Remove an account'), 'subtitle' => '', 'trailing' => $expanded === 'remove' ? 'chevron.up' : 'chevron.down'])
                    </pressable>
                    @if ($expanded === 'remove')
                        <column class="w-full px-4 pb-5 gap-4">
                            <text class="text-base text-theme-on-surface-variant">{{ __("In Settings, choose an account and remove it from this phone. To request deletion of your Cbox ID account and its server data, contact Cbox or the administrator of your organization. Your organization's retention rules may apply to security audit records.") }}</text>
                        </column>
                    @endif
                </column>
                <column class="w-full rounded-2xl bg-theme-surface border border-theme-outline/50">
                    <pressable class="w-full p-4" a11y-label="{{ __('If your phone is lost') }}" a11y-hint="{{ $expanded === 'lost' ? __('Collapse explanation') : __('Read explanation') }}" @press="toggle('lost')">
                        @include('native.partials.action-content', ['symbol' => 'iphone.slash', 'title' => __('If your phone is lost'), 'subtitle' => '', 'trailing' => $expanded === 'lost' ? 'chevron.up' : 'chevron.down'])
                    </pressable>
                    @if ($expanded === 'lost')
                        <column class="w-full px-4 pb-5 gap-4">
                            <text class="text-base text-theme-on-surface-variant">{{ __("Sign in to Cbox ID from another device. Open My account, then Trusted devices, and remove the lost phone. It will no longer be able to approve requests.") }}</text>
                        </column>
                    @endif
                </column>
                <column class="w-full rounded-2xl bg-theme-surface border border-theme-outline/50">
                    <pressable class="w-full p-4" a11y-label="{{ __('Unexpected request?') }}" a11y-hint="{{ $expanded === 'unexpected' ? __('Collapse explanation') : __('Read explanation') }}" @press="toggle('unexpected')">
                        @include('native.partials.action-content', ['symbol' => 'exclamationmark.shield', 'title' => __('Unexpected request?'), 'subtitle' => '', 'trailing' => $expanded === 'unexpected' ? 'chevron.up' : 'chevron.down'])
                    </pressable>
                    @if ($expanded === 'unexpected')
                        <column class="w-full px-4 pb-5 gap-4">
                            <text class="text-base text-theme-on-surface-variant">{{ __("Choose “This wasn't me” to deny and report it. Only approve a request that you started and whose description matches the action shown on your other device.") }}</text>
                        </column>
                    @endif
                </column>
            </column>
            @if ($error !== null)<text class="text-sm text-theme-destructive">{{ __($error) }}</text>@endif
            <button label="{{ __('Contact Cbox') }}" variant="primary" class="glass:prominent w-full" @press="support"></button>
            <button label="{{ __('Licenses') }}" variant="ghost" class="w-full" @press="licenses"></button>
            <text text-align="center" class="text-xs text-theme-on-surface-variant">{{ __('Version :version · Build :build', ['version' => config('nativephp.version'), 'build' => config('nativephp.version_code')]) }}</text>
        </column>
    </scroll-view>
</column>
