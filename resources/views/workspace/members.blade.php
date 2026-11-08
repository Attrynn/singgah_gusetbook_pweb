@extends('layouts.owner')

@section('title', 'Kelola Workspace')

@php
    use App\Enums\EventOwnerRole;
    use App\Enums\EventStaffRole;
    use App\Enums\OrganizationMembershipStatus;
    use App\Enums\OrganizationRole;
    use App\Enums\WorkspaceInvitationType;

    $roleBadge = fn (OrganizationRole $role): string => match ($role) {
        OrganizationRole::Owner => 'info',
        OrganizationRole::Admin => 'neutral',
        default => 'muted',
    };

    $statusBadge = fn (OrganizationMembershipStatus $status): string => match ($status) {
        OrganizationMembershipStatus::Active => 'success',
        OrganizationMembershipStatus::Suspended => 'warning',
        default => 'danger',
    };

    $typeBadge = fn (WorkspaceInvitationType $type): string => match ($type) {
        WorkspaceInvitationType::Staff => 'info',
        default => 'warning',
    };

    $activeOwnerCount = $members
        ->filter(fn ($m) => $m->role === OrganizationRole::Owner && $m->status->isAccessible())
        ->count();

    $eventOptions = $events->mapWithKeys(fn ($event) => [$event->id => $event->name]);
    $eventsById = $events->keyBy('id');
    $actingUserId = auth()->id();
@endphp

