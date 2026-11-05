@php($large = $large ?? false)
<column class="{{ $large ? 'w-16 h-16 rounded-2xl' : 'w-11 h-11 rounded-xl' }} {{ $code['icon'] !== null && $code['icon'] !== 'cbox' ? 'bg-white' : 'bg-theme-primary/10' }} items-center justify-center border border-theme-outline/30">
    @if ($code['icon'] === 'cbox')
        <icon name="checkmark.shield.fill" :size="$large ? 36 : 24" class="text-theme-primary" />
    @elseif ($code['icon'] !== null)
        <image src="{{ public_path('service-icons/'.$code['icon'].'.png') }}" class="{{ $large ? 'w-10 h-10' : 'w-7 h-7' }}" />
    @else
        <text class="{{ $large ? 'text-2xl' : 'text-lg' }} font-semibold text-theme-primary">{{ mb_strtoupper(mb_substr($code['issuer'] ?: $code['account'], 0, 1)) }}</text>
    @endif
</column>
