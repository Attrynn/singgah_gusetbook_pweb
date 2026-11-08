@extends('layouts.owner')

@section('title', 'Tim '.$event->name)

@php
    use App\Enums\MembershipStatus;

    $memberships = collect($memberships ?? []);
    $candidates = collect($candidates ?? []);
    $staffRoles = collect($staffRoles ?? []);
    $primaryPicId = $primaryPic?->id;

    $roleLabel = function ($role): string {
        return $role instanceof \App\Enums\EventStaffRole ? $role->label() : ucfirst(str_replace('_', ' ', (string) $role));
    };

    $candidateOptions = $candidates->mapWithKeys(fn ($user) => [$user->id => $user->name.' · '.$user->email]);
    $activeMemberships = $memberships->filter(fn ($membership) => $membership->status === MembershipStatus::Active);

    $canManage = auth()->user()->can('manageTeam', $event);
@endphp

@section('content')
    <x-ui.page-header
        :title="'Tim acara'"
        eyebrow="Tim"
        :subtitle="'Tugaskan anggota workspace untuk membantu pelaksanaan '.$event->name.'.'"
    >
        <x-ui.button variant="secondary" :href="route('workspace.events.show', [$organization, $event])" icon="arrow-left">Ringkasan</x-ui.button>
    </x-ui.page-header>

    @include('workspace.events.partials.event-tabs', ['organization' => $organization, 'event' => $event, 'setup' => $setup ?? null, 'active' => 'team'])

    @cannot('manageTeam', $event)
        <div class="mt-6 rounded-2xl border border-warning/30 bg-warning/10 px-4 py-3 text-sm text-warning" role="alert">
            <div class="flex items-start gap-3">
                <x-ui.icon name="lock" class="mt-0.5 h-4 w-4 shrink-0" />
                <p>Anda tidak memiliki izin mengubah tim acara ini. Daftar di bawah hanya dapat dilihat.</p>
            </div>
        </div>
    @endcannot

    <div class="mt-6 grid gap-5 lg:grid-cols-3">
        {{-- Roster --}}
        <div class="card overflow-hidden lg:col-span-2">
            <div class="flex flex-wrap items-center justify-between gap-2 border-b border-line/70 px-5 py-4">
                <div>
                    <h2 class="section-title">Anggota tim</h2>
                    <p class="mt-0.5 text-xs text-faint">{{ $memberships->count() }} penugasan · {{ $activeMemberships->count() }} aktif</p>
                </div>
            </div>

            @if ($memberships->isEmpty())
                <div class="p-5">
                    <x-ui.empty-state icon="users" title="Belum ada anggota tim" description="Tambahkan anggota workspace melalui formulir di samping untuk mulai menugaskan peran." />
                </div>
            @else
                <ul class="divide-y divide-line/60">
                    @foreach ($memberships as $membership)
                        @php
                            $isActive = $membership->status === MembershipStatus::Active;
                            $isPrimary = $primaryPicId !== null && $primaryPicId === $membership->id;
                        @endphp
                        <li class="px-4 py-4 sm:px-5">
                            <div class="flex flex-wrap items-center gap-x-4 gap-y-3">
                                <div class="flex min-w-[200px] flex-1 items-center gap-3">
                                    <x-ui.avatar :name="$membership->user?->name ?? '—'" size="md" />
                                    <div class="min-w-0">
                                        <div class="flex items-center gap-2">
                                            <p class="truncate text-sm font-semibold text-ink">{{ $membership->user?->name ?? 'Anggota' }}</p>
                                            @if ($isPrimary)
                                                <x-ui.badge variant="success" class="text-[10px]">
                                                    <x-ui.icon name="star" class="h-3 w-3" /> PIC utama
                                                </x-ui.badge>
                                            @endif
                                        </div>
                                        <p class="truncate text-xs text-muted">{{ $membership->user?->email }}</p>
                                    </div>
                                </div>

                                <div class="flex flex-wrap items-center gap-2">
                                    @if ($canManage && $isActive)
                                        <form method="POST"
                                              action="{{ route('workspace.events.team.update', [$organization, $event, $membership]) }}"
                                              class="flex items-center gap-1.5">
                                            @csrf
                                            @method('PUT')
                                            <input type="hidden" name="user_id" value="{{ $membership->user_id }}">
                                            <select name="role"
                                                    class="input input-sm appearance-none"
                                                    aria-label="Peran {{ $membership->user?->name }}"
                                                    x-on:change="$el.form.requestSubmit()">
                                                @foreach ($staffRoles as $role)
                                                    <option value="{{ $role->value ?? $role }}" @selected((string) ($membership->role->value ?? $membership->role) === (string) ($role->value ?? $role))>
                                                        {{ $roleLabel($role) }}
                                                    </option>
                                                @endforeach
                                            </select>
                                            <noscript>
                                                <button type="submit" class="btn btn-secondary btn-sm">Simpan</button>
                                            </noscript>
                                        </form>
                                    @else
                                        <x-ui.badge variant="info" class="text-[10px]">{{ $roleLabel($membership->role) }}</x-ui.badge>
                                    @endif

                                    <x-ui.badge :variant="$isActive ? 'success' : 'muted'" class="text-[10px]">
                                        {{ $membership->status->label() }}
                                    </x-ui.badge>
                                </div>

                                <div class="hidden w-28 shrink-0 text-right md:block">
                                    <p class="text-[10px] font-semibold uppercase tracking-[0.1em] text-faint">Ditugaskan</p>
                                    <p class="text-[11px] text-muted">{{ $membership->created_at?->translatedFormat('d M Y') ?? '—' }}</p>
                                </div>

                                @if ($canManage && $isActive)
                                    <div class="flex w-full items-center justify-end gap-2 sm:w-auto">
                                        <form method="POST"
                                              action="{{ route('workspace.events.team.destroy', [$organization, $event, $membership]) }}"
                                              data-confirm="Cabut penugasan {{ $membership->user?->name }} dari tim acara?"
                                              data-confirm-button="Ya, cabut">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-danger btn-sm">
                                                <x-ui.icon name="trash" class="h-3.5 w-3.5" /> Cabut
                                            </button>
                                        </form>
                                    </div>
                                @endif
                            </div>

                            <p class="mt-1.5 text-[11px] text-faint md:hidden">
                                Ditugaskan {{ $membership->created_at?->translatedFormat('d M Y') ?? '—' }}
                            </p>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>

        <div class="space-y-5">
            {{-- Tambah anggota --}}
            @if ($canManage)
                <div class="card p-5">
                    <div class="flex items-center gap-3">
                        <span class="flex h-10 w-10 items-center justify-center rounded-2xl bg-blue-soft text-ink">
                            <x-ui.icon name="user-check" class="h-5 w-5" />
                        </span>
                        <div>
                            <h2 class="section-title">Tambah anggota</h2>
                            <p class="mt-0.5 text-xs text-faint">Pilih anggota workspace yang sudah aktif.</p>
                        </div>
                    </div>

                    <form method="POST" action="{{ route('workspace.events.team.store', [$organization, $event]) }}" class="mt-5 space-y-4">
                        @csrf
                        <x-ui.select name="user_id" label="Anggota workspace" :options="$candidateOptions" placeholder="Pilih anggota" required />
                        <x-ui.select name="role" label="Peran" :options="$staffRoles" placeholder="Pilih peran" required />

                        @if ($candidates->isEmpty())
                            <p class="hint">Semua anggota aktif sudah ditugaskan, atau belum ada anggota lain di workspace ini.</p>
                        @endif

                        <button type="submit" class="btn btn-primary w-full justify-center" @disabled($candidates->isEmpty())>
                            <x-ui.icon name="plus" class="h-4 w-4" /> Tambahkan ke tim
                        </button>
                    </form>
                </div>

                {{-- PIC utama --}}
                <div class="card p-5">
                    <div class="flex items-center gap-3">
                        <span class="flex h-10 w-10 items-center justify-center rounded-2xl bg-accent/15 text-accent">
                            <x-ui.icon name="star" class="h-5 w-5" />
                        </span>
                        <div>
                            <h2 class="section-title">PIC utama</h2>
                            <p class="mt-0.5 text-xs text-faint">Penanggung jawab yang tampil sebagai kontak tim.</p>
                        </div>
                    </div>

                    <form method="POST" action="{{ route('workspace.events.team.primary', [$organization, $event]) }}" class="mt-5 space-y-3">
                        @csrf

                        @if ($activeMemberships->isEmpty())
                            <p class="hint">Belum ada anggota aktif yang dapat dijadikan PIC utama.</p>
                        @else
                            <label for="membership_id" class="label">Pilih anggota</label>
                            <select id="membership_id" name="membership_id" class="input appearance-none">
                                <option value="">Tidak ada PIC utama</option>
                                @foreach ($activeMemberships as $membership)
                                    <option value="{{ $membership->id }}" @selected($primaryPicId === $membership->id)>
                                        {{ $membership->user?->name }} — {{ $roleLabel($membership->role) }}
                                    </option>
                                @endforeach
                            </select>
                        @endif

                        <button type="submit" class="btn btn-secondary w-full justify-center" @disabled($activeMemberships->isEmpty())>
                            <x-ui.icon name="check" class="h-4 w-4" /> Simpan PIC utama
                        </button>
                    </form>
                </div>
            @else
                <div class="card p-5">
                    <div class="flex items-start gap-3 rounded-xl bg-surface-2/60 p-3 text-xs text-muted">
                        <x-ui.icon name="lock" class="mt-0.5 h-4 w-4 shrink-0" />
                        <p>Hanya Event Manager atau pengelola workspace yang dapat mengubah tim acara.</p>
                    </div>
                </div>
            @endif
        </div>
    </div>
@endsection