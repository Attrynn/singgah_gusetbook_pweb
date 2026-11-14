@extends('layouts.owner')

@section('title', 'Antrian Pemeriksaan')

@section('content')
    <div class="mb-5">
        <a href="{{ route('owner.memories.index') }}" class="text-accent text-sm inline-flex items-center gap-1 hover:underline">
            <x-ui.icon name="chevron-left" class="h-4 w-4" />
            Kembali ke kenangan
        </a>
    </div>

    <x-ui.page-header title="Antrian Pemeriksaan" subtitle="Konten yang menunggu keputusan Anda, diurutkan dari pengiriman terlama.">
        <div class="flex flex-wrap items-center gap-2">
            <a href="{{ route('owner.memories.timeline') }}" class="btn btn-soft btn-sm">
                <x-ui.icon name="list" class="h-4 w-4 shrink-0" />
                Linimasa
            </a>
            <a href="{{ route('owner.memories.index') }}" class="btn btn-soft btn-sm">
                <x-ui.icon name="image" class="h-4 w-4 shrink-0" />
                Galeri
            </a>
        </div>
    </x-ui.page-header>

    {{-- Filter bar: primary filters visible, date range behind a disclosure --}}
    <div class="card p-4 sm:p-5 mb-5">
        <form method="GET" action="{{ route('owner.memories.pending') }}" class="flex flex-col gap-3" x-data="{ more: false }">
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
                    <a href="{{ route('owner.memories.pending') }}" class="btn btn-ghost">Reset</a>
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

    {{-- Queue: scan-and-decide rows --}}
    <div class="space-y-3">
        @forelse ($rows as $row)
            @php
                $isPhoto = $row['type'] === App\Enums\MemoryItemType::MediaAsset;
                $model = $row['model'];
                $moderateRoute = $isPhoto
                    ? route('owner.memories.moderate', $model)
                    : route('owner.wishes.moderate', $model);
            @endphp
            <article class="card p-4 sm:p-5">
                <div class="flex items-start gap-3 sm:gap-4">
                    {{-- Thumbnail / type tile --}}
                    <div class="shrink-0">
                        @if ($isPhoto)
                            @if ($model->isApproved())
                                <img src="{{ $model->thumbnailUrl(true) }}" alt="{{ $model->original_name }}" class="h-16 w-16 rounded-2xl border border-line object-cover sm:h-20 sm:w-20" loading="lazy" />
                            @else
                                <div class="flex h-16 w-16 items-center justify-center rounded-2xl border border-line bg-surface-2/60 sm:h-20 sm:w-20">
                                    <x-ui.icon name="image" class="h-6 w-6 text-faint" />
                                </div>
                            @endif
                        @else
                            <div class="flex h-16 w-16 items-center justify-center rounded-2xl border border-line bg-teal-soft sm:h-20 sm:w-20">
                                <x-ui.icon name="message" class="h-6 w-6 text-ink" />
                            </div>
                        @endif
                    </div>

                    {{-- Type, state, contributor, time, excerpt --}}
                    <div class="min-w-0 flex-1">
                        <div class="flex flex-wrap items-center gap-x-2 gap-y-1">
                            <x-ui.badge variant="info" class="text-[10px]">{{ $isPhoto ? 'Foto' : 'Ucapan' }}</x-ui.badge>
                            <x-ui.badge :variant="$row['state']->badge()" class="text-[10px]">{{ $row['state']->label() }}</x-ui.badge>
                            <p class="text-[10px] text-faint">{{ $row['contributor'] ?? 'Tamu' }} · {{ $row['at']->timezone($event->timezoneName())->translatedFormat('d M Y H:i') }}</p>
                        </div>
                        @if ($isPhoto)
                            <p class="mt-1.5 line-clamp-1 text-sm font-medium text-ink">{{ $row['caption'] ?? $model->original_name }}</p>
                        @else
                            <p class="mt-1.5 line-clamp-2 text-sm text-muted">{{ $model->message }}</p>
                        @endif
                    </div>
                </div>

                {{-- Decision cluster: approve primary, hide neutral, reject destructive --}}
                <form method="POST" action="{{ $moderateRoute }}" class="mt-3 border-t border-line/60 pt-3">
                    @csrf
                    <div class="flex flex-col gap-2 sm:flex-row sm:items-center">
                        <div class="min-w-0 flex-1">
                            <label for="note-{{ $row['type']->value }}-{{ $model->id }}" class="sr-only">Catatan</label>
                            <input id="note-{{ $row['type']->value }}-{{ $model->id }}" type="text" name="note" value="{{ old('note') }}" placeholder="Opsional" class="input input-sm w-full">
                        </div>
                        <div class="grid grid-cols-3 gap-2 sm:flex sm:shrink-0">
                            <button type="submit" name="action" value="approve" class="btn btn-primary btn-sm justify-center">
                                <x-ui.icon name="check" class="h-3.5 w-3.5 shrink-0" />
                                Setujui
                            </button>
                            <button type="submit" name="action" value="hide" class="btn btn-ghost btn-sm justify-center">
                                <x-ui.icon name="eye" class="h-3.5 w-3.5 shrink-0" />
                                Sembunyikan
                            </button>
                            <button type="submit" name="action" value="reject" class="btn btn-danger btn-sm justify-center">
                                <x-ui.icon name="close" class="h-3.5 w-3.5 shrink-0" />
                                Tolak
                            </button>
                        </div>
                    </div>
                </form>
            </article>
        @empty
            <x-ui.empty-state icon="check" title="Tidak ada konten yang menunggu pemeriksaan." description="Semua kontribusi sudah diputuskan. Konten baru akan masuk ke antrian ini setelah dikirim tamu.">
                <a href="{{ route('owner.memories.timeline') }}" class="btn btn-soft btn-sm">Lihat linimasa</a>
            </x-ui.empty-state>
        @endforelse
    </div>

    @include('owner.partials.pagination', ['paginator' => $rows])
@endsection
