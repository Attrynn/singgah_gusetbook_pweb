@extends('layouts.owner')

@section('title', 'Operasi '.$event->name)

@php
    use App\Enums\OperationsStatus;
    use App\Enums\ReadinessStatus;

    $operationStatus = $operation?->status ?? OperationsStatus::Draft;
    $readinessStatus = $readiness['status'];
    $readinessItems = collect($readiness['items'] ?? []);
    $blockingItems = $readinessItems->where('status', ReadinessStatus::NotReady);
    $warningItems = $readinessItems->where('status', ReadinessStatus::Warning);

    $staff = $summary['staff'];
    $stations = $summary['stations'];
    $runSheet = $summary['run_sheet'];
    $tasks = $summary['tasks'];
    $incidents = $summary['incidents'];
    $announcements = $summary['announcements'];
    $attendance = $summary['attendance'];

    $canManage = auth()->user()->can('manageOperations', $event);
    $canClose = auth()->user()->can('closeOperations', $event);
    $canViewStaff = auth()->user()->can('viewStaff', $event);
    $canViewStations = auth()->user()->can('viewStations', $event);
    $canViewTasks = auth()->user()->can('viewTasks', $event);
    $canViewIncidents = auth()->user()->can('viewIncidents', $event);
    $canViewAnnouncements = auth()->user()->can('viewAnnouncements', $event);
    $canViewReports = auth()->user()->can('viewReports', $event);

    $transitionOptions = collect($operationStatus->allowedTransitions())
        ->reject(fn ($status) => $status === OperationsStatus::Completed)
        ->mapWithKeys(fn ($status) => [$status->value => $status->label()]);

    $timezone = $event->timezone ?: 'Asia/Jakarta';
    $toLocal = fn ($datetime) => $datetime?->copy()->setTimezone($timezone);

    $currentItem = $runSheet['current'];
    $nextItem = $runSheet['next'];
    $checkins = $attendance['checkins'] ?? ['total' => 0, 'not_checked_in' => 0, 'completion_percentage' => 0];
@endphp

