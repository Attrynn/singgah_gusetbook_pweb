@extends('layouts.owner')

@section('title', 'Venue & Layout - '.$event->name)

@php
    use App\Enums\VenueStatus;

    $spaceTypeOptions = collect($spaceTypes)->mapWithKeys(fn ($type) => [$type->value => $type->label()])->all();
@endphp

@section('content')
    <x-ui.page-header
        title="Venue & Layout"
        eyebrow="Venue"
        :subtitle="'Kelola venue dan ruang untuk '.$event->name.'.'"
    >
        <x-ui.badge variant="info">{{ $venues->count() }} venue</x-ui.badge>
        <x-ui.button variant="secondary" :href="route('workspace.events.show', [$organization, $event])" icon="arrow-left">Ringkasan</x-ui.button>
    </x-ui.page-header>

    @include('workspace.events.partials.event-tabs', ['organization' => $organization, 'event' => $event, 'active' => 'venues'])

    <div class="mt-6 flex items-start gap-3 rounded-2xl border border-line/70 bg-blue-soft/50 p-4 text-sm text-ink">
        <x-ui.icon name="info" class="mt-0.5 h-4 w-4 shrink-0 text-accent" />
        <p>
            Halaman ini mengatur venue dan ruang untuk acara ini. Venue atau ruang yang masih dipakai floor plan tidak dapat diarsipkan.
        </p>
    </div>

    <div class="mt-6 grid gap-5 lg:grid-cols-3">
        {{-- Tambah venue --}}
        @if ($canManage)
            <div class="card p-5 lg:col-span-1">
                <div class="flex items-center gap-3">
                    <span class="flex h-10 w-10 items-center justify-center rounded-2xl bg-blue-soft text-ink">
                        <x-ui.icon name="pin" class="h-5 w-5" />
                    </span>
                    <div>
                        <h2 class="section-title">Tambah venue</h2>
                        <p class="mt-0.5 text-xs text-faint">Informasi lokasi untuk acara.</p>
                    </div>
                </div>

                <form method="POST" action="{{ route('workspace.events.venues.store', [$organization, $event]) }}" class="mt-5 space-y-4">
                    @csrf
                    <x-ui.input name="name" label="Nama venue" required placeholder="Contoh: Ballroom Utama" />
                    <x-ui.textarea name="description" label="Deskripsi" rows="2" placeholder="Deskripsi venue (opsional)" />
                    <x-ui.input name="address" label="Alamat" placeholder="Jl. Contoh No. 123" />
                    <x-ui.input name="google_maps_url" label="URL Google Maps" placeholder="https://maps.google.com/?q=..." />
                    <div class="grid grid-cols-2 gap-3">
                        <x-ui.input name="latitude" label="Latitude" type="number" step="any" placeholder="-6.1754" />
                        <x-ui.input name="longitude" label="Longitude" type="number" step="any" placeholder="106.8896" />
                    </div>
                    <x-ui.input name="timezone" label="Zona waktu" placeholder="Asia/Jakarta" />

                    <button type="submit" class="btn btn-primary w-full justify-center">
                        <x-ui.icon name="plus" class="h-4 w-4" /> Tambah venue
                    </button>
                </form>
            </div>
        @endif

        {{-- Daftar venue --}}
        <div class="{{ $canManage ? 'lg:col-span-2' : 'lg:col-span-3' }}">
            @if ($venues->isEmpty())
                <x-ui.empty-state
                    icon="pin"
                    title="Belum ada venue"
                    description="Tambahkan venue pertama untuk mengatur lokasi dan ruang acara."
                >
                    @if ($canManage)
                        <a href="#venue-form" class="btn btn-primary">
                            <x-ui.icon name="plus" class="h-4 w-4" /> Tambah venue pertama
                        </a>
                    @endif
                </x-ui.empty-state>
            @else
                <div class="space-y-6">
                    @foreach ($venues as $venue)
                        <div class="card p-5">
                            <div class="flex flex-wrap items-start justify-between gap-3">
                                <div class="min-w-0 flex-1">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <h3 class="text-lg font-semibold text-ink">{{ $venue->name }}</h3>
                                        <x-ui.badge :variant="$venue->status === VenueStatus::Archived ? 'muted' : 'success'" class="text-[10px]">{{ $venue->status->label() }}</x-ui.badge>
                                    </div>

                                    @if ($venue->address)
                                        <p class="mt-1 text-sm text-muted">
                                            <x-ui.icon name="pin" class="mr-1 inline h-3.5 w-3.5 text-faint" />
                                            {{ $venue->address }}
                                        </p>
                                    @endif

                                    @if ($venue->google_maps_url)
                                        <p class="mt-1 text-sm">
                                            <a href="{{ $venue->google_maps_url }}" target="_blank" rel="noopener" class="inline-flex items-center gap-1 text-accent hover:underline">
                                                <x-ui.icon name="external-link" class="h-3.5 w-3.5" />
                                                Lihat di Google Maps
                                            </a>
                                        </p>
                                    @endif

                                    @if ($venue->description)
                                        <p class="mt-2 text-sm text-ink">{{ $venue->description }}</p>
                                    @endif
                                </div>

                                @if ($canManage && $venue->status !== VenueStatus::Archived)
                                    <div class="flex items-center gap-1.5">
                                        <button type="button" class="btn btn-ghost btn-sm" x-data x-on:click="$dispatch('open-modal-edit-venue-{{ $venue->id }}')">
                                            <x-ui.icon name="edit" class="h-3.5 w-3.5" />
                                            <span>Edit</span>
                                        </button>
                                        <form method="POST"
                                              action="{{ route('workspace.events.venues.archive', [$organization, $event, $venue]) }}"
                                              data-confirm="Arsipkan venue '{{ $venue->name }}'? Venue tidak akan tampil di daftar aktif."
                                              data-confirm-button="Ya, arsipkan">
                                            @csrf
                                            <button type="submit" class="btn btn-danger btn-sm" aria-label="Arsipkan venue">
                                                <x-ui.icon name="archive" class="h-3.5 w-3.5" />
                                            </button>
                                        </form>
                                    </div>
                                @endif
                            </div>

                            {{-- Spaces table --}}
                            <div class="mt-5 border-t border-line/60 pt-5">
                                <div class="flex items-center justify-between gap-3">
                                    <h4 class="text-sm font-semibold text-ink">Ruang</h4>
                                    <span class="text-xs text-faint">{{ $venue->spaces->count() }} ruang</span>
                                </div>

                                @if ($venue->spaces->isNotEmpty())
                                    <div class="table-wrap mt-3">
                                        <table class="data-table">
                                            <thead>
                                                <tr>
                                                    <th>Nama</th>
                                                    <th>Tipe</th>
                                                    <th>Lantai</th>
                                                    <th>Kapasitas</th>
                                                    <th>Status</th>
                                                    @if ($canManage)
                                                        <th class="text-right">Aksi</th>
                                                    @endif
                                                </tr>
                                            </thead>
                                            <tbody>
                                                @foreach ($venue->spaces as $space)
                                                    <tr>
                                                        <td class="font-medium text-ink">{{ $space->name }}</td>
                                                        <td>
                                                            <x-ui.badge variant="info" class="text-[10px]">{{ $space->type->label() }}</x-ui.badge>
                                                        </td>
                                                        <td>{{ $space->floor_label ?: '—' }}</td>
                                                        <td>{{ $space->capacity !== null ? $space->capacity : '—' }}</td>
                                                        <td>
                                                            <x-ui.badge :variant="$space->status === VenueStatus::Archived ? 'muted' : 'success'" class="text-[10px]">{{ $space->status->label() }}</x-ui.badge>
                                                        </td>
                                                        @if ($canManage)
                                                            <td>
                                                                <div class="flex items-center justify-end gap-1.5">
                                                                    <button type="button" class="btn btn-ghost btn-sm" x-data x-on:click="$dispatch('open-modal-edit-space-{{ $venue->id }}-{{ $space->id }}')" aria-label="Edit ruang">
                                                                        <x-ui.icon name="edit" class="h-3.5 w-3.5" />
                                                                    </button>
                                                                    <form method="POST"
                                                                          action="{{ route('workspace.events.venues.spaces.archive', [$organization, $event, $venue, $space]) }}"
                                                                          data-confirm="Arsipkan ruang '{{ $space->name }}'?"
                                                                          data-confirm-button="Ya, arsipkan">
                                                                        @csrf
                                                                        <button type="submit" class="btn btn-danger btn-sm" aria-label="Arsipkan ruang">
                                                                            <x-ui.icon name="archive" class="h-3.5 w-3.5" />
                                                                        </button>
                                                                    </form>
                                                                </div>
                                                            </td>
                                                        @endif
                                                    </tr>
                                                @endforeach
                                            </tbody>
                                        </table>
                                    </div>
                                @else
                                    <p class="mt-3 text-xs text-faint">Belum ada ruang pada venue ini.</p>
                                @endif

                                {{-- Add space form per venue --}}
                                @if ($canManage && $venue->status !== VenueStatus::Archived)
                                    <form method="POST" action="{{ route('workspace.events.venues.spaces.store', [$organization, $event, $venue]) }}" class="mt-4 grid gap-3 border-t border-line/60 pt-4 sm:grid-cols-2 lg:grid-cols-4">
                                        @csrf
                                        <x-ui.input name="name" label="Nama ruang" required placeholder="Contoh: Ruang A" />
                                        <x-ui.select name="type" label="Tipe" :options="$spaceTypeOptions" placeholder="Pilih tipe" />
                                        <x-ui.input name="floor_label" label="Label lantai" placeholder="L1" />
                                        <x-ui.input name="capacity" label="Kapasitas" type="number" min="0" placeholder="0" />
                                        <div class="sm:col-span-2 lg:col-span-4">
                                            <button type="submit" class="btn btn-secondary btn-sm">
                                                <x-ui.icon name="plus" class="h-3.5 w-3.5" /> Tambah ruang
                                            </button>
                                        </div>
                                    </form>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    </div>

    {{-- Edit venue modals --}}
    @if ($canManage)
        @foreach ($venues as $venue)
            <x-ui.modal name="edit-venue-{{ $venue->id }}" title="Edit venue" description="Perbarui informasi venue.">
                <form method="POST" action="{{ route('workspace.events.venues.update', [$organization, $event, $venue]) }}" class="space-y-4">
                    @csrf
                    @method('PUT')
                    <x-ui.input name="name" label="Nama venue" required :value="$venue->name" />
                    <x-ui.textarea name="description" label="Deskripsi" rows="3" :value="$venue->description" />
                    <x-ui.input name="address" label="Alamat" :value="$venue->address" />
                    <x-ui.input name="google_maps_url" label="URL Google Maps" :value="$venue->google_maps_url" />
                    <div class="grid grid-cols-2 gap-3">
                        <x-ui.input name="latitude" label="Latitude" type="number" step="any" :value="$venue->latitude" />
                        <x-ui.input name="longitude" label="Longitude" type="number" step="any" :value="$venue->longitude" />
                    </div>
                    <x-ui.input name="timezone" label="Zona waktu" :value="$venue->timezone" />

                    <div class="flex items-center justify-end gap-2">
                        <button type="button" class="btn btn-ghost" x-data x-on:click="$dispatch('close-modal-edit-venue-{{ $venue->id }}')">Batal</button>
                        <button type="submit" class="btn btn-primary">
                            <x-ui.icon name="save" class="h-4 w-4" /> Simpan perubahan
                        </button>
                    </div>
                </form>
            </x-ui.modal>
        @endforeach
    @endif

    {{-- Edit space modals --}}
    @if ($canManage)
        @foreach ($venues as $venue)
            @foreach ($venue->spaces as $space)
                <x-ui.modal name="edit-space-{{ $venue->id }}-{{ $space->id }}" title="Edit ruang" description="Perbarui informasi ruang.">
                    <form method="POST" action="{{ route('workspace.events.venues.spaces.update', [$organization, $event, $venue, $space]) }}" class="space-y-4">
                        @csrf
                        @method('PUT')
                        <x-ui.input name="name" label="Nama ruang" required :value="$space->name" />
                        <x-ui.select name="type" label="Tipe" :options="$spaceTypeOptions" :value="$space->type->value" />
                        <x-ui.input name="floor_label" label="Label lantai" :value="$space->floor_label" />
                        <x-ui.input name="capacity" label="Kapasitas" type="number" min="0" :value="$space->capacity" />
                        <x-ui.textarea name="description" label="Deskripsi" rows="3" :value="$space->description" />

                        <div class="flex items-center justify-end gap-2">
                            <button type="button" class="btn btn-ghost" x-data x-on:click="$dispatch('close-modal-edit-space-{{ $venue->id }}-{{ $space->id }}')">Batal</button>
                            <button type="submit" class="btn btn-primary">
                                <x-ui.icon name="save" class="h-4 w-4" /> Simpan perubahan
                            </button>
                        </div>
                    </form>
                </x-ui.modal>
            @endforeach
        @endforeach
    @endif
@endsection
