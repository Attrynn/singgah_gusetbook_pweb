@extends('layouts.admin')

@section('title', 'Song Requests')
@section('subtitle', 'Kelola permintaan lagu dari tamu')

@section('content')
    @php
        $eventId = $filters['event_id'] ?? null;
        $status = $filters['status'] ?? null;
    @endphp

    <x-ui.page-header
        eyebrow="Interactions"
        title="Permintaan Lagu"
        subtitle="Daftar permintaan lagu dari tamu, status dan aksi."
    >
        <div class="flex items-center gap-2">
            <select id="event_id" name="event_id" class="input appearance-none">
                <option value="">Semua event</option>
                @foreach ($events as $event)
                    <option value="{{ $event->id }}" @selected($eventId === $event->id)>
                        {{ $event->coupleNames() }}
                    </option>
                @endforeach
            </select>
            <select id="status" name="status" class="input appearance-none">
                <option value="">Semua status</option>
                @foreach ($statuses as $status)
                    <option value="{{ $status->value }}" @selected($status->value === ($filters['status'] ?? null))>{{ $status->label() }}</option>
                @endforeach
            </select>
        </div>
    </x-ui.page-header>

    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <x-ui.stat-card label="Total" :value="$songs->total() ?? 0" icon="music" tone="accent" hint="Total permintaan" />
    </div>

    <div class="card p-0">
        <div class="flex flex-wrap items-center justify-between gap-2 border-b border-line/70 px-5 py-4">
            <div>
                <h2 class="section-title">Daftar Permintaan</h2>
                <p class="mt-0.5 text-xs text-faint">{{ $songs->total() }} lagu · halaman {{ $songs->currentPage() }} dari {{ $songs->lastPage() }}</p>
            </div>
        </div>

        @if ($songs->isEmpty())
            <div class="p-5">
                <x-ui.empty-state
                    icon="music"
                    title="Belum ada permintaan lagu"
                    description="Permintaan lagu akan muncul di sini setelah tamu mengirimkan."
                >
                    <a href="#" class="btn-primary">Ajukan permintaan</a>
                </x-ui.empty-state>
            </div>
        @else
            <div class="table-wrap m-4">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Lagu</th>
                            <th>Artist</th>
                            <th>Tamu</th>
                            <th>Event</th>
                            <th>Catatan</th>
                            <th>Status</th>
                            <th class="text-right">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($songs as $song)
                            <tr>
                                <td class="font-medium text-ink">{{ $song->song_title }}</td>
                                <td class="text-xs text-muted">{{ $song->artist }}</td>
                                <td>
                                    <div class="flex items-center gap-3">
                                        <x-ui.avatar :name="$song->guest?->name" size="sm" />
                                        <div class="min-w-0">
                                            <p class="truncate font-semibold text-ink">{{ $song->guest?->name ?? 'Tamu tamu' }}</p>
                                            <p class="truncate text-[11px] text-faint">{{ $song->guest?->email ?? '—' }}</p>
                                        </div>
                                    </div>
                                </td>
                                <td class="text-xs text-muted">{{ $song->event?->coupleNames() ?? '—' }}</td>
                                <td class="text-xs text-muted">{{ $song->note ?: '—' }}</td>
                                <td><span class="{{ $song->status->badge() }}">{{ $song->status->label() }}</span></td>
                                <td class="text-right">
                                    <button
                                        type="button"
                                        class="btn-secondary btn-sm"
                                        x-on:click="$dispatch('open-modal-song-{{ $song->id }}')"
                                    >
                                        <x-ui.icon name="edit" class="h-3.5 w-3.5" /> Update
                                    </button>

                                    <x-ui.modal name="song-{{ $song->id }}" title="Update Status Lagu" :description="`Lagu: {{ $song->song_title }} - {{ $song->artist }}`">
                                        <form method="POST" action="{{ route('admin.interactions.songs.update', $song) }}" class="space-y-4">
                                            @csrf
                                            @method('PUT')

                                            <div>
                                                <label for="status-{{ $song->id }}" class="label">Status <span class="text-[#b76a73]">*</span></label>
                                                <select id="status-{{ $song->id }}" name="status" class="input appearance-none" required>
                                                    @foreach ($statuses as $s)
                                                        <option value="{{ $s->value }}" @selected($song->status === $s)>{{ $s->label() }}</option>
                                                    @endforeach
                                                </select>
                                            </div>

                                            <div class="flex items-center justify-end gap-2 pt-1">
                                                <button type="button" class="btn-ghost" x-on:click="$dispatch('close-modal-song-{{ $song->id }}')">Batal</button>
                                                <button type="submit" class="btn-primary">
                                                    <x-ui.icon name="check" class="h-4 w-4" /> Simpan
                                                </button>
                                            </div>
                                        </form>
                                    </x-ui.modal>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="px-4 pb-4">
                {{ $songs->links() }}
            </div>
        @endif
    </div>
@endsection