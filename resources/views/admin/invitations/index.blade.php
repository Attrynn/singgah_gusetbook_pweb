@extends('layouts.admin')

@section('title', 'Undangan')
@section('subtitle', 'Kelola tautan undangan personal')

@section('content')
    <x-ui.page-header title="Undangan" eyebrow="Invitations" subtitle="Pantau status pengiriman dan pembukaan undangan tamu." />

    <div class="mt-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <x-ui.stat-card label="Total undangan" :value="$stats['total']" icon="mail" />
        <x-ui.stat-card label="Sudah dibuka" :value="$stats['opened']" icon="eye" tone="success" />
        <x-ui.stat-card label="Sudah dibagikan" :value="$stats['shared']" icon="share" tone="teal" />
        <x-ui.stat-card label="Belum dikirim" :value="$stats['not_sent']" icon="clock" tone="warning" />
    </div>

    <form method="GET" action="{{ route('admin.invitations.index') }}" class="card-flat mt-6 p-4">
        <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-4">
            <div>
                <label for="event_id" class="label">Event</label>
                <select id="event_id" name="event_id" class="input appearance-none">
                    <option value="">Semua event</option>
                    @foreach ($events as $event)
                        <option value="{{ $event->id }}" @selected(($filters['event_id'] ?? null) == $event->id)>{{ $event->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="search" class="label">Cari tamu</label>
                <input id="search" name="search" type="text" class="input" value="{{ $filters['search'] ?? '' }}" placeholder="Nama tamu...">
            </div>
            <div>
                <label for="status" class="label">Status</label>
                <select id="status" name="status" class="input appearance-none">
                    <option value="">Semua status</option>
                    @foreach ($statuses as $status)
                        <option value="{{ $status->value }}" @selected(($filters['status'] ?? null) === $status->value)>{{ $status->label() }}</option>
                    @endforeach
                </select>
            </div>
            <div class="flex items-end gap-2">
                <button type="submit" class="btn-primary flex-1">
                    <x-ui.icon name="search" class="h-4 w-4" /> Terapkan
                </button>
                <a href="{{ route('admin.invitations.index') }}" class="btn-ghost">Reset</a>
            </div>
        </div>
    </form>

    <div class="card mt-6 p-0">
        <div class="flex flex-wrap items-center justify-between gap-2 border-b border-line/70 px-5 py-4">
            <div>
                <h2 class="section-title">Daftar undangan</h2>
                <p class="mt-0.5 text-xs text-faint">{{ $guests->total() }} tamu · halaman {{ $guests->currentPage() }} dari {{ $guests->lastPage() }}</p>
            </div>
            <a href="{{ route('admin.guests.index') }}" class="btn btn-ghost btn-sm">
                <x-ui.icon name="users" class="h-3.5 w-3.5" /> Kelola tamu
            </a>
        </div>

        @if ($guests->isEmpty())
            <div class="p-5">
                <x-ui.empty-state icon="mail" title="Belum ada undangan" description="Tambahkan tamu terlebih dahulu; undangan dibuat otomatis.">
                    <x-ui.button :href="route('admin.guests.create')" variant="primary" icon="plus">Tambah tamu</x-ui.button>
                </x-ui.empty-state>
            </div>
        @else
            <div class="table-wrap">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th class="text-left">Tamu</th>
                            <th class="text-left">Event</th>
                            <th class="text-left">Kategori</th>
                            <th class="text-left">Status</th>
                            <th class="text-left">Dibuka</th>
                            <th class="text-left">Dibagikan</th>
                            <th class="text-left">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($guests as $guest)
                            <tr class="hover:bg-surface-2/40">
                                <td class="text-ink">
                                    <a href="{{ route('admin.invitations.show', $guest) }}" class="font-medium text-ink">{{ $guest->name }}</a>
                                    <p class="text-xs text-muted">{{ $guest->email ?: $guest->phone ?: '—' }}</p>
                                </td>
                                <td class="text-ink">{{ $guest->event?->name ?? '—' }}</td>
                                <td class="text-ink">{{ $guest->category?->name ?? '—' }}</td>
                                <td>
                                    <x-ui.badge :variant="$guest->invitation?->status->badge()">{{ $guest->invitation?->status->label() ?? 'Belum dibuat' }}</x-ui.badge>
                                </td>
                                <td class="text-ink">
                                    {{ $guest->invitation?->opened_at?->translatedFormat('d M H:i') ?? '—' }}
                                    @if ($guest->invitation?->open_count)
                                        <p class="text-[11px] text-faint">{{ $guest->invitation->open_count }}× dibuka</p>
                                    @endif
                                </td>
                                <td class="text-ink">{{ $guest->invitation?->last_shared_at?->translatedFormat('d M H:i') ?? '—' }}</td>
                                <td>
                                    <div class="flex items-center gap-1.5">
                                        <a href="{{ route('admin.invitations.show', $guest) }}" class="icon-btn h-8 w-8" title="Detail undangan">
                                            <x-ui.icon name="eye" class="h-3.5 w-3.5" />
                                        </a>
                                        <a href="{{ route('admin.invitations.preview', $guest) }}" target="_blank" rel="noopener" class="icon-btn h-8 w-8" title="Pratinjau undangan">
                                            <x-ui.icon name="external-link" class="h-3.5 w-3.5" />
                                        </a>
                                        <a href="{{ route('admin.invitations.whatsapp', $guest) }}" class="icon-btn h-8 w-8" title="Bagikan via WhatsApp">
                                            <x-ui.icon name="send" class="h-3.5 w-3.5" />
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="px-4 pb-4">{{ $guests->links() }}</div>
        @endif
    </div>
@endsection
