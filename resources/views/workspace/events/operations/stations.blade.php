@extends('layouts.owner')

@section('title', 'Stasiun operasional · '.$event->name)

@php
    $stations = collect($stations ?? []);
    $staff = collect($staff ?? []);
    $categories = collect($categories ?? []);
    $statuses = collect($statuses ?? []);
    $coverage = collect($coverage ?? []);

    $roleLabel = fn ($role): string => $role instanceof \App\Enums\EventStaffRole
        ? $role->label()
        : ucfirst(str_replace('_', ' ', (string) $role));

    $staffOptions = $staff->mapWithKeys(fn ($membership) => [
        $membership->id => ($membership->user?->name ?? 'Anggota').' — '.$roleLabel($membership->role),
    ]);

    $categoryOptions = $categories->mapWithKeys(fn ($category) => [$category->value => $category->label()]);

    $coverageVariant = fn (string $level): string => match ($level) {
        'ready' => 'success',
        'no_staff' => 'danger',
        default => 'warning',
    };

    $withoutStaff = $stations->filter(fn ($station) => ($coverage[$station->id]['assigned'] ?? 0) === 0)->count();
    $withoutSupervisor = $stations->filter(fn ($station) => ! ($coverage[$station->id]['has_supervisor'] ?? false))->count();
    $operational = $stations->filter(fn ($station) => $station->status->isOperational())->count();

    $canManage = auth()->user()->can('manageStations', $event);
    $canAssign = auth()->user()->can('assignStations', $event);
@endphp

