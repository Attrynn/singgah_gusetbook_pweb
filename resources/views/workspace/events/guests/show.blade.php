@extends('layouts.owner')

@section('title', $party->name.' - Undangan')

@php
    use App\Enums\GuestPartyStatus;

    $memberStatusOptions = collect(GuestPartyStatus::cases())
        ->mapWithKeys(fn ($status) => [$status->value => $status->label()]);

    $identifiedCount = $party->identifiedCount();
    $remainingQuota = $party->remainingQuota();
    $quotaFull = $remainingQuota <= 0;
@endphp

@section('content')
    <x-ui.page-header
        :title="$party->name"
        eyebrow="Undangan"
        :subtitle="'Detail invitation party untuk '.$event->name.'. Kelola individu, kuota, dan guest pass dari halaman ini.'"
    >
        <div class="flex flex-wrap items-center gap-2">
            <x-ui.button variant="secondary" :href="route('workspace.events.guests.edit', [$organization, $event, $party])" icon="edit">Edit party</x-ui.button>
            <form method="POST"
                  action="{{ route('workspace.events.guests.destroy', [$organization, $event, $party]) }}"
                  data-confirm="Arsipkan invitation party '{{ $party->name }}'?"
                  data-confirm-button="Ya, arsipkan">
                @csrf
                @method('DELETE')
                <button type="submit" class="btn btn-danger">
                    <x-ui.icon name="trash" class="h-4 w-4" /> Hapus party
                </button>
            </form>
            <x-ui.button variant="secondary" :href="route('workspace.events.guests.index', [$organization, $event])" icon="arrow-left">Semua party</x-ui.button>
        </div>
    </x-ui.page-header>

    @include('workspace.events.partials.event-tabs', ['organization' => $organization, 'event' => $event, 'setup' => $setup ?? null, 'active' => 'guests'])

    <div class="mt-6 grid gap-5 lg:grid-cols-3">
        {{-- Ringkasan party --}}
        <div class="card p-5 lg:col-span-2">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h2 class="section-title">Ringkasan party</h2>
                    <p class="mt-0.5 text-xs text-faint">Kuota, identifikasi, dan informasi kontak party.</p>
                </div>
                <x-ui.badge :variant="$party->status->badge()" class="text-[10px]">{{ $party->status->label() }}</x-ui.badge>
            </div>

            <dl class="mt-5 grid gap-4 sm:grid-cols-2">
                <div class="rounded-xl border border-line/60 p-3">
                    <dt class="text-[10px] font-semibold uppercase tracking-[0.1em] text-faint">Kuota undangan</dt>
                    <dd class="mt-1 text-lg font-semibold text-ink">{{ $party->max_attendees }}</dd>
                </div>
                <div class="rounded-xl border border-line/60 p-3">
                    <dt class="text-[10px] font-semibold uppercase tracking-[0.1em] text-faint">Individu teridentifikasi</dt>
                    <dd class="mt-1 text-lg font-semibold text-ink">{{ $identifiedCount }}</dd>
                </div>
                <div class="rounded-xl border border-line/60 p-3">
                    <dt class="text-[10px] font-semibold uppercase tracking-[0.1em] text-faint">Sisa kuota</dt>
                    <dd class="mt-1 text-lg font-semibold {{ $quotaFull ? 'text-danger' : 'text-ink' }}">{{ $remainingQuota }}</dd>
                </div>
                <div class="rounded-xl border border-line/60 p-3">
                    <dt class="text-[10px] font-semibold uppercase tracking-[0.1em] text-faint">Hadir (RSVP)</dt>
                    <dd class="mt-1 text-lg font-semibold text-ink">{{ $party->rsvp?->attendance_count ?? 0 }}</dd>
                </div>
                <div class="rounded-xl border border-line/60 p-3">
                    <dt class="text-[10px] font-semibold uppercase tracking-[0.1em] text-faint">Kategori</dt>
                    <dd class="mt-1 text-sm font-medium text-ink">{{ $party->category?->name ?? 'Tanpa kategori' }}</dd>
                </div>
                <div class="rounded-xl border border-line/60 p-3">
                    <dt class="text-[10px] font-semibold uppercase tracking-[0.1em] text-faint">Household</dt>
                    <dd class="mt-1 text-sm font-medium text-ink">{{ $party->household?->name ?? 'Tanpa household' }}</dd>
                </div>
            </dl>

            <div class="mt-5 space-y-2 border-t border-line/60 pt-4 text-sm">
                <div class="flex items-center gap-2 text-muted">
                    <x-ui.icon name="mail" class="h-4 w-4 shrink-0 text-faint" />
                    <span>{{ $party->email ?: 'Email belum diisi' }}</span>
                </div>
                <div class="flex items-center gap-2 text-muted">
                    <x-ui.icon name="phone" class="h-4 w-4 shrink-0 text-faint" />
                    <span>{{ $party->phone ?: 'Nomor telepon belum diisi' }}</span>
                </div>
            </div>

            @if ($party->notes)
                <div class="mt-4 rounded-xl bg-surface-2/60 p-3 text-xs leading-relaxed text-muted">
                    <p class="text-[10px] font-semibold uppercase tracking-[0.1em] text-faint">Catatan</p>
                    <p class="mt-1 whitespace-pre-line">{{ $party->notes }}</p>
                </div>
            @endif
        </div>

        {{-- Tambah individu --}}
        <div class="card p-5">
            <div class="flex items-center gap-3">
                <span class="flex h-10 w-10 items-center justify-center rounded-2xl bg-blue-soft text-ink">
                    <x-ui.icon name="user-check" class="h-5 w-5" />
                </span>
                <div>
                    <h2 class="section-title">Tambah individu</h2>
                    <p class="mt-0.5 text-xs text-faint">Identifikasi anggota party yang sudah diketahui.</p>
                </div>
            </div>

            <form method="POST" action="{{ route('workspace.events.guests.members.store', [$organization, $event, $party]) }}" class="mt-5 space-y-4">
                @csrf
                <x-ui.input name="name" label="Nama" required placeholder="Nama lengkap individu" />
                <x-ui.input name="email" label="Email" type="email" placeholder="nama@contoh.com" />
                <x-ui.input name="phone" label="Nomor telepon" placeholder="08xxxxxxxxxx" />

                @if ($quotaFull)
                    <p class="hint text-warning">Kuota party sudah penuh. Tambah kuota terlebih dahulu melalui Edit party.</p>
                @endif

                <button type="submit" class="btn btn-primary w-full justify-center" @disabled($quotaFull)>
                    <x-ui.icon name="plus" class="h-4 w-4" /> Tambahkan individu
                </button>
            </form>
        </div>
    </div>

    {{-- Daftar individu --}}
    <div class="card mt-6 overflow-hidden">
        <div class="flex flex-wrap items-center justify-between gap-2 border-b border-line/70 px-5 py-4">
            <div>
                <h2 class="section-title">Individu party</h2>
                <p class="mt-0.5 text-xs text-faint">{{ $identifiedCount }} teridentifikasi dari {{ $party->max_attendees }} kuota · {{ $remainingQuota }} sisa</p>
            </div>
            <x-ui.badge :variant="$quotaFull ? 'warning' : 'success'" class="text-[10px]">
                {{ $quotaFull ? 'Kuota penuh' : 'Kuota tersedia' }}
            </x-ui.badge>
        </div>

        @if ($party->members->isEmpty())
            <div class="p-5">
                <x-ui.empty-state icon="users" title="Belum ada individu teridentifikasi" description="Tambahkan individu melalui formulir di atas agar guest pass dapat diterbitkan." />
            </div>
        @else
            <ul class="divide-y divide-line/60">
                @foreach ($party->members as $member)
                    @php
                        $activePass = $member->passes->firstWhere('status', 'active');
                    @endphp
                    <li class="px-4 py-5 sm:px-5">
                        <div class="flex flex-wrap items-start gap-x-4 gap-y-3">
                            <div class="flex min-w-[200px] flex-1 items-center gap-3">
                                <x-ui.avatar :name="$member->name" size="md" />
                                <div class="min-w-0">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <p class="truncate text-sm font-semibold text-ink">{{ $member->name }}</p>
                                        <x-ui.badge :variant="$member->status->badge()" class="text-[10px]">{{ $member->status->label() }}</x-ui.badge>
                                        @if ($activePass)
                                            <x-ui.badge variant="success" class="text-[10px]">Pass aktif</x-ui.badge>
                                        @endif
                                    </div>
                                    <p class="truncate text-xs text-muted">{{ $member->email ?: 'Email belum diisi' }}</p>
                                    <p class="truncate text-xs text-faint">{{ $member->phone ?: 'Nomor telepon belum diisi' }}</p>
                                </div>
                            </div>
                        </div>

                        {{-- Daftar pass --}}
                        <div class="mt-3 space-y-2">
                            @forelse ($member->passes as $pass)
                                <div class="flex flex-wrap items-center gap-2 rounded-xl border border-line/60 bg-surface-2/40 px-3 py-2 text-[11px]">
                                    <x-ui.icon name="ticket" class="h-3.5 w-3.5 shrink-0 text-faint" />
                                    <span class="font-semibold text-ink">{{ $pass->type->value === 'qr' ? 'QR' : 'Invitation' }}</span>
                                    <span class="font-mono text-faint">{{ $pass->token_prefix }}…</span>
                                    <x-ui.badge :variant="$pass->status === 'active' ? 'success' : 'muted'" class="text-[10px]">{{ $pass->status }}</x-ui.badge>
                                    @if ($pass->expires_at)
                                        <span class="text-faint">Berlaku s.d. {{ $pass->expires_at->translatedFormat('d M Y') }}</span>
                                    @endif
                                    @if ($pass->revoked_at)
                                        <span class="text-danger">Dicabut {{ $pass->revoked_at->translatedFormat('d M Y') }}</span>
                                    @endif
                                    @if ($pass->status === 'active')
                                        <form method="POST"
                                              action="{{ route('workspace.events.guests.members.pass.revoke', [$organization, $event, $party, $member, $pass]) }}"
                                              data-confirm="Cabut guest pass {{ $pass->type->value }} milik {{ $member->name }}?"
                                              data-confirm-button="Ya, cabut">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-danger btn-sm">Cabut pass</button>
                                        </form>
                                    @endif
                                </div>
                            @empty
                                <p class="text-[11px] text-faint">Belum ada guest pass diterbitkan.</p>
                            @endforelse
                        </div>

                        {{-- Aksi individu --}}
                        <div class="mt-3 flex flex-wrap items-center gap-2">
                            <details class="w-full">
                                <summary class="btn btn-ghost btn-sm cursor-pointer">Edit data individu</summary>
                                <form method="POST"
                                      action="{{ route('workspace.events.guests.members.update', [$organization, $event, $party, $member]) }}"
                                      class="mt-3 grid gap-3 rounded-xl border border-line/60 p-3 sm:grid-cols-2">
                                    @csrf
                                    @method('PUT')
                                    <x-ui.input name="name" label="Nama" :value="$member->name" required />
                                    <x-ui.select name="status" label="Status" :value="$member->status->value" :options="$memberStatusOptions" required />
                                    <x-ui.input name="email" label="Email" type="email" :value="$member->email" />
                                    <x-ui.input name="phone" label="Nomor telepon" :value="$member->phone" />
                                    <div class="sm:col-span-2">
                                        <button type="submit" class="btn btn-primary btn-sm">
                                            <x-ui.icon name="save" class="h-4 w-4" /> Simpan individu
                                        </button>
                                    </div>
                                </form>
                            </details>

                            <form method="POST" action="{{ route('workspace.events.guests.members.pass.issue', [$organization, $event, $party, $member]) }}" class="flex items-center gap-1.5">
                                @csrf
                                <select name="type" class="input input-sm appearance-none" aria-label="Jenis pass untuk {{ $member->name }}">
                                    <option value="invitation">Invitation</option>
                                    <option value="qr">QR</option>
                                </select>
                                <button type="submit" class="btn btn-secondary btn-sm">
                                    <x-ui.icon name="ticket" class="h-3.5 w-3.5" /> Terbitkan pass
                                </button>
                            </form>

                            @if ($otherParties->isNotEmpty())
                                <form method="POST" action="{{ route('workspace.events.guests.members.move', [$organization, $event, $party, $member]) }}" class="flex items-center gap-1.5">
                                    @csrf
                                    <select name="target_party_id" class="input input-sm appearance-none" required aria-label="Party tujuan untuk {{ $member->name }}">
                                        <option value="">Pindah ke party…</option>
                                        @foreach ($otherParties as $otherParty)
                                            <option value="{{ $otherParty->id }}">{{ $otherParty->name }}</option>
                                        @endforeach
                                    </select>
                                    <button type="submit" class="btn btn-secondary btn-sm">
                                        <x-ui.icon name="share" class="h-3.5 w-3.5" /> Pindahkan
                                    </button>
                                </form>
                            @endif

                            <form method="POST"
                                  action="{{ route('workspace.events.guests.members.destroy', [$organization, $event, $party, $member]) }}"
                                  data-confirm="Hapus individu {{ $member->name }} dari party ini?"
                                  data-confirm-button="Ya, hapus">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-danger btn-sm">
                                    <x-ui.icon name="trash" class="h-3.5 w-3.5" /> Hapus individu
                                </button>
                            </form>
                        </div>
                    </li>
                @endforeach
            </ul>
        @endif
    </div>
@endsection
