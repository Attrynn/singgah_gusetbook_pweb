@extends('layouts.owner')

@section('title', 'Buat Acara')

@php
    $playbooks = collect($playbooks ?? []);
    $sourceEvents = collect($events ?? []);

    $playbookOptions = $playbooks
        ->map(fn ($playbook) => [
            'version_id' => $playbook->latestVersion?->id,
            'name' => $playbook->name,
            'version' => $playbook->latestVersion?->version,
            'platform' => $playbook->isPlatform(),
            'event_type' => $playbook->event_type,
        ])
        ->filter(fn ($option) => $option['version_id'] !== null)
        ->values();

    $platformPlaybooks = $playbookOptions->where('platform', true);
    $organizationPlaybooks = $playbookOptions->where('platform', false);

    $timezones = [
        'Asia/Jakarta' => 'WIB · Asia/Jakarta',
        'Asia/Makassar' => 'WITA · Asia/Makassar',
        'Asia/Jayapura' => 'WIT · Asia/Jayapura',
        'Asia/Singapore' => 'Asia/Singapore',
        'Asia/Kuala_Lumpur' => 'Asia/Kuala_Lumpur',
        'Asia/Bangkok' => 'Asia/Bangkok',
        'UTC' => 'UTC',
    ];

    $initialMode = old('duplicate_source_event_id') ? 'duplicate' : (old('playbook_version_id') ? 'playbook' : 'blank');
@endphp