@section('content')
    <x-ui.page-header
        :title="$organization->name"
        eyebrow="Workspace"
        :subtitle="'Kelola anggota dan undangan untuk workspace ini · slug: '.$organization->slug"
    >
        @if ($actingRole)
            <x-ui.badge :variant="$roleBadge($actingRole)">Anda: {{ $actingRole->label() }}</x-ui.badge>
        @endif
    </x-ui.page-header>

    {{-- Anggota Workspace --}}
    <div class="card mt-6 overflow-hidden animate-float-in" style="animation-delay: 0.05s">
        <div class="flex flex-wrap items-center justify-between gap-2 border-b border-line/70 px-5 py-4">
            <div>
                <h2 class="section-title">Anggota workspace</h2>
                <p class="mt-0.5 text-xs text-faint">{{ $members->count() }} anggota terdaftar</p>
            </div>
            @unless ($canManageRoles)
                <span class="text-[11px] text-faint">Hanya owner & admin yang dapat mengubah peran.</span>
            @endunless
        </div>

        @if ($members->isEmpty())
            <div class="p-5">
                <x-ui.empty-state icon="users" title="Belum ada anggota" description="Anggota yang menerima undangan akan muncul di sini." />
            </div>
        @else
            <ul class="divide-y divide-line/60">
                @foreach ($members as $member)
                    @php
                        $isActingUser = $member->user_id === $actingUserId;
                        $isActingSoleOwner = $isActingUser
                            && $member->role === OrganizationRole::Owner
                            && $activeOwnerCount <= 1;
                    @endphp
                    <li class="px-4 py-4 sm:px-5">
                        <div class="flex flex-wrap items-center gap-x-4 gap-y-3">
                            {{-- Identitas --}}
                            <div class="flex min-w-[200px] flex-1 items-center gap-3">
                                <x-ui.avatar :name="$member->user->name" size="md" />
                                <div class="min-w-0">
                                    <div class="flex items-center gap-2">
                                        <p class="truncate text-sm font-semibold text-ink">{{ $member->user->name }}</p>
                                        @if ($isActingUser)
                                            <x-ui.badge variant="muted" class="text-[10px]">Anda</x-ui.badge>
                                        @endif
                                    </div>
                                    <p class="truncate text-xs text-muted">{{ $member->user->email }}</p>
                                </div>
                            </div>

                            {{-- Peran & status --}}
                            <div class="flex items-center gap-2">
                                @if ($canManageRoles)
                                    <form method="POST"
                                          action="{{ route('workspace.members.update', [$organization, $member->user]) }}">
                                        @csrf
                                        @method('PUT')
                                        <select name="role"
                                                class="input input-sm appearance-none"
                                                aria-label="Peran {{ $member->user->name }}"
                                                x-on:change="$el.form.submit()">
                                            @foreach ($organizationRoles as $role)
                                                <option value="{{ $role->value }}" @selected($member->role === $role)>{{ $role->label() }}</option>
                                            @endforeach
                                        </select>
                                    </form>
                                @else
                                    <x-ui.badge :variant="$roleBadge($member->role)">{{ $member->role->label() }}</x-ui.badge>
                                @endif

                                <x-ui.badge :variant="$statusBadge($member->status)">{{ $member->status->label() }}</x-ui.badge>
                            </div>

                            {{-- Tanggal bergabung --}}
                            <div class="hidden w-28 shrink-0 text-right md:block">
                                <p class="text-[10px] font-semibold uppercase tracking-[0.1em] text-faint">Bergabung</p>
                                <p class="text-[11px] text-muted">
                                    {{ $member->accepted_at?->translatedFormat('d M Y') ?? '—' }}
                                </p>
                            </div>

                            {{-- Aksi --}}
                            @if ($canManageRoles)
                                <div class="flex w-full items-center justify-end gap-2 sm:w-auto">
                                    @if ($isActingSoleOwner)
                                        <span class="text-[10px] text-faint" title="Anda pemilik aktif terakhir dari workspace ini">
                                            Pemilik terakhir
                                        </span>
                                    @else
                                        <form method="POST"
                                              action="{{ route('workspace.members.destroy', [$organization, $member->user]) }}"
                                              x-on:submit="if (! confirm('Cabut akses anggota ini dari workspace?')) $event.preventDefault()">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-danger btn-sm">
                                                <x-ui.icon name="trash" class="h-3.5 w-3.5" /> Cabut
                                            </button>
                                        </form>
                                    @endif
                                </div>
                            @endif
                        </div>

                        <p class="mt-1.5 text-[11px] text-faint md:hidden">
                            Bergabung {{ $member->accepted_at?->translatedFormat('d M Y') ?? '—' }}
                        </p>
                    </li>
                @endforeach
            </ul>
        @endif
    </div>

    {{-- Undangan Baru --}}
    @if ($canInvite)
        <div class="mt-6 grid gap-4 lg:grid-cols-2">
            {{-- Undang Staff --}}
            <div class="card p-5 animate-float-in" style="animation-delay: 0.1s">
                <div class="flex items-center gap-3">
                    <span class="flex h-10 w-10 items-center justify-center rounded-2xl bg-blue-soft text-ink">
                        <x-ui.icon name="user-check" class="h-5 w-5" />
                    </span>
                    <div>
                        <h2 class="section-title">Undang staff</h2>
                        <p class="mt-0.5 text-xs text-faint">Beri akses tim untuk membantu mengelola event.</p>
                    </div>
                </div>

                <form method="POST"
                      action="{{ route('workspace.invitations.store', $organization) }}"
                      class="mt-5 space-y-4"
                      x-data="{
                          assignments: [{ event_id: '', role: '' }],
                          add() { this.assignments.push({ event_id: '', role: '' }); },
                          remove(index) { this.assignments.splice(index, 1); }
                      }">
                    @csrf

                    <x-ui.input name="email" label="Email" type="email" required placeholder="nama@email.com" autocomplete="email" />

                    <x-ui.select name="organization_role" label="Peran di workspace" :options="$organizationRoles" :value="'member'" required />

                    <div class="space-y-2">
                        <div class="flex items-center justify-between gap-2">
                            <p class="label mb-0">Penugasan event</p>
                            @if ($events->isNotEmpty())
                                <button type="button" class="btn btn-ghost btn-sm" @click="add()">
                                    <x-ui.icon name="plus" class="h-3.5 w-3.5" /> Tambah penugasan
                                </button>
                            @endif
                        </div>

                        @if ($events->isEmpty())
                            <p class="hint">Belum ada event di workspace ini. Undangan tetap dapat dikirim tanpa penugasan event.</p>
                        @else
                            <template x-for="(assignment, index) in assignments" :key="index">
                                <div class="flex items-center gap-2">
                                    <select :name="'assignments[' + index + '][event_id]'"
                                            x-model="assignment.event_id"
                                            class="input appearance-none"
                                            aria-label="Event">
                                        <option value="">Pilih event</option>
                                        @foreach ($events as $event)
                                            <option value="{{ $event->id }}">{{ $event->name }}</option>
                                        @endforeach
                                    </select>
                                    <select :name="'assignments[' + index + '][role]'"
                                            x-model="assignment.role"
                                            class="input appearance-none"
                                            aria-label="Peran staff">
                                        <option value="">Pilih peran</option>
                                        @foreach ($staffRoles as $role)
                                            <option value="{{ $role->value }}">{{ $role->label() }}</option>
                                        @endforeach
                                    </select>
                                    <button type="button"
                                            class="icon-btn h-10 w-10 shrink-0 text-danger hover:border-danger/40"
                                            x-show="assignments.length > 1"
                                            @click="remove(index)"
                                            aria-label="Hapus penugasan">
                                        <x-ui.icon name="trash" class="h-4 w-4" />
                                    </button>
                                </div>
                            </template>
                        @endif
                    </div>

                    <div class="pt-1">
                        <x-ui.button type="submit" icon="send" class="w-full">Kirim undangan staff</x-ui.button>
                    </div>
                </form>
            </div>

            {{-- Undang Pasangan --}}
            <div class="card p-5 animate-float-in" style="animation-delay: 0.15s">
                <div class="flex items-center gap-3">
                    <span class="flex h-10 w-10 items-center justify-center rounded-2xl bg-accent/15 text-accent">
                        <x-ui.icon name="heart" class="h-5 w-5" />
                    </span>
                    <div>
                        <h2 class="section-title">Undang pasangan</h2>
                        <p class="mt-0.5 text-xs text-faint">Berikan akses khusus untuk calon pengantin.</p>
                    </div>
                </div>

                <form method="POST"
                      action="{{ route('workspace.couple-invitations.store', $organization) }}"
                      class="mt-5 space-y-4">
                    @csrf

                    <x-ui.input name="email" label="Email pasangan" type="email" required placeholder="nama@email.com" autocomplete="email" />

                    <x-ui.select name="event_id" label="Event" :options="$eventOptions" placeholder="Pilih event" required />

                    <x-ui.select name="role_label" label="Peran" :options="$coupleRoles" placeholder="Pilih peran" required />

                    @if ($events->isEmpty())
                        <p class="hint">Belum ada event di workspace ini. Tambahkan event terlebih dahulu sebelum mengundang pasangan.</p>
                    @endif

                    <div class="pt-1">
                        <x-ui.button type="submit" icon="send" class="w-full">Kirim akses pasangan</x-ui.button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    {{-- Undangan Tertunda --}}
    <div class="card mt-6 overflow-hidden animate-float-in" style="animation-delay: 0.2s">
        <div class="border-b border-line/70 px-5 py-4">
            <h2 class="section-title">Undangan tertunda</h2>
            <p class="mt-0.5 text-xs text-faint">{{ $pendingInvitations->count() }} undangan menunggu diterima</p>
        </div>

        @if ($pendingInvitations->isEmpty())
            <div class="p-5">
                <x-ui.empty-state icon="mail" title="Belum ada undangan tertunda" description="Undangan yang belum diterima akan muncul di sini." />
            </div>
        @else
            <ul class="divide-y divide-line/60">
                @foreach ($pendingInvitations as $invitation)
                    <li class="px-4 py-4 sm:px-5">
                        <div class="flex flex-wrap items-start gap-x-4 gap-y-3">
                            <div class="min-w-[220px] flex-1">
                                <div class="flex flex-wrap items-center gap-2">
                                    <p class="truncate text-sm font-semibold text-ink">{{ $invitation->email }}</p>
                                    <x-ui.badge :variant="$typeBadge($invitation->type)">{{ $invitation->type->label() }}</x-ui.badge>
                                </div>

                                <div class="mt-1.5 flex flex-wrap items-center gap-1.5">
                                    @if ($invitation->type === WorkspaceInvitationType::Staff)
                                        @php $orgRole = OrganizationRole::tryFrom($invitation->organization_role); @endphp
                                        @if ($orgRole)
                                            <x-ui.badge variant="neutral" class="text-[10px]">{{ $orgRole->label() }}</x-ui.badge>
                                        @endif
                                        @forelse ($invitation->event_assignments ?? [] as $assignment)
                                            @php
                                                $eventName = $eventsById->get($assignment['event_id'] ?? null)?->name ?? 'Event #'.($assignment['event_id'] ?? '—');
                                                $roleLabel = EventStaffRole::tryFrom($assignment['role'] ?? '')?->label() ?? ($assignment['role'] ?? 'Staff');
                                            @endphp
                                            <span class="inline-flex items-center gap-1 rounded-full border border-line bg-surface-2/60 px-2 py-0.5 text-[10px] text-muted">
                                                <x-ui.icon name="calendar" class="h-3 w-3 text-faint" />
                                                {{ $eventName }} · {{ $roleLabel }}
                                            </span>
                                        @empty
                                        @endforelse
                                    @else
                                        @forelse ($invitation->event_assignments ?? [] as $assignment)
                                            @php
                                                $eventName = $eventsById->get($assignment['event_id'] ?? null)?->name ?? 'Event #'.($assignment['event_id'] ?? '—');
                                                $roleLabel = EventOwnerRole::tryFrom($assignment['role_label'] ?? '')?->label() ?? ucfirst((string) ($assignment['role_label'] ?? 'Pasangan'));
                                            @endphp
                                            <span class="inline-flex items-center gap-1 rounded-full border border-line bg-surface-2/60 px-2 py-0.5 text-[10px] text-muted">
                                                <x-ui.icon name="heart" class="h-3 w-3 text-faint" />
                                                {{ $roleLabel }} · {{ $eventName }}
                                            </span>
                                        @empty
                                        @endforelse
                                    @endif
                                </div>

                                <p class="mt-1.5 text-[10px] text-faint">
                                    Diundang oleh {{ $invitation->invitedBy?->name ?? 'Tim workspace' }}
                                    · Berlaku hingga {{ $invitation->expires_at?->translatedFormat('d M Y, H:i') ?? '—' }}
                                </p>
                            </div>

                            <div class="flex w-full items-center justify-end gap-2 sm:w-auto">
                                <form method="POST" action="{{ route('workspace.invitations.resend', [$organization, $invitation]) }}">
                                    @csrf
                                    <button type="submit" class="btn btn-secondary btn-sm">
                                        <x-ui.icon name="refresh" class="h-3.5 w-3.5" /> Kirim ulang
                                    </button>
                                </form>
                                <form method="POST"
                                      action="{{ route('workspace.invitations.revoke', [$organization, $invitation]) }}"
                                      x-on:submit="if (! confirm('Batalkan undangan ini?')) $event.preventDefault()">
                                    @csrf
                                    <button type="submit" class="btn btn-danger btn-sm">
                                        <x-ui.icon name="close" class="h-3.5 w-3.5" /> Batalkan
                                    </button>
                                </form>
                            </div>
                        </div>
                    </li>
                @endforeach
            </ul>
        @endif
    </div>
@endsection
