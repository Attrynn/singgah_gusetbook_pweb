@extends('layouts.owner')

@section('title', 'Tugas operasional · '.$event->name)

@php
    use App\Enums\TaskStatus;

    $tasks = collect($tasks ?? []);
    $staff = collect($staff ?? []);
    $stations = collect($stations ?? []);
    $priorities = collect($priorities ?? []);
    $statuses = collect($statuses ?? TaskStatus::cases());
    $filters = array_merge(['status' => '', 'priority' => '', 'station_id' => ''], $filters ?? []);

    $roleLabel = fn ($role): string => $role instanceof \App\Enums\EventStaffRole
        ? $role->label()
        : ucfirst(str_replace('_', ' ', (string) $role));

    $staffOptions = $staff->mapWithKeys(fn ($membership) => [
        $membership->id => ($membership->user?->name ?? 'Anggota').' — '.$roleLabel($membership->role),
    ]);

    $stationOptions = $stations->mapWithKeys(fn ($station) => [$station->id => $station->name]);
    $taskOptions = $tasks->mapWithKeys(fn ($task) => [$task->id => $task->title]);

    $groups = [
        ['status' => TaskStatus::Open, 'title' => 'Terbuka', 'description' => 'Belum ditugaskan ke staf.'],
        ['status' => TaskStatus::Assigned, 'title' => 'Ditugaskan', 'description' => 'Sudah memiliki penanggung jawab.'],
        ['status' => TaskStatus::InProgress, 'title' => 'Dikerjakan', 'description' => 'Sedang berjalan di lapangan.'],
        ['status' => TaskStatus::Blocked, 'title' => 'Terhambat', 'description' => 'Menunggu dependensi atau keputusan.'],
        ['status' => TaskStatus::Completed, 'title' => 'Selesai', 'description' => 'Sudah diselesaikan.'],
        ['status' => TaskStatus::Cancelled, 'title' => 'Dibatalkan', 'description' => 'Tidak lagi dikerjakan.'],
    ];

    $openCount = $tasks->reject(fn ($task) => $task->status->isClosed())->count();
    $overdueCount = $tasks->filter(fn ($task) => $task->isOverdue())->count();
    $criticalCount = $tasks->filter(fn ($task) => $task->priority === \App\Enums\TaskPriority::Critical && ! $task->status->isClosed())->count();

    $hasActiveFilters = filled($filters['status']) || filled($filters['priority']) || filled($filters['station_id']);
@endphp

