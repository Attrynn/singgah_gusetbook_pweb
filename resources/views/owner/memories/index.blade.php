@extends('layouts.owner')

@section('title', 'Kenangan')

@section('content')
    <x-ui.page-header title="Kenangan" subtitle="Kelola foto, ucapan, dan koleksi kenangan acara Anda.">
        <div class="flex flex-wrap items-center gap-2">
            <a href="{{ route('owner.memories.pending') }}" class="btn btn-soft btn-sm">
                <x-ui.icon name="clock" class="h-4 w-4 shrink-0" />
                Antrian pemeriksaan
            </a>
            <a href="{{ route('owner.memories.timeline') }}" class="btn btn-soft btn-sm">
                <x-ui.icon name="list" class="h-4 w-4 shrink-0" />
                Linimasa
            </a>
            <a href="{{ route('owner.memories.collections.index') }}" class="btn btn-soft btn-sm">
                <x-ui.icon name="grid" class="h-4 w-4 shrink-0" />
                Koleksi
            </a>
            <a href="{{ route('owner.memories.settings.edit') }}" class="btn btn-soft btn-sm">
                <x-ui.icon name="settings" class="h-4 w-4 shrink-0" />
                Pengaturan
            </a>
            <a href="{{ route('owner.memories.export.index') }}" class="btn btn-soft btn-sm">
                <x-ui.icon name="download" class="h-4 w-4 shrink-0" />
                Ekspor
            </a>
        </div>
    </x-ui.page-header>

    {{-- Counts: plain numbers with labels, nothing decorative --}}
    <div class="card mb-5 flex flex-wrap items-center gap-x-6 gap-y-2 px-4 py-3">
        <p class="text-sm text-muted"><span class="text-lg font-semibold text-ink">{{ $stats['photos_total'] ?? 0 }}</span> <span class="ml-1 text-xs">Foto</span></p>
        <p class="text-sm text-muted"><span class="text-lg font-semibold {{ ($stats['photos_pending'] ?? 0) > 0 ? 'text-warning' : 'text-ink' }}">{{ $stats['photos_pending'] ?? 0 }}</span> <span class="ml-1 text-xs">Foto menunggu</span></p>
        <p class="text-sm text-muted"><span class="text-lg font-semibold text-ink">{{ $stats['messages_total'] ?? 0 }}</span> <span class="ml-1 text-xs">Ucapan</span></p>
        <p class="text-sm text-muted"><span class="text-lg font-semibold {{ ($stats['messages_pending'] ?? 0) > 0 ? 'text-warning' : 'text-ink' }}">{{ $stats['messages_pending'] ?? 0 }}</span> <span class="ml-1 text-xs">Ucapan menunggu</span></p>
        <p class="text-sm text-muted"><span class="text-lg font-semibold text-ink">{{ $stats['hidden_total'] ?? 0 }}</span> <span class="ml-1 text-xs">Disembunyikan</span></p>
        <p class="text-sm text-muted"><span class="text-lg font-semibold text-ink">{{ $stats['collections_total'] ?? 0 }}</span> <span class="ml-1 text-xs">Koleksi</span></p>
    </div>

    {{-- Album Filter Chips --}}
    <div class="card p-4 sm:p-5 mb-5">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <p class="section-kicker">Filter album</p>
            <button type="button" class="btn btn-soft btn-sm" x-on:click="$dispatch('open-modal-album-create.window')">
                <x-ui.icon name="plus" class="h-4 w-4 shrink-0" />
                Buat album
            </button>
        </div>
        <div class="mt-3 flex gap-2 overflow-x-auto pb-2 scrollbar-none">
            <a href="{{ route('owner.memories.index', array_filter($filters, fn (string $key) => $key !== 'album_id', ARRAY_FILTER_USE_KEY)) }}" class="shrink-0 px-3 py-1.5 rounded-full border border-line bg-surface text-muted text-[10px] font-semibold hover:bg-surface-2/60 hover:text-ink transition {{ !isset($filters['album_id']) ? 'bg-blue-soft text-ink' : '' }}">
                Semua
            </a>
            @foreach ($albums as $album)
                @php
                    $active = ($filters['album_id'] ?? null) === $album->id;
                @endphp
                <a href="{{ route('owner.memories.index', array_merge($filters, ['album_id' => $album->id])) }}" class="shrink-0 px-3 py-1.5 rounded-full border border-line bg-surface text-muted text-[10px] font-semibold hover:bg-surface-2/60 hover:text-ink transition {{ $active ? 'bg-blue-soft text-ink' : '' }}">
                    {{ $album->name }}
                </a>
            @endforeach
        </div>
    </div>

    {{-- Filter bar: primary filters visible, the rest behind a disclosure --}}
    <div class="card p-4 sm:p-5 mb-5">
        <form method="GET" action="{{ route('owner.memories.index') }}" class="flex flex-col gap-3" x-data="{ more: false }">
            @foreach ($filters as $key => $value)
                @if (! in_array($key, ['collection', 'state', 'date'], true))
                    <input type="hidden" name="{{ $key }}" value="{{ $value }}">
                @endif
            @endforeach
            <div class="flex flex-wrap items-end gap-3">
                <div class="w-full sm:w-44">
                    <label for="state" class="label">Status</label>
                    <select id="state" name="state" class="input" data-auto-submit>
                        <option value="approved" @selected(($filters['state'] ?? 'approved') === 'approved')>Disetujui</option>
                        <option value="pending" @selected(($filters['state'] ?? '') === 'pending')>Menunggu</option>
                        <option value="hidden" @selected(($filters['state'] ?? '') === 'hidden')>Disembunyikan</option>
                    </select>
                </div>
                <div class="w-full sm:w-56">
                    <x-ui.select name="collection" label="Koleksi" :options="['' => 'Semua koleksi'] + $collections->pluck('name', 'id')->all()" :value="$filters['collection'] ?? null" />
                </div>
                <div class="flex gap-2">
                    <button type="submit" class="btn btn-primary">Filter</button>
                    <a href="{{ route('owner.memories.index') }}" class="btn btn-ghost">Reset</a>
                </div>
                <button type="button" class="btn btn-ghost btn-sm ml-auto" @click="more = !more" :aria-expanded="more" :aria-label="more ? 'Sembunyikan filter lainnya' : 'Tampilkan filter lainnya'">
                    <x-ui.icon name="filter" class="h-3.5 w-3.5 shrink-0" />
                    Filter lainnya
                    <x-ui.icon name="chevron-down" class="h-3 w-3 shrink-0 transition-transform" x-bind:class="more ? 'rotate-180' : ''" />
                </button>
            </div>
            <div x-show="more" x-cloak class="grid gap-3 border-t border-line/60 pt-3 sm:grid-cols-2 lg:grid-cols-3">
                <x-ui.input name="date" label="Tanggal" type="date" :value="$filters['date'] ?? null" />
            </div>
        </form>
    </div>

    {{-- Photo grid with bulk attach: checkboxes stay in card markup, the bar
         posts one form with hidden item_ids[] kept in sync by Alpine --}}
    <div x-data="{
            selected: [],
            hasErrors: {{ ($errors->has('item') || $errors->has('item_ids')) ? 'true' : 'false' }},
        }">
        <form method="POST" action="{{ route('owner.memories.collections.attach') }}"
              x-show="selected.length > 0 || hasErrors" x-cloak
              x-transition:enter="transition ease-out duration-200"
              x-transition:enter-start="opacity-0 -translate-y-2" x-transition:enter-end="opacity-100 translate-y-0"
              x-transition:leave="transition ease-in duration-150"
              x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"
              class="card mb-5 flex flex-col gap-3 p-4 sm:flex-row sm:items-center">
            @csrf
            <input type="hidden" name="type" value="media_asset">
            <template x-for="id in selected" :key="id">
                <input type="hidden" name="item_ids[]" :value="id">
            </template>
            <p class="shrink-0 text-sm font-semibold text-ink">
                <span x-text="selected.length" class="tabular-nums"></span> foto terpilih
            </p>
            <div class="relative min-w-0 flex-1 sm:max-w-xs">
                <label for="bulk-collection" class="sr-only">Koleksi tujuan</label>
                <select id="bulk-collection" name="collection_id" required
                        class="input input-sm w-full appearance-none pr-8">
                    <option value="">Pilih koleksi</option>
                    @foreach ($collections as $collection)
                        <option value="{{ $collection->id }}" @selected(old('collection_id') == $collection->id)>{{ $collection->name }}</option>
                    @endforeach
                </select>
                <x-ui.icon name="chevron-down" class="pointer-events-none absolute right-2.5 top-1/2 h-3.5 w-3.5 -translate-y-1/2 text-faint" />
            </div>
            <div class="flex shrink-0 items-center gap-2">
                <button type="submit" class="btn btn-primary btn-sm justify-center">
                    <x-ui.icon name="plus" class="h-3.5 w-3.5 shrink-0" />
                    Tambahkan ke koleksi
                </button>
                <button type="button" class="btn btn-ghost btn-sm" @click="selected = []">Batal</button>
            </div>
            @error('item')
                <p class="error-text w-full">{{ $message }}</p>
            @enderror
            @error('item_ids')
                <p class="error-text w-full">{{ $message }}</p>
            @enderror
        </form>

        <div class="grid grid-cols-2 gap-3 mb-5 sm:grid-cols-3 lg:grid-cols-4">
        @forelse ($assets as $asset)
            @php
                $moderateAction = $asset->isHidden() ? 'unhide' : ($asset->isApproved() ? 'hide' : 'approve');
                $moderateLabel = $asset->isHidden() ? 'Tampilkan kembali' : ($asset->isApproved() ? 'Sembunyikan' : 'Setujui');
                $moderateIcon = $asset->isHidden() ? 'refresh' : ($asset->isApproved() ? 'close' : 'check');
            @endphp
            <div class="card group overflow-hidden transition hover:shadow-soft">
                <div class="relative aspect-[4/3] overflow-hidden bg-surface-2/60">
                    @if ($asset->isApproved())
                        <img src="{{ $asset->thumbnailUrl(true) }}" alt="{{ $asset->original_name }}"
                             class="h-full w-full object-cover transition duration-300 group-hover:scale-[1.03] {{ $asset->isHidden() ? 'opacity-70 grayscale' : '' }}" loading="lazy" />
                    @else
                        <div class="flex h-full w-full items-center justify-center bg-surface-2/60">
                            <x-ui.icon name="image" class="h-8 w-8 text-faint" />
                        </div>
                    @endif

                    {{-- Status chip: approved vs pending vs hidden at a glance --}}
                    <div class="absolute left-2 top-2 z-20">
                        @if ($asset->isHidden())
                            <x-ui.badge variant="neutral" class="text-[10px] shadow-raised">Disembunyikan</x-ui.badge>
                        @elseif ($asset->isApproved())
                            <x-ui.badge variant="success" class="text-[10px] shadow-raised">Disetujui</x-ui.badge>
                        @else
                            <x-ui.badge variant="warning" class="text-[10px] shadow-raised">Menunggu</x-ui.badge>
                        @endif
                    </div>

                    {{-- Bulk attach: always-visible checkbox (touch friendly), above the image link --}}
                    <label class="absolute right-2 top-2 z-20 cursor-pointer">
                        <input type="checkbox" value="{{ $asset->id }}" x-model="selected"
                               class="peer sr-only" aria-label="Pilih foto {{ $asset->original_name }}">
                        <span class="flex h-6 w-6 items-center justify-center rounded-full border border-white/80 bg-scrim/60 text-transparent backdrop-blur-sm transition peer-checked:border-accent peer-checked:bg-accent peer-checked:text-accent-text peer-focus-visible:ring-2 peer-focus-visible:ring-accent">
                            <x-ui.icon name="check" class="h-3.5 w-3.5" />
                        </span>
                    </label>

                    {{-- Pointer-only hover affordance; actions below stay reachable on touch --}}
                    <div class="pointer-events-none absolute inset-x-0 bottom-0 z-10 hidden items-center gap-1.5 bg-gradient-to-t from-scrim/60 to-transparent p-2 pt-6 opacity-0 transition group-hover:flex group-hover:opacity-100 sm:flex">
                        <span class="inline-flex items-center gap-1 rounded-full bg-scrim/60 px-2.5 py-1 text-[10px] font-semibold text-on-scrim backdrop-blur-sm">
                            <x-ui.icon name="eye" class="h-3 w-3" />
                            Lihat detail
                        </span>
                    </div>

                    <a href="{{ route('owner.memories.show', $asset) }}" class="block absolute inset-0 z-10" aria-label="Lihat {{ $asset->original_name }}"></a>
                </div>
                <div class="p-3">
                    <p class="line-clamp-1 text-sm font-semibold text-ink">{{ $asset->caption ?? $asset->original_name }}</p>
                    <p class="mt-0.5 text-[10px] text-faint">
                        {{ $asset->guest?->name ?? 'Tamu' }} · {{ $asset->created_at?->timezone($event->timezoneName())->translatedFormat('d M Y') }}
                        @if ($asset->hidden_at) · Disembunyikan @endif
                    </p>
                </div>
                <div class="flex items-center justify-end gap-2 border-t border-line/60 px-3 py-2.5">
                    <form method="POST" action="{{ route('owner.memories.moderate', $asset) }}" class="inline">
                        @csrf
                        <input type="hidden" name="action" value="{{ $moderateAction }}">
                        <button type="submit"
                                class="icon-btn h-8 w-8 {{ $asset->isHidden() ? 'text-accent' : ($asset->isApproved() ? 'text-muted' : 'text-success') }}"
                                title="{{ $moderateLabel }}" aria-label="{{ $moderateLabel }}">
                            <x-ui.icon name="{{ $moderateIcon }}" class="h-4 w-4" />
                        </button>
                    </form>
                    @if ($asset->isApproved())
                        <a href="{{ route('owner.memories.download', $asset) }}" class="icon-btn h-8 w-8" title="Unduh" aria-label="Unduh">
                            <x-ui.icon name="download" class="h-4 w-4" />
                        </a>
                    @endif
                </div>
            </div>
        @empty
            <div class="col-span-full">
                <x-ui.empty-state icon="image" title="Belum ada foto" description="Foto tamu akan muncul di sini setelah disetujui.">
                    <a href="{{ route('owner.memories.pending') }}" class="btn btn-soft btn-sm">Periksa antrian pemeriksaan</a>
                </x-ui.empty-state>
            </div>
        @endforelse
        </div>
    </div>

    @include('owner.partials.pagination', ['paginator' => $assets])

    {{-- Album Management Modal (Alpine) --}}
    <x-ui.modal name="album-create" title="Buat album kenangan" description="Berikan nama dan deskripsi untuk album baru.">
        <form method="POST" action="{{ route('owner.memories.albums.store') }}" class="space-y-4">
            @csrf
            <x-ui.input name="name" label="Nama album" required placeholder="Contoh: Hari H" />
            <x-ui.textarea name="description" label="Deskripsi (opsional)" rows="3" placeholder="Cerita singkat tentang momen ini..." />
            <div class="pt-2">
                <button type="submit" class="btn btn-primary w-full">Buat album</button>
            </div>
        </form>
    </x-ui.modal>
@endsection
