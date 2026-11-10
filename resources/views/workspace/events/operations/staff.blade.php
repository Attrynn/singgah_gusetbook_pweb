@extends('layouts.owner')

@section('title', 'Staf operasional · '.$event->name)

@php
    use App\Enums\MembershipStatus;

    $memberships = collect($memberships ?? []);
    $candidates = collect($candidates ?? []);
    $roles = collect($roles ?? []);
    $statuses = collect($statuses ?? MembershipStatus::cases());
    $delegatablePermissions = collect($delegatablePermissions ?? []);

    $roleLabel = fn ($role): string => $role instanceof \App\Enums\EventStaffRole
        ? $role->label()
        : ucfirst(str_replace('_', ' ', (string) $role));

    $candidateOptions = $candidates->mapWithKeys(fn ($membership) => [
        $membership->user_id => ($membership->user?->name ?? 'Anggota').' · '.($membership->user?->email ?? ''),
    ]);

    $permissionLabels = [
        'operations.view' => 'Melihat operasional',
        'operations.manage' => 'Mengelola operasional',
        'staff.view' => 'Melihat staf',
        'staff.invite' => 'Mengundang staf',
        'staff.manage' => 'Mengelola staf',
        'station.view' => 'Melihat stasiun',
        'station.manage' => 'Mengelola stasiun',
        'station.assign' => 'Menugaskan stasiun',
        'task.view' => 'Melihat tugas',
        'task.create' => 'Membuat tugas',
        'task.assign' => 'Menugaskan tugas',
        'task.manage' => 'Mengelola tugas',
        'incident.view' => 'Melihat insiden',
        'incident.create' => 'Melaporkan insiden',
        'incident.manage' => 'Mengelola insiden',
        'incident.resolve' => 'Menyelesaikan insiden',
        'announcement.view' => 'Melihat pengumuman',
        'announcement.manage' => 'Mengelola pengumuman',
        'announcement.publish' => 'Menerbitkan pengumuman',
        'event.run_of_show.view' => 'Melihat run of show',
        'event.run_of_show.manage' => 'Mengelola run of show',
        'reports.view' => 'Melihat laporan',
    ];

    $permissionLabel = fn (string $permission): string => $permissionLabels[$permission]
        ?? ucfirst(str_replace(['.', '_'], ' ', $permission));

    $statusCounts = $statuses
        ->mapWithKeys(fn ($status) => [$status->value => $memberships->where('status', $status)->count()])
        ->filter(fn ($count) => $count > 0);

    $canInvite = auth()->user()->can('inviteStaff', $event);
    $canManage = auth()->user()->can('manageStaff', $event);
    $canManagePermissions = auth()->user()->can('manageStaffPermissions', $event);
@endphp

