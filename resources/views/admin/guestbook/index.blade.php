@extends('layouts.admin')

@section('title', 'Guestbook')
@section('subtitle', 'Ucapan tamu dan moderasi')

@section('content')
    @php
        $eventId = $filters['event_id'] ?? null;
        $status = $filters['status'] ?? null;
        $visibility = $filters['visibility'] ?? null;
        $search = $filters['search'] ?? null;
    @endphp

    <x-ui.page-header
        eyebrow="Guests"
        title="Guestbook"
        subtitle="Ucapan tamu dan moderasi"
    >
        <x-ui.button :href="route('admin.guestbook.export', ['event_id' => $eventId])" variant="secondary" icon="download">
            Export CSV
        </x-ui.button>
    </x-ui.page-header>

    <form method="GET" action="{{ route('admin.guestbook.index') }}" class="card-flat p-4">
        <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-4">
            <div>
                <label for="event_id" class="label">Event</label>
                <select id="event_id" name="event_id" class="input appearance-none">
                    <option value="">Semua event</option>
                    @foreach ($events as $event)
                        <option value="{{ $event->id }}" @selected($eventId === $event->id)>
                            {{ $event->coupleNames() }} · {{ $event->event_date?->format('d M Y') }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div>
                <label for="status" class="label">Status</label>
                <select id="status" name="status" class="input appearance-none">
                    <option value="">Semua status</option>
                    @foreach ($statuses as $status)
                        <option value="{{ $status->value }}" @selected($status->value === ($filters['status'] ?? null))>{{ $status->label() }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label for="visibility" class="label">Visibilitas</label>
                <select id="visibility" name="visibility" class="input appearance-none">
                    <option value="">Semua visibilitas</option>
                    <option value="public" @selected(($filters['visibility'] ?? null) === 'public')>Public</option>
                    <option value="private" @selected(($filters['visibility'] ?? null) === 'private')>Private</option>
                </select>
            </div>

            <div>
                <label for="search" class="label">Cari ucapan</label>
                <input id="search" type="search" name="search" value="{{ $search }}" placeholder="Nama atau isi ucapan…" class="input">
            </div>

            <div class="flex items-end gap-2">
                <button type="submit" class="btn-primary flex-1">
                    <x-ui.icon name="search" class="h-4 w-4" /> Terapkan
                </button>
                <a href="{{ route('admin.guestbook.index') }}" class="btn-ghost">Reset</a>
            </div>
        </div>
    </form>

    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <x-ui.stat-card label="Total" :value="$stats['total'] ?? 0" icon="message" tone="accent" hint="Semua ucapan" />
        <x-ui.stat-card label="Pending" :value="$stats['pending'] ?? 0" icon="clock" tone="warning" hint="Menunggu persetujuan" />
        <x-ui.stat-card label="Approved" :value="$stats['approved'] ?? 0" icon="check" tone="success" hint="Sudah disetujui" />
        <x-ui.stat-card label="Private" :value="$stats['private'] ?? 0" icon="lock" tone="info" hint="Privat" />
    </div>

    <div class="card p-0">
        <div class="flex flex-wrap items-center justify-between gap-2 border-b border-line/70 px-5 py-4">
            <div>
                <h2 class="section-title">Ucapan Tamu</h2>
                <p class="mt-0.5 text-xs text-faint">{{ $messages->total() }} ucapan · halaman {{ $messages->currentPage() }} dari {{ $messages->lastPage() }}</p>
            </div>
        </div>

        @if ($messages->isEmpty())
            <div class="p-5">
                <x-ui.empty-state
                    icon="message"
                    title="Belum ada ucapan"
                    description="Ucapan tamu akan muncul di sini setelah tamu mengirimkan."
                >
                    <a href="#" class="btn-primary">Ajukan ucapan</a>
                </x-ui.empty-state>
            </div>
        @else
            <div class="table-wrap m-4">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Sender</th>
                            <th>Ucapan</th>
                            <th>Visibilitas</th>
                            <th>Status</th>
                            <th class="text-center">Pinned</th>
                            <th>Waktu</th>
                            <th class="text-right">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($messages as $message)
                            <tr>
                                <td>
                                    <div class="flex items-center gap-3">
                                        <x-ui.avatar :name="$message->sender_name" size="sm" />
                                        <div class="min-w-0">
                                            <p class="truncate font-semibold text-ink">{{ $message->sender_name }}</p>
                                            <p class="truncate text-[11px] text-faint">{{ $message->guest?->name ?? '—' }}</p>
                                        </div>
                                    </div>
                                </td>
                                <td class="max-w-[300px]">
                                    <p class="truncate text-sm text-ink" title="{{ $message->message }}">{{ $message->message }}</p>
                                </td>
                                <td>
                                    <span class="badge {{ $message->visibility->badge() }}">{{ $message->visibility->label() }}</span>
                                </td>
                                <td>
                                    <span class="{{ $message->status->badge() }}">{{ $message->status->label() }}</span>
                                </td>
                                <td class="text-center">
                                    @if ($message->is_pinned)
                                        <span class="badge-success">Yes</span>
                                    @else
                                        <button
                                            type="button"
                                            class="btn-ghost btn-xs"
                                            x-on:click="$dispatch('toggle-pin-{{ $message->id }}')"
                                        >
                                            <x-ui.icon name="pin" class="h-3 w-3" />
                                        </button>
                                    @endif
                                </td>
                                <td class="whitespace-nowrap text-xs text-muted">
                                    {{ $message->created_at?->format('d M Y H:i') }}
                                </td>
                                <td class="text-right">
                                    <form method="POST" action="{{ route('admin.guestbook.moderate', $message) }}" class="inline">
                                        @csrf
                                        @method('POST')
                                        <button type="submit" class="btn-ghost btn-xs" data-action="approve" title="Approve">
                                            <x-ui.icon name="check" class="h-3 w-3" />
                                        </button>
                                    </form>
                                    <form method="POST" action="{{ route('admin.guestbook.pin', $message) }}" class="inline">
                                        @csrf
                                        @if ($message->is_pinned)
                                            @method('POST')
                                            <button type="submit" class="btn-ghost btn-xs" data-action="unpin" title="Unpin">
                                                <x-ui.icon name="pin-off" class="h-3 w-3" />
                                            </button>
                                        @else
                                            @method('POST')
                                            <button type="submit" class="btn-ghost btn-xs" data-action="pin" title="Pin">
                                                <x-ui.icon name="pin" class="h-3 w-3" />
                                            </button>
                                        @endif
                                    </form>
                                    <form method="POST" action="{{ route('admin.guestbook.destroy', $message) }}" class="inline" data-confirm="Yakin menghapus ucapan ini?">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn-ghost btn-xs text-danger" title="Hapus">
                                            <x-ui.icon name="trash" class="h-3 w-3" />
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="px-4 pb-4">
                {{ $messages->links() }}
            </div>
        @endif
    </div>
@endsection