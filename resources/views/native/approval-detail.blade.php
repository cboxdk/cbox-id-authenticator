<column class="w-full h-full bg-theme-background">
    <scroll-view class="flex-1">
        <column class="w-full px-5 pt-4 pb-8 gap-5">

            @if ($error !== null)
                <text class="text-sm text-theme-destructive">{{ __($error) }}</text>
                <button label="{{ __('Refresh') }}" variant="secondary" :disabled="$working" @press="load"></button>
            @endif

            @if ($outcome !== null)
                <column class="w-full items-center gap-3 py-12">
                    <column class="w-20 h-20 items-center justify-center rounded-full {{ $outcome === 'approved' ? 'bg-theme-primary/10' : 'bg-theme-surface-variant' }}">
                        <icon
                            name="{{ $outcome === 'approved' ? 'checkmark.circle.fill' : ($outcome === 'failed' ? 'exclamationmark.triangle' : 'xmark.circle') }}"
                            :size="48"
                            class="{{ $outcome === 'approved' ? 'text-theme-primary' : 'text-theme-on-surface-variant' }}"
                        />
                    </column>
                    <text class="text-2xl font-bold text-theme-on-background">
                        @switch ($outcome)
                            @case('approved') {{ __("Approved") }} @break
                            @case('denied') {{ __("Denied") }} @break
                            @case('reported') {{ __("Reported and denied") }} @break
                            @case('expired') {{ __("This request expired") }} @break
                            @case('failed') {{ __("Couldn't reach your Cbox ID host") }} @break
                            @default {{ __("Already handled") }}
                        @endswitch
                    </text>

                    @if ($outcome === 'approved' && $fromLink)
                        {{-- iOS has no public way to switch back to the app that opened this
                             one. It shows its own "◀ Safari" in the status bar instead, and
                             the sign-in page continues by itself once it is visible again. --}}
                        <text text-align="center" class="text-base text-theme-on-background">{{ __("Go back to your browser to finish signing in.") }}</text>
                        <text text-align="center" class="text-sm text-theme-on-surface-variant">{{ __("Tap ◀ in the top-left corner. The page continues by itself.") }}</text>
                    @elseif ($outcome === 'approved')
                        <text text-align="center" class="text-sm text-theme-on-surface-variant">{{ __("Your sign-in continues where you started it.") }}</text>
                    @endif

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
                @php($d = $this->details)
                @php($left = $this->secondsLeft)

                {{-- Who is asking, and for what. --}}
                <row class="w-full items-center gap-4">
                    <column class="w-16 h-16 items-center justify-center rounded-2xl bg-theme-primary/10 border border-theme-primary/20">
                        @if (in_array($d['kind'], ['sign_in', 'step_up'], true))
                            <icon name="checkmark.shield.fill" :size="32" class="text-theme-primary" />
                        @else
                            <text class="text-2xl font-bold text-theme-primary">{{ mb_strtoupper(mb_substr($d['app'], 0, 1)) }}</text>
                        @endif
                    </column>
                    <column class="flex-1 gap-1">
                        <text class="text-xs font-semibold text-theme-primary">
                            @switch ($d['kind'])
                                @case('sign_in') {{ mb_strtoupper(__('Sign-in request')) }} @break
                                @case('step_up') {{ mb_strtoupper(__('Security check')) }} @break
                                @case('action') {{ mb_strtoupper(__('Action approval')) }} @break
                                @default {{ mb_strtoupper(__('Access request')) }}
                            @endswitch
                        </text>
                        <text class="text-2xl font-bold text-theme-on-background">
                            @switch ($d['kind'])
                                @case('sign_in') {{ __('Sign in to :app?', ['app' => $d['app']]) }} @break
                                @case('step_up') {{ __("Confirm it's you") }} @break
                                @case('action') {{ __('Approve this action?') }} @break
                                @default {{ __(':app wants access', ['app' => $d['app']]) }}
                            @endswitch
                        </text>
                        @if ($accountLabel !== null)<text class="text-sm text-theme-on-surface-variant">{{ $accountLabel }}</text>@endif
                    </column>
                </row>

                {{-- Above the fold, before the slider, and the loudest thing on the screen.
                     This is the anti-phishing primitive: the person compares it against what
                     the requesting page shows. It is never pushed. --}}
                @if ($d['code'] !== null)
                    <column class="w-full items-center gap-2 p-5 rounded-2xl bg-theme-surface border border-theme-primary/30">
                        <text class="text-sm font-medium text-theme-on-surface-variant">{{ __("Check this code matches your screen") }}</text>
                        <text a11y-label="{{ implode(' ', str_split($d['code'])) }}" class="text-4xl font-bold font-mono text-theme-on-background">{{ substr($d['code'], 0, 3).' '.substr($d['code'], 3) }}</text>
                    </column>
                @elseif ($d['message'] !== null)
                    <column class="w-full gap-3 p-5 rounded-2xl bg-theme-surface border border-theme-primary/30">
                        <text class="text-sm font-medium text-theme-on-surface-variant">{{ __("Check this matches what you were shown") }}</text>
                        <text class="text-2xl font-semibold text-theme-on-background">{{ $d['message'] }}</text>
                    </column>
                @else
                    <text class="text-base text-theme-destructive">{{ __("This request has no comparison message. Only approve if you just started this sign-in and recognize the application.") }}</text>
                @endif

                {{-- Where it comes from, as far as the server knows it. --}}
                <column class="w-full gap-3 p-4 rounded-2xl bg-theme-surface border border-theme-outline/50">
                    @if ($d['kind'] !== 'sign_in' && $d['kind'] !== 'step_up')
                        <row class="w-full items-center gap-3">
                            <icon name="app.badge" :size="16" class="text-theme-on-surface-variant" />
                            <text class="text-sm text-theme-on-surface-variant">{{ __('App') }}</text>
                            <spacer />
                            <text class="text-sm font-medium text-theme-on-background">{{ $d['app'] }}</text>
                        </row>
                    @endif
                    @if ($d['location'] !== null)
                        <row class="w-full items-center gap-3">
                            <icon name="location" :size="16" class="text-theme-on-surface-variant" />
                            <text class="text-sm text-theme-on-surface-variant">{{ __('Near') }}</text>
                            <spacer />
                            <text class="text-sm font-medium text-theme-on-background">{{ $d['location'] }}</text>
                        </row>
                    @endif
                    @if ($d['device'] !== null)
                        <row class="w-full items-center gap-3">
                            <icon name="desktopcomputer" :size="16" class="text-theme-on-surface-variant" />
                            <text class="text-sm text-theme-on-surface-variant">{{ __('Browser') }}</text>
                            <spacer />
                            <text class="text-sm font-medium text-theme-on-background">{{ $d['device'] }}</text>
                        </row>
                    @endif
                    @if ($d['host'] !== null)
                        <row class="w-full items-center gap-3">
                            <icon name="globe" :size="16" class="text-theme-on-surface-variant" />
                            <text class="text-sm text-theme-on-surface-variant">{{ __('Website') }}</text>
                            <spacer />
                            <text class="text-sm font-medium text-theme-on-background">{{ $d['host'] }}</text>
                        </row>
                    @endif
                    @if ($d['requested'] !== null)
                        <row class="w-full items-center gap-3">
                            <icon name="clock" :size="16" class="text-theme-on-surface-variant" />
                            <text class="text-sm text-theme-on-surface-variant">{{ __('Requested') }}</text>
                            <spacer />
                            <text class="text-sm font-medium text-theme-on-background">{{ $d['requested'] < 60 ? __('Just now') : __(':minutes min ago', ['minutes' => intdiv($d['requested'], 60)]) }}</text>
                        </row>
                    @endif
                    <row class="w-full items-center gap-3">
                        <icon name="timer" :size="16" class="{{ $left > 0 && $left <= 30 ? 'text-theme-destructive' : 'text-theme-on-surface-variant' }}" />
                        <text class="text-sm text-theme-on-surface-variant">{{ __('Expires in') }}</text>
                        <spacer />
                        <text class="text-sm font-semibold font-mono {{ $left > 0 && $left <= 30 ? 'text-theme-destructive' : 'text-theme-on-background' }}">{{ $left > 0 ? sprintf('%d:%02d', intdiv($left, 60), $left % 60) : __('Expired') }}</text>
                    </row>
                </column>

                @if (! empty($approval['scopes']))
                    <column class="w-full gap-3 p-4 rounded-2xl bg-theme-surface border border-theme-outline/50">
                        <text class="text-sm font-semibold text-theme-on-background">{{ __("It will be able to") }}</text>
                        @foreach ($approval['scopes'] as $scope)
                            <row class="w-full items-center gap-2">
                                <icon name="checkmark" :size="14" class="text-theme-primary" />
                                <text class="text-sm text-theme-on-surface-variant">{{ __($scope['label']) }}</text>
                            </row>
                        @endforeach
                    </column>
                @endif

                @if ($confirmingDeny)
                    {{-- A second, explicit step: Deny sits next to the slider, and a stray
                         tap must not decline a sign-in the person is in the middle of. --}}
                    <column class="w-full gap-3 p-4 rounded-2xl bg-theme-surface border border-theme-outline/50">
                        <text class="text-base font-semibold text-theme-on-background">{{ __("Deny this request?") }}</text>
                        <text class="text-sm text-theme-on-surface-variant">{{ __("If you didn't start it, report it instead of only denying it.") }}</text>
                        <button label="{{ __('Deny') }}" variant="secondary" class="w-full" :disabled="$working" @press="deny"></button>
                        {{-- Deliberately distinct from Deny: a person who did not start this
                             is reporting an attack, not declining a request. --}}
                        <button label="{{ __('This wasn\'t me') }}" variant="destructive" class="w-full" :disabled="$working" @press="wasNotMe"></button>
                        <button label="{{ __('Cancel') }}" variant="ghost" class="w-full" :disabled="$working" @press="keepRequest"></button>
                    </column>
                @else
                    <column class="w-full gap-3 pt-2">
                        <slide-confirm
                            class="w-full"
                            label="{{ $working || $biometricAttempt !== null ? __('Approving…') : __('Slide to approve') }}"
                            a11y-label="{{ __('Approve') }}"
                            a11y-hint="{{ __('Double-tap to approve. Face ID or your passcode follows.') }}"
                            :disabled="$left <= 0 || $working"
                            :busy="$working || $biometricAttempt !== null"
                            :reset="$slideReset"
                            @change="slid"
                        />
                        <button label="{{ __('Deny') }}" variant="ghost" class="w-full" size="md" :disabled="$working" @press="askDeny"></button>
                    </column>
                @endif
            @endif

        </column>
    </scroll-view>
</column>
