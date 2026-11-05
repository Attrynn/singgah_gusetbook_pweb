@extends('layouts.admin')

@section('title', 'Kategori Tamu')
@section('subtitle', 'Kelompokkan tamu per kategori')

@section('content')
    <x-ui.page-header title="Kategori Tamu" eyebrow="Guest Categories" subtitle="Kategori membantu memfilter dan melaporkan tamu per event." />

    <form method="GET" action="{{ route('admin.categories.index') }}" class="card-flat mt-6 p-4">
        <div class="grid gap-3 md:grid-cols-[minmax(0,320px)_auto]">
            <div>
                <label for="event_id" class="label">Event</label>
                <select id="event_id" name="event_id" class="input appearance-none" onchange="this.form.submit()">
                    @foreach ($events as $item)
                        <option value="{{ $item->id }}" @selected($event?->id === $item->id)>{{ $item->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="flex items-end">
                <button type="submit" class="btn-primary">
                    <x-ui.icon name="search" class="h-4 w-4" /> Tampilkan
                </button>
            </div>
        </div>
    </form>

    @if (! $event)
        <div class="mt-6">
            <x-ui.empty-state icon="calendar" title="Belum ada event" description="Buat event terlebih dahulu untuk mengelola kategori.">
                <x-ui.button :href="route('admin.events.create')" variant="primary" icon="plus">Buat event</x-ui.button>
            </x-ui.empty-state>
        </div>
    @else
        <div class="mt-6 grid gap-6 xl:grid-cols-[minmax(0,1fr)_380px]">
            <div class="card p-0">
                <div class="flex flex-wrap items-center justify-between gap-2 border-b border-line/70 px-5 py-4">
                    <div>
                        <h2 class="section-title">Kategori · {{ $event->name }}</h2>
                        <p class="mt-0.5 text-xs text-faint">{{ $categories->count() }} kategori</p>
                    </div>
                </div>

                @if ($categories->isEmpty())
                    <div class="p-5">
                        <x-ui.empty-state icon="list" title="Belum ada kategori" description="Tambahkan kategori pertama pada formulir di samping." />
                    </div>
                @else
                    <div class="divide-y divide-line/70">
                        @foreach ($categories as $category)
                            <div class="flex flex-wrap items-center gap-3 px-5 py-4">
                                <span class="h-9 w-9 shrink-0 rounded-xl border border-line" style="background-color: {{ $category->color ?: '#8EB4D3' }}"></span>
                                <div class="min-w-0 flex-1">
                                    <p class="truncate text-sm font-semibold text-ink">{{ $category->name }}</p>
                                    <p class="text-xs text-faint">{{ $category->slug }} · {{ $category->guests_count }} tamu</p>
                                    @if ($category->description)
                                        <p class="mt-1 text-xs text-muted">{{ $category->description }}</p>
                                    @endif
                                </div>
                                <details class="w-full sm:w-auto">
                                    <summary class="btn btn-ghost btn-sm cursor-pointer list-none">Edit</summary>
                                    <form method="POST" action="{{ route('admin.categories.update', $category) }}" class="mt-3 grid gap-3 rounded-2xl bg-surface-2/50 p-4 sm:grid-cols-2">
                                        @csrf
                                        @method('PUT')
                                        <input type="hidden" name="event_id" value="{{ $event->id }}">
                                        <div class="sm:col-span-2">
                                            <label class="label">Nama</label>
                                            <input name="name" type="text" class="input" value="{{ $category->name }}" required>
                                        </div>
                                        <div>
                                            <label class="label">Warna</label>
                                            <input name="color" type="color" class="input h-11 py-1" value="{{ $category->color ?: '#8EB4D3' }}">
                                        </div>
                                        <div>
                                            <label class="label">Deskripsi</label>
                                            <input name="description" type="text" class="input" value="{{ $category->description }}">
                                        </div>
                                        <div class="flex items-center gap-2 sm:col-span-2">
                                            <button type="submit" class="btn btn-primary btn-sm">Simpan</button>
                                        </div>
                                    </form>
                                </details>
                                <form method="POST" action="{{ route('admin.categories.destroy', $category) }}" data-confirm="Hapus kategori {{ $category->name }}? Tamu terkait tidak dihapus." data-confirm-title="Hapus Kategori">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="icon-btn h-8 w-8 text-[#a35b64]" title="Hapus">
                                        <x-ui.icon name="trash" class="h-3.5 w-3.5" />
                                    </button>
                                </form>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>

            <aside class="card h-fit p-6">
                <h2 class="section-title">Tambah kategori</h2>
                <form method="POST" action="{{ route('admin.categories.store') }}" class="mt-4 space-y-4">
                    @csrf
                    <input type="hidden" name="event_id" value="{{ $event->id }}">
                    <div>
                        <label class="label">Nama <span class="text-[#b76a73]">*</span></label>
                        <input name="name" type="text" class="input" value="{{ old('name') }}" required placeholder="Contoh: Keluarga">
                        @error('name')<p class="error-text">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label class="label">Warna</label>
                        <input name="color" type="color" class="input h-11 py-1" value="{{ old('color', '#8EB4D3') }}">
                    </div>
                    <div>
                        <label class="label">Deskripsi</label>
                        <input name="description" type="text" class="input" value="{{ old('description') }}" placeholder="Opsional">
                    </div>
                    <button type="submit" class="btn btn-primary w-full">
                        <x-ui.icon name="plus" class="h-4 w-4" /> Tambah kategori
                    </button>
                </form>
            </aside>
        </div>
    @endif
@endsection
