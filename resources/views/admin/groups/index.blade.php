@extends('layouts.admin')

@section('title', 'Kelompok Tamu')
@section('subtitle', 'Kelompokkan tamu untuk penempatan dan laporan')

@section('content')
    <x-ui.page-header title="Kelompok Tamu" eyebrow="Guest Groups" subtitle="Kelompok biasanya merepresentasikan keluarga, kantor, atau angkatan." />

    <form method="GET" action="{{ route('admin.groups.index') }}" class="card-flat mt-6 p-4">
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
            <x-ui.empty-state icon="calendar" title="Belum ada event" description="Buat event terlebih dahulu untuk mengelola kelompok tamu.">
                <x-ui.button :href="route('admin.events.create')" variant="primary" icon="plus">Buat event</x-ui.button>
            </x-ui.empty-state>
        </div>
    @else
        <div class="mt-6 grid gap-6 xl:grid-cols-[minmax(0,1fr)_380px]">
            <div class="card p-0">
                <div class="flex flex-wrap items-center justify-between gap-2 border-b border-line/70 px-5 py-4">
                    <div>
                        <h2 class="section-title">Kelompok · {{ $event->name }}</h2>
                        <p class="mt-0.5 text-xs text-faint">{{ $groups->count() }} kelompok</p>
                    </div>
                </div>

                @if ($groups->isEmpty())
                    <div class="p-5">
                        <x-ui.empty-state icon="users" title="Belum ada kelompok" description="Tambahkan kelompok pertama pada formulir di samping." />
                    </div>
                @else
                    <div class="divide-y divide-line/70">
                        @foreach ($groups as $group)
                            <div class="flex flex-wrap items-center gap-3 px-5 py-4">
                                <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-blue-soft text-ink">
                                    <x-ui.icon name="users" class="h-4 w-4" />
                                </span>
                                <div class="min-w-0 flex-1">
                                    <p class="truncate text-sm font-semibold text-ink">{{ $group->name }}</p>
                                    <p class="text-xs text-faint">{{ $group->guests_count }} tamu</p>
                                    @if ($group->description)
                                        <p class="mt-1 text-xs text-muted">{{ $group->description }}</p>
                                    @endif
                                </div>
                                <details class="w-full sm:w-auto">
                                    <summary class="btn btn-ghost btn-sm cursor-pointer list-none">Edit</summary>
                                    <form method="POST" action="{{ route('admin.groups.update', $group) }}" class="mt-3 grid gap-3 rounded-2xl bg-surface-2/50 p-4 sm:grid-cols-2">
                                        @csrf
                                        @method('PUT')
                                        <input type="hidden" name="event_id" value="{{ $event->id }}">
                                        <div>
                                            <label for="group-name-{{ $group->id }}" class="label">Nama</label>
                                            <input id="group-name-{{ $group->id }}" name="name" type="text" class="input" value="{{ $group->name }}" required>
                                        </div>
                                        <div>
                                            <label for="group-description-{{ $group->id }}" class="label">Deskripsi</label>
                                            <input id="group-description-{{ $group->id }}" name="description" type="text" class="input" value="{{ $group->description }}">
                                        </div>
                                        <div class="flex items-center gap-2 sm:col-span-2">
                                            <button type="submit" class="btn btn-primary btn-sm">Simpan</button>
                                        </div>
                                    </form>
                                </details>
                                <form method="POST" action="{{ route('admin.groups.destroy', $group) }}" data-confirm="Hapus kelompok {{ $group->name }}? Tamu terkait tidak dihapus." data-confirm-title="Hapus Kelompok">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="icon-btn h-8 w-8 text-danger" title="Hapus" aria-label="Hapus">
                                        <x-ui.icon name="trash" class="h-3.5 w-3.5" />
                                    </button>
                                </form>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>

            <aside class="card h-fit p-6">
                <h2 class="section-title">Tambah kelompok</h2>
                <form method="POST" action="{{ route('admin.groups.store') }}" class="mt-4 space-y-4">
                    @csrf
                    <input type="hidden" name="event_id" value="{{ $event->id }}">
                    <div>
                        <label for="group-name" class="label">Nama <span class="text-danger">*</span></label>
                        <input id="group-name" name="name" type="text" class="input" value="{{ old('name') }}" required placeholder="Contoh: Keluarga Besar" aria-invalid="true" aria-describedby="group-name-error">
                        @error('name')<p id="group-name-error" class="error-text">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label for="group-description" class="label">Deskripsi</label>
                        <input id="group-description" name="description" type="text" class="input" value="{{ old('description') }}" placeholder="Opsional">
                    </div>
                    <button type="submit" class="btn btn-primary w-full">
                        <x-ui.icon name="plus" class="h-4 w-4" /> Tambah kelompok
                    </button>
                </form>
            </aside>
        </div>
    @endif
@endsection
