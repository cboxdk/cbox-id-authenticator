<native:column class="w-full h-full bg-theme-background">
    <native:scroll-view class="flex-1">
        <native:column class="w-full p-4 gap-4">

            @if ($outcome !== null)
                <native:column class="w-full items-center gap-3 py-12">
                    <native:icon
                        name="{{ $outcome === 'approved' ? 'checkmark.circle' : ($outcome === 'failed' ? 'exclamationmark.triangle' : 'xmark.circle') }}"
                        :size="48"
                        class="{{ $outcome === 'approved' ? 'text-theme-primary' : 'text-theme-on-surface-variant' }}"
                    />
                    <native:text class="text-lg font-semibold text-theme-on-background">
                        @switch ($outcome)
                            @case('approved') Approved @break
                            @case('denied') Denied @break
                            @case('reported') Reported and denied @break
                            @case('expired') This request expired @break
                            @case('failed') Couldn't reach your Cbox ID host @break
                            @default Already handled
                        @endswitch
                    </native:text>

                    @if ($outcome === 'stale')
                        <native:text text-align="center" class="text-sm text-theme-on-surface-variant">
                            It may have expired, or been answered somewhere else.
                        </native:text>
                    @endif

                    @if ($outcome === 'reported')
                        <native:text text-align="center" class="text-sm text-theme-on-surface-variant">
                            If you didn't start this, change your password.
                        </native:text>
                    @endif

                    <x-btn label="Done" variant="secondary" press="goApprovals" />
                </native:column>

            @elseif ($missing || $approval === null)
                <native:column class="w-full items-center gap-3 py-12">
                    <native:icon name="questionmark.circle" :size="40" class="text-theme-on-surface-variant" />
                    <native:text class="text-base text-theme-on-background">This request is no longer available.</native:text>
                    <x-btn label="Back" variant="secondary" press="goBack" />
                </native:column>

            @else
                <native:text class="text-2xl font-bold text-theme-on-background">Approve this request?</native:text>

                <native:row class="w-full items-center gap-2">
                    <native:icon name="app.badge" :size="20" class="text-theme-primary" />
                    <native:text class="text-base font-semibold text-theme-on-background">
                        {{ $approval['client_name'] ?? 'An application' }}
                    </native:text>
                </native:row>

                {{-- Above the fold, before the buttons, and visually loudest thing on the
                     screen. This is the anti-phishing primitive: the user compares it
                     against what the requesting app showed them. It is never pushed. --}}
                @if (! empty($approval['binding_message']))
                    <native:column class="w-full gap-2 p-4 rounded-2xl bg-theme-surface-variant">
                        <native:text class="text-xs uppercase tracking-wide text-theme-on-surface-variant">
                            Check this matches what you were shown
                        </native:text>
                        <native:text class="text-lg font-semibold text-theme-on-surface">
                            {{ $approval['binding_message'] }}
                        </native:text>
                    </native:column>
                @endif

                @if (! empty($approval['scopes']))
                    <native:column class="w-full gap-2 p-4 rounded-2xl bg-theme-surface">
                        <native:text class="text-sm font-semibold text-theme-on-surface">It will be able to</native:text>
                        @foreach ($approval['scopes'] as $scope)
                            <native:row class="w-full items-center gap-2">
                                <native:icon name="checkmark" :size="14" class="text-theme-primary" />
                                <native:text class="text-sm text-theme-on-surface-variant">{{ $scope['label'] }}</native:text>
                            </native:row>
                        @endforeach
                    </native:column>
                @endif

                <native:text class="text-sm text-theme-on-surface-variant">
                    @if ($this->secondsLeft > 0)
                        Expires in {{ $this->secondsLeft }}s
                    @else
                        Expired
                    @endif
                </native:text>

                <native:column class="w-full gap-2 pt-2">
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
                </native:column>
            @endif

        </native:column>
    </native:scroll-view>
</native:column>