@section('content')
    <x-ui.page-header
        title="Staf operasional"
        eyebrow="Operasi"
        :subtitle="'Undang anggota workspace, atur peran lapangan, dan delegasikan izin untuk '.$event->name.'.'"
    >
        <x-ui.badge variant="neutral">{{ $memberships->count() }} penugasan</x-ui.badge>
        <x-ui.button variant="secondary" :href="route('workspace.events.operations.index', [$organization, $event])" icon="arrow-left">Ruang kendali</x-ui.button>
    </x-ui.page-header>

    @include('workspace.events.partials.event-tabs', ['organization' => $organization, 'event' => $event, 'setup' => $setup ?? null, 'active' => 'operations-staff'])

    <div class="mt-6 flex flex-wrap items-center gap-2">
        @foreach ($statusCounts as $statusValue => $count)
            @php $statusEnum = \App\Enums\MembershipStatus::from($statusValue); @endphp
            <span class="inline-flex items-center gap-1.5 rounded-full border border-line/70 bg-surface px-3 py-1 text-[11px] text-muted">
                <span class="h-1.5 w-1.5 rounded-full {{ $statusEnum === MembershipStatus::Active ? 'bg-success' : ($statusEnum === MembershipStatus::Pending ? 'bg-warning' : 'bg-surface-3') }}"></span>
                {{ $statusEnum->label() }} <span class="font-semibold tabular-nums text-ink">{{ $count }}</span>
            </span>
        @endforeach
    </div>

    <div class="mt-5 grid gap-5 lg:grid-cols-3">
        {{-- Roster --}}
        <div class="space-y-4 lg:col-span-2">
            @if ($memberships->isEmpty())
                <x-ui.empty-state icon="users" title="Belum ada staf operasional" description="Undang anggota workspace lewat formulir di samping untuk mulai membentuk tim lapangan." />
            @else
                @foreach ($memberships as $membership)
                    @php
                        $isSelf = (int) $membership->user_id === (int) auth()->id();
                        $isPending = $membership->status === MembershipStatus::Pending;
                        $isActive = $membership->status === MembershipStatus::Active;
                        $isSuspended = $membership->status === MembershipStatus::Suspended;
                        $stationNames = $membership->stationAssignments
                            ->filter(fn ($assignment) => $assignment->removed_at === null)
                            ->map(fn ($assignment) => $assignment->station?->name)
                            ->filter()
                            ->unique();
                        $grants = $membership->permissionGrants->pluck('permission');
                    @endphp

                    <article class="card overflow-hidden animate-float-in">
                        <div class="flex flex-wrap items-start gap-4 p-5">
                            <x-ui.avatar :name="$membership->user?->name ?? 'Anggota'" size="md" />

                            <div class="min-w-0 flex-1">
                                <div class="flex flex-wrap items-center gap-2">
                                    <p class="truncate text-sm font-semibold text-ink">{{ $membership->user?->name ?? 'Anggota' }}</p>
                                    @if ($isSelf)
                                        <x-ui.badge variant="info" class="text-[9px]">Anda</x-ui.badge>
                                    @endif
                                    <x-ui.badge :variant="$membership->status->badge()" class="text-[10px]">{{ $membership->status->label() }}</x-ui.badge>
                                </div>
                                <p class="truncate text-xs text-muted">{{ $membership->user?->email ?? '—' }}</p>

                                <div class="mt-2 flex flex-wrap items-center gap-x-3 gap-y-1 text-[11px] text-muted">
                                    <span class="inline-flex items-center gap-1 font-medium text-ink">
                                        <x-ui.icon name="shield" class="h-3 w-3 text-accent" />
                                        {{ $roleLabel($membership->role) }}
                                    </span>
                                    @if ($membership->invitedBy)
                                        <span class="inline-flex items-center gap-1">
                                            <x-ui.icon name="mail" class="h-3 w-3 text-faint" />
                                            Diundang {{ $membership->invitedBy->name }}
                                        </span>
                                    @endif
                                    @if ($membership->expires_at)
                                        <span class="inline-flex items-center gap-1">
                                            <x-ui.icon name="clock" class="h-3 w-3 text-faint" />
                                            Berlaku hingga {{ $membership->expires_at->translatedFormat('d M Y') }}
                                        </span>
                                    @endif
                                </div>

                                @if ($stationNames->isNotEmpty())
                                    <div class="mt-2 flex flex-wrap gap-1.5">
                                        @foreach ($stationNames as $stationName)
                                            <span class="inline-flex items-center gap-1 rounded-full border border-line/70 bg-surface-2/50 px-2 py-0.5 text-[10px] text-muted">
                                                <x-ui.icon name="pin" class="h-2.5 w-2.5 text-faint" />
                                                {{ $stationName }}
                                            </span>
                                        @endforeach
                                    </div>
                                @endif

                                @if ($membership->notes)
                                    <p class="mt-2 rounded-lg bg-surface-2/50 px-2.5 py-1.5 text-[11px] text-muted">{{ $membership->notes }}</p>
                                @endif
                            </div>

                            <div class="flex w-full flex-wrap items-center gap-2 sm:w-auto sm:flex-col sm:items-end">
                                @if ($isPending && $isSelf)
                                    <form method="POST" action="{{ route('workspace.events.operations.staff.accept', [$organization, $event, $membership]) }}">
                                        @csrf
                                        <button type="submit" class="btn btn-primary btn-sm w-full justify-center">
                                            <x-ui.icon name="check" class="h-3.5 w-3.5" /> Terima
                                        </button>
                                    </form>
                                    <form method="POST" action="{{ route('workspace.events.operations.staff.decline', [$organization, $event, $membership]) }}" data-confirm="Tolak undangan staf ini?" data-confirm-button="Ya, tolak">
                                        @csrf
                                        <button type="submit" class="btn btn-ghost btn-sm w-full justify-center">Tolak</button>
                                    </form>
                                @endif

                                @if ($canManage && $isActive)
                                    <form method="POST" action="{{ route('workspace.events.operations.staff.suspend', [$organization, $event, $membership]) }}" class="flex w-full items-center gap-1.5 sm:w-auto" data-confirm="Tangguhkan akses {{ $membership->user?->name }}?" data-confirm-button="Ya, tangguhkan">
                                        @csrf
                                        <input type="text" name="reason" class="input input-sm min-w-0 flex-1 sm:w-40" placeholder="Alasan (opsional)" aria-label="Alasan penangguhan">
                                        <button type="submit" class="btn btn-secondary btn-sm shrink-0">Tangguhkan</button>
                                    </form>
                                @endif

                                @if ($canManage && $isSuspended)
                                    <form method="POST" action="{{ route('workspace.events.operations.staff.reinstate', [$organization, $event, $membership]) }}">
                                        @csrf
                                        <button type="submit" class="btn btn-secondary btn-sm w-full justify-center">
                                            <x-ui.icon name="refresh" class="h-3.5 w-3.5" /> Pulihkan
                                        </button>
                                    </form>
                                @endif

                                @if ($canManage && ($isActive || $isSuspended))
                                    <form method="POST" action="{{ route('workspace.events.operations.staff.remove', [$organization, $event, $membership]) }}" data-confirm="Keluarkan {{ $membership->user?->name }} dari tim operasional?" data-confirm-button="Ya, keluarkan">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-danger btn-sm w-full justify-center">
                                            <x-ui.icon name="trash" class="h-3.5 w-3.5" /> Keluarkan
                                        </button>
                                    </form>
                                @endif
                            </div>
                        </div>

                        {{-- Delegasi izin --}}
                        @if ($canManagePermissions && $isActive && $delegatablePermissions->isNotEmpty())
                            <details class="group border-t border-line/60 bg-surface-2/20">
                                <summary class="flex cursor-pointer list-none items-center justify-between gap-2 px-5 py-3 text-[11px] font-semibold text-muted transition hover:text-ink">
                                    <span class="inline-flex items-center gap-1.5">
                                        <x-ui.icon name="sliders" class="h-3.5 w-3.5" />
                                        Delegasi izin
                                        @if ($grants->isNotEmpty())
                                            <span class="badge badge-info text-[9px]">{{ $grants->count() }} tambahan</span>
                                        @endif
                                    </span>
                                    <x-ui.icon name="chevron-down" class="h-3.5 w-3.5 transition group-open:rotate-180" />
                                </summary>

                                <form method="POST" action="{{ route('workspace.events.operations.staff.permissions', [$organization, $event, $membership]) }}" class="border-t border-line/50 px-5 py-4">
                                    @csrf
                                    @method('PUT')

                                    @if ($grants->isNotEmpty())
                                        <div class="mb-3 flex flex-wrap gap-1.5">
                                            @foreach ($grants as $grant)
                                                <span class="badge badge-info text-[9px]">{{ $permissionLabel($grant) }}</span>
                                            @endforeach
                                        </div>
                                    @endif

                                    <div class="grid gap-2 sm:grid-cols-2">
                                        @foreach ($delegatablePermissions as $permission)
                                            <label class="flex items-start gap-2 rounded-lg border border-line/60 bg-surface px-2.5 py-2 text-[11px] text-muted">
                                                <input type="checkbox"
                                                       name="permissions[]"
                                                       value="{{ $permission }}"
                                                       class="mt-0.5 h-3.5 w-3.5 rounded border-line text-accent focus:ring-accent/40"
                                                       @checked($grants->contains($permission))>
                                                <span>{{ $permissionLabel($permission) }}</span>
                                            </label>
                                        @endforeach
                                    </div>

                                    <div class="mt-3 flex items-center justify-between gap-2">
                                        <p class="text-[10px] text-faint">Izin bawaan peran tetap berlaku.</p>
                                        <button type="submit" class="btn btn-primary btn-sm">
                                            <x-ui.icon name="save" class="h-3.5 w-3.5" /> Simpan izin
                                        </button>
                                    </div>
                                </form>
                            </details>
                        @elseif ($grants->isNotEmpty())
                            <div class="flex flex-wrap items-center gap-1.5 border-t border-line/60 bg-surface-2/20 px-5 py-3">
                                <span class="text-[10px] font-semibold uppercase tracking-[0.08em] text-faint">Izin tambahan</span>
                                @foreach ($grants as $grant)
                                    <span class="badge badge-info text-[9px]">{{ $permissionLabel($grant) }}</span>
                                @endforeach
                            </div>
                        @endif
                    </article>
                @endforeach
            @endif
        </div>

        <div class="space-y-5">
            {{-- Undang staf --}}
            @if ($canInvite)
                <div class="card p-5">
                    <div class="flex items-center gap-3">
                        <span class="flex h-10 w-10 items-center justify-center rounded-2xl bg-blue-soft text-ink">
                            <x-ui.icon name="user-check" class="h-5 w-5" />
                        </span>
                        <div>
                            <h2 class="section-title">Undang staf</h2>
                            <p class="mt-0.5 text-xs text-faint">Anggota workspace yang sudah aktif.</p>
                        </div>
                    </div>

                    <form method="POST" action="{{ route('workspace.events.operations.staff.invite', [$organization, $event]) }}" class="mt-5 space-y-4">
                        @csrf
                        <x-ui.select name="user_id" label="Anggota workspace" :options="$candidateOptions" placeholder="Pilih anggota" required />
                        <x-ui.select name="role" label="Peran lapangan" :options="$roles" placeholder="Pilih peran" required />
                        <x-ui.input name="expires_at" label="Berlaku hingga" type="date" hint="Kosongkan bila tanpa batas waktu." />
                        <x-ui.textarea name="notes" label="Catatan" rows="2" placeholder="Tugas khusus atau konteks penugasan" />

                        @if ($candidateOptions->isEmpty())
                            <p class="hint">Belum ada anggota workspace aktif yang dapat diundang.</p>
                        @endif

                        <button type="submit" class="btn btn-primary w-full justify-center" @disabled($candidateOptions->isEmpty())>
                            <x-ui.icon name="send" class="h-4 w-4" /> Kirim undangan
                        </button>
                    </form>
                </div>
            @endif

            <div class="card p-5">
                <h2 class="section-title">Alur staf</h2>
                <ol class="mt-3 space-y-3">
                    @foreach ([
                        ['icon' => 'send', 'title' => 'Undangan dikirim', 'text' => 'Staf menerima status menunggu konfirmasi.'],
                        ['icon' => 'user-check', 'title' => 'Staf menerima', 'text' => 'Akses aktif sesuai peran dan izin tambahan.'],
                        ['icon' => 'pin', 'title' => 'Ditugaskan ke stasiun', 'text' => 'Supervisor dan anggota stasiun ditetapkan.'],
                        ['icon' => 'shield', 'title' => 'Akses dapat ditangguhkan', 'text' => 'Penangguhan menghentikan akses tanpa menghapus riwayat.'],
                    ] as $step)
                        <li class="flex items-start gap-2.5">
                            <span class="mt-0.5 flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-surface-2 text-muted">
                                <x-ui.icon :name="$step['icon']" class="h-3 w-3" />
                            </span>
                            <div>
                                <p class="text-[11px] font-semibold text-ink">{{ $step['title'] }}</p>
                                <p class="text-[10px] leading-relaxed text-faint">{{ $step['text'] }}</p>
                            </div>
                        </li>
                    @endforeach
                </ol>
            </div>

            @cannot('inviteStaff', $event)
                <div class="card p-5">
                    <div class="flex items-start gap-3 rounded-xl bg-surface-2/60 p-3 text-xs text-muted">
                        <x-ui.icon name="lock" class="mt-0.5 h-4 w-4 shrink-0" />
                        <p>Anda dapat melihat daftar staf, tetapi tidak dapat mengundang atau mengubah penugasan.</p>
                    </div>
                </div>
            @endcannot
        </div>
    </div>
@endsection
