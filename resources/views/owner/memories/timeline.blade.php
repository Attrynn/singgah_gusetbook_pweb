@extends('layouts.owner')

@section('title', 'Linimasa Kenangan')

@section('content')
    <div class="mb-5">
        <a href="{{ route('owner.memories.index') }}" class="text-accent text-sm inline-flex items-center gap-1 hover:underline">
            <x-ui.icon name="chevron-left" class="h-4 w-4" />
            Kembali ke kenangan
        </a>
    </div>

    <x-ui.page-header title="Linimasa Kenangan" subtitle="Foto dan ucapan yang sudah tampil, diurutkan berdasarkan waktu pengiriman.">
        <div class="flex flex-wrap items-center gap-2">
            <a href="{{ route('owner.memories.pending') }}" class="btn btn-soft btn-sm">
                <x-ui.icon name="clock" class="h-4 w-4 shrink-0" />
                Antrian pemeriksaan
            </a>
            <a href="{{ route('owner.memories.collections.index') }}" class="btn btn-soft btn-sm">
                <x-ui.icon name="grid" class="h-4 w-4 shrink-0" />
                Koleksi
            </a>
        </div>
    </x-ui.page-header>

    {{-- Filter bar: primary filters visible, date range behind a disclosure --}}
    <div class="card p-4 sm:p-5 mb-5">
        <form method="GET" action="{{ route('owner.memories.timeline') }}" class="flex flex-col gap-3" x-data="{ more: false }">
            <div class="flex flex-wrap items-end gap-3">
                <div class="w-full sm:w-56">
                    <x-ui.select name="collection_id" label="Koleksi" :options="['' => 'Semua koleksi'] + $collections->pluck('name', 'id')->all()" :value="$filters['collection_id'] ?? null" />
                </div>
                <div class="w-full sm:w-44">
                    <x-ui.select
                        name="type"
                        label="Tipe"
                        :options="['' => 'Semua tipe'] + collect(App\Enums\MemoryItemType::options())->pluck('label', 'value')->all()"
                        :value="$filters['type'] ?? null"
                    />
                </div>
                <div class="flex gap-2">
                    <button type="submit" class="btn btn-primary">Filter</button>
                    <a href="{{ route('owner.memories.timeline') }}" class="btn btn-ghost">Reset</a>
                </div>
                <button type="button" class="btn btn-ghost btn-sm ml-auto" @click="more = !more" :aria-expanded="more" :aria-label="more ? 'Sembunyikan filter tanggal' : 'Tampilkan filter tanggal'">
                    <x-ui.icon name="filter" class="h-3.5 w-3.5 shrink-0" />
                    Rentang tanggal
                    <x-ui.icon name="chevron-down" class="h-3 w-3 shrink-0 transition-transform" x-bind:class="more ? 'rotate-180' : ''" />
                </button>
            </div>
            <div x-show="more" x-cloak class="grid gap-3 border-t border-line/60 pt-3 sm:grid-cols-2">
                <x-ui.input name="from" label="Dari tanggal" type="date" :value="$filters['from'] ?? null" />
                <x-ui.input name="to" label="Sampai tanggal" type="date" :value="$filters['to'] ?? null" />
            </div>
        </form>
    </div>

    {{-- The submission-time note lives with the feed it explains --}}
    <p class="mb-5 flex items-center gap-1.5 text-[11px] text-faint">
        <x-ui.icon name="clock" class="h-3.5 w-3.5 shrink-0" />
        Waktu yang ditampilkan adalah waktu pengiriman tamu, bukan waktu pengambilan foto.
    </p>

    @php
        $grouped = $rows->getCollection()->groupBy(
            fn (array $row) => $row['at']->timezone($event->timezoneName())->toDateString()
        );
    @endphp

    {{-- One feed, grouped by a sticky date rail --}}
    @forelse ($grouped as $date => $items)
        <div class="mb-7">
            <div class="sticky top-[4.25rem] z-10 -mx-2 mb-3 flex items-center gap-2.5 bg-canvas/90 px-2 py-2 backdrop-blur">
                <span class="flex h-2 w-2 shrink-0 rounded-full bg-accent" aria-hidden="true"></span>
                <p class="section-kicker">{{ \Illuminate\Support\Carbon::parse($date)->locale('id')->translatedFormat('l, d F Y') }}</p>
                <span class="h-px flex-1 bg-line" aria-hidden="true"></span>
                <span class="text-[10px] font-semibold text-faint">{{ $items->count() }} kontribusi</span>
            </div>

            <div class="space-y-2.5">
                @foreach ($items as $row)
                    @php
                        $isPhoto = $row['type'] === App\Enums\MemoryItemType::MediaAsset;
                        $model = $row['model'];
                    @endphp
                    <article class="card flex items-start gap-3 p-3.5 transition hover:bg-surface-2/40 sm:gap-4 sm:p-4">
                        @if ($isPhoto)
                            <a href="{{ route('owner.memories.show', $model) }}" class="shrink-0" aria-label="Lihat {{ $row['caption'] ?? $model->original_name }}">
                                @if ($model->isApproved())
                                    <img src="{{ $model->thumbnailUrl(true) }}" alt="{{ $model->original_name }}" class="h-14 w-14 rounded-xl border border-line object-cover sm:h-16 sm:w-16" loading="lazy" />
                                @else
                                    <div class="flex h-14 w-14 items-center justify-center rounded-xl border border-line bg-surface-2/60 sm:h-16 sm:w-16">
                                        <x-ui.icon name="image" class="h-5 w-5 text-faint" />
                                    </div>
                                @endif
                            </a>
                        @else
                            <div class="flex h-14 w-14 shrink-0 items-center justify-center rounded-xl border border-line bg-teal-soft sm:h-16 sm:w-16">
                                <x-ui.icon name="message" class="h-5 w-5 text-ink" />
                            </div>
                        @endif

                        <div class="min-w-0 flex-1">
                            <div class="flex flex-wrap items-center gap-x-2 gap-y-1">
                                <x-ui.badge variant="muted" class="text-[10px]">{{ $isPhoto ? 'Foto' : 'Ucapan' }}</x-ui.badge>
                                <p class="text-[10px] text-faint">
                                    {{ $row['contributor'] ?? 'Tamu' }} · {{ $row['at']->timezone($event->timezoneName())->translatedFormat('H:i') }}
                                </p>
                            </div>
                            @if ($isPhoto)
                                <a href="{{ route('owner.memories.show', $model) }}" class="mt-1 block truncate text-sm font-medium text-ink transition hover:text-accent">
                                    {{ $row['caption'] ?? $model->original_name }}
                                </a>
                            @else
                                <p class="mt-1 line-clamp-2 text-sm text-muted">{{ $model->message }}</p>
                                <a href="{{ route('owner.wishes.show', $model) }}" class="mt-1 inline-block text-[10px] font-medium text-accent hover:underline">Lihat ucapan</a>
                            @endif
                        </div>
                    </article>
                @endforeach
            </div>
        </div>
    @empty
        <x-ui.empty-state icon="list" title="Belum ada kenangan yang tampil" description="Setujui kontribusi di antrian pemeriksaan agar muncul di linimasa ini.">
            <a href="{{ route('owner.memories.pending') }}" class="btn btn-soft btn-sm">Buka antrian pemeriksaan</a>
        </x-ui.empty-state>
    @endforelse

    @include('owner.partials.pagination', ['paginator' => $rows])
@endsection
