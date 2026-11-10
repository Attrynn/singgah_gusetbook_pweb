@extends('layouts.owner')

@section('title', 'Insiden · '.$event->name)

@php
    use App\Enums\IncidentCategory;
    use App\Enums\IncidentSeverity;
    use App\Enums\IncidentStatus;

    $incidents = collect($incidents ?? []);
    $stations = collect($stations ?? []);
    $managers = collect($managers ?? []);
    $categories = collect($categories ?? IncidentCategory::cases());
    $severities = collect($severities ?? IncidentSeverity::cases());
    $statuses = collect($statuses ?? IncidentStatus::cases());
    $filters = array_merge(['status' => '', 'severity' => ''], $filters ?? []);

    $managerOptions = $managers->mapWithKeys(fn ($membership) => [
        $membership->user_id => ($membership->user?->name ?? 'Manajer').' — '.($membership->role instanceof \App\Enums\EventStaffRole ? $membership->role->label() : ''),
    ]);

    $categoryOptions = $categories->mapWithKeys(fn ($c) => [$c->value => $c->label()]);
    $severityOptions = $severities->mapWithKeys(fn ($s) => [$s->value => $s->label()]);
    $stationOptions = $stations->mapWithKeys(fn ($station) => [$station->id => $station->name]);

    $typeLabel = fn (string $type): string => match ($type) {
        'reported' => 'Dilaporkan',
        'updated' => 'Diperbarui',
        'assigned' => 'Penugasan',
        'status_changed' => 'Status berubah',
        'escalated' => 'Dieskalasi',
        'resolved' => 'Diselesaikan',
        'closed' => 'Ditutup',
        'reopened' => 'Dibuka kembali',
        default => ucfirst($type),
    };

    $openCount = $incidents->where('status', IncidentStatus::Open)->count();
    $seriousCount = $incidents->whereIn('severity', ['high', 'critical'])->where('status', IncidentStatus::Open)->count();
    $resolvedCount = $incidents->where('status', IncidentStatus::Resolved)->count();
    $closedCount = $incidents->where('status', IncidentStatus::Closed)->count();

    $hasActiveFilters = filled($filters['status']) || filled($filters['severity']);
@endphp

