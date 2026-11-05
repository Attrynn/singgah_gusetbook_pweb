@props(['label' => null, 'value' => null, 'icon' => 'chart', 'hint' => null, 'tone' => 'accent'])

@php
    $toneClass = match ($tone) {
        'success' => 'bg-success/10 text-[#3f8069]',
        'warning' => 'bg-warning/10 text-[#8f6b2f]',
        'danger' => 'bg-danger/10 text-[#a35b64]',
        'teal' => 'bg-teal-soft text-[#3f7c74]',
        default => 'bg-blue-soft text-[#4f7592]',
    };
@endphp

<div {{ $attributes->merge(['class' => 'stat-card']) }}>
    <div class="flex items-start justify-between gap-3">
        <div>
            <p class="text-[11px] font-semibold uppercase tracking-[0.12em] text-muted">{{ $label }}</p>
            <p class="mt-2 text-2xl font-semibold tracking-tight text-ink">{{ $value }}</p>
            @if ($hint)
                <p class="mt-1 text-xs text-faint">{{ $hint }}</p>
            @endif
        </div>
        <span class="flex h-10 w-10 items-center justify-center rounded-2xl {{ $toneClass }}">
            <x-ui.icon :name="$icon" class="h-5 w-5" />
        </span>
    </div>
</div>
