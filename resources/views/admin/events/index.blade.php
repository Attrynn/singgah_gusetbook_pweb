@extends('layouts.admin')

@section('title', 'Events')
@section('subtitle', 'Buat dan kelola event')

@section('content')
    <x-ui.page-header title="Events" eyebrow="Wedding Events">
        <x-ui.button variant="secondary" href="{{ route('admin.events.create') }}" icon="plus">
            Buat event
        </x-ui.button>
    </x-ui.page-header>

    <div class="mt-6">
        <form method="GET" class="flex items-center gap-4">
            <input type="text" name="search" placeholder="Cari event..." class="input w-64" value="{{ request('search') }}" aria-label="Cari acara">
            <select name="status" class="input w-32" aria-label="Filter status">
                <option value="">Semua status</option>
                @foreach (\App\Enums\EventStatus::cases() as $status)
                    <option value="{{ $status->value }}" {{ request('status') == $status->value ? 'selected' : '' }}>
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
                <h2 class="section-title">Event List</h2>
                <div class="text-xs text-muted">12 events</div>
            </div>
            <div class="divide-y divide-line/70">
                @if ($events->count())
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th scope="col" class="text-left">Nama / Couple</th>
                                <th scope="col" class="text-left">Date</th>
                                <th scope="col" class="text-left">Venue</th>
                                <th scope="col" class="text-left">Status</th>
                                <th scope="col" class="text-left">Guests</th>
                                <th scope="col" class="text-left">RSVP</th>
                                <th scope="col" class="text-left">Check-ins</th>
                                <th scope="col" class="text-left">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($events as $event)
                                <tr class="hover:bg-surface-2/40">
                                    <td class="text-ink">
                                        <a href="{{ route('admin.events.show', $event) }}" class="text-ink">
                                            {{ $event->name }} <span class="text-xs text-muted">({{ $event->coupleNames() }})</span>
                                        </a>
                                    </td>
                                    <td class="text-ink">{{ $event->event_date->format('d M Y') }}</td>
                                    <td class="text-ink">{{ $event->primaryVenue->name ?? '-' }}</td>
                                    <td class="text-ink">
                                        <span class="{{ $event->status->badge() }}">
                                            {{ $event->status->label() }}
                                        </span>
                                    </td>
                                    <td class="text-ink">{{ $event->guests_count }}</td>
                                    <td class="text-ink">{{ $event->rsvps_count }}</td>
                                    <td class="text-ink">{{ $event->checkins_count }}</td>
                                    <td class="text-ink">
                                        <a href="{{ route('admin.events.show', $event) }}" class="text-xs text-muted" title="Lihat detail">
                                            <x-ui.icon name="eye" class="h-4 w-4" />
                                        </a>
                                        <a href="{{ route('admin.events.edit', $event) }}" class="text-xs text-muted" title="Edit">
                                            <x-ui.icon name="edit" class="h-4 w-4" />
                                        </a>
                                        <form method="POST" action="{{ route('admin.events.destroy', $event) }}" class="inline" onsubmit="return confirm('Yakin ingin menghapus event ini?');">
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
                        <x-ui.empty-state icon="calendar" title="Belum ada event" description="Buat event pertama Anda untuk memulai.">
                            <x-ui.button href="{{ route('admin.events.create') }}" size="sm" icon="plus">Buat event</x-ui.button>
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