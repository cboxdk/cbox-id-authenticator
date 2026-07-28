<column class="w-full h-full bg-white">
    <refreshable @refresh="load" class="flex-1">
        <column class="w-full p-4 gap-3">
            <text class="text-2xl font-bold text-zinc-900">Pending requests</text>

            @if ($failed)
                <row class="w-full items-center gap-2 p-3 rounded-xl bg-zinc-100">
                    <icon name="wifi.slash" :size="18" class="text-zinc-600" />
                    <text class="text-sm text-zinc-600">
                        Can't reach your Cbox ID host. Pull to retry.
                    </text>
                </row>
            @endif

            @forelse ($approvals as $approval)
                <pressable
                    class="w-full p-4 gap-1 rounded-2xl bg-zinc-50"
                    @press="open('{{ $approval['id'] }}')"
                >
                    <row class="w-full items-center gap-2">
                        <icon name="shield" :size="20" class="text-blue-600" />
                        <text class="text-base font-semibold text-zinc-900">
                            {{ $approval['client_name'] ?? 'An application' }}
                        </text>
                    </row>

                    {{-- The binding message is previewed, but the DECISION is only taken
                         on the detail screen, where the user has had to read it. --}}
                    @if (! empty($approval['binding_message']))
                        <text :max-lines="2" class="text-sm text-zinc-600">
                            {{ $approval['binding_message'] }}
                        </text>
                    @endif

                    <text class="text-xs text-zinc-600">Tap to review</text>
                </pressable>
            @empty
                <column class="w-full items-center justify-center gap-2 py-16">
                    <icon name="checkmark.circle" :size="40" class="text-zinc-600" />
                    <text class="text-base text-zinc-600">Nothing waiting.</text>
                    <text text-align="center" class="text-sm text-zinc-600">
                        You'll get a notification when an app asks for approval.
                    </text>
                </column>
            @endforelse
        </column>
    </refreshable>

    <row class="w-full p-4 items-center">
        <spacer />
        <x-btn label="Settings" variant="ghost" size="sm" press="goSettings" />
    </row>
</column>
