@extends('layouts.owner')

@section('title', 'Ekspor Kenangan')

@section('content')
    @php
        $formatBytes = function (?int $bytes): string {
            if (! $bytes) {
                return '-';
            }

            $units = ['B', 'KB', 'MB', 'GB'];
            $index = 0;

            while ($bytes >= 1024 && $index < count($units) - 1) {
                $bytes /= 1024;
                $index++;
            }

            return round($bytes, 1).' '.$units[$index];
        };
    @endphp

    <div class="mb-5">
        <a href="{{ route('owner.memories.index') }}" class="text-accent text-sm inline-flex items-center gap-1 hover:underline">
            <x-ui.icon name="chevron-left" class="h-4 w-4" />
            Kembali ke kenangan
        </a>
    </div>

    <x-ui.page-header title="Ekspor Kenangan" subtitle="Unduh arsip foto dan ucapan yang sudah disetujui sebagai berkas ZIP.">
        <a href="{{ route('owner.memories.timeline') }}" class="btn btn-soft btn-sm">
            <x-ui.icon name="list" class="h-4 w-4 shrink-0" />
            Linimasa
        </a>
    </x-ui.page-header>

    {{-- Compact request form --}}
    <div class="card p-5 mb-5">
        <p class="section-kicker">Ajukan ekspor baru</p>
        <form method="POST" action="{{ route('owner.memories.export.store') }}" class="mt-3">
            @csrf
            <div class="flex flex-col gap-3 lg:flex-row lg:items-end">
                <div class="w-full lg:w-64">
                    <x-ui.select name="collection_id" label="Koleksi" :options="['' => 'Semua koleksi'] + $collections->pluck('name', 'id')->all()" :value="old('collection_id')" hint="Kosongkan untuk mengekspor seluruh kenangan acara." />
                </div>
                <div>
                    <input type="hidden" name="include_photos" value="0">
                    <label for="include_photos" class="flex cursor-pointer items-center justify-between gap-4 rounded-2xl border border-line/70 bg-surface-2/40 px-4 py-2.5 transition hover:border-accent/40">
                        <span class="text-sm font-medium text-ink">Sertakan foto</span>
                        <span class="relative inline-flex h-6 w-11 shrink-0">
                            <input id="include_photos" type="checkbox" name="include_photos" value="1" class="peer sr-only" @if(old('include_photos', true)) checked @endif>
                            <span aria-hidden="true" class="absolute inset-0 rounded-full bg-surface-3 transition-colors peer-checked:bg-accent peer-focus-visible:ring-2 peer-focus-visible:ring-accent/50 peer-focus-visible:ring-offset-2 peer-focus-visible:ring-offset-surface"></span>
                            <span aria-hidden="true" class="absolute left-0.5 top-0.5 h-5 w-5 rounded-full bg-surface shadow-sm transition-transform peer-checked:translate-x-5"></span>
                        </span>
                    </label>
                    @error('include_photos')
                        <p class="error-text">{{ $message }}</p>
                    @enderror
                </div>
                <div>
                    <input type="hidden" name="include_messages" value="0">
                    <label for="include_messages" class="flex cursor-pointer items-center justify-between gap-4 rounded-2xl border border-line/70 bg-surface-2/40 px-4 py-2.5 transition hover:border-accent/40">
                        <span class="text-sm font-medium text-ink">Sertakan ucapan</span>
                        <span class="relative inline-flex h-6 w-11 shrink-0">
                            <input id="include_messages" type="checkbox" name="include_messages" value="1" class="peer sr-only" @if(old('include_messages', true)) checked @endif>
                            <span aria-hidden="true" class="absolute inset-0 rounded-full bg-surface-3 transition-colors peer-checked:bg-accent peer-focus-visible:ring-2 peer-focus-visible:ring-accent/50 peer-focus-visible:ring-offset-2 peer-focus-visible:ring-offset-surface"></span>
                            <span aria-hidden="true" class="absolute left-0.5 top-0.5 h-5 w-5 rounded-full bg-surface shadow-sm transition-transform peer-checked:translate-x-5"></span>
                        </span>
                    </label>
                    @error('include_messages')
                        <p class="error-text">{{ $message }}</p>
                    @enderror
                </div>
                <button type="submit" class="btn btn-primary lg:ml-auto">
                    <x-ui.icon name="download" class="h-4 w-4 shrink-0" />
                    Ajukan ekspor
                </button>
            </div>
        </form>
    </div>

    {{-- Selected job detail --}}
    @if ($export)
        <div class="card p-5 mb-5">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <p class="section-kicker">Ekspor #{{ $export->id }}</p>
                    <div class="mt-2 flex flex-wrap items-center gap-2">
                        <x-ui.badge :variant="$export->status->badge()" class="text-[10px]">{{ $export->status->label() }}</x-ui.badge>
                        <span class="text-xs text-muted">{{ $export->item_count }} item · {{ $formatBytes($export->file_size) }}</span>
                    </div>
                </div>
                @if ($export->status === App\Enums\MemoryExportStatus::Completed && $export->fileExists() && ! $export->isExpired())
                    <a href="{{ route('owner.memories.export.download', $export) }}" class="btn btn-primary btn-sm">
                        <x-ui.icon name="download" class="h-4 w-4 shrink-0" />
                        Unduh arsip
                    </a>
                @endif
            </div>

            <dl class="mt-4 grid grid-cols-1 gap-3 text-xs sm:grid-cols-2">
                <div class="rounded-2xl border border-line/60 bg-surface-2/40 p-3">
                    <dt class="text-[10px] font-semibold uppercase tracking-[0.12em] text-muted">Lingkup</dt>
                    <dd class="mt-1 text-ink">
                        @if ($export->scope['collection_id'])
                            Koleksi terpilih
                        @else
                            Seluruh kenangan
                        @endif
                        · {{ $export->scope['include_photos'] ? 'Foto' : 'Tanpa foto' }} · {{ $export->scope['include_messages'] ? 'Ucapan' : 'Tanpa ucapan' }}
                    </dd>
                </div>
                <div class="rounded-2xl border border-line/60 bg-surface-2/40 p-3">
                    <dt class="text-[10px] font-semibold uppercase tracking-[0.12em] text-muted">Waktu</dt>
                    <dd class="mt-1 text-ink">
                        Diminta {{ $export->created_at?->timezone($event->timezoneName())->translatedFormat('d M Y H:i') }}
                        @if ($export->completed_at)
                            · Selesai {{ $export->completed_at?->timezone($event->timezoneName())->translatedFormat('d M Y H:i') }}
                        @endif
                    </dd>
                </div>
                @if ($export->expires_at)
                    <div class="rounded-2xl border border-line/60 bg-surface-2/40 p-3">
                        <dt class="text-[10px] font-semibold uppercase tracking-[0.12em] text-muted">Kedaluwarsa</dt>
                        <dd class="mt-1 text-ink">{{ $export->expires_at?->timezone($event->timezoneName())->translatedFormat('d M Y H:i') }} ({{ $event->timezoneName() }})</dd>
                    </div>
                @endif
            </dl>

            @if ($export->error)
                <p class="mt-3 text-xs text-danger">Gagal: {{ $export->error }}</p>
            @endif
        </div>
    @endif

    {{-- Job history: the primary content --}}
    <div class="card p-5">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <p class="section-kicker">Riwayat ekspor</p>
            <p class="text-[10px] font-semibold uppercase tracking-[0.12em] text-faint">{{ $jobs->total() }} ekspor</p>
        </div>

        {{-- The snapshot explanation answers "why is this not live data" right where jobs are listed --}}
        <p class="mt-2 flex items-start gap-1.5 text-[11px] leading-relaxed text-faint">
            <x-ui.icon name="info" class="mt-0.5 h-3.5 w-3.5 shrink-0" />
            Ekspor mengambil snapshot kontribusi yang sudah disetujui pada saat permintaan; persetujuan setelahnya tidak ikut masuk. Maksimal {{ $itemCap }} item per ekspor.
        </p>

        <div class="table-wrap mt-3">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Status</th>
                        <th>Lingkup</th>
                        <th>Item</th>
                        <th>Ukuran</th>
                        <th>Kedaluwarsa</th>
                        <th class="text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($jobs as $job)
                        <tr>
                            <td class="font-mono text-xs">#{{ $job->id }}</td>
                            <td><x-ui.badge :variant="$job->status->badge()" class="text-[10px]">{{ $job->status->label() }}</x-ui.badge></td>
                            <td class="text-xs">
                                {{ $job->scope['collection_id'] ? 'Koleksi terpilih' : 'Seluruh kenangan' }}
                                · {{ $job->scope['include_photos'] ? 'Foto' : 'Tanpa foto' }}
                                · {{ $job->scope['include_messages'] ? 'Ucapan' : 'Tanpa ucapan' }}
                            </td>
                            <td class="text-xs">{{ $job->item_count ?? '-' }}</td>
                            <td class="text-xs">{{ $formatBytes($job->file_size) }}</td>
                            <td class="text-xs">{{ $job->expires_at?->timezone($event->timezoneName())->translatedFormat('d M Y H:i') ?? '-' }}</td>
                            <td class="text-right">
                                @if ($job->status === App\Enums\MemoryExportStatus::Completed && $job->fileExists() && ! $job->isExpired())
                                    <a href="{{ route('owner.memories.export.download', $job) }}" class="btn btn-soft btn-sm">
                                        <x-ui.icon name="download" class="h-4 w-4 shrink-0" />
                                        Unduh
                                    </a>
                                @else
                                    <a href="{{ route('owner.memories.export.show', $job) }}" class="text-accent text-xs hover:underline">Detail</a>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="text-center text-xs text-muted py-8">Belum ada ekspor yang diajukan.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    @include('owner.partials.pagination', ['paginator' => $jobs])
@endsection
