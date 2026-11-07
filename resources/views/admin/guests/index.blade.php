@extends('layouts.admin')

@section('title', 'Guests')
@section('subtitle', 'Kelola daftar tamu')

@section('content')
    <x-ui.page-header title="Guests" eyebrow="Guest List">
        <x-ui.button variant="secondary" href="{{ route('admin.guests.create') }}" icon="plus">
            Tambah tamu
        </x-ui.button>
        <x-ui.button variant="secondary" href="{{ route('admin.guests.import') }}" icon="download">
            Import
        </x-ui.button>
        <a href="{{ route('admin.guests.export', ['event_id' => request('event_id')]) }}" class="btn btn-soft">
            <x-ui.icon name="download" class="h-4 w-4" />
            Export
        </a>
    </x-ui.page-header>

    <div class="mt-6">
        <form method="GET" class="flex flex-wrap items-center gap-4">
            <input type="text" name="event_id" placeholder="Event" class="input w-48" value="{{ request('event_id') }}" aria-label="Filter event">
            <input type="text" name="search" placeholder="Cari tamu..." class="input w-64" value="{{ request('search') }}" aria-label="Cari tamu">
            <select name="guest_category_id" class="input w-48" aria-label="Filter kategori">
                <option value="">Semua kategori</option>
                @foreach ($categories as $category)
                    <option value="{{ $category->id }}" {{ request('guest_category_id') == $category->id ? 'selected' : '' }}>
                        {{ $category->name }}
                    </option>
                @endforeach
            </select>
            <select name="guest_group_id" class="input w-48" aria-label="Filter kelompok">
                <option value="">Semua kelompok</option>
                @foreach ($groups as $group)
                    <option value="{{ $group->id }}" {{ request('guest_group_id') == $group->id ? 'selected' : '' }}>
                        {{ $group->name }}
                    </option>
                @endforeach
            </select>
            <select name="rsvp_status" class="input w-48" aria-label="Filter status RSVP">
                <option value="">Semua RSVP</option>
                @foreach ($rsvpStatuses as $status)
                    <option value="{{ $status->value }}" {{ request('rsvp_status') == $status->value ? 'selected' : '' }}>
                        {{ $status->label() }}
                    </option>
                @endforeach
            </select>
            <select name="attendance" class="input w-48" aria-label="Filter kehadiran">
                <option value="">Semua kehadiran</option>
                <option value="checked_in" {{ request('attendance') == 'checked_in' ? 'selected' : '' }}>Checked In</option>
                <option value="not_checked_in" {{ request('attendance') == 'not_checked_in' ? 'selected' : '' }}>Not Checked In</option>
            </select>
            <select name="invitation_status" class="input w-48" aria-label="Filter status undangan">
                <option value="">Semua undangan</option>
                @foreach ($statuses as $status)
                    <option value="{{ $status->value }}" {{ request('invitation_status') == $status->value ? 'selected' : '' }}>
                        {{ $status->label() }}
                    </option>
                @endforeach
            </select>
            <button type="submit" class="btn btn-primary">
                <x-ui.icon name="search" class="h-4 w-4" />
                Cari
            </button>
        </form>
    </div>

    <div class="mt-6">
        <div class="card">
            <div class="flex items-center justify-between border-b border-line/70 px-6 py-4">
                <h2 class="section-title">Guest List</h2>
                <div class="text-xs text-muted">{{ $guests->total() }} tamu</div>
            </div>
            <div class="divide-y divide-line/70">
                @if ($guests->count())
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th scope="col" class="text-left">Nama / Kontak</th>
                                <th scope="col" class="text-left">Event</th>
                                <th scope="col" class="text-left">Category / Group</th>
                                <th scope="col" class="text-left">RSVP</th>
                                <th scope="col" class="text-left">Attendance</th>
                                <th scope="col" class="text-left">Check-in</th>
                                <th scope="col" class="text-left">Invitation</th>
                                <th scope="col" class="text-left">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($guests as $guest)
                                <tr class="hover:bg-surface-2/40">
                                    <td class="text-ink">
                                        <a href="{{ route('admin.guests.show', $guest) }}" class="text-ink">
                                            {{ $guest->name }}
                                        </a>
                                        <p class="text-xs text-muted">{{ $guest->email ?? '-' }} | {{ $guest->phone ?? '-' }}</p>
                                    </td>
                                    <td class="text-ink">{{ $guest->event->name }}</td>
                                    <td class="text-ink">
                                        <span class="badge badge-neutral text-[10px]">{{ $guest->category->name ?? '-' }}</span>
                                        <span class="badge badge-neutral text-[10px]">{{ $guest->group->name ?? '-' }}</span>
                                    </td>
                                    <td class="text-ink">
                                        <x-ui.badge :variant="$guest->rsvp_status->badge()" class="text-[10px]">{{ $guest->rsvp_status->label() }}</x-ui.badge>
                                    </td>
                                    <td class="text-ink">
                                        <span class="badge badge-{{ $guest->isCheckedIn() ? 'success' : 'muted' }} text-[10px]">{{ $guest->isCheckedIn() ? 'Checked In' : 'Not Checked In' }}</span>
                                    </td>
                                    <td class="text-ink">{{ $guest->checked_in_at ? $guest->checked_in_at->format('d M Y H:i') : '-' }}</td>
                                    <td class="text-ink">
                                        <x-ui.badge :variant="$guest->invitation?->status->badge()" class="text-[10px]">{{ $guest->invitation?->status->label() ?? '-' }}</x-ui.badge>
                                    </td>
                                    <td class="text-ink">
                                        <a href="{{ route('admin.guests.show', $guest) }}" class="text-xs text-muted" title="Lihat detail">
                                            <x-ui.icon name="eye" class="h-4 w-4" />
                                        </a>
                                        <a href="{{ route('admin.guests.edit', $guest) }}" class="text-xs text-muted" title="Edit">
                                            <x-ui.icon name="edit" class="h-4 w-4" />
                                        </a>
                                        <form method="POST" action="{{ route('admin.guests.destroy', $guest) }}" class="inline" data-confirm="Yakin ingin menghapus tamu ini?" data-confirm-title="Hapus Tamu">
                                            <button type="submit" class="text-xs text-danger" title="Hapus" aria-label="Hapus">
                                                <x-ui.icon name="trash" class="h-4 w-4" />
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="8" class="text-center text-muted">Tidak ada data</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                @else
                    <div class="p-8">
                        <x-ui.empty-state icon="users" title="Belum ada tamu" description="Tambahkan tamu untuk memulai.">
                            <x-ui.button href="{{ route('admin.guests.create') }}" size="sm" icon="plus">Tambah tamu</x-ui.button>
                        </x-ui.empty-state>
                    </div>
                @endif
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            document.querySelectorAll('[data-confirm]').forEach(form => {
                form.addEventListener('submit', (e) => {
                    if (form.dataset.confirmed === 'true') return;
                    e.preventDefault();
                    Swal.fire({
                        title: form.dataset.confirmTitle || 'Konfirmasi',
                        text: form.dataset.confirm,
                        icon: 'warning',
                        showCancelButton: true,
                        confirmButtonText: form.dataset.confirmButton || 'Ya, lanjutkan',
                        cancelButtonText: 'Batal',
                        confirmButtonColor: getComputedStyle(document.documentElement).getPropertyValue('--color-accent').trim(),
                        cancelButtonColor: getComputedStyle(document.documentElement).getPropertyValue('--color-muted').trim(),
                        reverseButtons: true,
                    }).then(result => {
                        if (result.isConfirmed) {
                            form.dataset.confirmed = 'true';
                            form.submit();
                        }
                    });
                });
            });
        });
    </script>
@endpush