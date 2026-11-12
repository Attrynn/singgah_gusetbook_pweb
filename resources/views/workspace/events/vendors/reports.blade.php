@extends('layouts.owner')

@section('title', 'Laporan vendor · '.$event->name)

@php
    $columns = $report['columns'] ?? [];
    $rows = $report['rows'] ?? [];
    $rowCount = count($rows);
    $currentType = array_key_exists((string) request('type'), $types) ? (string) request('type') : 'engagements';
    $currentLabel = $types[$currentType] ?? $report['title'];

    $moneyIndexes = collect($columns)
        ->filter(fn (string $column): bool => str_starts_with($column, 'Nilai'))
        ->keys()
        ->all();

    $canViewCommercial = auth()->user()->can('viewVendorCommercial', $event);
    $canExport = auth()->user()->can('exportVendorReports', $event);
    $exportUrl = route('workspace.events.vendors.reports.export', [
        $organization,
        $event,
        'type' => $currentType,
        'include_commercial' => $includeCommercial ? 1 : 0,
    ]);
@endphp

@section('content')
    <x-ui.page-header
        title="Laporan vendor"
        eyebrow="Vendor"
        :subtitle="'Tinjau data vendor acara lalu ekspor ke CSV. Laporan aktif: '.$report['title'].'.'"
    >
        <x-ui.button variant="secondary" :href="route('workspace.events.vendors.index', [$organization, $event])" icon="arrow-left">Hub vendor</x-ui.button>
    </x-ui.page-header>

    @include('workspace.events.partials.event-tabs', ['organization' => $organization, 'event' => $event, 'active' => 'vendors-reports'])

    {{-- Pemilih laporan --}}
    <form method="GET" action="{{ route('workspace.events.vendors.reports.index', [$organization, $event]) }}" class="card mt-6 p-5">
        <div class="grid gap-4 md:grid-cols-[minmax(0,20rem)_minmax(0,1fr)] md:items-end">
            <x-ui.select
                name="type"
                label="Jenis laporan"
                :options="$types"
                :value="$currentType"
                hint="Sembilan laporan tersedia dari direktori hingga kinerja."
            />

            <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
                @if ($canViewCommercial)
                    <label class="flex max-w-md items-start gap-2.5 rounded-xl border border-line/70 bg-surface-2/40 px-3 py-2.5">
                        <input
                            type="checkbox"
                            name="include_commercial"
                            value="1"
                            @checked($includeCommercial)
                            class="mt-0.5 h-4 w-4 rounded border-line text-accent focus:ring-accent/40"
                        >
                        <span class="text-[11px] leading-relaxed text-muted">
                            Sertakan angka komersial (nilai kesepakatan dan penawaran) di tabel dan berkas ekspor.
                        </span>
                    </label>
                @else
                    <p class="flex max-w-md items-start gap-2 rounded-xl border border-line/70 bg-surface-2/40 px-3 py-2.5 text-[11px] leading-relaxed text-muted">
                        <x-ui.icon name="lock" class="mt-0.5 h-3.5 w-3.5 shrink-0 text-faint" />
                        <span>Angka komersial disembunyikan untuk peran Anda.</span>
                    </p>
                @endif

                <button type="submit" class="btn btn-primary btn-sm shrink-0">
                    <x-ui.icon name="filter" class="h-3.5 w-3.5" /> Tampilkan
                </button>
            </div>
        </div>
    </form>

    {{-- Tabel laporan --}}
    <div class="card mt-5 overflow-hidden">
        <div class="flex flex-wrap items-center justify-between gap-3 border-b border-line/70 px-5 py-4">
            <div class="min-w-0">
                <div class="flex flex-wrap items-center gap-2">
                    <h2 class="section-title">{{ $report['title'] }}</h2>
                    <x-ui.badge :variant="$includeCommercial ? 'success' : 'neutral'" class="text-[9px]">
                        {{ $includeCommercial ? 'Termasuk angka komersial' : 'Tanpa angka komersial' }}
                    </x-ui.badge>
                </div>
                <p class="mt-0.5 text-xs text-faint">{{ count($columns) }} kolom · {{ $rowCount }} baris</p>
            </div>

            @if ($canExport)
                <x-ui.button variant="secondary" size="sm" :href="$exportUrl" icon="download">Ekspor CSV</x-ui.button>
            @endif
        </div>

        @if ($rowCount === 0)
            <div class="p-5">
                <x-ui.empty-state
                    icon="chart"
                    title="Belum ada data pada laporan ini"
                    description="Laporan {{ $currentLabel }} belum memiliki baris untuk acara ini. Pilih jenis lain atau lengkapi data vendor terlebih dahulu."
                />
            </div>
        @else
            <div class="max-h-[65vh] overflow-auto">
                <table class="data-table">
                    <thead>
                        <tr>
                            @foreach ($columns as $index => $column)
                                <th class="{{ in_array($index, $moneyIndexes, true) ? 'text-right' : '' }} sticky top-0 z-10 bg-surface-2 border-b border-line/80">
                                    {{ $column }}
                                </th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($rows as $row)
                            <tr>
                                @foreach ($columns as $index => $column)
                                    @php $cell = $row[$index] ?? null; @endphp
                                    <td class="{{ in_array($index, $moneyIndexes, true) ? 'text-right tabular-nums' : '' }}">
                                        @if ($cell === null || $cell === '')
                                            <span class="text-faint">—</span>
                                        @elseif (in_array($index, $moneyIndexes, true) && is_numeric($cell))
                                            {{ number_format((float) $cell, 0, ',', '.') }}
                                        @else
                                            {{ $cell }}
                                        @endif
                                    </td>
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

    <div class="mt-5 grid gap-5 md:grid-cols-2">
        <div class="card p-5">
            <h2 class="section-title">Tentang ekspor</h2>
            <p class="mt-2 text-[11px] leading-relaxed text-muted">
                Berkas CSV memakai kolom yang sama dengan tabel di atas. Angka komersial hanya disertakan bila opsi komersial dipilih dan peran Anda memiliki akses.
            </p>

            @if ($canExport)
                <a href="{{ $exportUrl }}" class="mt-3 inline-flex items-center gap-1.5 text-[11px] font-semibold text-accent transition hover:opacity-80">
                    <x-ui.icon name="download" class="h-3.5 w-3.5" />
                    Unduh {{ $currentLabel }}
                </a>
            @else
                <p class="mt-3 flex items-start gap-2 rounded-xl bg-surface-2/60 p-3 text-[11px] leading-relaxed text-muted">
                    <x-ui.icon name="lock" class="mt-0.5 h-3.5 w-3.5 shrink-0 text-faint" />
                    <span>Anda dapat melihat laporan ini, tetapi tidak dapat mengekspornya ke berkas.</span>
                </p>
            @endif
        </div>

        <div class="card p-5">
            <h2 class="section-title">Laporan lain</h2>
            <p class="mt-0.5 text-xs text-faint">Lompat langsung ke jenis laporan lain.</p>

            <div class="mt-3 flex flex-wrap gap-1.5">
                @foreach ($types as $type => $label)
                    @if ($type !== $currentType)
                        <a
                            href="{{ route('workspace.events.vendors.reports.index', [$organization, $event, 'type' => $type, 'include_commercial' => $includeCommercial ? 1 : 0]) }}"
                            class="inline-flex items-center gap-1 rounded-full border border-line/70 bg-surface px-2.5 py-1 text-[10px] font-medium text-muted transition hover:border-accent/40 hover:text-ink"
                        >
                            {{ $label }}
                        </a>
                    @endif
                @endforeach
            </div>
        </div>
    </div>
@endsection