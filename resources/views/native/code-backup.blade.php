<column class="w-full h-full bg-theme-background">
    <scroll-view class="flex-1">
        <column class="w-full p-4 gap-4">
            <text class="text-sm text-theme-on-surface-variant">{{ __('Back up your verification codes to a password-protected file. Cbox connections are not included. Nothing is synced automatically.') }}</text>
            @if ($error !== null)
                <text class="text-sm text-theme-error">{{ __($error) }}</text>
            @endif
            @if ($notice !== null)
                <text class="text-sm text-theme-on-surface-variant">{{ __($notice) }}</text>
            @endif
            @if ($preview)
                <text class="text-xl font-semibold text-theme-on-background">{{ __('Ready to import') }}</text>
                <text class="text-base text-theme-on-background">{{ __('Add :added codes and skip :duplicates duplicates.', ['added' => $added, 'duplicates' => $duplicates]) }}</text>
                <text class="text-sm text-theme-on-surface-variant">{{ __('Your existing codes and their names will be kept.') }}</text>
                <button label="{{ __('Import codes') }}" variant="primary" @press="import"></button>
                <button label="{{ __('Cancel import') }}" variant="secondary" @press="cancelPreview"></button>
            @elseif ($hasFile)
                <outlined-text-input native:model="password" label="{{ __('Backup password') }}" :secure="true" :max-length="1024" class="w-full"></outlined-text-input>
                <button label="{{ __('Preview import') }}" variant="primary" @press="inspect"></button>
                <button label="{{ __('Choose another backup') }}" variant="secondary" @press="chooseFile"></button>
            @else
                <text class="text-lg font-semibold text-theme-on-background">{{ __('Create backup') }}</text>
                <text class="text-sm text-theme-on-surface-variant">{{ __('Choose a unique password of at least 12 characters. We cannot recover a forgotten backup password.') }}</text>
                <outlined-text-input native:model="password" label="{{ __('Backup password') }}" :secure="true" :max-length="1024" class="w-full"></outlined-text-input>
                <outlined-text-input native:model="confirmation" label="{{ __('Repeat password') }}" :secure="true" :max-length="1024" class="w-full"></outlined-text-input>
                <button label="{{ __('Save encrypted backup') }}" variant="primary" @press="export"></button>
                <divider class="border-theme-outline" />
                <text class="text-lg font-semibold text-theme-on-background">{{ __('Restore codes') }}</text>
                <text class="text-sm text-theme-on-surface-variant">{{ __('Import a Cbox encrypted backup. For other authenticators, scan each service setup QR code or enter its setup key.') }}</text>
                <button label="{{ __('Choose backup in Files') }}" variant="secondary" @press="chooseFile"></button>
            @endif
        </column>
    </scroll-view>
</column>