@section('content')
    <x-ui.page-header
        title="Insiden"
        eyebrow="Operasi"
        :subtitle="'Laporkan, kelola, dan selesaikan masalah operasional untuk '.$event->name.'.'"
    >
        <x-ui.badge :variant="$seriousCount > 0 ? 'danger' : 'neutral'">{{ $incidents->count() }} insiden</x-ui.badge>
        <x-ui.button variant="secondary" :href="route('workspace.events.operations.index', [$organization, $event])" icon="arrow-left">Ruang kendali</x-ui.button>
    </x-ui.page-header>

    @include('workspace.events.partials.event-tabs', ['organization' => $organization, 'event' => $event, 'setup' => $setup ?? null, 'active' => 'operations-incidents'])

    {{-- Filter --}}
    <div class="card mt-6 p-4">
        <form method="GET" action="{{ route('workspace.events.operations.incidents.index', [$organization, $event]) }}" class="grid gap-3 md:grid-cols-12">
            <div class="md:col-span-4">
                <x-ui.select name="status" label="Status" :options="$statuses" :value="$filters['status']" placeholder="Semua status" />
            </div>
            <div class="md:col-span-4">
                <x-ui.select name="severity" label="Ketegaran" :options="$severities" :value="$filters['severity']" placeholder="Semua ketegaran" />
            </div>
            <div class="flex items-end gap-2 md:col-span-4">
                <button type="submit" class="btn btn-primary flex-1 justify-center">
                    <x-ui.icon name="filter" class="h-4 w-4" /> Terapkan
                </button>
                @if ($hasActiveFilters)
                    <a href="{{ route('workspace.events.operations.incidents.index', [$organization, $event]) }}" class="btn btn-ghost">Reset</a>
                @endif
            </div>
        </form>
    </div>

    <div class="mt-5 grid grid-cols-2 gap-3 md:grid-cols-4">
        <x-ui.stat-card label="Terbuka" :value="$openCount" icon="alert" tone="warning" hint="{{ $seriousCount }} serius" />
        <x-ui.stat-card label="Serius" :value="$seriousCount" icon="shield" :tone="$seriousCount > 0 ? 'danger' : 'teal'" />
        <x-ui.stat-card label="Teratasi" :value="$resolvedCount" icon="check" tone="success" />
        <x-ui.stat-card label="Ditutup" :value="$closedCount" icon="check" tone="accent" />
    </div>

    <div class="mt-5 grid gap-5 lg:grid-cols-3">
        <div class="space-y-4 lg:col-span-2">
            @if ($incidents->isEmpty())
                <x-ui.empty-state icon="alert" title="Belum ada insiden" description="Setiap masalah di lapangan akan tercatat di sini." />
            @else
                @foreach ($incidents as $incident)
                    @php
                        $isOpen = $incident->status->isOpen();
                        $timeline = $incident->updates;
                        $resolutionNotes = $incident->resolution_notes;
                    @endphp

                    <article class="card overflow-hidden animate-float-in">
                        <div class="p-4">
                            <div class="flex flex-wrap items-start justify-between gap-3">
                                <div class="min-w-0 flex-1">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <h3 class="text-sm font-semibold text-ink">{{ $incident->title }}</h3>
                                        <x-ui.badge :variant="$incident->severity->badge()" class="text-[10px]">{{ $incident->severity->label() }}</x-ui.badge>
                                        <x-ui.badge :variant="$incident->status->badge()" class="text-[10px]">{{ $incident->status->label() }}</x-ui.badge>
                                        @if ($incident->category)
                                            <x-ui.badge variant="neutral" class="text-[10px]">{{ $incident->category->label() }}</x-ui.badge>
                                        @endif
                                        @if ($incident->station)
                                            <x-ui.badge variant="info" class="text-[10px]">{{ $incident->station->name }}</x-ui.badge>
                                        @endif
                                    </div>

                                    @if ($incident->description)
                                        <p class="mt-1 text-xs leading-relaxed text-muted">{{ $incident->description }}</p>
                                    @endif

                                    <div class="mt-2 flex flex-wrap items-center gap-x-3 gap-y-1 text-[11px] text-muted">
                                        <span class="inline-flex items-center gap-1"><x-ui.icon name="user" class="h-3 w-3 text-faint" /> {{ $incident->reporter?->name ?? '—' }}</span>
                                        @if ($incident->assignee)
                                            <span class="inline-flex items-center gap-1"><x-ui.icon name="user" class="h-3 w-3 text-faint" /> {{ $incident->assignee->name ?? '—' }}</span>
                                        @endif
                                        <span class="inline-flex items-center gap-1"><x-ui.icon name="clock" class="h-3 w-3 text-faint" /> {{ $incident->reported_at?->diffForHumans() ?? '—' }}</span>
                                        @if ($incident->escalated_to)
                                            <span class="inline-flex items-center gap-1 text-warning"><x-ui.icon name="arrow-up-right" class="h-3 w-3" /> dieskalasi ke {{ $incident->escalated_to->name ?? '—' }}</span>
                                        @endif
                                    </div>

                                    @if ($resolutionNotes)
                                        <p class="mt-2 rounded-lg border border-success/30 bg-success/10 px-2.5 py-2 text-[11px] leading-relaxed text-success">
                                            <span class="font-semibold text-ink">Solusi:</span> {{ $resolutionNotes }}
                                        </p>
                                    @endif
                                </div>

                                <div class="flex shrink-0 flex-col gap-1.5 sm:flex-row sm:items-end">
                                    <form method="POST" action="{{ route('workspace.events.operations.incidents.status', [$organization, $event, $incident]) }}" class="flex w-full items-center gap-1.5 sm:w-auto">
                                        @csrf
                                        <select name="status" class="input input-sm appearance-none" aria-label="Ubah status {{ $incident->title }}" x-on:change="$el.form.requestSubmit()">
                                            <option value="{{ $incident->status->value }}" selected>{{ $incident->status->label() }}</option>
                                            @foreach ($incident->status->allowedTransitions() as $nextStatus)
                                                <option value="{{ $nextStatus->value }}">{{ $nextStatus->label() }}</option>
                                            @endforeach
                                        </select>
                                        <noscript><button type="submit" class="btn btn-secondary btn-sm">Simpan</button></noscript>
                                    </form>

                                    @if ($canResolve && $incident->status !== IncidentStatus::Resolved && $incident->status !== IncidentStatus::Closed)
                                        <a href="#resolve-{{ $incident->id }}" class="btn btn-primary btn-sm shrink-0" x-on:click.prevent="$el.closest('article').querySelector('details').open = true; $el.closest('article').querySelector('#resolve-{{ $incident->id }}')?.focus()">
                                            <x-ui.icon name="check" class="h-3.5 w-3.5" /> Selesaikan
                                        </a>
                                    @endif
                                </div>
                            </div>
                        </div>

                        <details class="group border-t border-line/60 bg-surface-2/20">
                            <summary class="flex cursor-pointer list-none items-center justify-between gap-2 px-4 py-2.5 text-[11px] font-semibold text-muted transition hover:text-ink">
                                <span class="inline-flex items-center gap-1.5"><x-ui.icon name="list" class="h-3.5 w-3.5" /> Riwayat dan detail</span>
                                <x-ui.icon name="chevron-down" class="h-3.5 w-3.5 transition group-open:rotate-180" />
                            </summary>

                            <div class="space-y-3 border-t border-line/50 p-4">
                                <ol class="relative space-y-3 pl-4">
                                    <span class="absolute left-0 top-1 h-2.5 w-2.5 rounded-full bg-danger"></span>
                                    @foreach ($timeline as $update)
                                        <li class="flex gap-3">
                                            <span class="mt-0.5 flex h-5 w-5 shrink-0 items-center justify-center rounded-full bg-surface-2 text-[9px] font-semibold text-ink">
                                                <x-ui.icon name="clock" class="h-2.5 w-2.5" />
                                            </span>
                                            <div class="min-w-0 flex-1">
                                                <p class="text-[11px] font-semibold text-ink">{{ $update->actor?->name ?? 'Sistem' }} <span class="font-normal text-faint">· {{ $update->created_at?->diffForHumans() }}</span></p>
                                                <p class="mt-0.5 text-[11px] leading-relaxed text-muted">{{ $typeLabel($update->type) }}</p>
                                                @if ($update->from_status && $update->to_status)
                                                    <p class="mt-0.5 text-[11px] text-faint">
                                                        {{ $update->from_status->label() }} → {{ $update->to_status->label() }}
                                                    </p>
                                                @endif
                                                @if (! empty($update->metadata))
                                                    @php
                                                        $metaNotes = collect(['reason', 'notes', 'resolution_notes'])
                                                            ->mapWithKeys(fn ($key) => [$key => $update->metadata[$key] ?? null])
                                                            ->filter();
                                                    @endphp
                                                    @if ($metaNotes->isNotEmpty())
                                                        <ul class="mt-1 space-y-0.5 text-[10px] text-faint">
                                                            @foreach ($metaNotes as $metaKey => $metaValue)
                                                                <li>{{ str_replace('_', ' ', $metaKey) }}: {{ $metaValue }}</li>
                                                            @endforeach
                                                        </ul>
                                                    @endif
                                                @endif
                                            </div>
                                        </li>
                                    @endforeach
                                </ol>

                                @if ($canManage && $incident->status !== IncidentStatus::Resolved && $incident->status !== IncidentStatus::Closed)
                                    <form method="POST" action="{{ route('workspace.events.operations.incidents.escalate', [$organization, $event, $incident]) }}" class="rounded-xl border border-warning/30 bg-warning/10 p-3">
                                        <h4 class="text-[11px] font-semibold uppercase tracking-[0.08em] text-faint">Eskalasi</h4>
                                        <p class="mt-1 text-[10px] text-muted">Bila insiden melebihi tanggung jawab staf yang ada.</p>
                                        <div class="mt-2 flex flex-wrap items-end gap-2">
                                            <div class="min-w-0 flex-1">
                                                <label class="label" for="escalate-{{ $incident->id }}">Target eskalasi</label>
                                                <select id="escalate-{{ $incident->id }}" name="escalated_to" class="input input-sm appearance-none">
                                                    <option value="">Pilih manajer</option>
                                                    @foreach ($managerOptions as $userId => $userName)
                                                        <option value="{{ $userId }}">{{ $userName }}</option>
                                                    @endforeach
                                                </select>
                                            </div>
                                            <x-ui.input name="reason" label="Alasan" placeholder="Mengapa diekskalasi" />
                                            <x-ui.select name="severity" label="Ketegaran baru" :options="$severityOptions" />
                                        </div>
                                        <button type="submit" class="btn btn-secondary btn-sm mt-2">
                                            <x-ui.icon name="send" class="h-3.5 w-3.5" /> Eskalasi
                                        </button>
                                    </form>
                                @endif

                                @if ($canResolve)
                                    <form method="POST" action="{{ route('workspace.events.operations.incidents.resolve', [$organization, $event, $incident]) }}" class="rounded-xl border border-success/30 bg-success/10 p-3">
                                        <h4 class="text-[11px] font-semibold uppercase tracking-[0.08em] text-success">Selesaikan insiden</h4>
                                        <p class="mt-1 text-[10px] text-muted">Catat solusi yang diterapkan.</p>
                                        <x-ui.textarea name="resolution_notes" label="Solusi" rows="3" placeholder="Langkah penyelesaian dan hasil akhir" />
                                        <button type="submit" class="btn btn-primary btn-sm mt-2">
                                            <x-ui.icon name="check" class="h-3.5 w-3.5" /> Selesaikan
                                        </button>
                                    </form>
                                @endif

                                @if ($canManage)
                                    <form method="POST" action="{{ route('workspace.events.operations.incidents.assign', [$organization, $event, $incident]) }}" class="rounded-xl border border-line/60 bg-surface p-3">
                                        <h4 class="text-[11px] font-semibold uppercase tracking-[0.08em] text-faint">Atur penanggung jawab</h4>
                                        <p class="mt-1 text-[10px] text-muted">Pilih anggota staf aktif yang akan menangani insiden ini.</p>
                                        <div class="mt-2 flex flex-wrap items-end gap-2">
                                            <div class="min-w-0 flex-1">
                                                <label class="label" for="assign-{{ $incident->id }}">Penanggung jawab</label>
                                                <select id="assign-{{ $incident->id }}" name="assigned_to" class="input input-sm appearance-none">
                                                    <option value="">Pilih anggota staf</option>
                                                    @foreach ($managerOptions as $userId => $userName)
                                                        <option value="{{ $userId }}" @selected((int) $incident->assigned_to === (int) $userId)>{{ $userName }}</option>
                                                    @endforeach
                                                </select>
                                            </div>
                                        </div>
                                        <button type="submit" class="btn btn-secondary btn-sm mt-2">
                                            <x-ui.icon name="save" class="h-3.5 w-3.5" /> Atur penugasan
                                        </button>
                                    </form>
                                @endif

                                @if ($canManage)
                                    <div class="rounded-xl border border-line/60 bg-surface p-3">
                                        <h4 class="text-[11px] font-semibold uppercase tracking-[0.08em] text-faint">Edit insiden</h4>
                                        <form method="POST" action="{{ route('workspace.events.operations.incidents.update', [$organization, $event, $incident]) }}" class="mt-3 grid gap-3 sm:grid-cols-2">
                                            @csrf
                                            @method('PUT')
                                            <div class="sm:col-span-2">
                                                <x-ui.input name="title" label="Judul" :value="$incident->title" required />
                                            </div>
                                            <div class="sm:col-span-2">
                                                <x-ui.textarea name="description" label="Deskripsi" :value="$incident->description" rows="2" />
                                            </div>
                                            <x-ui.select name="category" label="Kategori" :options="$categoryOptions" :value="$incident->category?->value" />
                                            <x-ui.select name="severity" label="Ketegaran" :options="$severityOptions" :value="$incident->severity->value" />
                                            <x-ui.select name="station_id" label="Stasiun" :options="$stationOptions" :value="$incident->station_id" placeholder="Tanpa stasiun" />
                                            <div class="flex justify-end sm:col-span-2">
                                                <button type="submit" class="btn btn-primary btn-sm">
                                                    <x-ui.icon name="save" class="h-3.5 w-3.5" /> Simpan perubahan
                                                </button>
                                            </div>
                                        </form>
                                    </div>
                                @endif
                            </div>
                        </details>
                    </article>
                @endforeach
            @endif
        </div>

        <div class="space-y-5">
            @if ($canReport)
                <div class="card p-5">
                    <div class="flex items-center gap-3">
                        <span class="flex h-10 w-10 items-center justify-center rounded-2xl bg-danger/15 text-danger">
                            <x-ui.icon name="alert" class="h-5 w-5" />
                        </span>
                        <div>
                            <h2 class="section-title">Laporkan insiden</h2>
                            <p class="mt-0.5 text-xs text-faint">Catat masalah operasional yang terjadi di lapangan.</p>
                        </div>
                    </div>

                    <form method="POST" action="{{ route('workspace.events.operations.incidents.store', [$organization, $event]) }}" class="mt-5 space-y-4">
                        @csrf
                        <x-ui.input name="title" label="Judul insiden" required placeholder="Contoh: Kerusakan lampu panggung" />
                        <x-ui.textarea name="description" label="Deskripsi" rows="3" placeholder="Konteks, dampak, dan langkah yang sudah dilakukan" />
                        <div class="grid gap-3 sm:grid-cols-2">
                            <x-ui.select name="category" label="Kategori" :options="$categories" :value="null" placeholder="Pilih kategori" />
                            <x-ui.select name="severity" label="Ketegaran" :options="$severities" :value="null" placeholder="Pilih ketegaran" />
                        </div>
                        <x-ui.select name="station_id" label="Stasiun" :options="$stationOptions" :value="null" placeholder="Tanpa stasiun" />
                        <x-ui.select name="assigned_to" label="Penanggung jawab" :options="$managerOptions" placeholder="Belum ditugaskan" />

                        <button type="submit" class="btn btn-primary w-full justify-center">
                            <x-ui.icon name="send" class="h-4 w-4" /> Laporkan insiden
                        </button>
                    </form>
                </div>
            @endif

            <div class="card p-5">
                <h2 class="section-title">Alur insiden</h2>
                <ol class="mt-3 space-y-2.5 text-[11px] leading-relaxed text-muted">
                    <li class="flex items-start gap-2"><span class="mt-0.5 flex h-5 w-5 shrink-0 items-center justify-center rounded-full bg-surface-2 text-[9px] font-semibold text-ink">1</span> Insiden dilaporkan dan ditandai terbuka.</li>
                    <li class="flex items-start gap-2"><span class="mt-0.5 flex h-5 w-5 shrink-0 items-center justify-center rounded-full bg-surface-2 text-[9px] font-semibold text-ink">2</span> Penanggung jawab ditugaskan dan status ditandai ditangani.</li>
                    <li class="flex items-start gap-2"><span class="mt-0.5 flex h-5 w-5 shrink-0 items-center justify-center rounded-full bg-surface-2 text-[9px] font-semibold text-ink">3</span> Insiden dieskalasi bila melebihi tanggung jawab staf.</li>
                    <li class="flex items-start gap-2"><span class="mt-0.5 flex h-5 w-5 shrink-0 items-center justify-center rounded-full bg-surface-2 text-[9px] font-semibold text-ink">4</span> Insiden diselesaikan dan ditandai teratasi.</li>
                </ol>
            </div>

            @cannot('reportIncidents', $event)
                <div class="card p-5">
                    <div class="flex items-start gap-3 rounded-xl bg-surface-2/60 p-3 text-xs text-muted">
                        <x-ui.icon name="lock" class="mt-0.5 h-4 w-4 shrink-0" />
                        <p>Anda dapat melihat dan menanggapi insiden, tetapi tidak dapat melaporkan baru.</p>
                    </div>
                </div>
            @endcannot
        </div>
    </div>
@endsection
