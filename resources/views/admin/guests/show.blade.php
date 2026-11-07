@extends('layouts.admin')

@section('title', 'Detail Tamu')
@section('subtitle', 'Profil, RSVP, dan aktivitas tamu')

@section('content')
    <x-ui.page-header title="{{ $guest->name }}" eyebrow="Guest Detail" subtitle="{{ $guest->event?->name }} · {{ $guest->event?->coupleNames() }}">
        <x-ui.button variant="secondary" href="{{ route('admin.guests.edit', $guest) }}" icon="edit">
            Edit
        </x-ui.button>
        <x-ui.button variant="secondary" href="{{ route('admin.invitations.show', $guest) }}" icon="mail">
            Undangan
        </x-ui.button>
        <x-ui.button variant="secondary" href="{{ route('admin.guests.index') }}" icon="arrow-left">
            Kembali
        </x-ui.button>
    </x-ui.page-header>

    <div class="mt-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <x-ui.stat-card label="RSVP" :value="$guest->rsvp_status->label()" icon="clipboard" :tone="$guest->rsvp_status->value === 'confirmed' ? 'success' : ($guest->rsvp_status->value === 'declined' ? 'danger' : 'accent')" :hint="$guest->rsvp?->responded_at?->format('d M Y H:i') ?? 'Belum merespons'" />
        <x-ui.stat-card label="Jumlah hadir" :value="$guest->rsvp?->attendance_count ?? 0" icon="users" hint="Dari maksimal {{ $guest->max_attendees }} orang" />
        <x-ui.stat-card label="Check-in" :value="$guest->isCheckedIn() ? 'Sudah' : 'Belum'" icon="calendar-check" :tone="$guest->isCheckedIn() ? 'success' : 'warning'" :hint="$guest->checked_in_at?->format('d M Y H:i') ?? 'Belum check-in'" />
        <x-ui.stat-card label="Undangan" :value="$guest->invitation?->status->label() ?? 'Belum dibuat'" icon="mail" :tone="$guest->invitation?->opened_at ? 'success' : 'accent'" :hint="$guest->invitation?->opened_at ? 'Dibuka '.$guest->invitation->open_count.'×' : 'Belum dibuka'" />
    </div>

    <div class="mt-6 grid gap-6 xl:grid-cols-[minmax(0,1fr)_360px]">
        <div class="space-y-6">
            <div class="card p-6">
                <h2 class="section-title">Identitas &amp; kontak</h2>
                <dl class="mt-4 grid gap-x-6 gap-y-4 text-sm sm:grid-cols-2">
                    <div>
                        <dt class="text-xs uppercase tracking-[0.12em] text-faint">Nama</dt>
                        <dd class="mt-1 font-medium text-ink">{{ $guest->name }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs uppercase tracking-[0.12em] text-faint">Status</dt>
                        <dd class="mt-1"><x-ui.badge :variant="$guest->status->badge()">{{ $guest->status->label() }}</x-ui.badge></dd>
                    </div>
                    <div>
                        <dt class="text-xs uppercase tracking-[0.12em] text-faint">Email</dt>
                        <dd class="mt-1 text-ink">{{ $guest->email ?: '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs uppercase tracking-[0.12em] text-faint">Telepon</dt>
                        <dd class="mt-1 text-ink">{{ $guest->phone ?: '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs uppercase tracking-[0.12em] text-faint">Kategori</dt>
                        <dd class="mt-1 text-ink">{{ $guest->category?->name ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs uppercase tracking-[0.12em] text-faint">Kelompok</dt>
                        <dd class="mt-1 text-ink">{{ $guest->group?->name ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs uppercase tracking-[0.12em] text-faint">Maksimal tamu</dt>
                        <dd class="mt-1 text-ink">{{ $guest->max_attendees }} orang</dd>
                    </div>
                    <div>
                        <dt class="text-xs uppercase tracking-[0.12em] text-faint">Ditambahkan</dt>
                        <dd class="mt-1 text-ink">{{ $guest->created_at?->translatedFormat('d M Y H:i') }}</dd>
                    </div>
                    <div class="sm:col-span-2">
                        <dt class="text-xs uppercase tracking-[0.12em] text-faint">Catatan internal</dt>
                        <dd class="mt-1 text-muted">{{ $guest->notes ?: '—' }}</dd>
                    </div>
                </dl>
            </div>

            <div class="grid gap-6 md:grid-cols-2">
                <div class="card p-6">
                    <h2 class="section-title">RSVP</h2>
                    @if ($guest->rsvp)
                        <dl class="mt-4 space-y-3 text-sm">
                            <div class="flex items-center justify-between gap-3">
                                <dt class="text-muted">Status</dt>
                                <dd><x-ui.badge :variant="$guest->rsvp->status->badge()">{{ $guest->rsvp->status->label() }}</x-ui.badge></dd>
                            </div>
                            <div class="flex items-center justify-between gap-3">
                                <dt class="text-muted">Jumlah hadir</dt>
                                <dd class="text-ink">{{ $guest->rsvp->attendance_count }}</dd>
                            </div>
                            <div class="flex items-center justify-between gap-3">
                                <dt class="text-muted">Nama pendamping</dt>
                                <dd class="text-ink">{{ $guest->rsvp->companion_names ?: '—' }}</dd>
                            </div>
                            <div class="flex items-center justify-between gap-3">
                                <dt class="text-muted">Preferensi makan</dt>
                                <dd class="text-ink">{{ $guest->rsvp->dietary_preference ?: '—' }}</dd>
                            </div>
                            <div class="flex items-center justify-between gap-3">
                                <dt class="text-muted">Direspons</dt>
                                <dd class="text-ink">{{ $guest->rsvp->responded_at?->translatedFormat('d M Y H:i') ?? '—' }}</dd>
                            </div>
                        </dl>
                        @if ($guest->rsvp->notes)
                            <p class="mt-4 rounded-xl bg-surface-2/60 p-3 text-xs text-muted">{{ $guest->rsvp->notes }}</p>
                        @endif
                    @else
                        <x-ui.empty-state icon="clipboard" title="Belum ada RSVP" description="Tamu belum mengisi formulir RSVP." />
                    @endif
                </div>

                <div class="card p-6">
                    <h2 class="section-title">Check-in &amp; tempat duduk</h2>
                    <dl class="mt-4 space-y-3 text-sm">
                        <div class="flex items-center justify-between gap-3">
                            <dt class="text-muted">Status</dt>
                            <dd><x-ui.badge :variant="$guest->isCheckedIn() ? 'success' : 'muted'">{{ $guest->isCheckedIn() ? 'Checked in' : 'Belum check-in' }}</x-ui.badge></dd>
                        </div>
                        <div class="flex items-center justify-between gap-3">
                            <dt class="text-muted">Waktu</dt>
                            <dd class="text-ink">{{ $guest->checkin?->checked_in_at?->translatedFormat('d M Y H:i') ?? '—' }}</dd>
                        </div>
                        <div class="flex items-center justify-between gap-3">
                            <dt class="text-muted">Metode</dt>
                            <dd class="text-ink">{{ $guest->checkin?->method->label() ?? '—' }}</dd>
                        </div>
                        <div class="flex items-center justify-between gap-3">
                            <dt class="text-muted">Petugas</dt>
                            <dd class="text-ink">{{ $guest->checkin?->officer?->name ?? '—' }}</dd>
                        </div>
                        <div class="flex items-center justify-between gap-3">
                            <dt class="text-muted">Meja</dt>
                            <dd class="text-ink">{{ $guest->tableAssignment?->seatingTable?->name ?? 'Belum ditempatkan' }}</dd>
                        </div>
                        <div class="flex items-center justify-between gap-3">
                            <dt class="text-muted">Nomor kursi</dt>
                            <dd class="text-ink">{{ $guest->tableAssignment?->seat_number ?? '—' }}</dd>
                        </div>
                    </dl>
                </div>
            </div>

            <div class="card p-6">
                <h2 class="section-title">Aktivitas terbaru</h2>
                @if ($activities->isEmpty())
                    <x-ui.empty-state icon="clock" title="Belum ada aktivitas" description="Aktivitas tamu akan tampil di sini." />
                @else
                    <ol class="mt-4 space-y-4">
                        @foreach ($activities as $activity)
                            <li class="flex gap-3">
                                <span class="mt-1 flex h-7 w-7 shrink-0 items-center justify-center rounded-xl bg-blue-soft text-ink">
                                    <x-ui.icon name="sparkles" class="h-3.5 w-3.5" />
                                </span>
                                <div class="min-w-0">
                                    <p class="text-sm text-ink">{{ $activity->description }}</p>
                                    <p class="mt-0.5 text-[11px] text-faint">{{ $activity->type }} · {{ $activity->created_at?->translatedFormat('d M Y H:i') }}</p>
                                </div>
                            </li>
                        @endforeach
                    </ol>
                @endif
            </div>
        </div>

        <aside class="space-y-4">
            <div class="card p-5">
                <h2 class="section-title">Tautan undangan</h2>
                @if ($invitationUrl)
                    <p class="mt-2 break-all rounded-xl bg-surface-2/60 p-3 text-[11px] text-muted">{{ $invitationUrl }}</p>
                    <div class="mt-3 flex flex-wrap gap-2">
                        <a href="{{ $invitationUrl }}" target="_blank" rel="noopener" class="btn btn-soft btn-sm">
                            <x-ui.icon name="external-link" class="h-3.5 w-3.5" /> Buka
                        </a>
                        <button type="button" class="btn btn-ghost btn-sm" data-copy="{{ $invitationUrl }}">
                            <x-ui.icon name="copy" class="h-3.5 w-3.5" /> Salin
                        </button>
                    </div>
                @else
                    <p class="mt-2 text-xs text-muted">Tautan belum tersedia. Buat ulang token undangan.</p>
                @endif

                <form method="POST" action="{{ route('admin.guests.regenerate', $guest) }}" class="mt-4 border-t border-line/70 pt-4" data-confirm="Buat ulang token undangan dan QR pass? Tautan lama tidak akan berlaku lagi." data-confirm-title="Regenerate Token">
                    @csrf
                    <button type="submit" class="btn btn-ghost btn-sm">
                        <x-ui.icon name="refresh" class="h-3.5 w-3.5" /> Regenerate token
                    </button>
                </form>
            </div>

            <div class="card p-5">
                <h2 class="section-title">Ringkasan konten</h2>
                <dl class="mt-3 space-y-2.5 text-sm">
                    <div class="flex items-center justify-between gap-3">
                        <dt class="text-muted">Ucapan</dt>
                        <dd class="text-ink">{{ $guest->messages->count() }}</dd>
                    </div>
                    <div class="flex items-center justify-between gap-3">
                        <dt class="text-muted">Foto</dt>
                        <dd class="text-ink">{{ $guest->mediaAssets->count() }}</dd>
                    </div>
                    <div class="flex items-center justify-between gap-3">
                        <dt class="text-muted">Request lagu</dt>
                        <dd class="text-ink">{{ $guest->songRequests->count() }}</dd>
                    </div>
                </dl>
            </div>

            <div class="card border-danger/20 p-5">
                <h2 class="section-title text-danger">Zona berbahaya</h2>
                <p class="mt-2 text-xs text-muted">Menghapus tamu juga menghapus RSVP, check-in, dan konten terkait.</p>
                <form method="POST" action="{{ route('admin.guests.destroy', $guest) }}" class="mt-3" data-confirm="Hapus tamu {{ $guest->name }}? Tindakan ini tidak bisa dibatalkan." data-confirm-title="Hapus Tamu">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="btn btn-danger btn-sm">
                        <x-ui.icon name="trash" class="h-3.5 w-3.5" /> Hapus tamu
                    </button>
                </form>
            </div>
        </aside>
    </div>
@endsection
