@extends('layouts.owner')

@section('title', 'Persiapan Acara')

@php
    use App\Enums\SetupStatus;

    $step = max(1, min(8, (int) ($step ?? 1)));
    $totalSteps = 8;

    $stepsMeta = [
        1 => ['label' => 'Blueprint', 'icon' => 'file-text'],
        2 => ['label' => 'Identitas', 'icon' => 'heart'],
        3 => ['label' => 'Tanggal & waktu', 'icon' => 'calendar'],
        4 => ['label' => 'Struktur & sesi', 'icon' => 'list'],
        5 => ['label' => 'Venue', 'icon' => 'map-pin'],
        6 => ['label' => 'Pratinjau operasional', 'icon' => 'eye'],
        7 => ['label' => 'Komposisi tim', 'icon' => 'users'],
        8 => ['label' => 'Tinjau & simpan', 'icon' => 'check'],
    ];

    $readiness = $readiness ?? null;
    $setupStatus = $setup?->setup_status ?? SetupStatus::NotStarted;

    $playbooks = collect($playbooks ?? []);
    $playbookOptions = $playbooks
        ->map(fn ($playbook) => [
            'version_id' => $playbook->latestVersion?->id,
            'name' => $playbook->name,
            'version' => $playbook->latestVersion?->version,
            'platform' => $playbook->isPlatform(),
            'event_type' => $playbook->event_type,
            'days' => $playbook->days,
            'description' => $playbook->description,
        ])
        ->filter(fn ($option) => $option['version_id'] !== null)
        ->values();

    $appliedVersionId = old('playbook_version_id', $setup?->playbook_version_id);

    $phaseCollection = collect($phases ?? $event->phases ?? []);
    $sessionCollection = collect($sessions ?? $event->sessions ?? []);
    $venueCollection = collect($venues ?? $event->venues ?? []);
    $teamMemberships = collect($teamMemberships ?? []);
    $candidateMembers = collect($candidateMembers ?? []);
    $checklistItems = collect($setup?->checklistItems ?? $event->checklistItems ?? []);

    $phaseRows = $phaseCollection->values()->map(fn ($phase, $index) => [
        'id' => $phase->id,
        'name' => $phase->name,
        'sequence' => $phase->sequence ?? $index,
    ])->all();

    $phaseIndexById = $phaseCollection->values()->mapWithKeys(fn ($phase, $index) => [$phase->id => $index])->all();

    $sessionRows = $sessionCollection->values()->map(fn ($session) => [
        'name' => $session->name,
        'session_date' => $session->session_date?->format('Y-m-d'),
        'start_time' => $session->start_time?->format('Y-m-d\TH:i'),
        'end_time' => $session->end_time?->format('Y-m-d\TH:i'),
        'phase_index' => $session->phase_id !== null ? (string) ($phaseIndexById[$session->phase_id] ?? '') : '',
    ])->all();

    $venueRows = $venueCollection->values()->map(fn ($venue) => [
        'name' => $venue->name,
        'address' => $venue->address,
        'is_primary' => (bool) $venue->is_primary,
    ])->all();
    $primaryVenueIndex = $venueCollection->values()->search(fn ($venue) => (bool) $venue->is_primary);
    $primaryVenueIndex = $primaryVenueIndex === false ? 0 : (int) $primaryVenueIndex;

    $timezones = [
        'Asia/Jakarta' => 'WIB · Asia/Jakarta',
        'Asia/Makassar' => 'WITA · Asia/Makassar',
        'Asia/Jayapura' => 'WIT · Asia/Jayapura',
        'Asia/Singapore' => 'Asia/Singapore',
        'Asia/Kuala_Lumpur' => 'Asia/Kuala_Lumpur',
        'Asia/Bangkok' => 'Asia/Bangkok',
        'UTC' => 'UTC',
    ];

    $staffRoleLabel = function ($role): string {
        return $role instanceof \App\Enums\EventStaffRole ? $role->label() : ucfirst(str_replace('_', ' ', (string) $role));
    };
@endphp

