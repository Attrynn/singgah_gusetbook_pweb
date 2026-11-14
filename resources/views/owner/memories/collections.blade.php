@extends('layouts.owner')

@section('title', 'Koleksi Kenangan')

@section('content')
    <div class="mb-5">
        <a href="{{ route('owner.memories.index') }}" class="text-accent text-sm inline-flex items-center gap-1 hover:underline">
            <x-ui.icon name="chevron-left" class="h-4 w-4" />
            Kembali ke kenangan
        </a>
    </div>

    <x-ui.page-header title="Koleksi Kenangan" subtitle="Kelompokkan foto dan ucapan menjadi koleksi kurasi untuk tamu Anda.">
        <button type="button" class="btn btn-primary btn-sm" x-on:click="$dispatch('open-modal-collection-create.window')">
            <x-ui.icon name="plus" class="h-4 w-4 shrink-0" />
            Buat koleksi
        </button>
    </x-ui.page-header>

    {{-- Active collections: read view first, mutations behind disclosures --}}
    @forelse ($collections as $collection)
        <article class="card mb-5 overflow-hidden">
            <div class="flex flex-col gap-4 p-5 lg:flex-row lg:items-start lg:justify-between">
                <div class="min-w-0">
                    <div class="flex flex-wrap items-center gap-2">
                        <h3 class="font-serif text-lg font-semibold text-ink">{{ $collection->name }}</h3>
                        <x-ui.badge :variant="$collection->is_visible_to_guests ? 'success' : 'muted'" class="text-[10px]">
                            {{ $collection->is_visible_to_guests ? 'Tampak untuk tamu' : 'Hanya pemilik' }}
                        </x-ui.badge>
                        <x-ui.badge variant="info" class="text-[10px]">{{ $collection->items_count }} item</x-ui.badge>
                    </div>
                    @if ($collection->description)
                        <p class="mt-1.5 line-clamp-2 text-sm text-muted">{{ $collection->description }}</p>
                    @endif
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    <form method="POST" action="{{ route('owner.memories.collections.archive', $collection) }}" class="inline" data-confirm data-confirm-title="Arsipkan koleksi?" data-confirm-text="Koleksi disembunyikan dari tamu, itemnya tetap tersimpan.">
                        @csrf
                        <button type="submit" class="btn btn-secondary btn-sm">
                            <x-ui.icon name="archive" class="h-4 w-4 shrink-0" />
                            Arsipkan
                        </button>
                    </form>
                    <form method="POST" action="{{ route('owner.memories.collections.destroy', $collection) }}" class="inline" data-confirm data-confirm-title="Hapus koleksi?" data-confirm-text="Koleksi dihapus permanen. Foto dan ucapan tidak terpengaruh." data-confirm-icon="warning" data-confirm-button="Ya, Hapus">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn btn-danger btn-sm">
                            <x-ui.icon name="trash" class="h-4 w-4 shrink-0" />
                            Hapus
                        </button>
                    </form>
                </div>
            </div>

            {{-- Item previews --}}
            <div class="border-t border-line/60 px-5 py-4">
                @forelse ($collection->items as $entry)
                    @php
                        $isPhoto = $entry->item_type === App\Enums\MemoryItemType::MediaAsset->value;
                        $target = $entry->item();
                    @endphp
                    <div class="flex items-center gap-3 border-b border-line/40 py-2.5 first:pt-0 last:border-0 last:pb-0">
                        @if ($target && $isPhoto && $target->isApproved())
                            <img src="{{ $target->thumbnailUrl(true) }}" alt="" class="h-10 w-10 shrink-0 rounded-lg border border-line object-cover" loading="lazy" />
                        @elseif ($isPhoto)
                            <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-surface-2 text-faint">
                                <x-ui.icon name="image" class="h-4 w-4" />
                            </span>
                        @else
                            <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-teal-soft text-ink">
                                <x-ui.icon name="message" class="h-4 w-4" />
                            </span>
                        @endif
                        <x-ui.badge variant="muted" class="shrink-0 text-[10px]">{{ $isPhoto ? 'Foto' : 'Ucapan' }}</x-ui.badge>
                        <div class="min-w-0 flex-1">
                            @if ($target)
                                @if ($isPhoto)
                                    <a href="{{ route('owner.memories.show', $target) }}" class="block truncate text-sm font-medium text-ink hover:text-accent">{{ $target->caption ?? $target->original_name }}</a>
                                @else
                                    <a href="{{ route('owner.wishes.show', $target) }}" class="block line-clamp-1 text-sm font-medium text-ink hover:text-accent">{{ $target->sender_name }} · {{ $target->message }}</a>
                                @endif
                            @else
                                <p class="truncate text-sm text-faint">Item tidak ditemukan</p>
                            @endif
                        </div>
                        <form method="POST" action="{{ route('owner.memories.collections.items.destroy', [$collection, $entry->item_type, $entry->item_id]) }}" class="inline" data-confirm data-confirm-title="Hapus item?" data-confirm-text="Item dihapus dari koleksi ini saja.">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="icon-btn h-8 w-8" title="Hapus dari koleksi" aria-label="Hapus dari koleksi">
                                <x-ui.icon name="close" class="h-4 w-4" />
                            </button>
                        </form>
                    </div>
                @empty
                    <p class="text-sm text-muted">Belum ada item di koleksi ini.</p>
                @endforelse
            </div>

            {{-- Mutation controls: disclosures, not permanently open forms --}}
            <div class="space-y-2 border-t border-line/60 bg-surface-2/30 px-5 py-3">
                <details class="group">
                    <summary class="btn btn-ghost btn-sm w-full list-none justify-between [&::-webkit-details-marker]:hidden">
                        <span class="inline-flex items-center gap-2">
                            <x-ui.icon name="plus" class="h-4 w-4 shrink-0" />
                            Tambahkan item
                        </span>
                        <x-ui.icon name="chevron-down" class="h-3.5 w-3.5 shrink-0 transition-transform group-open:rotate-180" />
                    </summary>
                    <form method="POST" action="{{ route('owner.memories.collections.items.store', $collection) }}" class="mt-3 grid grid-cols-1 items-end gap-3 sm:grid-cols-[1fr_1fr_auto]">
                        @csrf
                        <x-ui.select name="type" label="Tipe item" required :id="'type-'.$collection->id" :options="collect($itemTypes)->pluck('label', 'value')->all()" :value="old('type')" />
                        <x-ui.input name="item_id" label="ID item" type="number" min="1" required :id="'item-'.$collection->id" :value="old('item_id')" placeholder="ID foto atau ucapan" hint="Ambil ID dari galeri atau halaman ucapan." />
                        <button type="submit" class="btn btn-soft btn-sm">Tambahkan</button>
                    </form>
                </details>

                <details class="group">
                    <summary class="btn btn-ghost btn-sm w-full list-none justify-between [&::-webkit-details-marker]:hidden">
                        <span class="inline-flex items-center gap-2">
                            <x-ui.icon name="edit" class="h-4 w-4 shrink-0" />
                            Edit koleksi
                        </span>
                        <x-ui.icon name="chevron-down" class="h-3.5 w-3.5 shrink-0 transition-transform group-open:rotate-180" />
                    </summary>
                    <form method="POST" action="{{ route('owner.memories.collections.update', $collection) }}" class="mt-3 grid grid-cols-1 gap-3 sm:grid-cols-2">
                        @csrf
                        @method('PATCH')
                        <x-ui.input name="name" label="Nama" required :id="'name-'.$collection->id" :value="old('name', $collection->name)" />
                        <x-ui.input name="sort_order" label="Urutan" type="number" min="0" :id="'sort-'.$collection->id" :value="old('sort_order', $collection->sort_order)" />
                        <x-ui.textarea name="description" label="Deskripsi" rows="2" :id="'description-'.$collection->id" :value="old('description', $collection->description)" />
                        <x-ui.select name="is_visible_to_guests" label="Tampak untuk tamu" :id="'visible-'.$collection->id" :options="['1' => 'Ya', '0' => 'Tidak']" :value="old('is_visible_to_guests', $collection->is_visible_to_guests ? '1' : '0')" />
                        <div class="sm:col-span-2">
                            <button type="submit" class="btn btn-primary btn-sm">Simpan perubahan</button>
                        </div>
                    </form>
                </details>
            </div>
        </article>
    @empty
        <x-ui.empty-state icon="grid" title="Belum ada koleksi" description="Buat koleksi pertama untuk mengurasi foto dan ucapan terbaik acara Anda.">
            <button type="button" class="btn btn-soft btn-sm" x-on:click="$dispatch('open-modal-collection-create.window')">Buat koleksi</button>
        </x-ui.empty-state>
    @endforelse

    {{-- Archived --}}
    @if ($archived->isNotEmpty())
        <div class="card p-5">
            <p class="section-kicker">Arsip</p>
            <div class="mt-3 space-y-2">
                @foreach ($archived as $collection)
                    <div class="flex flex-col gap-3 rounded-2xl border border-line/60 bg-surface-2/40 p-3 sm:flex-row sm:items-center sm:justify-between">
                        <div class="min-w-0">
                            <p class="truncate text-sm font-semibold text-ink">{{ $collection->name }}</p>
                            <p class="text-[10px] text-faint">Diarsipkan {{ $collection->archived_at?->timezone($event->timezoneName())->translatedFormat('d M Y H:i') }} · {{ $collection->items_count }} item</p>
                        </div>
                        <div class="flex items-center gap-2">
                            <form method="POST" action="{{ route('owner.memories.collections.unarchive', $collection) }}" class="inline">
                                @csrf
                                <button type="submit" class="btn btn-soft btn-sm">
                                    <x-ui.icon name="refresh" class="h-4 w-4 shrink-0" />
                                    Buka arsip
                                </button>
                            </form>
                            <form method="POST" action="{{ route('owner.memories.collections.destroy', $collection) }}" class="inline" data-confirm data-confirm-title="Hapus koleksi?" data-confirm-text="Koleksi dihapus permanen. Foto dan ucapan tidak terpengaruh." data-confirm-icon="warning" data-confirm-button="Ya, Hapus">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-danger btn-sm">
                                    <x-ui.icon name="trash" class="h-4 w-4 shrink-0" />
                                    Hapus
                                </button>
                            </form>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    {{-- Create modal: the only creation path --}}
    <x-ui.modal name="collection-create" title="Buat koleksi memori" description="Berikan nama untuk koleksi baru Anda.">
        <form method="POST" action="{{ route('owner.memories.collections.store') }}" class="space-y-4">
            @csrf
            <x-ui.input name="name" label="Nama koleksi" required placeholder="Contoh: Momen terbaik" />
            <x-ui.textarea name="description" label="Deskripsi (opsional)" rows="3" placeholder="Cerita singkat tentang koleksi ini..." />
            <x-ui.select name="is_visible_to_guests" label="Tampak untuk tamu" :options="['1' => 'Ya', '0' => 'Tidak']" :value="old('is_visible_to_guests', '0')" />
            <x-ui.input name="sort_order" label="Urutan" type="number" min="0" :value="old('sort_order', 0)" />
            <div class="pt-2">
                <button type="submit" class="btn btn-primary w-full">Buat koleksi</button>
            </div>
        </form>
    </x-ui.modal>
@endsection
