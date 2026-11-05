<list class="w-full h-full" separator>
    @if ($error !== null || $notice !== null)
        <list-section>
            <column class="w-full p-4 gap-3">
                @if ($error !== null)
                    <text class="text-sm text-theme-destructive">{{ __($error) }}</text>
                @endif
                @if ($notice !== null)
                    <text class="text-sm text-theme-on-surface-variant">{{ __($notice) }}</text>
                @endif
            </column>
        </list-section>
    @endif
    @if ($preview)
        <list-section header="{{ __('Ready to import') }}" footer="{{ __('Your existing codes and their names will be kept.') }}">
            <column class="w-full p-4 gap-3">
                <text class="text-base text-theme-on-background">{{ __('Add :added codes and skip :duplicates duplicates.', ['added' => $added, 'duplicates' => $duplicates]) }}</text>
                <button label="{{ __('Import codes') }}" variant="primary" class="glass:prominent w-full" @press="import"></button>
                <button label="{{ __('Cancel import') }}" variant="ghost" class="w-full" @press="cancelPreview"></button>
            </column>
        </list-section>
    @elseif ($hasFile)
        <list-section header="{{ __('Restore codes') }}">
            <bare-text-input native:model="password" placeholder="{{ __('Backup password') }}" a11y-label="{{ __('Backup password') }}" :secure="true" :max-length="1024" class="w-full px-4 py-3"></bare-text-input>
            <column class="w-full p-4">
                <button label="{{ __('Preview import') }}" variant="primary" class="glass:prominent w-full" @press="inspect"></button>
            </column>
            <list-item headline="{{ __('Choose another backup') }}" leadingIcon="folder" trailingIcon="chevron.right" @press="chooseFile" />
        </list-section>
    @elseif ($creating)
        <list-section header="{{ __('Create backup') }}" footer="{{ __('Choose a unique password of at least 12 characters. We cannot recover a forgotten backup password.') }}">
            <bare-text-input native:model="password" placeholder="{{ __('Backup password') }}" a11y-label="{{ __('Backup password') }}" :secure="true" :max-length="1024" class="w-full px-4 py-3"></bare-text-input>
            <bare-text-input native:model="confirmation" placeholder="{{ __('Repeat password') }}" a11y-label="{{ __('Repeat password') }}" :secure="true" :max-length="1024" class="w-full px-4 py-3"></bare-text-input>
        </list-section>
        <list-section>
            <column class="w-full p-4 gap-3">
                <button label="{{ __('Save encrypted backup') }}" variant="primary" class="glass:prominent w-full" @press="export"></button>
                <button label="{{ __('Cancel') }}" variant="ghost" class="w-full" @press="cancelExport"></button>
            </column>
        </list-section>
    @else
        <list-section footer="{{ __('Back up your verification codes to a password-protected file. Cbox connections are not included. Nothing is synced automatically.') }}">
            <list-item headline="{{ __('Create backup') }}" supporting="{{ __('Save an encrypted file in Files') }}" leadingIcon="square.and.arrow.up" trailingIcon="chevron.right" @press="createBackup" />
            <list-item headline="{{ __('Restore codes') }}" supporting="{{ __('Choose backup in Files') }}" leadingIcon="square.and.arrow.down" trailingIcon="chevron.right" @press="chooseFile" />
        </list-section>
        <list-section footer="{{ __('For other authenticators, scan each service setup QR code or enter its setup key.') }}"></list-section>
    @endif
</list>
