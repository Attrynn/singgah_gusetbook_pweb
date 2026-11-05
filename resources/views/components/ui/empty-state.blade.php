@props(['icon' => 'sparkles', 'title' => 'Belum ada data', 'description' => null])

<div {{ $attributes->merge(['class' => 'flex flex-col items-center justify-center rounded-3xl border border-dashed border-line bg-surface/60 px-6 py-12 text-center']) }}>
    <span class="flex h-12 w-12 items-center justify-center rounded-2xl bg-blue-soft text-[#5c86a8]">
        <x-ui.icon :name="$icon" class="h-6 w-6" />
    </span>
    <h3 class="mt-4 text-sm font-semibold text-ink">{{ $title }}</h3>
    @if ($description)
        <p class="mt-1.5 max-w-sm text-xs leading-relaxed text-muted">{{ $description }}</p>
    @endif
    @if (! $slot->isEmpty())
        <div class="mt-5">{{ $slot }}</div>
    @endif
</div>
