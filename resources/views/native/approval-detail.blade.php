<column class="w-full h-full bg-white">
    <scroll-view class="flex-1">
        <column class="w-full p-4 gap-4">

            @if ($outcome !== null)
                <column class="w-full items-center gap-3 py-12">
                    <icon
                        name="{{ $outcome === 'approved' ? 'checkmark.circle' : ($outcome === 'failed' ? 'exclamationmark.triangle' : 'xmark.circle') }}"
                        :size="48"
                        class="{{ $outcome === 'approved' ? 'text-blue-600' : 'text-zinc-600' }}"
                    />
                    <text class="text-lg font-semibold text-zinc-900">
                        @switch ($outcome)
                            @case('approved') Approved @break
                            @case('denied') Denied @break
                            @case('reported') Reported and denied @break
                            @case('expired') This request expired @break
                            @case('failed') Couldn't reach your Cbox ID host @break
                            @default Already handled
                        @endswitch
                    </text>

                    @if ($outcome === 'stale')
                        <text text-align="center" class="text-sm text-zinc-600">
                            It may have expired, or been answered somewhere else.
                        </text>
                    @endif

                    @if ($outcome === 'reported')
                        <text text-align="center" class="text-sm text-zinc-600">
                            If you didn't start this, change your password.
                        </text>
                    @endif

                    <x-btn label="Done" variant="secondary" press="goApprovals" />
                </column>

            @elseif ($missing || $approval === null)
                <column class="w-full items-center gap-3 py-12">
                    <icon name="questionmark.circle" :size="40" class="text-zinc-600" />
                    <text class="text-base text-zinc-900">This request is no longer available.</text>
                    <x-btn label="Back" variant="secondary" press="goBack" />
                </column>

            @else
                <text class="text-2xl font-bold text-zinc-900">Approve this request?</text>

                <row class="w-full items-center gap-2">
                    <icon name="app.badge" :size="20" class="text-blue-600" />
                    <text class="text-base font-semibold text-zinc-900">
                        {{ $approval['client_name'] ?? 'An application' }}
                    </text>
                </row>

                {{-- Above the fold, before the buttons, and visually loudest thing on the
                     screen. This is the anti-phishing primitive: the user compares it
                     against what the requesting app showed them. It is never pushed. --}}
                @if (! empty($approval['binding_message']))
                    <column class="w-full gap-2 p-4 rounded-2xl bg-zinc-100">
                        <text class="text-xs uppercase tracking-wide text-zinc-600">
                            Check this matches what you were shown
                        </text>
                        <text class="text-lg font-semibold text-zinc-900">
                            {{ $approval['binding_message'] }}
                        </text>
                    </column>
                @endif

                @if (! empty($approval['scopes']))
                    <column class="w-full gap-2 p-4 rounded-2xl bg-zinc-50">
                        <text class="text-sm font-semibold text-zinc-900">It will be able to</text>
                        @foreach ($approval['scopes'] as $scope)
                            <row class="w-full items-center gap-2">
                                <icon name="checkmark" :size="14" class="text-blue-600" />
                                <text class="text-sm text-zinc-600">{{ $scope['label'] }}</text>
                            </row>
                        @endforeach
                    </column>
                @endif

                <text class="text-sm text-zinc-600">
                    @if ($this->secondsLeft > 0)
                        Expires in {{ $this->secondsLeft }}s
                    @else
                        Expired
                    @endif
                </text>

                <column class="w-full gap-2 pt-2">
                    <x-btn
                        label="Approve"
                        variant="primary"
                        size="lg"
                        :loading="$working"
                        :disabled="$this->secondsLeft <= 0"
                        @press="approve"
                    />
                    <x-btn label="Deny" variant="secondary" size="lg" :disabled="$working" press="deny" />

                    {{-- Deliberately distinct from Deny: a user who did not start this
                         is reporting an attack, not declining a request. --}}
                    <x-btn label="This wasn't me" variant="destructive" size="md" :disabled="$working" press="wasNotMe" />
                </column>
            @endif

        </column>
    </scroll-view>
</column>
