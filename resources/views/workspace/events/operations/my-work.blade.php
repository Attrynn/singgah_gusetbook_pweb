@extends('layouts.owner')

@section('title', 'Tugas saya · '.$event->name)

@php
    use App\Enums\ReadinessStatus;

    $memberships = collect($memberships ?? []);
    $stations = collect($stations ?? []);
    $tasks = collect($tasks ?? []);
    $schedule = collect($schedule ?? []);
    $announcements = collect($announcements ?? []);

    $announcements->each(function ($announcement): void {
        $announcement->load(['recipients' => fn ($query) => $query->where('user_id', auth()->id())]);
    });

    $readinessStatus = $readiness['status'];
    $timezone = $event->timezone ?: 'Asia/Jakarta';
    $toLocal = fn ($datetime) => $datetime?->copy()->setTimezone($timezone);

    $roleLabel = fn ($role): string => $role instanceof \App\Enums\EventStaffRole
        ? $role->label()
        : ucfirst(str_replace('_', ' ', (string) $role));

    $pendingAcknowledgements = $announcements->filter(function ($announcement) {
        $recipient = $announcement->recipients->first();

        return $announcement->requires_acknowledgement && $recipient?->acknowledged_at === null;
    });

    $overdueTasks = $tasks->filter(fn ($task) => $task->isOverdue());
@endphp

