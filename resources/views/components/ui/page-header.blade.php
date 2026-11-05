@props(['title', 'subtitle' => null, 'eyebrow' => null])

<div {{ $attributes->merge(['class' => 'flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between']) }}>
    <div>
        @if ($eyebrow)
            <p class="section-kicker">{{ $eyebrow }}</p>
        @endif
        <h1 class="mt-1 text-2xl font-semibold tracking-tight text-ink">{{ $title }}</h1>
        @if ($subtitle)
            <p class="mt-1.5 max-w-2xl text-sm leading-relaxed text-muted">{{ $subtitle }}</p>
        @endif
    </div>

    @if (! $slot->isEmpty())
        <div class="flex flex-wrap items-center gap-2">{{ $slot }}</div>
    @endif
</div>