@section('content')
    <x-ui.page-header
        title="Tugas operasional"
        eyebrow="Operasi"
        :subtitle="'Bagi pekerjaan, pantau tenggat, dan catat progres lapangan untuk '.$event->name.'.'"
    >
        <x-ui.badge :variant="$overdueCount > 0 ? 'danger' : 'neutral'">{{ $openCount }} tugas terbuka</x-ui.badge>
        <x-ui.button variant="secondary" :href="route('workspace.events.operations.my-work', [$organization, $event])" icon="clipboard">Tugas saya</x-ui.button>
        <x-ui.button variant="secondary" :href="route('workspace.events.operations.index', [$organization, $event])" icon="arrow-left">Ruang kendali</x-ui.button>
    </x-ui.page-header>

    @include('workspace.events.partials.event-tabs', ['organization' => $organization, 'event' => $event, 'setup' => $setup ?? null, 'active' => 'operations-tasks'])

    {{-- Filter --}}
    <div class="card mt-6 p-4">
        <form method="GET" action="{{ route('workspace.events.operations.tasks.index', [$organization, $event]) }}" class="grid gap-3 md:grid-cols-12">
            <div class="md:col-span-3">
                <x-ui.select name="status" label="Status" :options="$statuses" :value="$filters['status']" placeholder="Semua status" />
            </div>
            <div class="md:col-span-3">
                <x-ui.select name="priority" label="Prioritas" :options="$priorities" :value="$filters['priority']" placeholder="Semua prioritas" />
            </div>
            <div class="md:col-span-3">
                <x-ui.select name="station_id" label="Stasiun" :options="$stationOptions" :value="$filters['station_id']" placeholder="Semua stasiun" />
            </div>
            <div class="flex items-end gap-2 md:col-span-3">
                <button type="submit" class="btn btn-primary flex-1 justify-center">
                    <x-ui.icon name="filter" class="h-4 w-4" /> Terapkan
                </button>
                @if ($hasActiveFilters)
                    <a href="{{ route('workspace.events.operations.tasks.index', [$organization, $event]) }}" class="btn btn-ghost">Reset</a>
                @endif
            </div>
        </form>
    </div>

    <div class="mt-3 grid grid-cols-2 gap-3 md:grid-cols-4">
        <x-ui.stat-card label="Terbuka" :value="$openCount" icon="clipboard" tone="accent" />
        <x-ui.stat-card label="Terhambat" :value="$tasks->where('status', TaskStatus::Blocked)->count()" icon="alert" tone="warning" />
        <x-ui.stat-card label="Melewati tenggat" :value="$overdueCount" icon="clock" :tone="$overdueCount > 0 ? 'danger' : 'teal'" />
        <x-ui.stat-card label="Prioritas kritis" :value="$criticalCount" icon="shield" :tone="$criticalCount > 0 ? 'danger' : 'teal'" />
    </div>

    <div class="mt-5 grid gap-5 lg:grid-cols-3">
        <div class="space-y-6 lg:col-span-2">
            @if ($tasks->isEmpty())
                <x-ui.empty-state icon="clipboard" title="Belum ada tugas" description="Tugas membantu tim lapangan tahu apa yang harus dikerjakan dan kapan tenggatnya." />
            @else
                @foreach ($groups as $group)
                    @php $groupTasks = $tasks->where('status', $group['status']); @endphp
                    @continue($groupTasks->isEmpty())

                    <section>
                        <div class="mb-3 flex flex-wrap items-center gap-2">
                            <h2 class="section-title">{{ $group['title'] }}</h2>
                            <x-ui.badge :variant="$group['status']->badge()" class="text-[10px]">{{ $groupTasks->count() }}</x-ui.badge>
                            <span class="text-[11px] text-faint">{{ $group['description'] }}</span>
                        </div>

                        <div class="space-y-3">
                            @foreach ($groupTasks as $task)
                                @php
                                    $isOverdue = $task->isOverdue();
                                    $checklist = $task->checklistItems;
                                    $completedItems = $checklist->where('completed', true)->count();
                                    $dependencyRecords = ($task->dependencies_count ?? 0) > 0
                                        ? \App\Models\EventTaskDependency::query()->where('event_task_id', $task->id)->with('dependsOn')->get()
                                        : collect();
                                    $comments = $task->comments()->with('user')->latest()->limit(5)->get();
                                @endphp

                                <article class="card overflow-hidden animate-float-in">
                                    <div class="p-4">
                                        <div class="flex flex-wrap items-start justify-between gap-3">
                                            <div class="min-w-0 flex-1">
                                                <div class="flex flex-wrap items-center gap-2">
                                                    <h3 class="text-sm font-semibold text-ink">{{ $task->title }}</h3>
                                                    <x-ui.badge :variant="$task->priority->badge()" class="text-[10px]">{{ $task->priority->label() }}</x-ui.badge>
                                                    @if ($isOverdue)
                                                        <x-ui.badge variant="danger" class="text-[10px]">Lewat tenggat</x-ui.badge>
                                                    @endif
                                                    @if ($task->category)
                                                        <x-ui.badge variant="neutral" class="text-[10px]">{{ $task->category }}</x-ui.badge>
                                                    @endif
                                                </div>

                                                @if ($task->description)
                                                    <p class="mt-1 text-xs leading-relaxed text-muted">{{ $task->description }}</p>
                                                @endif

                                                <div class="mt-2 flex flex-wrap items-center gap-x-3 gap-y-1 text-[11px] text-muted">
                                                    <span class="inline-flex items-center gap-1">
                                                        <x-ui.icon name="user" class="h-3 w-3 text-faint" />
                                                        {{ $task->assignee?->user?->name ?? 'Belum ditugaskan' }}
                                                    </span>
                                                    @if ($task->station)
                                                        <span class="inline-flex items-center gap-1"><x-ui.icon name="pin" class="h-3 w-3 text-faint" /> {{ $task->station->name }}</span>
                                                    @endif
                                                    <span class="inline-flex items-center gap-1 {{ $isOverdue ? 'text-danger' : '' }}">
                                                        <x-ui.icon name="clock" class="h-3 w-3 {{ $isOverdue ? '' : 'text-faint' }}" />
                                                        {{ $task->due_at ? $task->due_at->translatedFormat('d M Y, H:i') : 'Tanpa tenggat' }}
                                                    </span>
                                                    @if ($checklist->isNotEmpty())
                                                        <span class="inline-flex items-center gap-1">
                                                            <x-ui.icon name="list" class="h-3 w-3 text-faint" />
                                                            {{ $completedItems }}/{{ $checklist->count() }}
                                                        </span>
                                                    @endif
                                                    @if ($dependencyRecords->isNotEmpty())
                                                        <span class="inline-flex items-center gap-1 text-warning">
                                                            <x-ui.icon name="link" class="h-3 w-3" />
                                                            {{ $dependencyRecords->count() }} dependensi
                                                        </span>
                                                    @endif
                                                </div>
                                            </div>

                                            <div class="flex w-full flex-wrap items-center gap-2 sm:w-auto sm:flex-col sm:items-end">
                                                <form method="POST" action="{{ route('workspace.events.operations.tasks.status', [$organization, $event, $task]) }}" class="flex w-full items-center gap-1.5 sm:w-auto">
                                                    @csrf
                                                    <select name="status" class="input input-sm appearance-none" aria-label="Ubah status {{ $task->title }}" x-on:change="$el.form.requestSubmit()">
                                                        <option value="{{ $task->status->value }}" selected>{{ $task->status->label() }}</option>
                                                        @foreach ($task->status->allowedTransitions() as $nextStatus)
                                                            <option value="{{ $nextStatus->value }}">{{ $nextStatus->label() }}</option>
                                                        @endforeach
                                                    </select>
                                                    <noscript><button type="submit" class="btn btn-secondary btn-sm">Simpan</button></noscript>
                                                </form>

                                                @if ($canAssign)
                                                    <form method="POST" action="{{ route('workspace.events.operations.tasks.assign', [$organization, $event, $task]) }}" class="flex w-full items-center gap-1.5 sm:w-auto">
                                                        @csrf
                                                        <select name="assigned_membership_id" class="input input-sm appearance-none" aria-label="Tugaskan {{ $task->title }}" x-on:change="$el.form.requestSubmit()">
                                                            <option value="">Tanpa PIC</option>
                                                            @foreach ($staff as $membership)
                                                                <option value="{{ $membership->id }}" @selected((int) $task->assigned_membership_id === (int) $membership->id)>
                                                                    {{ $membership->user?->name }}
                                                                </option>
                                                            @endforeach
                                                        </select>
                                                        <noscript><button type="submit" class="btn btn-secondary btn-sm">Tugaskan</button></noscript>
                                                    </form>
                                                @endif
                                            </div>
                                        </div>
                                    </div>

                                    <details class="group border-t border-line/60 bg-surface-2/20">
                                        <summary class="flex cursor-pointer list-none items-center justify-between gap-2 px-4 py-2.5 text-[11px] font-semibold text-muted transition hover:text-ink">
                                            <span class="inline-flex items-center gap-1.5">
                                                <x-ui.icon name="list" class="h-3.5 w-3.5" />
                                                Detail, checklist, dan diskusi
                                            </span>
                                            <x-ui.icon name="chevron-down" class="h-3.5 w-3.5 transition group-open:rotate-180" />
                                        </summary>

                                        <div class="space-y-5 border-t border-line/50 p-4">
                                            {{-- Checklist --}}
                                            <div>
                                                <div class="flex items-center justify-between gap-2">
                                                    <h4 class="text-[11px] font-semibold uppercase tracking-[0.08em] text-faint">Checklist</h4>
                                                    @if ($checklist->isNotEmpty())
                                                        <span class="text-[10px] text-faint">{{ $completedItems }}/{{ $checklist->count() }} selesai</span>
                                                    @endif
                                                </div>

                                                @if ($checklist->isEmpty())
                                                    <p class="mt-2 text-[11px] text-muted">Belum ada item checklist.</p>
                                                @else
                                                    <ul class="mt-2 space-y-1.5">
                                                        @foreach ($checklist as $item)
                                                            <li class="flex items-center gap-2 rounded-lg border border-line/60 bg-surface px-2.5 py-1.5">
                                                                <form method="POST" action="{{ route('workspace.events.operations.tasks.checklist.update', [$organization, $event, $task, $item]) }}">
                                                                    @csrf
                                                                    @method('PUT')
                                                                    <input type="hidden" name="completed" value="{{ $item->completed ? 0 : 1 }}">
                                                                    <button type="submit" class="flex h-4 w-4 items-center justify-center rounded border {{ $item->completed ? 'border-success bg-success/15 text-success' : 'border-line text-transparent hover:border-accent' }}" aria-label="{{ $item->completed ? 'Tandai belum selesai' : 'Tandai selesai' }}">
                                                                        <x-ui.icon name="check" class="h-2.5 w-2.5" />
                                                                    </button>
                                                                </form>
                                                                <span class="min-w-0 flex-1 text-[11px] {{ $item->completed ? 'text-faint line-through' : 'text-ink' }}">{{ $item->label }}</span>
                                                                @if ($canManage)
                                                                    <form method="POST" action="{{ route('workspace.events.operations.tasks.checklist.destroy', [$organization, $event, $task, $item]) }}" data-confirm="Hapus item checklist ini?" data-confirm-button="Ya, hapus">
                                                                        @csrf
                                                                        @method('DELETE')
                                                                        <button type="submit" class="text-faint transition hover:text-danger" aria-label="Hapus item checklist">
                                                                            <x-ui.icon name="close" class="h-3 w-3" />
                                                                        </button>
                                                                    </form>
                                                                @endif
                                                            </li>
                                                        @endforeach
                                                    </ul>
                                                @endif

                                                <form method="POST" action="{{ route('workspace.events.operations.tasks.checklist.store', [$organization, $event, $task]) }}" class="mt-2 flex items-center gap-1.5">
                                                    @csrf
                                                    <input type="text" name="label" class="input input-sm min-w-0 flex-1" placeholder="Tambah langkah checklist" required aria-label="Item checklist baru">
                                                    <button type="submit" class="btn btn-secondary btn-sm shrink-0">
                                                        <x-ui.icon name="plus" class="h-3.5 w-3.5" /> Tambah
                                                    </button>
                                                </form>
                                            </div>

                                            {{-- Dependensi --}}
                                            <div>
                                                <h4 class="text-[11px] font-semibold uppercase tracking-[0.08em] text-faint">Dependensi</h4>

                                                @if ($dependencyRecords->isEmpty())
                                                    <p class="mt-2 text-[11px] text-muted">Tidak ada dependensi. Tugas ini dapat dikerjakan mandiri.</p>
                                                @else
                                                    <ul class="mt-2 space-y-1.5">
                                                        @foreach ($dependencyRecords as $dependency)
                                                            <li class="flex items-center gap-2 rounded-lg border border-line/60 bg-surface px-2.5 py-1.5">
                                                                <x-ui.icon name="link" class="h-3 w-3 shrink-0 text-warning" />
                                                                <span class="min-w-0 flex-1 truncate text-[11px] text-ink">{{ $dependency->dependsOn?->title ?? 'Tugas' }}</span>
                                                                <x-ui.badge :variant="$dependency->dependsOn?->status?->badge() ?? 'neutral'" class="text-[9px]">{{ $dependency->dependsOn?->status?->label() ?? '—' }}</x-ui.badge>
                                                                @if ($canManage)
                                                                    <form method="POST" action="{{ route('workspace.events.operations.tasks.dependencies.destroy', [$organization, $event, $task, $dependency]) }}" data-confirm="Hapus dependensi ini?" data-confirm-button="Ya, hapus">
                                                                        @csrf
                                                                        @method('DELETE')
                                                                        <button type="submit" class="text-faint transition hover:text-danger" aria-label="Hapus dependensi">
                                                                            <x-ui.icon name="close" class="h-3 w-3" />
                                                                        </button>
                                                                    </form>
                                                                @endif
                                                            </li>
                                                        @endforeach
                                                    </ul>
                                                @endif

                                                @if ($canManage)
                                                    <form method="POST" action="{{ route('workspace.events.operations.tasks.dependencies.store', [$organization, $event, $task]) }}" class="mt-2 flex items-center gap-1.5">
                                                        @csrf
                                                        <select name="depends_on_task_id" class="input input-sm min-w-0 flex-1 appearance-none" required aria-label="Pilih tugas dependensi">
                                                            <option value="">Pilih tugas prasyarat</option>
                                                            @foreach ($taskOptions as $taskId => $taskTitle)
                                                                @continue((int) $taskId === (int) $task->id)
                                                                <option value="{{ $taskId }}">{{ $taskTitle }}</option>
                                                            @endforeach
                                                        </select>
                                                        <button type="submit" class="btn btn-secondary btn-sm shrink-0">
                                                            <x-ui.icon name="plus" class="h-3.5 w-3.5" /> Tautkan
                                                        </button>
                                                    </form>
                                                @endif
                                            </div>

                                            {{-- Diskusi --}}
                                            <div>
                                                <h4 class="text-[11px] font-semibold uppercase tracking-[0.08em] text-faint">Diskusi</h4>

                                                @if ($comments->isEmpty())
                                                    <p class="mt-2 text-[11px] text-muted">Belum ada komentar.</p>
                                                @else
                                                    <ul class="mt-2 space-y-2">
                                                        @foreach ($comments as $comment)
                                                            <li class="flex items-start gap-2">
                                                                <x-ui.avatar :name="$comment->user?->name ?? 'Anggota'" size="sm" class="mt-0.5 shrink-0" />
                                                                <div class="min-w-0 flex-1 rounded-lg border border-line/60 bg-surface px-2.5 py-1.5">
                                                                    <p class="text-[10px] font-semibold text-ink">{{ $comment->user?->name ?? 'Anggota' }} <span class="font-normal text-faint">· {{ $comment->created_at?->diffForHumans() }}</span></p>
                                                                    <p class="mt-0.5 text-[11px] leading-relaxed text-muted">{{ $comment->body }}</p>
                                                                </div>
                                                            </li>
                                                        @endforeach
                                                    </ul>
                                                @endif

                                                <form method="POST" action="{{ route('workspace.events.operations.tasks.comments.store', [$organization, $event, $task]) }}" class="mt-2 flex items-end gap-1.5">
                                                    @csrf
                                                    <textarea name="body" rows="2" class="input input-sm min-w-0 flex-1 resize-y" placeholder="Tulis komentar untuk tim" required aria-label="Komentar baru"></textarea>
                                                    <button type="submit" class="btn btn-secondary btn-sm shrink-0">
                                                        <x-ui.icon name="send" class="h-3.5 w-3.5" /> Kirim
                                                    </button>
                                                </form>
                                            </div>

                                            {{-- Edit tugas --}}
                                            @if ($canManage)
                                                <div class="rounded-xl border border-line/60 bg-surface p-3">
                                                    <h4 class="text-[11px] font-semibold uppercase tracking-[0.08em] text-faint">Edit tugas</h4>
                                                    <form method="POST" action="{{ route('workspace.events.operations.tasks.update', [$organization, $event, $task]) }}" class="mt-3 grid gap-3 sm:grid-cols-2">
                                                        @csrf
                                                        @method('PUT')
                                                        <div class="sm:col-span-2">
                                                            <x-ui.input name="title" label="Judul" :value="$task->title" required />
                                                        </div>
                                                        <div class="sm:col-span-2">
                                                            <x-ui.textarea name="description" label="Deskripsi" :value="$task->description" rows="2" />
                                                        </div>
                                                        <x-ui.input name="category" label="Kategori" :value="$task->category" placeholder="Contoh: logistik" />
                                                        <x-ui.select name="priority" label="Prioritas" :options="$priorities" :value="$task->priority->value" />
                                                        <x-ui.select name="station_id" label="Stasiun" :options="$stationOptions" :value="$task->station_id" placeholder="Tanpa stasiun" />
                                                        <x-ui.input name="due_at" label="Tenggat" type="datetime-local" :value="$task->due_at?->format('Y-m-d\TH:i')" />
                                                        <div class="flex justify-end sm:col-span-2">
                                                            <button type="submit" class="btn btn-primary btn-sm">
                                                                <x-ui.icon name="save" class="h-3.5 w-3.5" /> Simpan tugas
                                                            </button>
                                                        </div>
                                                    </form>
                                                </div>
                                            @endif
                                        </div>
                                    </details>
                                </article>
                            @endforeach
                        </div>
                    </section>
                @endforeach
            @endif
        </div>

        <div class="space-y-5">
            @if ($canCreate)
                <div class="card p-5">
                    <div class="flex items-center gap-3">
                        <span class="flex h-10 w-10 items-center justify-center rounded-2xl bg-blue-soft text-ink">
                            <x-ui.icon name="plus" class="h-5 w-5" />
                        </span>
                        <div>
                            <h2 class="section-title">Tugas baru</h2>
                            <p class="mt-0.5 text-xs text-faint">Tugas dimulai dengan status terbuka.</p>
                        </div>
                    </div>

                    <form method="POST" action="{{ route('workspace.events.operations.tasks.store', [$organization, $event]) }}" class="mt-5 space-y-4">
                        @csrf
                        <x-ui.input name="title" label="Judul tugas" required placeholder="Contoh: Siapkan meja registrasi" />
                        <x-ui.textarea name="description" label="Deskripsi" rows="2" placeholder="Konteks dan hasil yang diharapkan" />
                        <x-ui.input name="category" label="Kategori" placeholder="Contoh: logistik" />
                        <x-ui.select name="priority" label="Prioritas" :options="$priorities" :value="\App\Enums\TaskPriority::Medium->value" />
                        <x-ui.select name="assigned_membership_id" label="Penanggung jawab" :options="$staffOptions" placeholder="Belum ditugaskan" />
                        <x-ui.select name="station_id" label="Stasiun" :options="$stationOptions" placeholder="Tanpa stasiun" />
                        <x-ui.input name="due_at" label="Tenggat" type="datetime-local" />

                        <button type="submit" class="btn btn-primary w-full justify-center">
                            <x-ui.icon name="plus" class="h-4 w-4" /> Buat tugas
                        </button>
                    </form>
                </div>
            @endif

            <div class="card p-5">
                <h2 class="section-title">Alur tugas</h2>
                <ol class="mt-3 space-y-2.5 text-[11px] leading-relaxed text-muted">
                    <li class="flex items-start gap-2"><span class="mt-0.5 flex h-5 w-5 shrink-0 items-center justify-center rounded-full bg-surface-2 text-[9px] font-semibold text-ink">1</span> Tugas dibuat dan berstatus terbuka.</li>
                    <li class="flex items-start gap-2"><span class="mt-0.5 flex h-5 w-5 shrink-0 items-center justify-center rounded-full bg-surface-2 text-[9px] font-semibold text-ink">2</span> Tentukan penanggung jawab dan stasiun.</li>
                    <li class="flex items-start gap-2"><span class="mt-0.5 flex h-5 w-5 shrink-0 items-center justify-center rounded-full bg-surface-2 text-[9px] font-semibold text-ink">3</span> Catat progres lewat checklist dan komentar.</li>
                    <li class="flex items-start gap-2"><span class="mt-0.5 flex h-5 w-5 shrink-0 items-center justify-center rounded-full bg-surface-2 text-[9px] font-semibold text-ink">4</span> Tandai selesai setelah hasil diperiksa.</li>
                </ol>
            </div>
        </div>
    </div>
@endsection
