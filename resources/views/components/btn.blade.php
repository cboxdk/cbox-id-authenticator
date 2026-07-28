@props([
    'label' => '',
    'variant' => 'primary',
    'size' => 'lg',
    'disabled' => false,
    'loading' => false,
    'press' => 'noop',
])

{{--
    A button, built from `native:pressable` + `native:text`.

    NativePHP 4.0.0-rc.1 ships an `Edge\Elements\Button` class but never registers it, so
    `<native:button>` throws "Unknown native element type: button" at render — verified
    against the package's own test harness, not inferred. The same is true of text-input,
    toggle and activity-indicator. The docs index bundled with the package describes all
    of them because it is written against a later state of the engine than this release
    candidate ships. `pressable` IS registered and takes `@press`, so this is a faithful
    stand-in: same tap target, same native press feedback. When the real element lands,
    this component is the one place that changes.

    TWO PRECOMPILER CONSTRAINTS LEARNED THE HARD WAY, both visible in the compiled view:

    1. Blade directives cannot appear INSIDE an EDGE element's attribute list. The
       precompiler runs over the raw template before Blade does, so an `@if` in there is
       parsed as an attribute — `'if' => true, 'inert' => true` — and silently mangles
       the element. Conditionals go outside the tag.
    2. `@navigate` cannot interpolate: `@navigate="{{ $x }}"` compiles the braces straight
       into PHP and produces a syntax error. So this component does not navigate at all —
       callers pass a method name and navigate from PHP with `$this->navigate()`, which
       keeps navigation intent in the component where it is testable anyway.
--}}

@php
    $palette = match ($variant) {
        'secondary' => ['bg' => 'bg-zinc-100', 'fg' => 'text-zinc-600'],
        'destructive' => ['bg' => 'bg-red-100', 'fg' => 'text-red-800'],
        'ghost' => ['bg' => 'bg-transparent', 'fg' => 'text-blue-600'],
        default => ['bg' => 'bg-blue-600', 'fg' => 'text-white'],
    };

    $padding = match ($size) {
        'sm' => 'py-2 px-3',
        'md' => 'py-3 px-4',
        default => 'py-4 px-5',
    };

    $textSize = $size === 'sm' ? 'text-sm' : 'text-base';

    // Disabled and loading both block the press and both dim the control: a button that
    // looks live but ignores taps reads as a broken app rather than a busy one. The
    // handler is swapped for a no-op rather than conditionally omitted, because the
    // attribute cannot be wrapped in a directive (see note 1 above).
    $inert = $disabled || $loading;
    $handler = $inert ? 'noop' : $press;
    $dim = $inert ? ' opacity-50' : '';
@endphp

<native:pressable
    class="w-full items-center justify-center rounded-2xl {{ $padding }} {{ $palette['bg'] }}{{ $dim }}"
    :press-scale="0.97"
    :press-opacity="0.85"
    @press="{{ $handler }}"
>
    <native:text class="{{ $textSize }} font-semibold {{ $palette['fg'] }}" text-align="center">
        {{ $loading ? 'Working…' : ($label !== '' ? $label : $slot) }}
    </native:text>
</native:pressable>
