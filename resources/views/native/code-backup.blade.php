<column class="w-full h-full bg-theme-background">
    <scroll-view class="flex-1">
        <column class="w-full px-5 pt-5 pb-8 gap-6">
            @if ($error !== null)<text class="text-sm text-theme-destructive">{{ __($error) }}</text>@endif
            @if ($notice !== null)<text class="text-sm text-theme-on-surface-variant">{{ __($notice) }}</text>@endif
            @if ($preview)
                <column class="w-full p-6 gap-4 rounded-3xl bg-gradient-to-br from-blue-500/10 to-transparent border border-theme-outline/50">
                    @include('native.partials.mark', ['symbol' => 'checkmark.doc'])
                    <text class="text-2xl font-bold text-theme-on-background">{{ __('Ready to import') }}</text>
                    <text class="text-base text-theme-on-surface-variant">{{ __('Add :added codes and skip :duplicates duplicates.', ['added' => $added, 'duplicates' => $duplicates]) }}</text>
                    <text class="text-sm text-theme-on-surface-variant">{{ __('Your existing codes and their names will be kept.') }}</text>
                </column>
                <button label="{{ __('Import codes') }}" variant="primary" size="lg" class="glass:prominent w-full" @press="import"></button>
                <button label="{{ __('Cancel import') }}" variant="ghost" class="w-full" @press="cancelPreview"></button>
            @elseif ($hasFile || $creating)
                <column class="w-full gap-2">
                    <text class="text-2xl font-bold tracking-tight text-theme-on-background">{{ $creating ? __('Protect your backup') : __('Unlock your backup') }}</text>
                    <text class="text-base text-theme-on-surface-variant">{{ $creating ? __('Choose a unique password of at least 12 characters. We cannot recover a forgotten backup password.') : __('Enter the password you used when you created this file.') }}</text>
                </column>
                <column class="w-full p-4 gap-4 rounded-2xl bg-theme-surface border border-theme-outline/50">
                    <column class="w-full gap-2">
                        <text class="text-sm font-medium text-theme-on-background">{{ __('Backup password') }}</text>
                        <bare-text-input native:model="password" placeholder="{{ __('At least 12 characters') }}" a11y-label="{{ __('Backup password') }}" :secure="true" :max-length="1024" class="w-full p-3 rounded-xl bg-theme-surface-variant"></bare-text-input>
                    </column>
                    @if ($creating)
                        <column class="w-full gap-2">
                            <text class="text-sm font-medium text-theme-on-background">{{ __('Repeat password') }}</text>
                            <bare-text-input native:model="confirmation" placeholder="{{ __('Enter it once more') }}" a11y-label="{{ __('Repeat password') }}" :secure="true" :max-length="1024" class="w-full p-3 rounded-xl bg-theme-surface-variant"></bare-text-input>
                        </column>
                    @endif
                </column>
                @if ($creating)
                    <button label="{{ __('Save encrypted backup') }}" variant="primary" size="lg" class="glass:prominent w-full" @press="export"></button>
                    <button label="{{ __('Cancel') }}" variant="ghost" class="w-full" @press="cancelExport"></button>
                @else
                    <button label="{{ __('Preview import') }}" variant="primary" size="lg" class="glass:prominent w-full" @press="inspect"></button>
                    <button label="{{ __('Choose another backup') }}" variant="ghost" class="w-full" @press="chooseFile"></button>
                @endif
            @else
                <column class="w-full p-6 gap-5 rounded-3xl bg-gradient-to-br from-blue-500/10 to-transparent border border-theme-outline/50">
                    @include('native.partials.mark', ['symbol' => 'lock.doc'])
                    <column class="w-full gap-2">
                        <text class="text-3xl font-bold tracking-tight text-theme-on-background">{{ __('Keep a spare key.') }}</text>
                        <text class="text-base text-theme-on-surface-variant">{{ __('Save your verification codes in a file only your password can open.') }}</text>
                    </column>
                    <button label="{{ __('Create backup') }}" variant="primary" size="lg" class="glass:prominent w-full" @press="createBackup"></button>
                </column>
                <pressable class="w-full p-4 rounded-2xl bg-theme-surface border border-theme-outline/50" a11y-label="{{ __('Restore codes') }}" @press="chooseFile">
                    @include('native.partials.action-content', ['symbol' => 'square.and.arrow.down', 'title' => __('Restore codes'), 'subtitle' => __('Choose backup in Files')])
                </pressable>
                <row class="w-full px-1 gap-3 items-center">
                    <icon name="lock.shield" :size="22" class="text-theme-primary" />
                    <text class="flex-1 text-sm text-theme-on-surface-variant">{{ __('Backups contain verification codes only. Cbox accounts stay separate. Nothing is synced automatically.') }}</text>
                </row>
                <text class="px-1 text-sm text-theme-on-surface-variant">{{ __('For other authenticators, scan each service setup QR code or enter its setup key.') }}</text>
            @endif
        </column>
    </scroll-view>
</column>
