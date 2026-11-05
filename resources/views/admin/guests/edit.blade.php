@extends('layouts.admin')

@section('title', 'Edit Tamu')
@section('subtitle', 'Perbarui data tamu')

@section('content')
    <x-ui.page-header title="Edit Tamu" eyebrow="Guest Details" subtitle="{{ $guest->name }}">
        <x-ui.button variant="secondary" href="{{ route('admin.guests.show', $guest) }}" icon="eye">
            Lihat detail
        </x-ui.button>
        <x-ui.button variant="secondary" href="{{ route('admin.guests.index') }}" icon="arrow-left">
            Kembali
        </x-ui.button>
    </x-ui.page-header>

    <div class="mt-6 grid gap-6 xl:grid-cols-[minmax(0,1fr)_320px]">
        <form method="POST" action="{{ route('admin.guests.update', $guest) }}" class="card space-y-5 p-6">
            @csrf
            @method('PUT')

            <div class="grid gap-5 sm:grid-cols-2">
                <x-ui.select name="event_id" label="Event" :value="$guest->event_id" :options="$events->mapWithKeys(fn ($event) => [$event->id => $event->name])" placeholder="Pilih event" required />

                <x-ui.input name="name" label="Nama" :value="$guest->name" required />

                <x-ui.input name="email" type="email" label="Email" :value="$guest->email" />

                <x-ui.input name="phone" label="Telepon" :value="$guest->phone" />

                <x-ui.select name="guest_category_id" label="Kategori" :value="$guest->guest_category_id" :options="$categories->mapWithKeys(fn ($category) => [$category->id => $category->name])" placeholder="Tanpa kategori" />

                <x-ui.select name="guest_group_id" label="Kelompok" :value="$guest->guest_group_id" :options="$groups->mapWithKeys(fn ($group) => [$group->id => $group->name])" placeholder="Tanpa kelompok" />

                <x-ui.select name="status" label="Status tamu" :value="$guest->status->value" :options="$statuses" required />

                <x-ui.input name="max_attendees" type="number" label="Maksimal tamu" :value="$guest->max_attendees" min="1" max="20" required hint="Jumlah maksimal orang termasuk tamu utama." />
            </div>

            <x-ui.textarea name="notes" label="Catatan" rows="3" :value="$guest->notes" hint="Catatan internal, tidak tampil ke tamu." />

            <div class="flex flex-wrap items-center justify-between gap-3 border-t border-line/70 pt-5">
                <button type="submit" class="btn btn-primary">
                    <x-ui.icon name="save" class="h-4 w-4" /> Simpan perubahan
                </button>
                <a href="{{ route('admin.guests.show', $guest) }}" class="btn btn-ghost">
                    <x-ui.icon name="arrow-left" class="h-4 w-4" /> Batal
                </a>
            </div>
        </form>

        <aside class="space-y-4">
            <div class="card p-5">
                <h2 class="section-title">Status saat ini</h2>
                <dl class="mt-4 space-y-3 text-sm">
                    <div class="flex items-center justify-between gap-3">
                        <dt class="text-muted">RSVP</dt>
                        <dd><x-ui.badge :variant="$guest->rsvp_status->badge()">{{ $guest->rsvp_status->label() }}</x-ui.badge></dd>
                    </div>
                    <div class="flex items-center justify-between gap-3">
                        <dt class="text-muted">Kehadiran</dt>
                        <dd><x-ui.badge :variant="$guest->isCheckedIn() ? 'success' : 'muted'">{{ $guest->isCheckedIn() ? 'Checked in' : 'Belum check-in' }}</x-ui.badge></dd>
                    </div>
                    <div class="flex items-center justify-between gap-3">
                        <dt class="text-muted">Undangan</dt>
                        <dd><x-ui.badge :variant="$guest->invitation?->status->badge()">{{ $guest->invitation?->status->label() ?? 'Belum dibuat' }}</x-ui.badge></dd>
                    </div>
                    <div class="flex items-center justify-between gap-3">
                        <dt class="text-muted">Dibuat</dt>
                        <dd class="text-ink">{{ $guest->created_at?->format('d M Y') }}</dd>
                    </div>
                </dl>
            </div>

            <div class="card p-5">
                <h2 class="section-title">Tautan undangan</h2>
                <p class="mt-2 text-xs text-muted">Tautan personal aktif untuk tamu ini.</p>
                <div class="mt-3 flex items-center gap-2">
                    <a href="{{ route('admin.invitations.show', $guest) }}" class="btn btn-soft btn-sm">
                        <x-ui.icon name="link" class="h-3.5 w-3.5" /> Kelola undangan
                    </a>
                    <a href="{{ route('admin.guests.show', $guest) }}" class="btn btn-ghost btn-sm">
                        <x-ui.icon name="qr" class="h-3.5 w-3.5" /> QR pass
                    </a>
                </div>
            </div>

            <div class="card border-danger/20 p-5">
                <h2 class="section-title text-[#8f4f58]">Hapus tamu</h2>
                <p class="mt-2 text-xs text-muted">Tamu dan seluruh data terkait akan dihapus dari event ini.</p>
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