@section('content')
    <x-ui.page-header
        :title="'Persiapan: '.$event->name"
        eyebrow="Wizard Persiapan"
        :subtitle="$event->coupleNames().' · '.($event->event_date?->translatedFormat('d F Y') ?? 'Tanggal belum ditetapkan')"
    >
        <x-ui.badge :variant="$setupStatus->badge()">{{ $setupStatus->label() }}</x-ui.badge>
        <x-ui.button variant="secondary" :href="route('workspace.events.show', [$organization, $event])" icon="eye">Ringkasan</x-ui.button>
    </x-ui.page-header>

    @include('workspace.events.partials.event-tabs', ['organization' => $organization, 'event' => $event, 'setup' => $setup, 'active' => 'setup'])

    <div class="mt-6" x-data="{ step: {{ $step }}, total: {{ $totalSteps }} }">
        {{-- Indikator langkah --}}
        <div class="card p-4">
            <div class="flex items-center justify-between gap-3">
                <p class="section-kicker">Langkah <span x-text="step">{{ $step }}</span> dari {{ $totalSteps }}</p>
                <p class="text-xs text-faint">{{ $event->status === \App\Enums\EventStatus::Archived ? 'Acara diarsipkan' : 'Simpan draf kapan saja' }}</p>
            </div>

            <div class="mt-2 h-2 w-full overflow-hidden rounded-full bg-surface-2">
                <div class="h-full rounded-full bg-accent transition-all duration-300" :style="'width: ' + Math.round(step / total * 100) + '%'"></div>
            </div>

            <div class="mt-4 -mx-1 overflow-x-auto">
                <ol class="flex min-w-max items-center gap-1 px-1">
                    @foreach ($stepsMeta as $index => $meta)
                        <li>
                            <button type="button"
                                    class="inline-flex items-center gap-2 rounded-xl px-2.5 py-2 text-xs transition"
                                    :class="step === {{ $index }} ? 'bg-accent/10 font-semibold text-accent' : (step > {{ $index }} ? 'text-ink hover:bg-surface-2/60' : 'text-faint hover:bg-surface-2/60')"
                                    @click="step = {{ $index }}"
                                    :aria-current="step === {{ $index }} ? 'step' : null">
                                <span class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full border text-[10px] font-bold"
                                      :class="step > {{ $index }} ? 'border-success/40 bg-success/15 text-success' : (step === {{ $index }} ? 'border-accent/50 bg-accent/15 text-accent' : 'border-line text-faint')">
                                    <span x-show="step > {{ $index }}" x-cloak><x-ui.icon name="check" class="h-3 w-3" /></span>
                                    <span x-show="step <= {{ $index }}">{{ $index }}</span>
                                </span>
                                <span class="hidden sm:inline">{{ $meta['label'] }}</span>
                            </button>
                        </li>
                    @endforeach
                </ol>
            </div>
        </div>

        {{-- Formulir wizard (satu formulir, semua langkah tetap tersimpan) --}}
        <form method="POST"
              action="{{ route('workspace.events.setup.save', [$organization, $event]) }}"
              novalidate
              class="mt-5 space-y-5">
            @csrf
            @method('PUT')
            <input type="hidden" name="step" :value="step">

            {{-- 1. Blueprint --}}
            <section class="card p-5" x-show="step === 1" x-cloak x-data="{ blueprint: @js((string) ($appliedVersionId ?: '')) }">
                <h2 class="section-title">Pilih blueprint acara</h2>
                <p class="mt-1 text-xs text-faint">Blueprint mengisi struktur awal acara: fase, daftar periksa, dan kebutuhan tim.</p>

                <div class="mt-4 grid gap-3 sm:grid-cols-2">
                    <label class="cursor-pointer rounded-xl border p-4 transition"
                           :class="blueprint === '' ? 'border-accent/60 bg-accent/5' : 'border-line/70 hover:bg-surface-2/40'">
                        <input type="radio" name="playbook_version_id" value="" class="sr-only" x-model="blueprint">
                        <span class="flex items-center gap-2 text-sm font-medium text-ink">
                            <x-ui.icon name="file-text" class="h-4 w-4 text-accent" /> Mulai dari kosong
                        </span>
                        <span class="mt-1 block text-[11px] text-faint">Susun fase, sesi, dan daftar periksa secara manual.</span>
                    </label>

                    @foreach ($playbookOptions as $option)
                        <label class="cursor-pointer rounded-xl border p-4 transition"
                               :class="blueprint === '{{ $option['version_id'] }}' ? 'border-accent/60 bg-accent/5' : 'border-line/70 hover:bg-surface-2/40'">
                            <input type="radio" name="playbook_version_id" value="{{ $option['version_id'] }}" class="sr-only" x-model="blueprint">
                            <span class="flex items-center justify-between gap-2">
                                <span class="text-sm font-medium text-ink">{{ $option['name'] }}</span>
                                <x-ui.badge :variant="$option['platform'] ? 'info' : 'neutral'" class="text-[10px]">
                                    {{ $option['platform'] ? 'Platform' : 'Workspace' }}
                                </x-ui.badge>
                            </span>
                            <span class="mt-1 block text-[11px] text-faint">
                                v{{ $option['version'] }}
                                @if ($option['event_type']) · {{ ucfirst(str_replace('_', ' ', $option['event_type'])) }} @endif
                                @if ($option['days']) · {{ $option['days'] }} hari @endif
                            </span>
                            @if ($option['description'])
                                <span class="mt-1 block text-[11px] text-muted">{{ \Illuminate\Support\Str::limit($option['description'], 90) }}</span>
                            @endif
                        </label>
                    @endforeach
                </div>

                @if ($playbookOptions->isEmpty())
                    <p class="mt-3 text-xs text-faint">Belum ada playbook tersedia. Acara akan dimulai dari struktur kosong.</p>
                @endif
                @error('playbook_version_id') <p class="error-text">{{ $message }}</p> @enderror
            </section>

            {{-- 2. Identitas --}}
            <section class="card p-5" x-show="step === 2" x-cloak>
                <h2 class="section-title">Identitas acara</h2>
                <p class="mt-1 text-xs text-faint">Nama dan detail pasangan yang tampil di seluruh acara.</p>

                <div class="mt-4 grid gap-4 sm:grid-cols-2">
                    <div class="sm:col-span-2">
                        <x-ui.input name="name" label="Nama acara" :value="old('name', $event->name)" placeholder="Contoh: Pernikahan Raka & Sinta" />
                    </div>
                    <x-ui.input name="groom_name" label="Mempelai pria" :value="old('groom_name', $event->groom_name)" />
                    <x-ui.input name="bride_name" label="Mempelai wanita" :value="old('bride_name', $event->bride_name)" />
                    <div class="sm:col-span-2">
                        <x-ui.textarea name="description" label="Catatan acara" :value="old('description', $event->description)" rows="3" placeholder="Konteks singkat acara (opsional)" />
                    </div>
                </div>
            </section>

            {{-- 3. Tanggal & waktu --}}
            <section class="card p-5" x-show="step === 3" x-cloak>
                <h2 class="section-title">Tanggal, waktu & zona waktu</h2>
                <p class="mt-1 text-xs text-faint">Waktu ditampilkan mengikuti zona waktu acara.</p>

                <div class="mt-4 grid gap-4 sm:grid-cols-2">
                    <x-ui.input name="event_date" label="Tanggal acara" :value="old('event_date', $event->event_date?->format('Y-m-d'))" placeholder="Pilih tanggal" data-datepicker readonly />
                    <div>
                        <label for="timezone" class="label">Zona waktu</label>
                        <select id="timezone" name="timezone" class="input appearance-none">
                            @foreach ($timezones as $value => $label)
                                <option value="{{ $value }}" @selected(old('timezone', $event->timezone ?: 'Asia/Jakarta') === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                        @error('timezone') <p class="error-text">{{ $message }}</p> @enderror
                    </div>
                    <x-ui.input name="start_time" label="Waktu mulai" type="time" :value="old('start_time', $event->start_time)" />
                    <x-ui.input name="end_time" label="Waktu selesai" type="time" :value="old('end_time', $event->end_time)" />
                </div>
            </section>

            {{-- 4. Struktur & sesi --}}
            <section class="card p-5" x-show="step === 4" x-cloak"
                     x-data="{
                        phases: @js($phaseRows),
                        sessions: @js($sessionRows),
                        addPhase() { this.phases.push({ name: '', sequence: this.phases.length }); },
                        removePhase(index) { this.phases.splice(index, 1); },
                        addSession() { this.sessions.push({ name: '', session_date: '', start_time: '', end_time: '', phase_index: '' }); },
                        removeSession(index) { this.sessions.splice(index, 1); }
                     }">
                <div class="flex items-center justify-between gap-3">
                    <div>
                        <h2 class="section-title">Struktur & sesi</h2>
                        <p class="mt-1 text-xs text-faint">Pecah acara menjadi fase (mis. Akad, Resepsi) lalu tambahkan sesi per hari.</p>
                    </div>
                </div>

                {{-- Fase --}}
                <div class="mt-5">
                    <div class="flex items-center justify-between gap-2">
                        <p class="label mb-0">Fase acara</p>
                        <button type="button" class="btn btn-ghost btn-sm" @click="addPhase()">
                            <x-ui.icon name="plus" class="h-3.5 w-3.5" /> Tambah fase
                        </button>
                    </div>

                    <div class="mt-2 space-y-2">
                        <template x-for="(phase, index) in phases" :key="'phase-' + index">
                            <div class="flex items-center gap-2">
                                <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-surface-2 text-[11px] font-semibold text-muted" x-text="index + 1"></span>
                                <input type="text" class="input" :name="'phases[' + index + '][name]'" x-model="phase.name" placeholder="Nama fase" aria-label="Nama fase">
                                <input type="hidden" :name="'phases[' + index + '][sequence]'" :value="index">
                                <button type="button" class="icon-btn h-10 w-10 shrink-0 text-danger hover:border-danger/40" @click="removePhase(index)" aria-label="Hapus fase">
                                    <x-ui.icon name="trash" class="h-4 w-4" />
                                </button>
                            </div>
                        </template>
                    </div>
                    <p class="hint" x-show="phases.length === 0" x-cloak>Belum ada fase. Tambahkan minimal satu fase untuk struktur acara.</p>
                </div>

                {{-- Sesi --}}
                <div class="mt-6">
                    <div class="flex items-center justify-between gap-2">
                        <p class="label mb-0">Sesi</p>
                        <button type="button" class="btn btn-ghost btn-sm" @click="addSession()">
                            <x-ui.icon name="plus" class="h-3.5 w-3.5" /> Tambah sesi
                        </button>
                    </div>

                    <div class="mt-2 space-y-3">
                        <template x-for="(session, index) in sessions" :key="'session-' + index">
                            <div class="rounded-xl border border-line/70 bg-surface-2/30 p-3">
                                <div class="flex items-center justify-between gap-2">
                                    <p class="text-[11px] font-semibold uppercase tracking-[0.1em] text-faint" x-text="'Sesi ' + (index + 1)"></p>
                                    <button type="button" class="icon-btn h-8 w-8 text-danger hover:border-danger/40" @click="removeSession(index)" aria-label="Hapus sesi">
                                        <x-ui.icon name="trash" class="h-3.5 w-3.5" />
                                    </button>
                                </div>
                                <div class="mt-2 grid gap-2 sm:grid-cols-2">
                                    <input type="text" class="input sm:col-span-2" :name="'sessions[' + index + '][name]'" x-model="session.name" placeholder="Nama sesi" aria-label="Nama sesi">
                                    <input type="date" class="input" :name="'sessions[' + index + '][session_date]'" x-model="session.session_date" aria-label="Tanggal sesi">
                                    <select class="input appearance-none" :name="'sessions[' + index + '][phase_index]'" x-model="session.phase_index" aria-label="Fase sesi">
                                        <option value="">Tanpa fase</option>
                                        <template x-for="(phase, pi) in phases" :key="'opt-' + pi">
                                            <option :value="pi" x-text="phase.name || ('Fase ' + (pi + 1))"></option>
                                        </template>
                                    </select>
                                    <input type="datetime-local" class="input" :name="'sessions[' + index + '][start_time]'" x-model="session.start_time" aria-label="Mulai sesi">
                                    <input type="datetime-local" class="input" :name="'sessions[' + index + '][end_time]'" x-model="session.end_time" aria-label="Selesai sesi">
                                </div>
                            </div>
                        </template>
                    </div>
                    <p class="hint" x-show="sessions.length === 0" x-cloak>Belum ada sesi. Untuk acara satu hari, satu sesi biasanya cukup.</p>
                </div>
            </section>

            {{-- 5. Venue --}}
            <section class="card p-5" x-show="step === 5" x-cloak
                     x-data="{
                        venues: @js($venueRows),
                        primaryIndex: {{ $primaryVenueIndex }},
                        addVenue() { this.venues.push({ name: '', address: '', is_primary: false }); },
                        removeVenue(index) {
                            this.venues.splice(index, 1);
                            if (this.primaryIndex >= this.venues.length) { this.primaryIndex = 0; }
                        }
                     }">
                <div class="flex items-center justify-between gap-3">
                    <div>
                        <h2 class="section-title">Venue</h2>
                        <p class="mt-1 text-xs text-faint">Tandai satu venue sebagai venue utama.</p>
                    </div>
                    <button type="button" class="btn btn-ghost btn-sm" @click="addVenue()">
                        <x-ui.icon name="plus" class="h-3.5 w-3.5" /> Tambah venue
                    </button>
                </div>

                <div class="mt-4 space-y-3">
                    <template x-for="(venue, index) in venues" :key="'venue-' + index">
                        <div class="rounded-xl border border-line/70 bg-surface-2/30 p-3">
                            <div class="flex items-center justify-between gap-2">
                                <label class="inline-flex items-center gap-2 text-xs font-medium text-ink">
                                    <input type="radio" :checked="primaryIndex === index" @change="primaryIndex = index" aria-label="Jadikan venue utama">
                                    <span x-text="'Venue ' + (index + 1)"></span>
                                </label>
                                <button type="button" class="icon-btn h-8 w-8 text-danger hover:border-danger/40" @click="removeVenue(index)" aria-label="Hapus venue">
                                    <x-ui.icon name="trash" class="h-3.5 w-3.5" />
                                </button>
                            </div>
                            <input type="hidden" :name="'venues[' + index + '][is_primary]'" :value="primaryIndex === index ? 1 : 0">
                            <div class="mt-2 grid gap-2">
                                <input type="text" class="input" :name="'venues[' + index + '][name]'" x-model="venue.name" placeholder="Nama venue" aria-label="Nama venue">
                                <input type="text" class="input" :name="'venues[' + index + '][address]'" x-model="venue.address" placeholder="Alamat venue" aria-label="Alamat venue">
                            </div>
                        </div>
                    </template>
                </div>
                <p class="hint" x-show="venues.length === 0" x-cloak>Belum ada venue. Venue utama akan tampil di undangan tamu.</p>
            </section>

            {{-- 6. Pratinjau operasional --}}
            <section class="card p-5" x-show="step === 6" x-cloak>
                <h2 class="section-title">Pratinjau operasional</h2>
                <p class="mt-1 text-xs text-faint">Ringkasan struktur acara dan daftar periksa persiapan.</p>

                <div class="mt-4 grid gap-4 sm:grid-cols-2">
                    <div class="rounded-xl border border-line/70 bg-surface-2/30 p-4">
                        <p class="text-[11px] font-semibold uppercase tracking-[0.1em] text-muted">Fase & sesi</p>
                        <p class="mt-2 text-sm text-ink">
                            {{ $phaseCollection->count() }} fase · {{ $sessionCollection->count() }} sesi tersimpan
                        </p>
                        <p class="mt-1 text-[11px] text-faint">Perubahan pada langkah sebelumnya disimpan saat Anda menekan Simpan atau Lanjut.</p>
                    </div>
                    <div class="rounded-xl border border-line/70 bg-surface-2/30 p-4">
                        <p class="text-[11px] font-semibold uppercase tracking-[0.1em] text-muted">Venue</p>
                        <p class="mt-2 text-sm text-ink">{{ $venueCollection->count() }} venue tersimpan</p>
                        <p class="mt-1 text-[11px] text-faint">Venue utama: {{ $venueCollection->firstWhere('is_primary', true)?->name ?? 'belum ditetapkan' }}</p>
                    </div>
                </div>

                <div class="mt-5">
                    <div class="flex items-center justify-between gap-2">
                        <p class="label mb-0">Daftar periksa</p>
                        <span class="text-[11px] text-faint">{{ $checklistItems->where('completed', true)->count() }}/{{ $checklistItems->count() }} selesai</span>
                    </div>

                    @if ($checklistItems->isEmpty())
                        <p class="mt-2 text-xs text-faint">Belum ada daftar periksa. Terapkan playbook pada langkah Blueprint untuk mengisinya.</p>
                    @else
                        <ul class="mt-2 divide-y divide-line/60">
                            @foreach ($checklistItems as $item)
                                <li class="flex items-start gap-3 py-2.5">
                                    <input type="hidden" name="checklist[{{ $loop->index }}][label]" value="{{ $item->label }}">
                                    <input type="hidden" name="checklist[{{ $loop->index }}][required]" value="{{ $item->required ? 1 : 0 }}">
                                    <input type="hidden" name="checklist[{{ $loop->index }}][sequence]" value="{{ $item->sequence }}">
                                    <input type="hidden" name="checklist[{{ $loop->index }}][source_key]" value="{{ $item->source_key }}">
                                    <input type="checkbox"
                                           id="checklist-{{ $loop->index }}"
                                           name="checklist[{{ $loop->index }}][completed]"
                                           value="1"
                                           class="mt-0.5 h-4 w-4 rounded border-line text-accent focus:ring-accent/40"
                                           @checked($item->completed)>
                                    <label for="checklist-{{ $loop->index }}" class="flex-1">
                                        <span class="block text-sm text-ink">{{ $item->label }}</span>
                                        @if ($item->description)
                                            <span class="block text-[11px] text-faint">{{ $item->description }}</span>
                                        @endif
                                    </label>
                                    @if ($item->required)
                                        <x-ui.badge variant="warning" class="text-[10px]">Wajib</x-ui.badge>
                                    @endif
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </div>
            </section>

            {{-- 7. Komposisi tim --}}
            <section class="card p-5" x-show="step === 7" x-cloak>
                <h2 class="section-title">Komposisi tim</h2>
                <p class="mt-1 text-xs text-faint">Penugasan tim dikelola di halaman Tim acara. Di sini Anda melihat kebutuhan dan siapa yang sudah bertugas.</p>

                <div class="mt-4 grid gap-4 sm:grid-cols-2">
                    <div class="rounded-xl border border-line/70 bg-surface-2/30 p-4">
                        <p class="text-[11px] font-semibold uppercase tracking-[0.1em] text-muted">Peran tersedia</p>
                        <div class="mt-2 flex flex-wrap gap-1.5">
                            @foreach (collect($staffRoles ?? []) as $role)
                                <x-ui.badge variant="neutral" class="text-[10px]">{{ $staffRoleLabel($role) }}</x-ui.badge>
                            @endforeach
                        </div>
                    </div>
                    <div class="rounded-xl border border-line/70 bg-surface-2/30 p-4">
                        <p class="text-[11px] font-semibold uppercase tracking-[0.1em] text-muted">Anggota workspace tersedia</p>
                        <p class="mt-2 text-lg font-semibold text-ink">{{ $candidateMembers->count() }}</p>
                        <p class="mt-1 text-[11px] text-faint">Kandidat yang belum ditugaskan ke acara ini.</p>
                    </div>
                </div>

                <div class="mt-5">
                    <p class="label mb-2">Tim saat ini</p>
                    @if ($teamMemberships->isEmpty())
                        <x-ui.empty-state icon="users" title="Belum ada anggota tim" description="Buka halaman Tim acara untuk menugaskan anggota workspace." />
                    @else
                        <ul class="divide-y divide-line/60">
                            @foreach ($teamMemberships as $membership)
                                <li class="flex items-center gap-3 py-2.5">
                                    <x-ui.avatar :name="$membership->user?->name ?? '—'" size="sm" />
                                    <div class="min-w-0 flex-1">
                                        <p class="truncate text-sm font-medium text-ink">{{ $membership->user?->name ?? 'Anggota' }}</p>
                                        <p class="truncate text-[11px] text-faint">{{ $membership->user?->email }}</p>
                                    </div>
                                    <x-ui.badge variant="info" class="text-[10px]">{{ $staffRoleLabel($membership->role) }}</x-ui.badge>
                                </li>
                            @endforeach
                        </ul>
                    @endif

                    @can('manageTeam', $event)
                        <a href="{{ route('workspace.events.team.index', [$organization, $event]) }}" class="btn btn-secondary btn-sm mt-3">
                            <x-ui.icon name="users" class="h-3.5 w-3.5" /> Kelola tim acara
                        </a>
                    @endcan
                </div>
            </section>

            {{-- 8. Tinjau & simpan --}}
            <section class="card p-5" x-show="step === 8" x-cloak>
                <h2 class="section-title">Tinjau & simpan</h2>
                <p class="mt-1 text-xs text-faint">Periksa ringkasan sebelum menyimpan. Menandai siap <strong>tidak</strong> mempublikasikan acara.</p>

                <dl class="mt-4 grid gap-4 sm:grid-cols-2">
                    <div>
                        <dt class="text-[11px] font-semibold uppercase tracking-[0.12em] text-muted">Nama acara</dt>
                        <dd class="mt-1 text-sm text-ink">{{ $event->name }}</dd>
                    </div>
                    <div>
                        <dt class="text-[11px] font-semibold uppercase tracking-[0.12em] text-muted">Pasangan</dt>
                        <dd class="mt-1 text-sm text-ink">{{ $event->coupleNames() }}</dd>
                    </div>
                    <div>
                        <dt class="text-[11px] font-semibold uppercase tracking-[0.12em] text-muted">Tanggal & zona waktu</dt>
                        <dd class="mt-1 text-sm text-ink">{{ $event->event_date?->translatedFormat('d F Y') ?? 'Belum ditetapkan' }} · {{ $event->timezone ?: 'Asia/Jakarta' }}</dd>
                    </div>
                    <div>
                        <dt class="text-[11px] font-semibold uppercase tracking-[0.12em] text-muted">Venue utama</dt>
                        <dd class="mt-1 text-sm text-ink">{{ $event->primaryVenue?->name ?? $event->venue_name ?? 'Belum ditetapkan' }}</dd>
                    </div>
                    <div>
                        <dt class="text-[11px] font-semibold uppercase tracking-[0.12em] text-muted">Struktur</dt>
                        <dd class="mt-1 text-sm text-ink">{{ $phaseCollection->count() }} fase · {{ $sessionCollection->count() }} sesi</dd>
                    </div>
                    <div>
                        <dt class="text-[11px] font-semibold uppercase tracking-[0.12em] text-muted">Kesiapan</dt>
                        <dd class="mt-1 text-sm text-ink">{{ $setupStatus->label() }}</dd>
                    </div>
                </dl>

                <div class="mt-4 rounded-xl border border-line/70 bg-blue-soft/50 p-3 text-xs text-ink">
                    <p class="flex items-start gap-2">
                        <x-ui.icon name="info" class="mt-0.5 h-4 w-4 shrink-0 text-accent" />
                        Gunakan tombol <strong>Simpan draf</strong> untuk menyimpan. Bila semua item wajib sudah lengkap, tombol <strong>Tandai siap diulas</strong> akan mengubah status kesiapan — publikasi tetap dilakukan terpisah dari halaman ringkasan.
                    </p>
                </div>
            </section>

            {{-- Navigasi bawah --}}
            <div class="card flex flex-wrap items-center justify-between gap-2 p-4">
                <button type="button" class="btn btn-ghost" @click="step = Math.max(1, step - 1)" x-bind:disabled="step === 1">
                    <x-ui.icon name="arrow-left" class="h-4 w-4" /> Kembali
                </button>

                <div class="flex flex-wrap items-center gap-2">
                    <button type="submit" class="btn btn-secondary">
                        <x-ui.icon name="save" class="h-4 w-4" /> Simpan draf
                    </button>
                    <button type="button"
                            class="btn btn-primary"
                            x-show="step < total"
                            x-on:click="if (step < total) { step = Math.min(total, step + 1); $nextTick(() => $el.form.requestSubmit()); }">
                        Lanjut
                        <x-ui.icon name="chevron-right" class="h-4 w-4" />
                    </button>
                </div>
            </div>
        </form>

        {{-- Finalisasi (form terpisah agar method POST tidak bertabrakan dengan PUT) --}}
        <form method="POST"
              action="{{ route('workspace.events.setup.finalize', [$organization, $event]) }}"
              class="mt-4"
              x-show="step === total"
              x-cloak>
            @csrf
            <div class="card flex flex-wrap items-center justify-between gap-3 border-accent/30 bg-accent/5 p-4">
                <div class="min-w-0">
                    <p class="text-sm font-semibold text-ink">Siap untuk diulas?</p>
                    <p class="mt-0.5 text-xs text-muted">Status kesiapan diperbarui bila seluruh item wajib sudah lengkap. Acara tetap Draft.</p>
                </div>
                <button type="submit" class="btn btn-primary">
                    <x-ui.icon name="check" class="h-4 w-4" /> Tandai siap diulas
                </button>
            </div>
        </form>
    </div>
@endsection