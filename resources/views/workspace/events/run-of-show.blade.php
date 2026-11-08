@extends('layouts.owner')

@section('title', 'Run of Show '.$event->name)

@php
    use App\Enums\RunOfShowStatus;

    $items = collect($items ?? []);
    $sessions = collect($sessions ?? [])->sortBy('session_date')->values();
    $phases = collect($phases ?? []);
    $staff = collect($staff ?? []);
    $statuses = collect($statuses ?? RunOfShowStatus::cases());

    $timezone = $event->timezone ?: 'Asia/Jakarta';
    $toLocal = fn ($datetime) => $datetime?->copy()->setTimezone($timezone);

    $itemsBySession = $items->groupBy(fn ($item) => $item->event_session_id ?? 'none');
    $looseItems = $itemsBySession->get('none', collect());

    $staffLabel = function ($membership): string {
        $name = $membership->user?->name ?? 'Anggota';
        $role = $membership->role instanceof \App\Enums\EventStaffRole ? $membership->role->label() : ucfirst(str_replace('_', ' ', (string) $membership->role));

        return $name.' — '.$role;
    };

    $staffOptions = $staff->mapWithKeys(fn ($membership) => [$membership->id => $staffLabel($membership)]);
    $sessionOptions = $sessions->mapWithKeys(fn ($session) => [
        $session->id => $session->name.' · '.$session->session_date?->translatedFormat('d M Y'),
    ]);
    $phaseOptions = $phases->mapWithKeys(fn ($phase) => [$phase->id => $phase->name]);

    $canManage = auth()->user()->can('manageRunOfShow', $event);
@endphp

@section('content')
    <x-ui.page-header
        title="Run of show"
        eyebrow="Internal"
        :subtitle="'Alur teknis dan waktu nyata untuk '.$event->name.'.'"
    >
        <x-ui.badge variant="muted">{{ $items->count() }} item</x-ui.badge>
        <x-ui.button variant="secondary" :href="route('workspace.events.show', [$organization, $event])" icon="arrow-left">Ringkasan</x-ui.button>
    </x-ui.page-header>

    @include('workspace.events.partials.event-tabs', ['organization' => $organization, 'event' => $event, 'setup' => $setup ?? null, 'active' => 'run-of-show'])

    <div class="mt-6 flex items-start gap-3 rounded-2xl border border-warning/30 bg-warning/10 p-4 text-sm text-warning">
        <x-ui.icon name="lock" class="mt-0.5 h-4 w-4 shrink-0" />
        <p>
            <strong>Internal.</strong> Halaman ini berisi jadwal teknis, waktu aktual, PIC, dan catatan internal.
            Data di sini tidak pernah ditampilkan ke tamu — gunakan Jadwal publik untuk informasi tamu.
        </p>
    </div>

    <div class="mt-6 grid gap-5 lg:grid-cols-3">
        {{-- Tambah item --}}
        @if ($canManage)
            <div class="card p-5 lg:col-span-1">
                <div class="flex items-center gap-3">
                    <span class="flex h-10 w-10 items-center justify-center rounded-2xl bg-blue-soft text-ink">
                        <x-ui.icon name="list" class="h-5 w-5" />
                    </span>
                    <div>
                        <h2 class="section-title">Tambah item</h2>
                        <p class="mt-0.5 text-xs text-faint">Waktu dalam zona {{ $timezone }}.</p>
                    </div>
                </div>

                <form method="POST" action="{{ route('workspace.events.run-of-show.store', [$organization, $event]) }}" class="mt-5 space-y-4">
                    @csrf
                    <x-ui.input name="title" label="Judul item" required placeholder="Contoh: Kirab pengantin" />

                    <x-ui.select name="session_id" label="Sesi" :options="$sessionOptions" placeholder="Tanpa sesi" />
                    <x-ui.select name="phase_id" label="Fase" :options="$phaseOptions" placeholder="Tanpa fase" />
                    <x-ui.select name="pic_staff_membership_id" label="PIC" :options="$staffOptions" placeholder="Tanpa PIC" />
                    <x-ui.select name="status" label="Status" :options="$statuses" :value="RunOfShowStatus::Pending->value" />

                    <div class="grid grid-cols-2 gap-3">
                        <x-ui.input name="planned_start_local" label="Rencana mulai" type="datetime-local" />
                        <x-ui.input name="planned_end_local" label="Rencana selesai" type="datetime-local" />
                        <x-ui.input name="actual_start_local" label="Aktual mulai" type="datetime-local" />
                        <x-ui.input name="actual_end_local" label="Aktual selesai" type="datetime-local" />
                    </div>

                    <x-ui.textarea name="internal_notes" label="Catatan internal" rows="2" placeholder="Hanya terlihat oleh tim" />

                    <button type="submit" class="btn btn-primary w-full justify-center">
                        <x-ui.icon name="plus" class="h-4 w-4" /> Tambah item
                    </button>
                </form>
            </div>
        @endif

        {{-- Timeline --}}
        <div class="{{ $canManage ? 'lg:col-span-2' : 'lg:col-span-3' }} space-y-6">
            @if ($items->isEmpty())
                <x-ui.empty-state icon="list" title="Belum ada item run of show" description="Susun alur teknis acara dari sesi pertama hingga selesai." />
            @else
                @foreach ($sessions as $session)
                    @php $sessionItems = $itemsBySession->get($session->id, collect()); @endphp
                    @if ($sessionItems->isNotEmpty())
                        <section>
                            <div class="mb-3 flex flex-wrap items-center gap-2">
                                <h2 class="section-title">{{ $session->name }}</h2>
                                <x-ui.badge variant="neutral" class="text-[10px]">{{ $session->session_date?->translatedFormat('l, d M Y') }}</x-ui.badge>
                                <span class="text-[11px] text-faint">{{ $sessionItems->count() }} item</span>
                            </div>
                            @include('workspace.events.partials.run-of-show-items', ['runItems' => $sessionItems, 'event' => $event, 'organization' => $organization, 'toLocal' => $toLocal, 'timezone' => $timezone, 'canManage' => $canManage, 'statuses' => $statuses, 'sessionOptions' => $sessionOptions, 'phaseOptions' => $phaseOptions, 'staffOptions' => $staffOptions])
                        </section>
                    @endif
                @endforeach

                @if ($looseItems->isNotEmpty())
                    <section>
                        <div class="mb-3 flex flex-wrap items-center gap-2">
                            <h2 class="section-title">Tanpa sesi</h2>
                            <span class="text-[11px] text-faint">{{ $looseItems->count() }} item</span>
                        </div>
                        @include('workspace.events.partials.run-of-show-items', ['runItems' => $looseItems, 'event' => $event, 'organization' => $organization, 'toLocal' => $toLocal, 'timezone' => $timezone, 'canManage' => $canManage, 'statuses' => $statuses, 'sessionOptions' => $sessionOptions, 'phaseOptions' => $phaseOptions, 'staffOptions' => $staffOptions])
                    </section>
                @endif
            @endif
        </div>
    </div>
@endsection