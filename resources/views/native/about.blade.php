<column class="w-full h-full bg-theme-background">
    <scroll-view class="flex-1">
        <column class="w-full p-4 gap-4">
            <text class="text-2xl font-bold text-theme-on-background">{{ __("Cbox ID Authenticator") }}</text>
            <text class="text-sm text-theme-on-surface-variant">{{ __('Version :version · Build :build', ['version' => config('nativephp.version'), 'build' => config('nativephp.version_code')]) }}</text>
            <text class="text-lg font-semibold text-theme-on-background">{{ __("Your accounts and privacy") }}</text>
            <text class="text-base text-theme-on-background">{{ __("Verification code setup keys stay in this iPhone's Keychain. They are never sent to Cbox or the service. Encrypted backups contain your verification codes only, and are saved wherever you choose in Files. Keep your backup password safe; Cbox cannot recover it. No automatic synchronization is enabled.") }}</text>
            <text class="text-base text-theme-on-background">{{ __("Your account names, sign-in tokens and device keys are stored in this iPhone's Keychain. Passwords are entered in the system sign-in browser and are not stored by this app.") }}</text>
            <text class="text-base text-theme-on-background">{{ __("Your Cbox ID host receives your device name, platform, app version and approval decisions. If notifications are enabled, Firebase and Apple deliver a notification using a push token. Request details are fetched from your host when you open the app.") }}</text>
            <text class="text-base text-theme-on-background">{{ __("Face ID and the device passcode are handled by iOS. The app receives the authentication result, not your biometric data. Camera access is used to scan a connection code.") }}</text>
            <text class="text-lg font-semibold text-theme-on-background">{{ __("Remove an account") }}</text>
            <text class="text-base text-theme-on-background">{{ __("In Settings, choose an account and remove it from this phone. To request deletion of your Cbox ID account and its server data, contact Cbox or the administrator of your organization. Your organization's retention rules may apply to security audit records.") }}</text>
            <text class="text-lg font-semibold text-theme-on-background">{{ __("If your phone is lost") }}</text>
            <text class="text-base text-theme-on-background">{{ __("Sign in to Cbox ID from another device. Open My account, then Trusted devices, and remove the lost phone. It will no longer be able to approve requests.") }}</text>
            <text class="text-lg font-semibold text-theme-on-background">{{ __("Unexpected request?") }}</text>
            <text class="text-base text-theme-on-background">{{ __("Choose “This wasn't me” to deny and report it. Only approve a request that you started and whose description matches the action shown on your other device.") }}</text>
            @if ($error !== null)
                <text class="text-sm text-theme-destructive">{{ __($error) }}</text>
            @endif
            <button label="{{ __('Licenses') }}" variant="secondary" @press="licenses"></button>
            <button label="{{ __('Contact Cbox') }}" variant="secondary" @press="support"></button>
        </column>
    </scroll-view>
</column>
