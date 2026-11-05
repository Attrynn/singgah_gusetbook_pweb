@extends('layouts.owner')

@section('title', 'Kenangan')

@section('content')
    <x-ui.page-header title="Kenangan" subtitle="Album foto dan momen spesial dari pernikahan Anda.">
        <div class="flex flex-wrap items-center gap-2">
            <form method="POST" action="{{ route('owner.memories.albums.store') }}" class="inline">
                @csrf
                <button type="button" class="btn btn-soft btn-sm" x-on:click="$dispatch('open-modal-album-create.window')">
                    <x-ui.icon name="plus" class="h-4 w-4 shrink-0" />
                    Buat album
                </button>
            </form>
        </div>
    </x-ui.page-header>

    {{-- Album Filter Chips --}}
    <div class="card p-5 mb-5">
        <p class="section-kicker">Filter album</p>
        <div class="mt-3 flex gap-2 overflow-x-auto pb-2 scrollbar-none">
            <a href="{{ route('owner.memories.index', array_filter($filters)) }}" class="shrink-0 px-3 py-1.5 rounded-full border border-line bg-surface text-muted text-[10px] font-semibold hover:bg-surface-2/60 hover:text-ink transition {{ !isset($filters['album_id']) ? 'bg-blue-soft text-ink' : '' }}">
                Semua
            </a>
            @foreach ($albums as $album)
                @php
                    $active = $filters['album_id'] ?? null === $album->id;
                @endphp
                <a href="{{ route('owner.memories.index', array_merge($filters, ['album_id' => $album->id])) }}" class="shrink-0 px-3 py-1.5 rounded-full border border-line bg-surface text-muted text-[10px] font-semibold hover:bg-surface-2/60 hover:text-ink transition {{ $active ? 'bg-blue-soft text-ink' : '' }}">
                    {{ $album->name }}
                </a>
            @endforeach
        </div>
    </div>

    {{-- Date Filter --}}
    <div class="card p-5 mb-5">
        <p class="section-kicker">Filter tanggal</p>
        <form method="GET" action="{{ route('owner.memories.index') }}" class="mt-3 flex flex-col sm:flex-row gap-3">
            @foreach ($filters as $key => $value)
                @if ($key !== 'date')
                    <input type="hidden" name="{{ $key }}" value="{{ $value }}">
                @endif
            @endforeach
            <div class="sm:w-48">
                <label for="date" class="label">Tanggal</label>
                <input type="text" id="date" name="date" class="input" placeholder="dd M yyyy" value="{{ $filters['date'] ?? '' }}" data-datepicker />
            </div>
            <div class="flex gap-2 self-end">
                <button type="submit" class="btn btn-primary">Filter</button>
                <a href="{{ route('owner.memories.index', $filters) }}" class="btn btn-ghost">Reset</a>
            </div>
        </form>
    </div>

    {{-- Photo Grid --}}
    <div class="grid grid-cols-2 gap-3 mb-5">
        @forelse ($assets as $asset)
            <div class="card p-0 overflow-hidden relative group">
                <a href="{{ route('owner.memories.show', $asset) }}" class="block absolute inset-0 z-10">
                    <img src="{{ $asset->thumbnailUrl() }}" alt="{{ $asset->original_name }}" class="w-full h-40 object-cover" loading="lazy" />
                </a>
                <div class="p-3 pb-0">
                    <p class="font-semibold text-sm text-ink line-clamp-1">{{ $asset->caption ?? $asset->original_name }}</p>
                    <p class="mt-0.5 text-[10px] text-faint">{{ $asset->guest?->name ?? 'Tamu' }} · {{ $asset->created_at?->translatedFormat('d M Y') }}</p>
                </div>
                <div class="px-3 pb-3 mt-2 flex justify-end">
                    <a href="{{ route('owner.memories.download', $asset) }}" class="icon-btn h-8 w-8 btn-ghost">
                        <x-ui.icon name="download" class="h-4 w-4" />
                    </a>
                </div>
            </div>
        @empty
            <div class="col-span-full">
                <x-ui.empty-state icon="image" title="Belum ada foto" description="Foto tamu akan muncul di sini setelah disetujui.">
                    <form method="POST" action="{{ route('owner.memories.albums.store') }}" class="mt-4">
                        @csrf
                        <input type="hidden" name="name" value="Album pertama">
                        <button type="submit" class="btn btn-soft btn-sm">Buat album pertama</button>
                    </form>
                </x-ui.empty-state>
            </div>
        @endforelse
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