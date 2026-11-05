@props(['variant' => 'neutral'])

@php
    $variant = str_starts_with($variant, 'badge-') ? substr($variant, 6) : $variant;

    $classes = match ($variant) {
        'success' => 'badge-success',
        'warning' => 'badge-warning',
        'danger' => 'badge-danger',
        'info' => 'badge-info',
        'muted' => 'badge-muted',
        default => 'badge-neutral',
    };
@endphp

<span {{ $attributes->merge(['class' => 'badge '.$classes]) }}>{{ $slot }}</span>