@section('content')
    <x-ui.page-header
        title="Stasiun operasional"
        eyebrow="Operasi"
        :subtitle="'Susun titik layanan, supervisor, dan anggota staf untuk '.$event->name.'.'"
    >
        <x-ui.badge :variant="$withoutStaff > 0 ? 'warning' : 'success'">{{ $stations->count() }} stasiun</x-ui.badge>
        <x-ui.button variant="secondary" :href="route('workspace.events.operations.index', [$organization, $event])" icon="arrow-left">Ruang kendali</x-ui.button>
    </x-ui.page-header>

    @include('workspace.events.partials.event-tabs', ['organization' => $organization, 'event' => $event, 'setup' => $setup ?? null, 'active' => 'operations-stations'])

    <div class="mt-6 grid grid-cols-2 gap-3 md:grid-cols-4">
        <x-ui.stat-card label="Total stasiun" :value="$stations->count()" icon="pin" tone="accent" />
        <x-ui.stat-card label="Beroperasi" :value="$operational" icon="check" tone="success" hint="Siap atau aktif" />
        <x-ui.stat-card label="Tanpa staf" :value="$withoutStaff" icon="users" :tone="$withoutStaff > 0 ? 'danger' : 'teal'" />
        <x-ui.stat-card label="Tanpa supervisor" :value="$withoutSupervisor" icon="shield" :tone="$withoutSupervisor > 0 ? 'warning' : 'teal'" />
    </div>

    <div class="mt-5 grid gap-5 lg:grid-cols-3">
        <div class="space-y-4 lg:col-span-2">
            @if ($stations->isEmpty())
                <x-ui.empty-state icon="pin" title="Belum ada stasiun" description="Buat stasiun pertama untuk membagi area kerja, supervisor, dan penugasan staf." />
            @else
                @foreach ($stations as $station)
                    @php
                        $stationCoverage = $coverage[$station->id] ?? ['level' => 'no_staff', 'label' => 'Belum ada staf', 'assigned' => 0, 'has_supervisor' => false];
                        $activeAssignments = $station->activeAssignments;
                        $supervisor = $station->supervisor;
                    @endphp

                    <article class="card overflow-hidden animate-float-in">
                        <div class="p-5">
                            <div class="flex flex-wrap items-start justify-between gap-3">
                                <div class="min-w-0">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <h2 class="text-sm font-semibold text-ink">{{ $station->name }}</h2>
                                        <x-ui.badge :variant="$station->status->badge()" class="text-[10px]">{{ $station->status->label() }}</x-ui.badge>
                                        @if ($station->category)
                                            <x-ui.badge variant="neutral" class="text-[10px]">{{ $station->category->label() }}</x-ui.badge>
                                        @endif
                                    </div>

                                    <div class="mt-1.5 flex flex-wrap items-center gap-x-3 gap-y-1 text-[11px] text-muted">
                                        @if ($station->location)
                                            <span class="inline-flex items-center gap-1"><x-ui.icon name="map-pin" class="h-3 w-3 text-faint" /> {{ $station->location }}</span>
                                        @endif
                                        <span class="inline-flex items-center gap-1">
                                            <x-ui.icon name="users" class="h-3 w-3 text-faint" />
                                            {{ $activeAssignments->count() }} staf
                                        </span>
                                        <span class="inline-flex items-center gap-1">
                                            <x-ui.icon name="shield" class="h-3 w-3 text-faint" />
                                            {{ $supervisor?->user?->name ?? 'Tanpa supervisor' }}
                                        </span>
                                    </div>

                                    @if ($station->description)
                                        <p class="mt-2 text-xs leading-relaxed text-muted">{{ $station->description }}</p>
                                    @endif

                                    @if ($station->instructions)
                                        <p class="mt-2 rounded-lg border border-line/60 bg-surface-2/40 px-2.5 py-2 text-[11px] leading-relaxed text-muted">
                                            <span class="font-semibold text-ink">Instruksi:</span> {{ $station->instructions }}
                                        </p>
                                    @endif
                                </div>

                                <div class="flex shrink-0 flex-col items-end gap-2">
                                    <x-ui.badge :variant="$coverageVariant($stationCoverage['level'])">{{ $stationCoverage['label'] }}</x-ui.badge>
                                    @if ($canManage)
                                        <form method="POST" action="{{ route('workspace.events.operations.stations.destroy', [$organization, $event, $station]) }}" data-confirm="Hapus stasiun {{ $station->name }}?" data-confirm-button="Ya, hapus">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="icon-btn h-8 w-8 text-danger hover:border-danger/40" aria-label="Hapus stasiun">
                                                <x-ui.icon name="trash" class="h-3.5 w-3.5" />
                                            </button>
                                        </form>
                                    @endif
                                </div>
                            </div>

                            {{-- Anggota stasiun --}}
                            <div class="mt-4 flex flex-wrap gap-1.5">
                                @forelse ($activeAssignments as $assignment)
                                    <span class="inline-flex items-center gap-1.5 rounded-full border border-line/70 bg-surface-2/50 py-1 pl-1 pr-1.5 text-[10px] text-ink">
                                        <x-ui.avatar :name="$assignment->membership?->user?->name ?? 'Anggota'" size="sm" class="!h-5 !w-5 !rounded-full !text-[8px]" />
                                        {{ $assignment->membership?->user?->name ?? 'Anggota' }}
                                        @if ($assignment->is_supervisor)
                                            <x-ui.icon name="star" class="h-2.5 w-2.5 text-warning" />
                                        @endif
                                        @if ($canAssign)
                                            <form method="POST" action="{{ route('workspace.events.operations.stations.unassign', [$organization, $event, $station, $assignment->staff_membership_id]) }}" data-confirm="Lepas {{ $assignment->membership?->user?->name }} dari stasiun ini?" data-confirm-button="Ya, lepas">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="flex h-4 w-4 items-center justify-center rounded-full text-faint transition hover:bg-danger/15 hover:text-danger" aria-label="Lepas penugasan">
                                                    <x-ui.icon name="close" class="h-2.5 w-2.5" />
                                                </button>
                                            </form>
                                        @endif
                                    </span>
                                @empty
                                    <span class="text-[11px] text-faint">Belum ada staf ditugaskan.</span>
                                @endforelse
                            </div>
                        </div>

                        {{-- Aksi cepat --}}
                        @if ($canManage || $canAssign)
                            <div class="grid gap-3 border-t border-line/60 bg-surface-2/20 p-4 md:grid-cols-2">
                                @if ($canAssign)
                                    <form method="POST" action="{{ route('workspace.events.operations.stations.assign', [$organization, $event, $station]) }}" class="flex items-end gap-2">
                                        @csrf
                                        <div class="min-w-0 flex-1">
                                            <label class="label" for="assign-{{ $station->id }}">Tugaskan staf</label>
                                            <select id="assign-{{ $station->id }}" name="staff_membership_id" class="input input-sm appearance-none">
                                                <option value="">Pilih staf</option>
                                                @foreach ($staff as $membership)
                                                    <option value="{{ $membership->id }}">{{ $membership->user?->name }} — {{ $roleLabel($membership->role) }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <button type="submit" class="btn btn-secondary btn-sm shrink-0" @disabled($staffOptions->isEmpty())>
                                            <x-ui.icon name="plus" class="h-3.5 w-3.5" /> Tambah
                                        </button>
                                    </form>
                                @endif

                                @if ($canManage)
                                    <form method="POST" action="{{ route('workspace.events.operations.stations.status', [$organization, $event, $station]) }}" class="flex items-end gap-2">
                                        @csrf
                                        <div class="min-w-0 flex-1">
                                            <label class="label" for="status-{{ $station->id }}">Ubah status</label>
                                            <select id="status-{{ $station->id }}" name="status" class="input input-sm appearance-none">
                                                <option value="{{ $station->status->value }}" selected>{{ $station->status->label() }}</option>
                                                @foreach ($station->status->allowedTransitions() as $nextStatus)
                                                    <option value="{{ $nextStatus->value }}">{{ $nextStatus->label() }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <button type="submit" class="btn btn-secondary btn-sm shrink-0">Simpan</button>
                                    </form>
                                @endif
                            </div>
                        @endif

                        {{-- Pengaturan lengkap --}}
                        @if ($canManage)
                            <details class="group border-t border-line/60">
                                <summary class="flex cursor-pointer list-none items-center justify-between gap-2 px-4 py-3 text-[11px] font-semibold text-muted transition hover:bg-surface-2/30 hover:text-ink">
                                    <span class="inline-flex items-center gap-1.5"><x-ui.icon name="edit" class="h-3.5 w-3.5" /> Edit stasiun & supervisor</span>
                                    <x-ui.icon name="chevron-down" class="h-3.5 w-3.5 transition group-open:rotate-180" />
                                </summary>

                                <div class="space-y-4 border-t border-line/50 p-4">
                                    <form method="POST" action="{{ route('workspace.events.operations.stations.supervisor', [$organization, $event, $station]) }}" class="flex flex-wrap items-end gap-2">
                                        @csrf
                                        <div class="min-w-0 flex-1">
                                            <label class="label" for="supervisor-{{ $station->id }}">Supervisor stasiun</label>
                                            <select id="supervisor-{{ $station->id }}" name="staff_membership_id" class="input input-sm appearance-none">
                                                <option value="">Tanpa supervisor</option>
                                                @foreach ($staff as $membership)
                                                    <option value="{{ $membership->id }}" @selected((int) $station->supervisor_membership_id === (int) $membership->id)>
                                                        {{ $membership->user?->name }} — {{ $roleLabel($membership->role) }}
                                                    </option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <button type="submit" class="btn btn-secondary btn-sm">Simpan supervisor</button>
                                    </form>

                                    <form method="POST" action="{{ route('workspace.events.operations.stations.update', [$organization, $event, $station]) }}" class="grid gap-3 sm:grid-cols-2">
                                        @csrf
                                        @method('PUT')
                                        <x-ui.input name="name" label="Nama stasiun" :value="$station->name" required />
                                        <x-ui.select name="category" label="Kategori" :options="$categoryOptions" :value="$station->category?->value" placeholder="Tanpa kategori" />
                                        <x-ui.input name="location" label="Lokasi" :value="$station->location" placeholder="Contoh: Lobi utama" />
                                        <div class="hidden sm:block"></div>
                                        <div class="sm:col-span-2">
                                            <x-ui.textarea name="description" label="Deskripsi" :value="$station->description" rows="2" />
                                        </div>
                                        <div class="sm:col-span-2">
                                            <x-ui.textarea name="instructions" label="Instruksi" :value="$station->instructions" rows="2" placeholder="Langkah kerja singkat untuk stasiun ini" />
                                        </div>
                                        <div class="flex justify-end sm:col-span-2">
                                            <button type="submit" class="btn btn-primary btn-sm">
                                                <x-ui.icon name="save" class="h-3.5 w-3.5" /> Simpan perubahan
                                            </button>
                                        </div>
                                    </form>
                                </div>
                            </details>
                        @endif
                    </article>
                @endforeach
            @endif
        </div>

        <div class="space-y-5">
            @if ($canManage)
                <div class="card p-5">
                    <div class="flex items-center gap-3">
                        <span class="flex h-10 w-10 items-center justify-center rounded-2xl bg-teal-soft text-ink">
                            <x-ui.icon name="pin" class="h-5 w-5" />
                        </span>
                        <div>
                            <h2 class="section-title">Stasiun baru</h2>
                            <p class="mt-0.5 text-xs text-faint">Stasiun baru dimulai sebagai draft.</p>
                        </div>
                    </div>

                    <form method="POST" action="{{ route('workspace.events.operations.stations.store', [$organization, $event]) }}" class="mt-5 space-y-4">
                        @csrf
                        <x-ui.input name="name" label="Nama stasiun" required placeholder="Contoh: Meja registrasi" />
                        <x-ui.select name="category" label="Kategori" :options="$categoryOptions" placeholder="Pilih kategori" />
                        <x-ui.input name="location" label="Lokasi" placeholder="Contoh: Pintu masuk utama" />
                        <x-ui.select name="supervisor_membership_id" label="Supervisor" :options="$staffOptions" placeholder="Tanpa supervisor" />
                        <x-ui.textarea name="description" label="Deskripsi" rows="2" />
                        <x-ui.textarea name="instructions" label="Instruksi" rows="2" placeholder="Panduan singkat untuk staf stasiun" />

                        <button type="submit" class="btn btn-primary w-full justify-center">
                            <x-ui.icon name="plus" class="h-4 w-4" /> Tambah stasiun
                        </button>
                    </form>
                </div>
            @endif

            <div class="card p-5">
                <h2 class="section-title">Arti cakupan</h2>
                <ul class="mt-3 space-y-2.5 text-[11px] leading-relaxed">
                    <li class="flex items-start gap-2"><span class="badge badge-success shrink-0 text-[9px]">Siap</span><span class="text-muted">Ada staf, ada supervisor, dan stasiun beroperasi.</span></li>
                    <li class="flex items-start gap-2"><span class="badge badge-warning shrink-0 text-[9px]">Perhatian</span><span class="text-muted">Belum ada supervisor atau status belum siap.</span></li>
                    <li class="flex items-start gap-2"><span class="badge badge-danger shrink-0 text-[9px]">Kosong</span><span class="text-muted">Belum ada staf yang ditugaskan.</span></li>
                </ul>
            </div>

            @cannot('manageStations', $event)
                <div class="card p-5">
                    <div class="flex items-start gap-3 rounded-xl bg-surface-2/60 p-3 text-xs text-muted">
                        <x-ui.icon name="lock" class="mt-0.5 h-4 w-4 shrink-0" />
                        <p>Anda hanya dapat melihat stasiun. Perubahan status dan penugasan memerlukan izin mengelola stasiun.</p>
                    </div>
                </div>
            @endcannot
        </div>
    </div>
@endsection
