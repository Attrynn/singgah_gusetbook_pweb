@extends('layouts.auth')

@section('title', 'Undangan')

@php
    use App\Enums\EventOwnerRole;
    use App\Enums\EventStaffRole;
    use App\Enums\OrganizationRole;
    use App\Enums\WorkspaceInvitationType;

    $typeBadge = $invitation->type === WorkspaceInvitationType::Staff ? 'info' : 'warning';
    $inviterName = $invitation->invitedBy?->name ?? 'Tim workspace';
    $currentEmail = auth()->user()?->email;
    $expiresLabel = $invitation->expires_at?->translatedFormat('d M Y, H:i');

    $reasonMessage = match ($reason) {
        'expired' => 'Undangan ini sudah kedaluwarsa.',
        'accepted', 'used' => 'Undangan ini sudah pernah digunakan.',
        'revoked' => 'Undangan ini telah dibatalkan oleh pengirimnya.',
        default => $reason ?: 'Undangan ini tidak dapat digunakan lagi.',
    };
@endphp

@section('content')
    <div class="card p-6 sm:p-8">
        <p class="section-kicker">Undangan workspace</p>
        <h1 class="mt-2 font-serif text-2xl text-ink">Anda diundang ke {{ $invitation->organization->name }}</h1>
        <p class="mt-2 text-sm leading-relaxed text-muted">
            {{ $inviterName }} mengundang Anda untuk bergabung ke workspace ini.
        </p>

        {{-- Ringkasan undangan --}}
        <div class="mt-4 flex flex-wrap items-center gap-2">
            <x-ui.badge :variant="$typeBadge">{{ $invitation->type->label() }}</x-ui.badge>
            <span class="inline-flex items-center gap-1.5 rounded-full border border-line bg-surface-2/60 px-2.5 py-1 text-[11px] text-muted">
                <x-ui.icon name="mail" class="h-3 w-3 text-faint" />
                {{ $invitation->email }}
            </span>
            <span class="inline-flex items-center gap-1.5 rounded-full border border-line bg-surface-2/60 px-2.5 py-1 text-[11px] text-muted">
                <x-ui.icon name="clock" class="h-3 w-3 text-faint" />
                Berlaku hingga {{ $expiresLabel ?? '—' }}
            </span>
        </div>

        {{-- Akses yang diberikan --}}
        <div class="mt-4 rounded-2xl border border-line/70 bg-surface-2/40 p-4">
            <p class="text-[10px] font-semibold uppercase tracking-[0.14em] text-faint">
                {{ $invitation->type === WorkspaceInvitationType::Staff ? 'Peran & penugasan' : 'Akses pasangan' }}
            </p>
            <div class="mt-2 flex flex-wrap items-center gap-1.5">
                @if ($invitation->type === WorkspaceInvitationType::Staff)
                    @php $orgRole = OrganizationRole::tryFrom($invitation->organization_role); @endphp
                    @if ($orgRole)
                        <x-ui.badge variant="neutral">{{ $orgRole->label() }}</x-ui.badge>
                    @endif
                    @forelse ($invitation->event_assignments ?? [] as $assignment)
                        @php
                            $event = \App\Models\Event::find($assignment['event_id'] ?? null);
                            $eventName = $event?->name ?? 'Event #'.($assignment['event_id'] ?? '—');
                            $roleLabel = EventStaffRole::tryFrom($assignment['role'] ?? '')?->label() ?? ($assignment['role'] ?? 'Staff');
                        @endphp
                        <span class="inline-flex items-center gap-1 rounded-full border border-line bg-surface px-2.5 py-1 text-[11px] text-muted">
                            <x-ui.icon name="calendar" class="h-3 w-3 text-faint" />
                            {{ $eventName }} · {{ $roleLabel }}
                        </span>
                    @empty
                        <span class="text-xs text-muted">Tanpa penugasan event khusus.</span>
                    @endforelse
                @else
                    @forelse ($invitation->event_assignments ?? [] as $assignment)
                        @php
                            $event = \App\Models\Event::find($assignment['event_id'] ?? null);
                            $eventName = $event?->name ?? 'Event #'.($assignment['event_id'] ?? '—');
                            $roleLabel = EventOwnerRole::tryFrom($assignment['role_label'] ?? '')?->label() ?? ucfirst((string) ($assignment['role_label'] ?? 'Pasangan'));
                        @endphp
                        <span class="inline-flex items-center gap-1 rounded-full border border-line bg-surface px-2.5 py-1 text-[11px] text-muted">
                            <x-ui.icon name="heart" class="h-3 w-3 text-faint" />
                            {{ $roleLabel }} · {{ $eventName }}
                        </span>
                    @empty
                        <span class="text-xs text-muted">Akses pasangan untuk event di workspace ini.</span>
                    @endforelse
                @endif
            </div>
        </div>

        {{-- Aksi sesuai status --}}
        @if ($state === 'ready')
            <div class="mt-6 border-t border-line/70 pt-6">
                <form method="POST" action="{{ route('invitations.workspace.accept.store', ['token' => $token]) }}">
                    @csrf
                    <x-ui.button type="submit" icon="check" class="w-full">Terima Undangan</x-ui.button>
                </form>
                <p class="mt-3 text-xs leading-relaxed text-faint">
                    Anda masuk sebagai <span class="font-medium text-ink">{{ $currentEmail }}</span>.
                    Email akun harus sama dengan email undangan untuk dapat menerima undangan ini.
                </p>
            </div>
        @elseif ($state === 'login_required')
            <div class="mt-6 space-y-5 border-t border-line/70 pt-6">
                <p class="text-sm leading-relaxed text-muted">
                    Anda perlu masuk atau membuat akun untuk menerima undangan ini.
                </p>

                <x-ui.button :href="route('login')" variant="secondary" icon="arrow-up-right" class="w-full">Masuk</x-ui.button>

                <div class="flex items-center gap-3">
                    <span class="h-px flex-1 bg-line"></span>
                    <span class="text-[10px] font-semibold uppercase tracking-[0.16em] text-faint">atau buat akun</span>
                    <span class="h-px flex-1 bg-line"></span>
                </div>

                <form method="POST" action="{{ route('invitations.workspace.accept.register', ['token' => $token]) }}" class="space-y-4">
                    @csrf

                    <div>
                        <span class="label">Email undangan</span>
                        <div class="input flex items-center gap-2 bg-surface-2/80 text-muted" aria-readonly="true">
                            <x-ui.icon name="lock" class="h-3.5 w-3.5 shrink-0 text-faint" />
                            <span class="truncate">{{ $invitation->email }}</span>
                        </div>
                        <p class="hint">Email ini terkunci. Undangan hanya berlaku untuk alamat di atas.</p>
                    </div>

                    <x-ui.input name="name" label="Nama lengkap" required placeholder="Nama Anda" autocomplete="name" />
                    <x-ui.input name="password" label="Password" type="password" required placeholder="••••••••" autocomplete="new-password" />
                    <x-ui.input name="password_confirmation" label="Konfirmasi password" type="password" required placeholder="••••••••" autocomplete="new-password" />

                    <x-ui.button type="submit" icon="arrow-up-right" class="w-full">Buat akun &amp; terima</x-ui.button>
                </form>
            </div>
        @elseif ($state === 'email_mismatch')
            <div class="mt-6 border-t border-line/70 pt-6">
                <div class="flex items-start gap-3 rounded-2xl border border-warning/30 bg-warning/10 px-4 py-3">
                    <x-ui.icon name="alert" class="mt-0.5 h-4 w-4 shrink-0 text-warning" />
                    <div>
                        <p class="text-sm font-semibold text-ink">Email tidak sesuai</p>
                        <p class="mt-1 text-xs leading-relaxed text-muted">
                            Email akun Anda tidak sesuai dengan target undangan ini. Keluar lalu masuk dengan akun yang benar untuk menerima undangan.
                        </p>
                    </div>
                </div>

                <p class="mt-3 text-xs leading-relaxed text-faint">
                    Saat ini masuk sebagai <span class="font-medium text-ink">{{ $currentEmail ?? '—' }}</span>,
                    sedangkan undangan ditujukan untuk <span class="font-medium text-ink">{{ $invitation->email }}</span>.
                </p>

                <div class="mt-4 flex flex-col gap-2 sm:flex-row">
                    <form method="POST" action="{{ route('logout') }}" class="flex-1">
                        @csrf
                        <x-ui.button type="submit" variant="secondary" icon="logout" class="w-full">Keluar &amp; ganti akun</x-ui.button>
                    </form>
                    <x-ui.button :href="route('home')" variant="ghost" class="flex-1">Ke beranda</x-ui.button>
                </div>
            </div>
        @else
            <div class="mt-6 border-t border-line/70 pt-6">
                <div class="flex items-start gap-3 rounded-2xl border border-warning/30 bg-warning/10 px-4 py-3">
                    <x-ui.icon name="alert" class="mt-0.5 h-4 w-4 shrink-0 text-warning" />
                    <div>
                        <p class="text-sm font-semibold text-ink">Undangan tidak berlaku</p>
                        <p class="mt-1 text-xs leading-relaxed text-muted">{{ $reasonMessage }}</p>
                    </div>
                </div>

                <x-ui.button :href="route('home')" variant="secondary" class="mt-4 w-full">Kembali ke beranda</x-ui.button>
            </div>
        @endif
    </div>
@endsection