@section('content')
    <x-ui.page-header
        title="Operasi acara"
        eyebrow="Operasi"
        :subtitle="'Ruang kendali hari-H untuk '.$event->name.'. Pantau kesiapan, staf, tugas, dan insiden dalam satu layar.'"
    >
        @if ($announcements['unacknowledged_for_viewer'] > 0)
            <x-ui.badge variant="danger">{{ $announcements['unacknowledged_for_viewer'] }} pengumuman perlu konfirmasi</x-ui.badge>
        @endif

        @if ($canViewTasks)
            <x-ui.button variant="secondary" :href="route('workspace.events.operations.my-work', [$organization, $event])" icon="clipboard">Tugas saya</x-ui.button>
        @endif

        @if ($canClose && $operationStatus === OperationsStatus::Active)
            <x-ui.button variant="secondary" :href="route('workspace.events.operations.closure', [$organization, $event])" icon="check">Tutup operasional</x-ui.button>
        @endif
    </x-ui.page-header>

    @include('workspace.events.partials.event-tabs', ['organization' => $organization, 'event' => $event, 'setup' => $setup ?? null, 'active' => 'operations'])

    {{-- Status hero --}}
    <div class="mt-6 overflow-hidden rounded-3xl border border-line/80 bg-gradient-to-br from-accent-soft/80 via-surface to-blue-soft/50 shadow-raised animate-rise">
        <div class="flex flex-col gap-6 p-6 md:flex-row md:items-center md:justify-between md:p-8">
            <div class="min-w-0">
                <p class="section-kicker">Status operasional</p>
                <div class="mt-2 flex flex-wrap items-center gap-3">
                    <h2 class="font-serif text-3xl font-bold tracking-tight text-ink md:text-4xl">{{ $operationStatus->label() }}</h2>
                    <span class="badge {{ $operationStatus->badge() }}">{{ $operationStatus->label() }}</span>
                </div>
                <p class="mt-2 max-w-xl text-sm leading-relaxed text-muted">
                    @switch($operationStatus)
                        @case(OperationsStatus::Draft)
                            Operasional belum disiapkan. Lengkapi staf dan stasiun, lalu naikkan status ke persiapan.
                        @break
                        @case(OperationsStatus::Preparing)
                            Tim sedang bersiap. Selesaikan penghambat kesiapan sebelum menandai siap.
                        @break
                        @case(OperationsStatus::Ready)
                            Semua penghambat sudah bersih. Operasional dapat dimulai kapan saja.
                        @break
                        @case(OperationsStatus::Active)
                            Operasional sedang berjalan. Pantau insiden dan item run of show yang sedang berlangsung.
                        @break
                        @case(OperationsStatus::Completed)
                            Operasional sudah ditutup dan diarsipkan pada {{ $toLocal($operation?->completed_at)?->translatedFormat('d M Y, H:i') ?? '—' }}.
                        @break
                        @case(OperationsStatus::Cancelled)
                            Operasional dibatalkan. Buka kembali melalui halaman penutupan bila acara dilanjutkan.
                        @break
                    @endswitch
                </p>

                <div class="mt-4 flex flex-wrap items-center gap-x-5 gap-y-2 text-[11px] text-muted">
                    <span class="inline-flex items-center gap-1.5">
                        <x-ui.icon name="clock" class="h-3.5 w-3.5 text-faint" />
                        Zona waktu {{ $timezone }}
                    </span>
                    @if ($operation?->activated_at)
                        <span class="inline-flex items-center gap-1.5">
                            <x-ui.icon name="play" class="h-3.5 w-3.5 text-faint" />
                            Dimulai {{ $toLocal($operation->activated_at)?->translatedFormat('d M Y, H:i') }}
                        </span>
                    @endif
                    <span class="inline-flex items-center gap-1.5">
                        <x-ui.icon name="refresh" class="h-3.5 w-3.5 text-faint" />
                        Diperbarui {{ now()->translatedFormat('H:i') }}
                    </span>
                </div>
            </div>

            <div class="flex shrink-0 items-center gap-5 rounded-2xl border border-line/70 bg-surface/80 px-5 py-4 backdrop-blur">
                <div class="text-center">
                    <p class="text-[10px] font-semibold uppercase tracking-[0.12em] text-faint">Kesiapan</p>
                    <p class="mt-1 text-2xl font-semibold tracking-tight {{ $readinessStatus === ReadinessStatus::Ready ? 'text-success' : ($readinessStatus === ReadinessStatus::NotReady ? 'text-danger' : 'text-warning') }}">
                        {{ $readinessStatus->label() }}
                    </p>
                </div>
                <div class="h-12 w-px bg-line/80"></div>
                <div class="space-y-1 text-[11px]">
                    <p class="flex items-center gap-2 text-muted">
                        <span class="h-2 w-2 rounded-full bg-danger"></span>
                        {{ $readiness['blocking'] }} penghambat
                    </p>
                    <p class="flex items-center gap-2 text-muted">
                        <span class="h-2 w-2 rounded-full bg-warning"></span>
                        {{ $readiness['warnings'] }} peringatan
                    </p>
                </div>
            </div>
        </div>
    </div>

    {{-- Metrik utama --}}
    <div class="mt-5 grid grid-cols-2 gap-3 md:grid-cols-3 xl:grid-cols-6">
        <x-ui.stat-card label="Staf aktif" :value="$staff['active']" icon="users" tone="accent" :hint="$staff['pending'] > 0 ? $staff['pending'].' menunggu konfirmasi' : null" />
        <x-ui.stat-card label="Stasiun siap" :value="$stations['ready'].' / '.$stations['total']" icon="pin" tone="teal" :hint="$stations['without_staff'].' tanpa staf'" />
        <x-ui.stat-card label="Tugas terbuka" :value="$tasks['open'] + $tasks['assigned'] + $tasks['in_progress'] + $tasks['blocked']" icon="clipboard" tone="warning" :hint="$tasks['overdue'].' melewati tenggat'" />
        <x-ui.stat-card label="Insiden terbuka" :value="$incidents['open']" icon="alert" :tone="$incidents['critical'] > 0 ? 'danger' : 'warning'" :hint="$incidents['escalated'].' dieskalasi'" />
        <x-ui.stat-card label="Pengumuman" :value="$announcements['published']" icon="send" tone="accent" :hint="$announcements['pending_acknowledgements'].' konfirmasi tertunda'" />
        <x-ui.stat-card label="Check-in" :value="$checkins['completion_percentage'].'%'" icon="qr" tone="success" :hint="$checkins['total'].' tamu hadir'" />
    </div>

    <div class="mt-5 grid gap-5 xl:grid-cols-3">
        <div class="space-y-5 xl:col-span-2">
            {{-- Run of show saat ini --}}
            <div class="card overflow-hidden">
                <div class="flex flex-wrap items-center justify-between gap-2 border-b border-line/70 px-5 py-4">
                    <div>
                        <h2 class="section-title">Run of show</h2>
                        <p class="mt-0.5 text-xs text-faint">{{ $runSheet['completed'] }} selesai · {{ $runSheet['in_progress'] }} berjalan · {{ $runSheet['pending'] }} menunggu</p>
                    </div>
                    <x-ui.button variant="ghost" size="sm" :href="route('workspace.events.run-of-show.index', [$organization, $event])" icon="arrow-up-right">Buka run of show</x-ui.button>
                </div>

                <div class="grid gap-4 p-5 md:grid-cols-2">
                    <div class="rounded-2xl border border-line/70 bg-surface-2/40 p-4">
                        <p class="text-[10px] font-semibold uppercase tracking-[0.12em] text-faint">Sedang berjalan</p>
                        @if ($currentItem)
                            <p class="mt-2 text-sm font-semibold text-ink">{{ $currentItem->title }}</p>
                            <p class="mt-1 flex items-center gap-1.5 text-xs text-muted">
                                <x-ui.icon name="clock" class="h-3.5 w-3.5 text-faint" />
                                Rencana {{ $toLocal($currentItem->planned_start)?->translatedFormat('H:i') ?? '—' }}
                            </p>
                        @else
                            <p class="mt-2 text-sm text-muted">Belum ada item yang ditandai berjalan.</p>
                        @endif
                    </div>

                    <div class="rounded-2xl border border-line/70 bg-surface-2/40 p-4">
                        <p class="text-[10px] font-semibold uppercase tracking-[0.12em] text-faint">Berikutnya</p>
                        @if ($nextItem)
                            <p class="mt-2 text-sm font-semibold text-ink">{{ $nextItem->title }}</p>
                            <p class="mt-1 flex items-center gap-1.5 text-xs text-muted">
                                <x-ui.icon name="clock" class="h-3.5 w-3.5 text-faint" />
                                {{ $toLocal($nextItem->planned_start)?->translatedFormat('d M, H:i') ?? 'Belum dijadwalkan' }}
                            </p>
                        @else
                            <p class="mt-2 text-sm text-muted">Tidak ada item terjadwal berikutnya.</p>
                        @endif
                    </div>
                </div>

                @if ($runSheet['delayed'] > 0 || $runSheet['skipped'] > 0 || $runSheet['cancelled'] > 0)
                    <div class="flex flex-wrap items-center gap-2 border-t border-line/60 bg-warning/10 px-5 py-3 text-xs text-warning">
                        <x-ui.icon name="alert" class="h-3.5 w-3.5 shrink-0" />
                        <span>
                            @if ($runSheet['delayed'] > 0){{ $runSheet['delayed'] }} item terlambat.@endif
                            @if ($runSheet['skipped'] > 0) {{ $runSheet['skipped'] }} dilewati.@endif
                            @if ($runSheet['cancelled'] > 0) {{ $runSheet['cancelled'] }} dibatalkan.@endif
                        </span>
                    </div>
                @endif
            </div>

            {{-- Distribusi tugas & insiden --}}
            <div class="grid gap-5 md:grid-cols-2">
                <div class="card p-5">
                    <div class="flex items-center justify-between gap-2">
                        <h2 class="section-title">Tugas</h2>
                        <x-ui.badge variant="neutral" class="text-[10px]">{{ $tasks['total'] }} total</x-ui.badge>
                    </div>

                    <div class="mt-4 space-y-2.5">
                        @foreach ([
                            ['label' => 'Terbuka', 'value' => $tasks['open'], 'tone' => 'bg-surface-3'],
                            ['label' => 'Ditugaskan', 'value' => $tasks['assigned'], 'tone' => 'bg-accent'],
                            ['label' => 'Dikerjakan', 'value' => $tasks['in_progress'], 'tone' => 'bg-teal-soft'],
                            ['label' => 'Terhambat', 'value' => $tasks['blocked'], 'tone' => 'bg-warning'],
                            ['label' => 'Selesai', 'value' => $tasks['completed'], 'tone' => 'bg-success'],
                        ] as $row)
                            @php $width = $tasks['total'] > 0 ? max(2, (int) round(($row['value'] / $tasks['total']) * 100)) : 0; @endphp
                            <div>
                                <div class="flex items-center justify-between text-[11px]">
                                    <span class="text-muted">{{ $row['label'] }}</span>
                                    <span class="font-semibold tabular-nums text-ink">{{ $row['value'] }}</span>
                                </div>
                                <div class="mt-1 h-1.5 w-full overflow-hidden rounded-full bg-surface-2">
                                    <div class="h-full rounded-full {{ $row['tone'] }}" style="width: {{ $width }}%"></div>
                                </div>
                            </div>
                        @endforeach
                    </div>

                    @if ($tasks['critical_open'] > 0)
                        <p class="mt-4 flex items-center gap-2 rounded-xl border border-danger/25 bg-danger/10 px-3 py-2 text-[11px] text-danger">
                            <x-ui.icon name="alert" class="h-3.5 w-3.5 shrink-0" />
                            {{ $tasks['critical_open'] }} tugas kritis masih terbuka.
                        </p>
                    @endif
                </div>

                <div class="card p-5">
                    <div class="flex items-center justify-between gap-2">
                        <h2 class="section-title">Insiden</h2>
                        <x-ui.badge :variant="$incidents['open'] > 0 ? 'danger' : 'success'" class="text-[10px]">{{ $incidents['open'] }} terbuka</x-ui.badge>
                    </div>

                    <div class="mt-4 grid grid-cols-2 gap-2.5">
                        @foreach ([
                            ['label' => 'Terbuka', 'value' => $incidents['open'], 'tone' => 'text-danger'],
                            ['label' => 'Kritis', 'value' => $incidents['critical'], 'tone' => 'text-danger'],
                            ['label' => 'Tinggi', 'value' => $incidents['high'], 'tone' => 'text-warning'],
                            ['label' => 'Teratasi', 'value' => $incidents['resolved'], 'tone' => 'text-success'],
                        ] as $row)
                            <div class="rounded-xl border border-line/70 bg-surface-2/40 px-3 py-2.5">
                                <p class="text-[10px] font-semibold uppercase tracking-[0.1em] text-faint">{{ $row['label'] }}</p>
                                <p class="mt-1 text-lg font-semibold tabular-nums {{ $row['tone'] }}">{{ $row['value'] }}</p>
                            </div>
                        @endforeach
                    </div>

                    <p class="mt-4 text-xs text-muted">
                        {{ $incidents['escalated'] }} insiden dieskalasi dan {{ $incidents['closed'] }} sudah ditutup.
                    </p>
                </div>
            </div>

            {{-- Aktivitas --}}
            <div class="card overflow-hidden">
                <div class="flex items-center justify-between gap-2 border-b border-line/70 px-5 py-4">
                    <h2 class="section-title">Aktivitas terbaru</h2>
                    <span class="text-[11px] text-faint">30 catatan terakhir</span>
                </div>

                @if ($activity->isEmpty())
                    <div class="p-5">
                        <x-ui.empty-state icon="sparkles" title="Belum ada aktivitas" description="Setiap perubahan status, penugasan, dan insiden akan tercatat di sini." />
                    </div>
                @else
                    <ol class="divide-y divide-line/60">
                        @foreach ($activity as $log)
                            <li class="flex items-start gap-3 px-5 py-3">
                                <x-ui.avatar :name="$log->user?->name ?? 'Sistem'" size="sm" class="mt-0.5 shrink-0" />
                                <div class="min-w-0 flex-1">
                                    <p class="text-xs leading-relaxed text-ink">{{ $log->description }}</p>
                                    <p class="mt-0.5 text-[10px] text-faint">
                                        {{ $log->user?->name ?? 'Sistem' }} · {{ $log->created_at?->diffForHumans() ?? '—' }}
                                    </p>
                                </div>
                                <span class="hidden shrink-0 rounded-full border border-line/70 px-2 py-0.5 text-[9px] font-medium uppercase tracking-[0.08em] text-faint sm:inline-block">
                                    {{ str_replace('.', ' · ', $log->action) }}
                                </span>
                            </li>
                        @endforeach
                    </ol>
                @endif
            </div>
        </div>

        <div class="space-y-5">
            {{-- Kesiapan --}}
            <div class="card p-5">
                <div class="flex items-center justify-between gap-2">
                    <div>
                        <h2 class="section-title">Kesiapan operasional</h2>
                        <p class="mt-0.5 text-xs text-faint">Diperiksa dari data acara yang tersimpan.</p>
                    </div>
                    <x-ui.badge :variant="$readinessStatus->badge()">{{ $readinessStatus->label() }}</x-ui.badge>
                </div>

                <ul class="mt-4 space-y-2.5">
                    @foreach ($readinessItems as $item)
                        <li class="rounded-xl border border-line/60 bg-surface-2/30 px-3 py-2.5">
                            <div class="flex items-start justify-between gap-2">
                                <p class="text-xs font-semibold text-ink">{{ $item['label'] }}</p>
                                <x-ui.badge :variant="$item['status']->badge()" class="shrink-0 text-[9px]">{{ $item['status']->label() }}</x-ui.badge>
                            </div>
                            <p class="mt-1 text-[11px] leading-relaxed text-muted">{{ $item['message'] }}</p>
                        </li>
                    @endforeach
                </ul>

                @if ($blockingItems->isNotEmpty() || $warningItems->isNotEmpty())
                    <div class="mt-4 space-y-2">
                        @foreach ($blockingItems as $item)
                            <p class="flex items-start gap-2 text-[11px] text-danger">
                                <x-ui.icon name="alert" class="mt-0.5 h-3.5 w-3.5 shrink-0" />
                                <span><strong class="font-semibold">{{ $item['label'] }}:</strong> {{ $item['message'] }}</span>
                            </p>
                        @endforeach
                    </div>
                @endif
            </div>

            {{-- Ubah status --}}
            @if ($canManage && $transitionOptions->isNotEmpty())
                <div class="card p-5">
                    <div class="flex items-center gap-3">
                        <span class="flex h-10 w-10 items-center justify-center rounded-2xl bg-accent/15 text-accent">
                            <x-ui.icon name="refresh" class="h-5 w-5" />
                        </span>
                        <div>
                            <h2 class="section-title">Ubah status</h2>
                            <p class="mt-0.5 text-xs text-faint">Hanya transisi yang diizinkan yang tersedia.</p>
                        </div>
                    </div>

                    <form method="POST" action="{{ route('workspace.events.operations.transition', [$organization, $event]) }}" class="mt-5 space-y-4">
                        @csrf
                        <x-ui.select name="status" label="Status baru" :options="$transitionOptions" placeholder="Pilih status" required />
                        <x-ui.input name="reason" label="Alasan" placeholder="Wajib saat memakai override" />
                        <x-ui.textarea name="notes" label="Catatan" rows="2" placeholder="Konteks tambahan untuk tim" />

                        <label class="flex items-start gap-2.5 rounded-xl border border-warning/30 bg-warning/10 px-3 py-2.5">
                            <input type="checkbox" name="override" value="1" class="mt-0.5 h-4 w-4 rounded border-line text-accent focus:ring-accent/40">
                            <span class="text-[11px] leading-relaxed text-warning">
                                <strong class="font-semibold">Override kesiapan.</strong>
                                Gunakan hanya bila penghambat sudah diketahui dan disetujui. Alasan wajib diisi.
                            </span>
                        </label>

                        <button type="submit" class="btn btn-primary w-full justify-center">
                            <x-ui.icon name="check" class="h-4 w-4" /> Simpan status
                        </button>
                    </form>

                    @if ($canClose)
                        <a href="{{ route('workspace.events.operations.closure', [$organization, $event]) }}" class="mt-3 flex items-center justify-between rounded-xl border border-line/70 px-3 py-2 text-[11px] text-muted transition hover:bg-surface-2/50 hover:text-ink">
                            <span>Menutup operasional? Tinjau penghambat terlebih dahulu.</span>
                            <x-ui.icon name="chevron-right" class="h-3.5 w-3.5 shrink-0" />
                        </a>
                    @endif
                </div>
            @endif

            {{-- Tautan cepat --}}
            <div class="card p-5">
                <h2 class="section-title">Ruang kerja operasi</h2>
                <div class="mt-3 grid gap-2">
                    @if ($canViewStaff)
                        <a href="{{ route('workspace.events.operations.staff.index', [$organization, $event]) }}" class="group flex items-center gap-3 rounded-xl border border-line/60 px-3 py-2.5 transition hover:border-accent/40 hover:bg-surface-2/40">
                            <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-blue-soft text-ink"><x-ui.icon name="users" class="h-4 w-4" /></span>
                            <span class="min-w-0 flex-1">
                                <span class="block text-xs font-semibold text-ink">Staf operasional</span>
                                <span class="block text-[10px] text-faint">{{ $staff['active'] }} aktif · {{ $staff['pending'] }} menunggu</span>
                            </span>
                            <x-ui.icon name="chevron-right" class="h-3.5 w-3.5 text-faint transition group-hover:text-accent" />
                        </a>
                    @endif

                    @if ($canViewStations)
                        <a href="{{ route('workspace.events.operations.stations.index', [$organization, $event]) }}" class="group flex items-center gap-3 rounded-xl border border-line/60 px-3 py-2.5 transition hover:border-accent/40 hover:bg-surface-2/40">
                            <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-teal-soft text-ink"><x-ui.icon name="pin" class="h-4 w-4" /></span>
                            <span class="min-w-0 flex-1">
                                <span class="block text-xs font-semibold text-ink">Stasiun</span>
                                <span class="block text-[10px] text-faint">{{ $stations['active'] }} aktif · {{ $stations['temporarily_closed'] }} ditutup sementara</span>
                            </span>
                            <x-ui.icon name="chevron-right" class="h-3.5 w-3.5 text-faint transition group-hover:text-accent" />
                        </a>
                    @endif

                    @if ($canViewIncidents)
                        <a href="{{ route('workspace.events.operations.incidents.index', [$organization, $event]) }}" class="group flex items-center gap-3 rounded-xl border border-line/60 px-3 py-2.5 transition hover:border-accent/40 hover:bg-surface-2/40">
                            <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-danger/15 text-danger"><x-ui.icon name="alert" class="h-4 w-4" /></span>
                            <span class="min-w-0 flex-1">
                                <span class="block text-xs font-semibold text-ink">Insiden</span>
                                <span class="block text-[10px] text-faint">{{ $incidents['open'] }} terbuka · {{ $incidents['critical'] }} kritis</span>
                            </span>
                            <x-ui.icon name="chevron-right" class="h-3.5 w-3.5 text-faint transition group-hover:text-accent" />
                        </a>
                    @endif

                    @if ($canViewAnnouncements)
                        <a href="{{ route('workspace.events.operations.announcements.index', [$organization, $event]) }}" class="group flex items-center gap-3 rounded-xl border border-line/60 px-3 py-2.5 transition hover:border-accent/40 hover:bg-surface-2/40">
                            <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-accent/15 text-accent"><x-ui.icon name="send" class="h-4 w-4" /></span>
                            <span class="min-w-0 flex-1">
                                <span class="block text-xs font-semibold text-ink">Pengumuman</span>
                                <span class="block text-[10px] text-faint">{{ $announcements['published'] }} terbit</span>
                            </span>
                            <x-ui.icon name="chevron-right" class="h-3.5 w-3.5 text-faint transition group-hover:text-accent" />
                        </a>
                    @endif

                    @if ($canViewReports)
                        <a href="{{ route('workspace.events.operations.reports.index', [$organization, $event]) }}" class="group flex items-center gap-3 rounded-xl border border-line/60 px-3 py-2.5 transition hover:border-accent/40 hover:bg-surface-2/40">
                            <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-surface-3 text-ink"><x-ui.icon name="chart" class="h-4 w-4" /></span>
                            <span class="min-w-0 flex-1">
                                <span class="block text-xs font-semibold text-ink">Laporan</span>
                                <span class="block text-[10px] text-faint">Staf, stasiun, tugas, insiden</span>
                            </span>
                            <x-ui.icon name="chevron-right" class="h-3.5 w-3.5 text-faint transition group-hover:text-accent" />
                        </a>
                    @endif
                </div>
            </div>

            {{-- Pengumuman terbaru --}}
            @if ($canViewAnnouncements && $announcements['latest']->isNotEmpty())
                <div class="card p-5">
                    <h2 class="section-title">Pengumuman terbaru</h2>
                    <ul class="mt-3 space-y-2">
                        @foreach ($announcements['latest'] as $announcement)
                            <li class="rounded-xl border border-line/60 bg-surface-2/30 px-3 py-2.5">
                                <div class="flex items-start justify-between gap-2">
                                    <p class="text-xs font-semibold text-ink">{{ $announcement->title }}</p>
                                    <x-ui.badge :variant="$announcement->priority->badge()" class="shrink-0 text-[9px]">{{ $announcement->priority->label() }}</x-ui.badge>
                                </div>
                                <p class="mt-1 text-[11px] text-faint">{{ $announcement->published_at?->diffForHumans() ?? 'Belum diterbitkan' }}</p>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif
        </div>
    </div>
@endsection
