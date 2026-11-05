<column class="w-full h-full bg-theme-background">
    <scroll-view class="flex-1">
        <column class="w-full px-5 py-4 gap-4">

            @if ($error !== null)
                <text class="text-sm text-theme-destructive">{{ __($error) }}</text>
                <button label="{{ __('Refresh') }}" variant="secondary" :disabled="$working" @press="load"></button>
            @endif

            @if ($outcome !== null)
                <column class="w-full items-center gap-3 py-12">
                    <icon
                        name="{{ $outcome === 'approved' ? 'checkmark.circle' : ($outcome === 'failed' ? 'exclamationmark.triangle' : 'xmark.circle') }}"
                        :size="48"
                        class="{{ $outcome === 'approved' ? 'text-theme-primary' : 'text-theme-on-surface-variant' }}"
                    />
                    <text class="text-lg font-semibold text-theme-on-background">
                        @switch ($outcome)
                            @case('approved') {{ __("Approved") }} @break
                            @case('denied') {{ __("Denied") }} @break
                            @case('reported') {{ __("Reported and denied") }} @break
                            @case('expired') {{ __("This request expired") }} @break
                            @case('failed') {{ __("Couldn't reach your Cbox ID host") }} @break
                            @default {{ __("Already handled") }}
                        @endswitch
                    </text>

                    @if ($outcome === 'stale')
                        <text text-align="center" class="text-sm text-theme-on-surface-variant">{{ __("It may have expired, or been answered somewhere else.") }}</text>
                    @endif

                    @if ($outcome === 'reported')
                        <text text-align="center" class="text-sm text-theme-on-surface-variant">{{ __("If you didn't start this, change your password.") }}</text>
                    @endif

                    <button label="{{ __('Done') }}" variant="secondary" class="w-full" @press="goApprovals"></button>
                </column>

            @elseif ($missing)
                <column class="w-full items-center gap-3 py-12">
                    <icon name="questionmark.circle" :size="40" class="text-theme-on-surface-variant" />
                    <text class="text-base text-theme-on-background">{{ __("This request is no longer available.") }}</text>
                    <button label="{{ __('Back') }}" variant="secondary" class="w-full" @press="goBack"></button>
                </column>

            @elseif ($approval !== null)
                <text class="text-2xl font-bold text-theme-on-background">{{ ($approval['purpose'] ?? '') === 'action' ? __('Approve this action?') : __('Approve this request?') }}</text>

                <row class="w-full items-center gap-2">
                    <icon name="app.badge" :size="20" class="text-theme-primary" />
                    <text class="text-base font-semibold text-theme-on-background">
                        {{ $approval['client_name'] ?? __('An application') }}
                    </text>
                </row>

                {{-- Whose account is being approved. With several connected, "approve
                     this request?" is only half a question without it. --}}
                @if ($accountLabel !== null)
                    <text class="text-sm text-theme-primary">{{ $accountLabel }}</text>
                @endif

                {{-- Above the fold, before the buttons, and visually loudest thing on the
                     screen. This is the anti-phishing primitive: the user compares it
                     against what the requesting app showed them. It is never pushed. --}}
                @if (! empty($approval['binding_message']))
                    <column class="w-full gap-2 p-4 rounded-2xl bg-theme-surface-variant">
                        <text class="text-xs uppercase tracking-wide text-theme-on-surface-variant">{{ __("Check this matches what you were shown") }}</text>
                        <text class="text-lg font-semibold text-theme-on-background">
                            {{ $approval['binding_message'] }}
                        </text>
                    </column>
                @else
                    <text class="text-base text-theme-destructive">{{ __("This request has no comparison message. Only approve if you just started this sign-in and recognize the application.") }}</text>
                @endif

                @if (! empty($approval['scopes']))
                    <column class="w-full gap-2 p-4 rounded-2xl bg-theme-surface">
                        <text class="text-sm font-semibold text-theme-on-background">{{ __("It will be able to") }}</text>
                        @foreach ($approval['scopes'] as $scope)
                            <row class="w-full items-center gap-2">
                                <icon name="checkmark" :size="14" class="text-theme-primary" />
                                <text class="text-sm text-theme-on-surface-variant">{{ __($scope['label']) }}</text>
                            </row>
                        @endforeach
                    </column>
                @endif

                <text class="text-sm text-theme-on-surface-variant">
                    @if ($this->secondsLeft > 0)
                        {{ __('Expires in :seconds s', ['seconds' => $this->secondsLeft]) }}
                    @else
                        {{ __('Expired') }}
                    @endif
                </text>

                <column class="w-full gap-2 pt-2">
                    <button
                        label="{{ __('Approve') }}"
                        variant="primary"
                        class="w-full glass:prominent"
                        size="lg"
                        :loading="$working || $biometricAttempt !== null"
                        :disabled="$this->secondsLeft <= 0 || $working || $biometricAttempt !== null"
                        @press="approve"
                    ></button>
                    <button label="{{ __('Deny') }}" variant="secondary" class="w-full" size="lg" :disabled="$working" @press="deny"></button>

                    {{-- Deliberately distinct from Deny: a user who did not start this
                         is reporting an attack, not declining a request. --}}
                    <button label="{{ __('This wasn\'t me') }}" variant="destructive" class="w-full" size="md" :disabled="$working" @press="wasNotMe"></button>
                </column>
            @endif

        </column>
    </scroll-view>
</column>
