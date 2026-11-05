@props(['name', 'title' => null, 'description' => null, 'maxWidth' => 'max-w-lg'])

<div
    x-data="{ open: false }"
    x-on:open-modal-{{ $name }}.window="open = true"
    x-on:close-modal-{{ $name }}.window="open = false"
    @keydown.escape.window="open = false"
    x-cloak
>
    <div x-show="open" class="fixed inset-0 z-50 flex items-end justify-center p-0 sm:items-center sm:p-6" role="dialog" aria-modal="true">
        <div class="absolute inset-0 bg-ink/30 backdrop-blur-sm" x-on:click="open = false"></div>

        <div
            x-show="open"
            x-transition:enter="transition ease-out duration-200"
            x-transition:enter-start="translate-y-6 opacity-0 sm:translate-y-0 sm:scale-95"
            x-transition:enter-end="translate-y-0 opacity-100 sm:scale-100"
            x-transition:leave="transition ease-in duration-150"
            x-transition:leave-start="translate-y-0 opacity-100 sm:scale-100"
            x-transition:leave-end="translate-y-6 opacity-0 sm:translate-y-0 sm:scale-95"
            class="relative w-full {{ $maxWidth }} rounded-t-3xl border border-line bg-surface p-6 shadow-soft sm:rounded-3xl"
        >
            <div class="flex items-start justify-between gap-4">
                <div>
                    @if ($title)
                        <h2 class="text-lg font-semibold text-ink">{{ $title }}</h2>
                    @endif
                    @if ($description)
                        <p class="mt-1 text-sm text-muted">{{ $description }}</p>
                    @endif
                </div>
                <button type="button" class="icon-btn h-9 w-9" x-on:click="open = false" aria-label="Tutup">
                    <x-ui.icon name="close" class="h-4 w-4" />
                </button>
            </div>

            <div class="mt-5">
                {{ $slot }}
            </div>

            @isset($footer)
                <div class="mt-6 flex flex-wrap items-center justify-end gap-2">
                    {{ $footer }}
                </div>
            @endisset
        </div>
    </div>
</div>
