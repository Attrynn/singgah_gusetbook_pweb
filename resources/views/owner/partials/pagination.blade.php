@php
    $from = $paginator->firstItem() ?? 0;
    $to = $paginator->lastItem() ?? 0;
    $total = $paginator->total();
@endphp

@if ($total > 0)
    <div class="mt-6 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
        <p class="text-[11px] font-semibold text-muted">
            Menampilkan <b>{{ $from }}</b>–<b>{{ $to }}</b> dari <b>{{ $total }}</b>
        </p>
        <div class="flex items-center gap-2">
            @if ($paginator->onFirstPage())
                <span class="btn-ghost btn-sm text-faint">Sebelumnya</span>
            @else
                <a href="{{ $paginator->previousPageUrl() }}" class="btn-ghost btn-sm">
                    <x-ui.icon name="chevron-left" class="h-4 w-4 shrink-0" />
                    Sebelumnya
                </a>
            @endif
            @if ($paginator->hasMorePages())
                <a href="{{ $paginator->nextPageUrl() }}" class="btn-ghost btn-sm">
                    Selanjutnya
                    <x-ui.icon name="chevron-right" class="h-4 w-4 shrink-0" />
                </a>
            @else
                <span class="btn-ghost btn-sm text-faint">Selanjutnya</span>
            @endif
        </div>
    </div>
@endif
