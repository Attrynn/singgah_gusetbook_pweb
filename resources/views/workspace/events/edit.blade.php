@extends('layouts.owner')

@section('title', 'Edit '.$event->name)

@php
    use App\Enums\EventStatus;

    $startTime = old('start_time', $event->start_time ? \Illuminate\Support\Str::of($event->start_time)->substr(0, 5) : null);
    $endTime = old('end_time', $event->end_time ? \Illuminate\Support\Str::of($event->end_time)->substr(0, 5) : null);

    $timezones = [
        'Asia/Jakarta' => 'WIB · Asia/Jakarta',
        'Asia/Makassar' => 'WITA · Asia/Makassar',
        'Asia/Jayapura' => 'WIT · Asia/Jayapura',
        'Asia/Singapore' => 'Asia/Singapore',
        'Asia/Kuala_Lumpur' => 'Asia/Kuala_Lumpur',
        'Asia/Bangkok' => 'Asia/Bangkok',
        'UTC' => 'UTC',
    ];

    $appliedPlaybook = $event->setupConfig?->playbook;
@endphp

@section('content')
    <x-ui.page-header
        :title="'Edit '.$event->name"
        eyebrow="Edit Acara"
        subtitle="Perbarui identitas, jadwal, dan venue acara. Perubahan status siklus hidup dilakukan dari halaman ringkasan acara."
    >
        <x-ui.button variant="secondary" :href="route('workspace.events.show', [$organization, $event])" icon="arrow-left">Ringkasan</x-ui.button>
    </x-ui.page-header>

    <form method="POST" action="{{ route('workspace.events.update', [$organization, $event]) }}" class="mt-6 grid gap-5 lg:grid-cols-3">
        @csrf
        @method('PUT')

        {{-- Identitas & jadwal --}}
        <div class="card p-5 lg:col-span-2">
            <h2 class="section-title">Identitas & jadwal</h2>
            <p class="mt-1 text-xs text-faint">Struktur dan playbook dapat diubah melalui wizard Persiapan.</p>

            <div class="mt-5 grid gap-4 sm:grid-cols-2">
                <div class="sm:col-span-2">
                    <x-ui.input name="name" label="Nama acara" :value="old('name', $event->name)" required placeholder="Contoh: Pernikahan Raka & Sinta" />
                </div>
                <div class="sm:col-span-2">
                    <x-ui.input name="slug" label="Slug tautan" :value="old('slug', $event->slug)" hint="Digunakan pada tautan undangan. Gunakan huruf, angka, dan tanda hubung." placeholder="pernikahan-raka-sinta" />
                </div>
                <x-ui.input name="groom_name" label="Nama mempelai pria" :value="old('groom_name', $event->groom_name)" placeholder="Nama lengkap" />
                <x-ui.input name="bride_name" label="Nama mempelai wanita" :value="old('bride_name', $event->bride_name)" placeholder="Nama lengkap" />
                <x-ui.input name="event_date" label="Tanggal acara" :value="old('event_date', $event->event_date?->format('Y-m-d'))" required placeholder="Pilih tanggal" data-datepicker readonly />
                <div>
                    <label for="timezone" class="label">Zona waktu <span class="text-danger" aria-hidden="true">*</span><span class="sr-only">(wajib)</span></label>
                    <select id="timezone" name="timezone" class="input appearance-none" required>
                        @foreach ($timezones as $value => $label)
                            <option value="{{ $value }}" @selected(old('timezone', $event->timezone ?: 'Asia/Jakarta') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                    @error('timezone') <p class="error-text">{{ $message }}</p> @enderror
                </div>
                <x-ui.input name="start_time" label="Waktu mulai" type="time" :value="$startTime" />
                <x-ui.input name="end_time" label="Waktu selesai" type="time" :value="$endTime" hint="Harus setelah waktu mulai." />
                <div class="sm:col-span-2">
                    <x-ui.input name="venue_name" label="Nama venue" :value="old('venue_name', $event->venue_name)" required placeholder="Contoh: Gedung Serbaguna Melati" />
                </div>
                <div class="sm:col-span-2">
                    <x-ui.input name="venue_address" label="Alamat venue" :value="old('venue_address', $event->venue_address)" required placeholder="Jalan, kota, provinsi" />
                </div>
                <div class="sm:col-span-2">
                    <x-ui.input name="google_maps_url" label="Tautan Google Maps" type="url" :value="old('google_maps_url', $event->google_maps_url)" placeholder="https://maps.google.com/…" />
                </div>
                <div class="sm:col-span-2">
                    <x-ui.textarea name="description" label="Catatan acara" :value="old('description', $event->description)" rows="3" placeholder="Deskripsi singkat acara (opsional)" />
                </div>
            </div>
        </div>

        {{-- Kolom samping --}}
        <div class="space-y-5">
            {{-- Catatan status siklus hidup --}}
            <div class="card p-5">
                <div class="flex items-center justify-between gap-3">
                    <h2 class="section-title">Status acara</h2>
                    <x-ui.badge :variant="$event->status->badge()">{{ $event->status->stageLabel() }}</x-ui.badge>
                </div>
                <div class="mt-3 flex items-start gap-3 rounded-xl bg-blue-soft/60 p-3 text-xs text-ink">
                    <x-ui.icon name="info" class="mt-0.5 h-4 w-4 shrink-0 text-accent" />
                    <p>Status siklus hidup (Draft, Publikasi, Berlangsung, Selesai, Arsip) diubah dari halaman ringkasan acara, bukan dari formulir ini.</p>
                </div>
                <a href="{{ route('workspace.events.show', [$organization, $event]) }}" class="btn btn-secondary btn-sm mt-3 w-full justify-center">
                    <x-ui.icon name="eye" class="h-3.5 w-3.5" /> Kelola status di ringkasan
                </a>
            </div>

            {{-- Pemilik acara --}}
            <div class="card p-5">
                <h2 class="section-title">Pemilik acara</h2>
                @if ($event->owners->isNotEmpty())
                    <ul class="mt-3 space-y-2">
                        @foreach ($event->owners as $owner)
                            <li class="flex items-center gap-3">
                                <x-ui.avatar :name="$owner->name" size="sm" />
                                <div class="min-w-0 flex-1">
                                    <p class="truncate text-sm font-medium text-ink">{{ $owner->name }}</p>
                                    <p class="truncate text-[11px] text-faint">{{ $owner->email }}</p>
                                </div>
                                <x-ui.badge variant="muted" class="text-[10px]">{{ $owner->pivot->role_label ?? 'Pasangan' }}</x-ui.badge>
                            </li>
                        @endforeach
                    </ul>
                @else
                    <p class="mt-3 text-xs text-faint">Belum ada pemilik terhubung ke acara ini.</p>
                @endif
            </div>

            {{-- Playbook diterapkan --}}
            <div class="card p-5">
                <h2 class="section-title">Playbook</h2>
                @if ($appliedPlaybook)
                    <p class="mt-3 text-sm font-medium text-ink">{{ $appliedPlaybook->name }}</p>
                    <p class="mt-0.5 text-[11px] text-faint">Versi diterapkan: v{{ $event->setupConfig?->playbookVersion?->version ?? '—' }}</p>
                @else
                    <p class="mt-3 text-xs text-faint">Belum ada playbook diterapkan pada acara ini.</p>
                @endif
                @can('manageSetup', $event)
                    <a href="{{ route('workspace.events.setup', [$organization, $event]) }}" class="btn btn-ghost btn-sm mt-3 w-full justify-center">
                        <x-ui.icon name="sliders" class="h-3.5 w-3.5" /> Ubah di Persiapan
                    </a>
                @endcan
            </div>

            {{-- Aksi --}}
            <div class="card p-5">
                <div class="flex flex-col gap-2">
                    <button type="submit" class="btn btn-primary w-full justify-center">
                        <x-ui.icon name="save" class="h-4 w-4" /> Simpan perubahan
                    </button>
                    <a href="{{ route('workspace.events.show', [$organization, $event]) }}" class="btn btn-ghost w-full justify-center">Batal</a>
                </div>
            </div>
        </div>
    </form>
@endsection