@section('content')
    <x-ui.page-header
        title="Tugas saya"
        eyebrow="Operasi"
        :subtitle="'Ringkasan penugasan pribadi Anda pada '.$event->name.'.'"
    >
        <x-ui.badge :variant="$readinessStatus->badge()">Kesiapan {{ $readinessStatus->label() }}</x-ui.badge>
        <x-ui.button variant="secondary" :href="route('workspace.events.operations.index', [$organization, $event])" icon="arrow-left">Ruang kendali</x-ui.button>
    </x-ui.page-header>

    @include('workspace.events.partials.event-tabs', ['organization' => $organization, 'event' => $event, 'setup' => $setup ?? null, 'active' => 'operations-my-work'])

    @if ($memberships->isEmpty())
        <div class="mt-6 flex items-start gap-3 rounded-2xl border border-warning/30 bg-warning/10 p-4 text-sm text-warning">
            <x-ui.icon name="info" class="mt-0.5 h-4 w-4 shrink-0" />
            <p>Anda belum memiliki penugasan staf aktif pada acara ini. Hubungi koordinator operasional bila seharusnya sudah ditugaskan.</p>
        </div>
    @endif

    {{-- Peran & metrik pribadi --}}
    <div class="mt-6 grid gap-5 lg:grid-cols-4">
        <div class="card p-5 lg:col-span-2">
            <p class="section-kicker">Peran Anda</p>
            <div class="mt-2 flex flex-wrap items-center gap-2">
                @forelse ($memberships as $membership)
                    <span class="inline-flex items-center gap-2 rounded-full border border-line/70 bg-surface-2/50 px-3 py-1.5 text-xs font-semibold text-ink">
                        <x-ui.icon name="shield" class="h-3.5 w-3.5 text-accent" />
                        {{ $roleLabel($membership->role) }}
                    </span>
                @empty
                    <span class="text-sm text-muted">Belum ada peran aktif.</span>
                @endforelse
            </div>
            <p class="mt-3 text-[11px] leading-relaxed text-faint">
                Penugasan berlaku sampai koordinator mencabut atau menangguhkan akses Anda.
            </p>
        </div>

        <div class="card p-5">
            <p class="text-[10px] font-semibold uppercase tracking-[0.12em] text-faint">Tugas terbuka</p>
            <p class="mt-2 text-2xl font-semibold tracking-tight text-ink">{{ $tasks->count() }}</p>
            <p class="mt-1 text-[11px] {{ $overdueTasks->isNotEmpty() ? 'text-danger' : 'text-faint' }}">
                {{ $overdueTasks->count() }} melewati tenggat
            </p>
        </div>

        <div class="card p-5">
            <p class="text-[10px] font-semibold uppercase tracking-[0.12em] text-faint">Perlu konfirmasi</p>
            <p class="mt-2 text-2xl font-semibold tracking-tight {{ $pendingAcknowledgements->isNotEmpty() ? 'text-danger' : 'text-ink' }}">{{ $pendingAcknowledgements->count() }}</p>
            <p class="mt-1 text-[11px] text-faint">Pengumuman wajib dibaca</p>
        </div>
    </div>

    <div class="mt-5 grid gap-5 lg:grid-cols-3">
        <div class="space-y-5 lg:col-span-2">
            {{-- Tugas --}}
            <div class="card overflow-hidden">
                <div class="flex flex-wrap items-center justify-between gap-2 border-b border-line/70 px-5 py-4">
                    <div>
                        <h2 class="section-title">Tugas saya</h2>
                        <p class="mt-0.5 text-xs text-faint">Diurutkan berdasarkan tenggat terdekat.</p>
                    </div>
                    <x-ui.badge variant="neutral" class="text-[10px]">{{ $tasks->count() }} tugas</x-ui.badge>
                </div>

                @if ($tasks->isEmpty())
                    <div class="p-5">
                        <x-ui.empty-state icon="clipboard" title="Tidak ada tugas terbuka" description="Semua tugas yang ditugaskan kepada Anda sudah selesai atau belum ada penugasan baru." />
                    </div>
                @else
                    <ul class="divide-y divide-line/60">
                        @foreach ($tasks as $task)
                            @php
                                $isOverdue = $task->isOverdue();
                                $checklist = $task->checklistItems;
                                $completedItems = $checklist->where('completed', true)->count();
                            @endphp
                            <li class="px-5 py-4">
                                <div class="flex flex-wrap items-start justify-between gap-3">
                                    <div class="min-w-0 flex-1">
                                        <div class="flex flex-wrap items-center gap-2">
                                            <h3 class="text-sm font-semibold text-ink">{{ $task->title }}</h3>
                                            <x-ui.badge :variant="$task->priority->badge()" class="text-[10px]">{{ $task->priority->label() }}</x-ui.badge>
                                            <x-ui.badge :variant="$task->status->badge()" class="text-[10px]">{{ $task->status->label() }}</x-ui.badge>
                                        </div>

                                        @if ($task->description)
                                            <p class="mt-1 line-clamp-2 text-xs leading-relaxed text-muted">{{ $task->description }}</p>
                                        @endif

                                        <div class="mt-2 flex flex-wrap items-center gap-x-3 gap-y-1 text-[11px] text-muted">
                                            @if ($task->station)
                                                <span class="inline-flex items-center gap-1"><x-ui.icon name="pin" class="h-3 w-3 text-faint" /> {{ $task->station->name }}</span>
                                            @endif
                                            <span class="inline-flex items-center gap-1 {{ $isOverdue ? 'text-danger' : '' }}">
                                                <x-ui.icon name="clock" class="h-3 w-3 {{ $isOverdue ? '' : 'text-faint' }}" />
                                                {{ $task->due_at ? $toLocal($task->due_at)?->translatedFormat('d M, H:i') : 'Tanpa tenggat' }}
                                            </span>
                                            @if ($checklist->isNotEmpty())
                                                <span class="inline-flex items-center gap-1">
                                                    <x-ui.icon name="list" class="h-3 w-3 text-faint" />
                                                    {{ $completedItems }}/{{ $checklist->count() }} checklist
                                                </span>
                                            @endif
                                        </div>
                                    </div>

                                    <form method="POST" action="{{ route('workspace.events.operations.tasks.status', [$organization, $event, $task]) }}" class="flex shrink-0 items-center gap-1.5">
                                        @csrf
                                        <select name="status" class="input input-sm appearance-none" aria-label="Ubah status {{ $task->title }}" x-on:change="$el.form.requestSubmit()">
                                            <option value="{{ $task->status->value }}" selected>{{ $task->status->label() }}</option>
                                            @foreach ($task->status->allowedTransitions() as $nextStatus)
                                                <option value="{{ $nextStatus->value }}">{{ $nextStatus->label() }}</option>
                                            @endforeach
                                        </select>
                                        <noscript><button type="submit" class="btn btn-secondary btn-sm">Simpan</button></noscript>
                                    </form>
                                </div>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>

            {{-- Jadwal pribadi --}}
            <div class="card overflow-hidden">
                <div class="flex flex-wrap items-center justify-between gap-2 border-b border-line/70 px-5 py-4">
                    <div>
                        <h2 class="section-title">Jadwal saya</h2>
                        <p class="mt-0.5 text-xs text-faint">Item run of show yang menjadi tanggung jawab Anda.</p>
                    </div>
                    <x-ui.button variant="ghost" size="sm" :href="route('workspace.events.run-of-show.index', [$organization, $event])" icon="arrow-up-right">Run of show</x-ui.button>
                </div>

                @if ($schedule->isEmpty())
                    <div class="p-5">
                        <x-ui.empty-state icon="clock" title="Belum ada jadwal" description="Anda belum ditetapkan sebagai PIC atau pelaksana pada item run of show." />
                    </div>
                @else
                    <ol class="divide-y divide-line/60">
                        @foreach ($schedule as $item)
                            <li class="flex items-start gap-3 px-5 py-3.5">
                                <div class="w-14 shrink-0 text-center">
                                    <p class="text-sm font-bold tabular-nums text-ink">{{ $toLocal($item->planned_start)?->format('H:i') ?? '—' }}</p>
                                    <p class="text-[10px] text-faint">{{ $toLocal($item->planned_start)?->translatedFormat('d M') }}</p>
                                </div>
                                <div class="min-w-0 flex-1">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <p class="text-sm font-semibold text-ink">{{ $item->title }}</p>
                                        <x-ui.badge :variant="$item->status->badge()" class="text-[10px]">{{ $item->status->label() }}</x-ui.badge>
                                    </div>
                                    @if ($item->internal_description)
                                        <p class="mt-0.5 text-[11px] text-muted">{{ $item->internal_description }}</p>
                                    @endif
                                </div>
                            </li>
                        @endforeach
                    </ol>
                @endif
            </div>
        </div>

        <div class="space-y-5">
            {{-- Stasiun saya --}}
            <div class="card p-5">
                <h2 class="section-title">Stasiun saya</h2>
                <p class="mt-0.5 text-xs text-faint">Tempat Anda bertugas saat acara berlangsung.</p>

                @if ($stations->isEmpty())
                    <p class="mt-4 rounded-xl border border-dashed border-line px-3 py-4 text-center text-xs text-muted">
                        Belum ada penugasan stasiun.
                    </p>
                @else
                    <ul class="mt-4 space-y-2.5">
                        @foreach ($stations as $station)
                            <li class="rounded-xl border border-line/70 bg-surface-2/40 px-3 py-3">
                                <div class="flex items-start justify-between gap-2">
                                    <p class="text-xs font-semibold text-ink">{{ $station->name }}</p>
                                    <x-ui.badge :variant="$station->status->badge()" class="shrink-0 text-[9px]">{{ $station->status->label() }}</x-ui.badge>
                                </div>
                                @if ($station->location)
                                    <p class="mt-1 inline-flex items-center gap-1 text-[11px] text-muted">
                                        <x-ui.icon name="map-pin" class="h-3 w-3 text-faint" /> {{ $station->location }}
                                    </p>
                                @endif
                                @if ($station->supervisor)
                                    <p class="mt-1 text-[11px] text-faint">Supervisor: {{ $station->supervisor->user?->name ?? '—' }}</p>
                                @endif
                                @if ($station->instructions)
                                    <p class="mt-2 rounded-lg bg-surface px-2 py-1.5 text-[11px] leading-relaxed text-muted">{{ $station->instructions }}</p>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>

            {{-- Pengumuman --}}
            <div class="card p-5">
                <div class="flex items-center justify-between gap-2">
                    <h2 class="section-title">Pengumuman</h2>
                    <x-ui.badge variant="neutral" class="text-[10px]">{{ $announcements->count() }}</x-ui.badge>
                </div>

                @if ($announcements->isEmpty())
                    <p class="mt-4 rounded-xl border border-dashed border-line px-3 py-4 text-center text-xs text-muted">
                        Belum ada pengumuman untuk Anda.
                    </p>
                @else
                    <ul class="mt-4 space-y-3">
                        @foreach ($announcements as $announcement)
                            @php
                                $recipient = $announcement->recipients->first();
                                $isRead = $recipient?->read_at !== null;
                                $isAcknowledged = $recipient?->acknowledged_at !== null;
                                $needsAck = $announcement->requires_acknowledgement;
                            @endphp
                            <li class="rounded-xl border {{ $needsAck && ! $isAcknowledged ? 'border-danger/30 bg-danger/5' : 'border-line/70 bg-surface-2/30' }} p-3">
                                <div class="flex flex-wrap items-center gap-1.5">
                                    <p class="min-w-0 flex-1 text-xs font-semibold text-ink">{{ $announcement->title }}</p>
                                    <x-ui.badge :variant="$announcement->priority->badge()" class="text-[9px]">{{ $announcement->priority->label() }}</x-ui.badge>
                                </div>
                                <p class="mt-1.5 text-[11px] leading-relaxed text-muted">{{ $announcement->body }}</p>
                                <p class="mt-1.5 text-[10px] text-faint">{{ $announcement->published_at?->diffForHumans() ?? '—' }}</p>

                                <div class="mt-2 flex flex-wrap items-center gap-2">
                                    @if (! $isRead)
                                        <form method="POST" action="{{ route('workspace.events.operations.announcements.read', [$organization, $event, $announcement]) }}">
                                            @csrf
                                            <button type="submit" class="btn btn-ghost btn-sm">
                                                <x-ui.icon name="eye" class="h-3 w-3" /> Tandai dibaca
                                            </button>
                                        </form>
                                    @else
                                        <span class="inline-flex items-center gap-1 text-[10px] font-medium text-success">
                                            <x-ui.icon name="check" class="h-3 w-3" /> Sudah dibaca
                                        </span>
                                    @endif

                                    @if ($needsAck)
                                        @if ($isAcknowledged)
                                            <span class="inline-flex items-center gap-1 text-[10px] font-medium text-success">
                                                <x-ui.icon name="shield" class="h-3 w-3" /> Dikonfirmasi
                                            </span>
                                        @else
                                            <form method="POST" action="{{ route('workspace.events.operations.announcements.acknowledge', [$organization, $event, $announcement]) }}">
                                                @csrf
                                                <button type="submit" class="btn btn-primary btn-sm">
                                                    <x-ui.icon name="check" class="h-3 w-3" /> Konfirmasi
                                                </button>
                                            </form>
                                        @endif
                                    @endif
                                </div>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>
        </div>
    </div>
@endsection