@section('content')
    <x-ui.page-header
        title="Buat acara baru"
        eyebrow="Portofolio Acara"
        subtitle="Isi identitas dan jadwal acara. Acara baru selalu tersimpan sebagai Draft dan tidak langsung tampil ke tamu."
    >
        <x-ui.button variant="secondary" :href="route('workspace.events.index', $organization)" icon="arrow-left">Kembali</x-ui.button>
    </x-ui.page-header>

    <form method="POST" action="{{ route('workspace.events.store', $organization) }}" class="mt-6 grid gap-5 lg:grid-cols-3">
        @csrf

        {{-- Identitas & jadwal --}}
        <div class="card p-5 lg:col-span-2">
            <h2 class="section-title">Identitas & jadwal</h2>
            <p class="mt-1 text-xs text-faint">Data ini dapat disempurnakan nanti melalui wizard Persiapan.</p>

            <div class="mt-5 grid gap-4 sm:grid-cols-2">
                <div class="sm:col-span-2">
                    <x-ui.input name="name" label="Nama acara" :value="old('name')" required placeholder="Contoh: Pernikahan Raka & Sinta" />
                </div>
                <x-ui.input name="groom_name" label="Nama mempelai pria" :value="old('groom_name')" placeholder="Nama lengkap" />
                <x-ui.input name="bride_name" label="Nama mempelai wanita" :value="old('bride_name')" placeholder="Nama lengkap" />
                <x-ui.input name="event_date" label="Tanggal acara" :value="old('event_date')" required placeholder="Pilih tanggal" data-datepicker readonly />
                <div>
                    <label for="timezone" class="label">Zona waktu <span class="text-danger" aria-hidden="true">*</span><span class="sr-only">(wajib)</span></label>
                    <select id="timezone" name="timezone" class="input appearance-none" required>
                        @foreach ($timezones as $value => $label)
                            <option value="{{ $value }}" @selected(old('timezone', 'Asia/Jakarta') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                    @error('timezone') <p class="error-text">{{ $message }}</p> @enderror
                </div>
                <x-ui.input name="start_time" label="Waktu mulai" type="time" :value="old('start_time')" />
                <x-ui.input name="end_time" label="Waktu selesai" type="time" :value="old('end_time')" />
                <div class="sm:col-span-2">
                    <x-ui.input name="venue_name" label="Nama venue" :value="old('venue_name')" required placeholder="Contoh: Gedung Serbaguna Melati" />
                </div>
                <div class="sm:col-span-2">
                    <x-ui.input name="venue_address" label="Alamat venue" :value="old('venue_address')" required placeholder="Jalan, kota, provinsi" />
                </div>
                <div class="sm:col-span-2">
                    <x-ui.input name="google_maps_url" label="Tautan Google Maps" type="url" :value="old('google_maps_url')" placeholder="https://maps.google.com/…" />
                </div>
                <div class="sm:col-span-2">
                    <x-ui.textarea name="description" label="Catatan acara" :value="old('description')" rows="3" placeholder="Deskripsi singkat acara (opsional)" />
                </div>
            </div>
        </div>

        {{-- Titik mulai --}}
        <div class="space-y-5">
            <div class="card p-5" x-data="{ mode: @js($initialMode) }">
                <h2 class="section-title">Titik mulai</h2>
                <p class="mt-1 text-xs text-faint">Pilih cara menyusun acara ini.</p>

                <div class="mt-4 space-y-2">
                    <label class="flex cursor-pointer items-start gap-3 rounded-xl border border-line/70 p-3 transition" :class="mode === 'blank' ? 'border-accent/60 bg-accent/5' : 'hover:bg-surface-2/40'">
                        <input type="radio" name="_start_mode" value="blank" class="mt-0.5" x-model="mode">
                        <span>
                            <span class="block text-sm font-medium text-ink">Mulai dari kosong</span>
                            <span class="mt-0.5 block text-[11px] text-faint">Susun langkah demi langkah di wizard.</span>
                        </span>
                    </label>

                    <label class="flex cursor-pointer items-start gap-3 rounded-xl border border-line/70 p-3 transition"
                           :class="mode === 'playbook' ? 'border-accent/60 bg-accent/5' : 'hover:bg-surface-2/40'"
                           @if ($playbookOptions->isEmpty()) aria-disabled="true" @endif>
                        <input type="radio" name="_start_mode" value="playbook" class="mt-0.5" x-model="mode" @if ($playbookOptions->isEmpty()) disabled @endif>
                        <span>
                            <span class="block text-sm font-medium text-ink">Gunakan playbook</span>
                            <span class="mt-0.5 block text-[11px] text-faint">Isi struktur acara dari template yang sudah ada.</span>
                        </span>
                    </label>

                    <label class="flex cursor-pointer items-start gap-3 rounded-xl border border-line/70 p-3 transition"
                           :class="mode === 'duplicate' ? 'border-accent/60 bg-accent/5' : 'hover:bg-surface-2/40'"
                           @if ($sourceEvents->isEmpty()) aria-disabled="true" @endif>
                        <input type="radio" name="_start_mode" value="duplicate" class="mt-0.5" x-model="mode" @if ($sourceEvents->isEmpty()) disabled @endif>
                        <span>
                            <span class="block text-sm font-medium text-ink">Duplikat acara</span>
                            <span class="mt-0.5 block text-[11px] text-faint">Salin struktur dari acara lain di workspace ini.</span>
                        </span>
                    </label>
                </div>

                <div class="mt-4" x-show="mode === 'playbook'" x-cloak>
                    <label for="playbook_version_id" class="label">Pilih playbook</label>
                    <select id="playbook_version_id" name="playbook_version_id" class="input appearance-none" x-bind:disabled="mode !== 'playbook'">
                        <option value="">Pilih playbook</option>
                        @if ($platformPlaybooks->isNotEmpty())
                            <optgroup label="Template platform">
                                @foreach ($platformPlaybooks as $option)
                                    <option value="{{ $option['version_id'] }}" @selected((string) old('playbook_version_id') === (string) $option['version_id'])>
                                        {{ $option['name'] }} · v{{ $option['version'] }}
                                    </option>
                                @endforeach
                            </optgroup>
                        @endif
                        @if ($organizationPlaybooks->isNotEmpty())
                            <optgroup label="Playbook workspace">
                                @foreach ($organizationPlaybooks as $option)
                                    <option value="{{ $option['version_id'] }}" @selected((string) old('playbook_version_id') === (string) $option['version_id'])>
                                        {{ $option['name'] }} · v{{ $option['version'] }}
                                    </option>
                                @endforeach
                            </optgroup>
                        @endif
                    </select>
                    @error('playbook_version_id') <p class="error-text">{{ $message }}</p> @enderror
                    <p class="hint">Struktur playbook akan diterapkan setelah acara dibuat.</p>
                </div>

                <div class="mt-4" x-show="mode === 'duplicate'" x-cloak>
                    <label for="duplicate_source_event_id" class="label">Pilih acara sumber</label>
                    <select id="duplicate_source_event_id" name="duplicate_source_event_id" class="input appearance-none" x-bind:disabled="mode !== 'duplicate'">
                        <option value="">Pilih acara</option>
                        @foreach ($sourceEvents as $source)
                            <option value="{{ $source->id }}" @selected((string) old('duplicate_source_event_id') === (string) $source->id)>
                                {{ $source->name }} · {{ $source->coupleNames() }}
                            </option>
                        @endforeach
                    </select>
                    @error('duplicate_source_event_id') <p class="error-text">{{ $message }}</p> @enderror
                    <p class="hint">Hanya struktur acara yang disalin. Data tamu, undangan, dan tim tidak ikut dipindahkan.</p>
                </div>
            </div>

            <div class="card p-5">
                <div class="flex items-start gap-3 rounded-xl bg-blue-soft/60 p-3 text-xs text-ink">
                    <x-ui.icon name="info" class="mt-0.5 h-4 w-4 shrink-0 text-accent" />
                    <p>Acara baru berstatus <strong>Draft</strong>. Publikasikan dari halaman ringkasan setelah persiapan selesai.</p>
                </div>

                <div class="mt-4 flex flex-col gap-2">
                    <button type="submit" class="btn btn-primary w-full justify-center">
                        <x-ui.icon name="plus" class="h-4 w-4" /> Buat acara
                    </button>
                    <a href="{{ route('workspace.events.index', $organization) }}" class="btn btn-ghost w-full justify-center">Batal</a>
                </div>
            </div>
        </div>
    </form>
@endsection