@props(['name' => null, 'size' => 'md', 'src' => null])

@php
    $initials = collect(explode(' ', trim((string) $name)))->filter()->take(2)->map(fn ($part) => mb_strtoupper(mb_substr($part, 0, 1)))->implode('');
    $sizeClass = match ($size) {
        'sm' => 'h-8 w-8 text-[10px]',
        'lg' => 'h-14 w-14 text-base',
        'xl' => 'h-20 w-20 text-xl',
        default => 'h-10 w-10 text-xs',
    };
@endphp

@if ($src)
    <img src="{{ $src }}" alt="{{ $name }}" {{ $attributes->merge(['class' => 'rounded-2xl object-cover '.$sizeClass]) }}>
@else
    <span {{ $attributes->merge(['class' => 'inline-flex items-center justify-center rounded-2xl bg-blue-soft font-semibold text-[#4f7592] '.$sizeClass]) }}>
        {{ $initials ?: 'V' }}
    </span>
@endif
