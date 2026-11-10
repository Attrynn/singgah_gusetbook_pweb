@extends('layouts.owner')

@section('title', 'Laporan operasional · '.$event->name)

@php
    $reports = collect($reports ?? []);
    $canExport = auth()->user()->can('exportReports', $event);

    $reportTitles = [
        'staff' => 'Laporan staf',
        'stations' => 'Laporan stasiun',
        'run_sheet' => 'Laporan run of show',
        'tasks' => 'Laporan tugas',
        'incidents' => 'Laporan insiden',
        'attendance' => 'Laporan kehadiran & check-in',
    ];

    $reportIcons = [
        'staff' => 'users',
        'stations' => 'pin',
        'run_sheet' => 'list',
        'tasks' => 'clipboard',
        'incidents' => 'alert',
        'attendance' => 'qr',
    ];

    $reportTones = [
        'staff' => 'info',
        'stations' => 'success',
        'run_sheet' => 'neutral',
        'tasks' => 'warning',
        'incidents' => 'danger',
        'attendance' => 'success',
    ];

    $hasContactToggle = $reports->has('staff');
@endphp

@section('content')
    <x-ui.page-header
        title="Laporan operasional"
        eyebrow="Operasi"
        :subtitle="'Ekspor data operasional event ini ke format CSV.'"
    >
        <x-ui.button variant="secondary" :href="route('workspace.events.operations.index', [$organization, $event])" icon="arrow-left">Ruang kendali</x-ui.button>
    </x-ui.page-header>

    @include('workspace.events.partials.event-tabs', ['organization' => $organization, 'event' => $event, 'setup' => $setup ?? null, 'active' => 'operations-reports'])

    <div class="mt-6 grid gap-5 xl:grid-cols-3">
        <div class="space-y-5 xl:col-span-2">
            @foreach ($reports as $type => $report)
                @php
                    $rowCount = count($report['rows'] ?? []);
                    $colCount = count($report['headers'] ?? []);
                    $previewRows = array_slice($report['rows'] ?? [], 0, 5);
                @endphp

                <div class="card overflow-hidden animate-rise">
                    <div class="flex flex-wrap items-center justify-between gap-2 border-b border-line/70 px-5 py-4">
                        <div>
                            <h2 class="section-title">{{ $reportTitles[$type] ?? ucfirst($type) }}</h2>
                            <p class="mt-0.5 text-xs text-faint">{{ $colCount }} kolom · {{ $rowCount }} baris</p>
                        </div>
                        <span class="badge badge-{{ $reportTones[$type] ?? 'neutral' }} text-[10px]">{{ str_replace('.', ' · ', $type) }}</span>
                    </div>

                    <div class="overflow-hidden rounded-xl border border-line/60 bg-surface-2/40">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    @foreach ($report['headers'] ?? [] as $header)
                                        <th>{{ $header }}</th>
                                    @endforeach
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($previewRows as $row)
                                    <tr>
                                        @foreach ($row as $cell)
                                            <td>{{ $cell }}</td>
                                        @endforeach
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @endforeach
        </div>

        <div class="space-y-5">
            <div class="card p-5">
                <h2 class="section-title">Ekspor laporan</h2>
                <p class="mt-0.5 text-xs text-faint">Pilih jenis dan opsi kontak.</p>

                @if ($hasContactToggle)
                    <form method="GET" action="{{ route('workspace.events.operations.reports.export', [$organization, $event]) }}" class="mt-3 flex flex-wrap items-end gap-2">
                        <input type="hidden" name="type" value="staff">
                        <label class="flex items-start gap-2 rounded-xl border border-line/60 bg-surface px-3 py-2.5 text-[11px] text-muted">
                            <input type="checkbox" name="include_contact" value="1" class="mt-0.5 h-4 w-4 rounded border-line text-accent focus:ring-accent/40">
                            <span>Sertakan email anggota di laporan staf.</span>
                        </label>
                        <button type="submit" class="btn btn-primary btn-sm shrink-0">
                            <x-ui.icon name="download" class="h-3.5 w-3.5" /> Ekspor staf
                        </button>
                    </form>
                @endif

                <div class="mt-3 space-y-2">
                    @foreach ([
                        ['type' => 'staff', 'label' => 'Staf'],
                        ['type' => 'stations', 'label' => 'Stasiun'],
                        ['type' => 'run_sheet', 'label' => 'Run of show'],
                        ['type' => 'tasks', 'label' => 'Tugas'],
                        ['type' => 'incidents', 'label' => 'Insiden'],
                        ['type' => 'attendance', 'label' => 'Kehadiran & check-in'],
                    ] as $option)
                        <a href="{{ route('workspace.events.operations.reports.export', [$organization, $event, 'type' => $option['type']]) }}" class="block rounded-xl border border-line/60 px-3 py-2.5 text-[11px] transition hover:border-accent/40 hover:bg-surface-2/40">
                            <span class="inline-flex items-center gap-1.5 font-semibold text-ink">{{ $option['label'] }}</span>
                            <span class="ml-auto text-faint">CSV</span>
                        </a>
                    @endforeach
                </div>

                @if ($canExport)
                    <p class="mt-4 text-[11px] leading-relaxed text-muted">
                        Format file adalah <code class="text-faint">.csv</code>. Konten laporan bersifat internal dan tidak ditampilkan ke tamu.
                    </p>
                @endif
            </div>

            @cannot('exportReports', $event)
                <div class="card p-5">
                    <div class="flex items-start gap-3 rounded-xl bg-surface-2/60 p-3 text-xs text-muted">
                        <x-ui.icon name="lock" class="mt-0.5 h-4 w-4 shrink-0" />
                        <p>Anda dapat melihat laporan, tetapi tidak dapat mengekspornya ke format file.</p>
                    </div>
                </div>
            @endcannot
        </div>
    </div>
@endsection
