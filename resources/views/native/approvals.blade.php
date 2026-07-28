<native:column class="w-full h-full bg-white">
    <native:refreshable @refresh="load" class="flex-1">
        <native:column class="w-full p-4 gap-3">
            <native:text class="text-2xl font-bold text-zinc-900">Pending requests</native:text>

            @if ($failed)
                <native:row class="w-full items-center gap-2 p-3 rounded-xl bg-zinc-100">
                    <native:icon name="wifi.slash" :size="18" class="text-zinc-600" />
                    <native:text class="text-sm text-zinc-600">
                        Can't reach your Cbox ID host. Pull to retry.
                    </native:text>
                </native:row>
            @endif

            @forelse ($approvals as $approval)
                <native:pressable
                    class="w-full p-4 gap-1 rounded-2xl bg-zinc-50"
                    @press="open('{{ $approval['id'] }}')"
                >
                    <native:row class="w-full items-center gap-2">
                        <native:icon name="shield" :size="20" class="text-blue-600" />
                        <native:text class="text-base font-semibold text-zinc-900">
                            {{ $approval['client_name'] ?? 'An application' }}
                        </native:text>
                    </native:row>

                    {{-- The binding message is previewed, but the DECISION is only taken
                         on the detail screen, where the user has had to read it. --}}
                    @if (! empty($approval['binding_message']))
                        <native:text :max-lines="2" class="text-sm text-zinc-600">
                            {{ $approval['binding_message'] }}
                        </native:text>
                    @endif

                    <native:text class="text-xs text-zinc-600">Tap to review</native:text>
                </native:pressable>
            @empty
                <native:column class="w-full items-center justify-center gap-2 py-16">
                    <native:icon name="checkmark.circle" :size="40" class="text-zinc-600" />
                    <native:text class="text-base text-zinc-600">Nothing waiting.</native:text>
                    <native:text text-align="center" class="text-sm text-zinc-600">
                        You'll get a notification when an app asks for approval.
                    </native:text>
                </native:column>
            @endforelse
        </native:column>
    </native:refreshable>

    <native:row class="w-full p-4 items-center">
        <native:spacer />
        <x-btn label="Settings" variant="ghost" size="sm" press="goSettings" />
    </native:row>
</native:column>
