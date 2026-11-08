@extends('layouts.owner')

@section('title', $event->name)

@php
    use App\Enums\EventStatus;
    use App\Enums\RunOfShowStatus;

    $readiness = $readiness ?? ['status' => null, 'items' => collect(), 'completed' => 0, 'total' => 0, 'percent' => 0];
    $teamMembers = collect($teamMembers ?? []);
    $rosSummary = is_array($runOfShowSummary ?? null) ? $runOfShowSummary : [];

    $setupStatus = $setup?->setup_status;
    $readinessStatus = $readiness['status'] instanceof \App\Enums\SetupStatus
        ? $readiness['status']
        : ($setupStatus ?? \App\Enums\SetupStatus::NotStarted);

    $percent = (int) ($readiness['percent'] ?? 0);
    $completed = (int) ($readiness['completed'] ?? 0);
    $total = (int) ($readiness['total'] ?? 0);

    $timezone = $event->timezone ?: 'Asia/Jakarta';
    $eventType = $setup?->playbook?->event_type;
    $primaryPic = $setup?->primaryPic;
@endphp

@section('content')
    <x-ui.page-header
        :title="$event->name"
        eyebrow="Ringkasan Acara"
        :subtitle="$event->coupleNames().' · '.($event->event_date?->translatedFormat('l, d F Y') ?? 'Tanggal belum ditetapkan')"
    >
        <x-ui.badge :variant="$event->status->badge()">{{ $event->status->stageLabel() }}</x-ui.badge>

        @can('update', $event)
            <x-ui.button variant="secondary" :href="route('workspace.events.edit', [$organization, $event])" icon="edit">Edit acara</x-ui.button>
        @endcan

        @can('manageSetup', $event)
            <x-ui.button variant="secondary" :href="route('workspace.events.setup', [$organization, $event])" icon="sliders">Persiapan</x-ui.button>
        @endcan
    </x-ui.page-header>

    @include('workspace.events.partials.event-tabs', ['organization' => $organization, 'event' => $event, 'setup' => $setup, 'active' => 'overview'])

    {{-- Siklus hidup --}}
    @if (auth()->user()->can('publish', $event) || auth()->user()->can('archive', $event) || auth()->user()->can('unarchive', $event))
        <div class="card mt-6 flex flex-wrap items-center gap-2 p-4">
            <p class="mr-2 text-xs font-semibold uppercase tracking-[0.1em] text-muted">Ubah status</p>

            @if ($event->status === EventStatus::Draft && auth()->user()->can('publish', $event))
                <form method="POST" action="{{ route('workspace.events.status', [$organization, $event]) }}" data-confirm="Publikasikan acara ini? Tamu akan dapat mengakses undangan." data-confirm-button="Ya, publikasikan">
                    @csrf
                    <input type="hidden" name="status" value="{{ EventStatus::Published->value }}">
                    <button type="submit" class="btn btn-primary btn-sm">
                        <x-ui.icon name="send" class="h-3.5 w-3.5" /> Publikasikan
                    </button>
                </form>
            @endif

            @if ($event->status === EventStatus::Published && auth()->user()->can('update', $event))
                <form method="POST" action="{{ route('workspace.events.status', [$organization, $event]) }}" data-confirm="Tandai acara berlangsung sekarang?">
                    @csrf
                    <input type="hidden" name="status" value="{{ EventStatus::Live->value }}">
                    <button type="submit" class="btn btn-secondary btn-sm">
                        <x-ui.icon name="play" class="h-3.5 w-3.5" /> Mulai berlangsung
                    </button>
                </form>
            @endif

            @if (in_array($event->status, [EventStatus::Published, EventStatus::Live], true) && auth()->user()->can('update', $event))
                <form method="POST" action="{{ route('workspace.events.status', [$organization, $event]) }}" data-confirm="Tandai acara ini selesai?">
                    @csrf
                    <input type="hidden" name="status" value="{{ EventStatus::Completed->value }}">
                    <button type="submit" class="btn btn-secondary btn-sm">
                        <x-ui.icon name="check" class="h-3.5 w-3.5" /> Tandai selesai
                    </button>
                </form>
            @endif

            @if ($event->status !== EventStatus::Archived && auth()->user()->can('archive', $event))
                <form method="POST" action="{{ route('workspace.events.archive', [$organization, $event]) }}" data-confirm="Arsipkan acara ini? Acara tidak akan tampil di daftar aktif." data-confirm-button="Ya, arsipkan">
                    @csrf
                    <button type="submit" class="btn btn-danger btn-sm">
                        <x-ui.icon name="archive" class="h-3.5 w-3.5" /> Arsipkan
                    </button>
                </form>
            @endif

            @if ($event->status === EventStatus::Archived && auth()->user()->can('unarchive', $event))
                <form method="POST" action="{{ route('workspace.events.unarchive', [$organization, $event]) }}" data-confirm="Pulihkan acara dari arsip?">
                    @csrf
                    <button type="submit" class="btn btn-secondary btn-sm">
                        <x-ui.icon name="refresh" class="h-3.5 w-3.5" /> Pulihkan
                    </button>
                </form>
            @endif
        </div>
    @endif

    <div class="mt-6 grid gap-5 lg:grid-cols-3">
        {{-- Identitas & jadwal --}}
        <div class="card p-5 lg:col-span-2">
            <h2 class="section-title">Identitas acara</h2>
            <div class="mt-4 grid gap-4 sm:grid-cols-2">
                <div>
                    <p class="text-[11px] font-semibold uppercase tracking-[0.12em] text-muted">Mempelai</p>
                    <p class="mt-1 text-sm font-medium text-ink">{{ $event->coupleNames() ?: 'Belum dikonfigurasi' }}</p>
                </div>
                <div>
                    <p class="text-[11px] font-semibold uppercase tracking-[0.12em] text-muted">Jenis acara</p>
                    <p class="mt-1 text-sm font-medium text-ink">{{ $eventType ? ucfirst(str_replace('_', ' ', $eventType)) : 'Pernikahan' }}</p>
                </div>
                <div>
                    <p class="text-[11px] font-semibold uppercase tracking-[0.12em] text-muted">Tanggal & waktu</p>
                    <p class="mt-1 text-sm font-medium text-ink">
                        {{ $event->event_date?->translatedFormat('d F Y') ?? 'Belum ditetapkan' }}
                        @if ($event->start_time)
                            · {{ \Illuminate\Support\Str::of($event->start_time)->substr(0, 5) }}
                        @endif
                        @if ($event->end_time)
                            – {{ \Illuminate\Support\Str::of($event->end_time)->substr(0, 5) }}
                        @endif
                    </p>
                </div>
                <div>
                    <p class="text-[11px] font-semibold uppercase tracking-[0.12em] text-muted">Zona waktu</p>
                    <p class="mt-1 text-sm font-medium text-ink">{{ $timezone }}</p>
                </div>
                <div class="sm:col-span-2">
                    <p class="text-[11px] font-semibold uppercase tracking-[0.12em] text-muted">Venue utama</p>
                    @if ($event->primaryVenue)
                        <p class="mt-1 text-sm font-medium text-ink">{{ $event->primaryVenue->name }}</p>
                        @if ($event->primaryVenue->address)
                            <p class="text-xs text-muted">{{ $event->primaryVenue->address }}</p>
                        @endif
                    @elseif ($event->venue_name)
                        <p class="mt-1 text-sm font-medium text-ink">{{ $event->venue_name }}</p>
                        @if ($event->venue_address)
                            <p class="text-xs text-muted">{{ $event->venue_address }}</p>
                        @endif
                    @else
                        <p class="mt-1 text-sm text-faint">Belum dikonfigurasi</p>
                    @endif
                </div>
            </div>
        </div>

        {{-- Kesiapan setup --}}
        <div class="card p-5">
            <div class="flex items-center justify-between gap-3">
                <h2 class="section-title">Kesiapan setup</h2>
                <x-ui.badge :variant="$readinessStatus->badge()" class="text-[10px]">{{ $readinessStatus->label() }}</x-ui.badge>
            </div>

            <div class="mt-4">
                <div class="flex items-end justify-between">
                    <p class="font-serif text-3xl font-bold text-ink">{{ $percent }}%</p>
                    <p class="text-xs text-faint">{{ $completed }}/{{ $total }} item selesai</p>
                </div>
                <div class="mt-2 h-2 w-full overflow-hidden rounded-full bg-surface-2">
                    <div class="h-full rounded-full bg-accent transition-all" style="width: {{ $percent }}%"></div>
                </div>
                @if ($total === 0)
                    <p class="mt-3 text-xs text-faint">Belum ada daftar periksa. Terapkan playbook pada langkah Persiapan untuk memulai.</p>
                @endif
            </div>

            @if ($primaryPic?->user)
                <div class="mt-4 flex items-center gap-2.5 rounded-xl border border-line/70 bg-surface-2/40 p-2.5">
                    <x-ui.avatar :name="$primaryPic->user->name" size="sm" />
                    <div class="min-w-0">
                        <p class="truncate text-xs font-semibold text-ink">{{ $primaryPic->user->name }}</p>
                        <p class="truncate text-[10px] text-faint">Penanggung jawab utama</p>
                    </div>
                </div>
            @endif

            @can('manageSetup', $event)
                <a href="{{ route('workspace.events.setup', [$organization, $event]) }}" class="btn btn-secondary btn-sm mt-4 w-full justify-center">
                    <x-ui.icon name="sliders" class="h-3.5 w-3.5" /> Buka persiapan acara
                </a>
            @endcan
        </div>

        {{-- Tim --}}
        <div class="card p-5 lg:col-span-2">
            <div class="flex items-center justify-between gap-3">
                <h2 class="section-title">Tim acara</h2>
                <span class="text-xs text-faint">{{ $teamMembers->count() }} anggota</span>
            </div>

            @if ($teamMembers->isNotEmpty())
                <ul class="mt-4 divide-y divide-line/60">
                    @foreach ($teamMembers->take(5) as $membership)
                        <li class="flex items-center gap-3 py-2.5">
                            <x-ui.avatar :name="$membership->user?->name ?? '—'" size="sm" />
                            <div class="min-w-0 flex-1">
                                <p class="truncate text-sm font-medium text-ink">{{ $membership->user?->name ?? 'Anggota' }}</p>
                                <p class="truncate text-[11px] text-faint">{{ $membership->user?->email }}</p>
                            </div>
                            <x-ui.badge variant="info" class="text-[10px]">{{ $membership->role->label() }}</x-ui.badge>
                            @if ($primaryPic && $primaryPic->id === $membership->id)
                                <x-ui.badge variant="success" class="text-[10px]">PIC utama</x-ui.badge>
                            @endif
                        </li>
                    @endforeach
                </ul>
                @if ($teamMembers->count() > 5)
                    <p class="mt-2 text-[11px] text-faint">+{{ $teamMembers->count() - 5 }} anggota lainnya</p>
                @endif
            @else
                <div class="mt-4">
                    <x-ui.empty-state icon="users" title="Belum ada anggota tim" description="Tugaskan anggota workspace sebagai tim acara untuk membantu pelaksanaan." />
                </div>
            @endif

            @can('manageTeam', $event)
                <a href="{{ route('workspace.events.team.index', [$organization, $event]) }}" class="btn btn-secondary btn-sm mt-4 justify-center">
                    <x-ui.icon name="users" class="h-3.5 w-3.5" /> Kelola tim
                </a>
            @endcan
        </div>

        {{-- Ringkasan publik & run of show --}}
        <div class="space-y-5">
            <div class="card p-5">
                <div class="flex items-center justify-between gap-3">
                    <h2 class="section-title">Jadwal publik</h2>
                    <x-ui.badge variant="muted" class="text-[10px]">{{ (int) ($scheduleCount ?? 0) }} item</x-ui.badge>
                </div>
                <p class="mt-3 text-xs text-muted">Rangkaian acara yang akan dilihat tamu di undangan digital.</p>
                @can('manageSchedule', $event)
                    <a href="{{ route('workspace.events.schedule.index', [$organization, $event]) }}" class="btn btn-secondary btn-sm mt-3 w-full justify-center">
                        <x-ui.icon name="clock" class="h-3.5 w-3.5" /> Atur jadwal publik
                    </a>
                @endcan
            </div>

            <div class="card p-5">
                <div class="flex items-center justify-between gap-3">
                    <h2 class="section-title">Run of show</h2>
                    <x-ui.badge variant="muted" class="text-[10px]">{{ (int) ($rosSummary['total'] ?? 0) }} item</x-ui.badge>
                </div>

                @if (! empty($rosSummary))
                    <dl class="mt-3 grid grid-cols-2 gap-3 text-xs">
                        <div>
                            <dt class="text-faint">Berlangsung</dt>
                            <dd class="mt-0.5 text-lg font-semibold text-ink">{{ (int) ($rosSummary['in_progress'] ?? 0) }}</dd>
                        </div>
                        <div>
                            <dt class="text-faint">Selesai</dt>
                            <dd class="mt-0.5 text-lg font-semibold text-success">{{ (int) ($rosSummary['completed'] ?? 0) }}</dd>
                        </div>
                        <div>
                            <dt class="text-faint">Tertunda</dt>
                            <dd class="mt-0.5 text-lg font-semibold text-ink">{{ (int) ($rosSummary['pending'] ?? 0) }}</dd>
                        </div>
                        <div>
                            <dt class="text-faint">Terlambat</dt>
                            <dd class="mt-0.5 text-lg font-semibold text-warning">{{ (int) ($rosSummary['delayed'] ?? 0) }}</dd>
                        </div>
                    </dl>
                @else
                    <p class="mt-3 text-xs text-muted">Belum ada item run of show untuk acara ini.</p>
                @endif

                @can('viewRunOfShow', $event)
                    <a href="{{ route('workspace.events.run-of-show.index', [$organization, $event]) }}" class="btn btn-secondary btn-sm mt-3 w-full justify-center">
                        <x-ui.icon name="list" class="h-3.5 w-3.5" /> Buka run of show
                    </a>
                @endcan
            </div>
        </div>
    </div>
@endsection
