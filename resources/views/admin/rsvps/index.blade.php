@extends('layouts.admin')

@section('title', 'RSVP')
@section('subtitle', 'Pantau konfirmasi kehadiran tamu')

@section('content')
    @php
        $eventId = $filters['event_id'] ?? null;
        $statusFilter = $filters['status'] ?? null;
        $search = $filters['search'] ?? null;
    @endphp

    <div class="space-y-6">
        <x-ui.page-header
            eyebrow="Guests"
            title="RSVP Tamu"
            subtitle="Lihat dan sesuaikan konfirmasi kehadiran, jumlah tamu, serta preferensi konsumsi."
        >
            <x-ui.button :href="route('admin.rsvps.export', ['event_id' => $eventId])" variant="secondary" icon="download">
                Export CSV
            </x-ui.button>
            <x-ui.button :href="route('admin.checkins.index', ['event_id' => $eventId])" variant="soft" icon="qr">
                Check-in
            </x-ui.button>
        </x-ui.page-header>

        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <x-ui.stat-card label="Confirmed" :value="$stats['confirmed'] ?? 0" icon="check" tone="success" hint="Tamu yang akan hadir" />
            <x-ui.stat-card label="Declined" :value="$stats['declined'] ?? 0" icon="x" tone="danger" hint="Tamu yang tidak hadir" />
            <x-ui.stat-card label="Maybe" :value="$stats['maybe'] ?? 0" icon="clock" tone="warning" hint="Masih belum pasti" />
            <x-ui.stat-card label="Total Kehadiran" :value="$stats['attendance'] ?? 0" icon="users" tone="teal" hint="Akumulasi attendance count" />
        </div>

        <form method="GET" action="{{ route('admin.rsvps.index') }}" class="card-flat p-4">
            <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-4">
                <div>
                    <label for="event_id" class="label">Event</label>
                    <select id="event_id" name="event_id" class="input appearance-none">
                        <option value="">Semua event</option>
                        @foreach ($events as $event)
                            <option value="{{ $event->id }}" @selected((string) $eventId === (string) $event->id)>
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
                            <option value="{{ $status->value }}" @selected($statusFilter === $status->value)>{{ $status->label() }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label for="search" class="label">Cari tamu</label>
                    <input id="search" type="search" name="search" value="{{ $search }}" placeholder="Nama tamu…" class="input">
                </div>

                <div class="flex items-end gap-2">
                    <button type="submit" class="btn-primary flex-1">
                        <x-ui.icon name="search" class="h-4 w-4" /> Terapkan
                    </button>
                    <a href="{{ route('admin.rsvps.index') }}" class="btn-ghost">Reset</a>
                </div>
            </div>
        </form>

        <div class="card p-0">
            <div class="flex flex-wrap items-center justify-between gap-2 border-b border-line/70 px-5 py-4">
                <div>
                    <h2 class="section-title">Daftar RSVP</h2>
                    <p class="mt-0.5 text-xs text-faint">{{ $rsvps->total() }} respons · halaman {{ $rsvps->currentPage() }} dari {{ $rsvps->lastPage() }}</p>
                </div>
                <span class="badge-info">{{ $rsvps->count() }} baris ditampilkan</span>
            </div>

            @if ($rsvps->isEmpty())
                <div class="p-5">
                    <x-ui.empty-state
                        icon="clipboard"
                        title="Belum ada RSVP"
                        description="Respons tamu akan muncul di sini setelah mereka mengisi formulir RSVP."
                    >
                        <x-ui.button :href="route('admin.guests.index')" variant="soft" icon="users">Kelola tamu</x-ui.button>
                    </x-ui.empty-state>
                </div>
            @else
                <div class="table-wrap m-4">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Tamu</th>
                                <th>Event</th>
                                <th>Status</th>
                                <th class="text-center">Hadir</th>
                                <th>Pendamping</th>
                                <th>Diet</th>
                                <th>Responded</th>
                                <th class="text-right">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($rsvps as $rsvp)
                                @php($guest = $rsvp->guest)
                                <tr>
                                    <td>
                                        <div class="flex items-center gap-3">
                                            <x-ui.avatar :name="$guest?->name ?? 'Tamu'" size="sm" />
                                            <div class="min-w-0">
                                                <p class="truncate font-semibold text-ink">{{ $guest?->name ?? 'Tamu dihapus' }}</p>
                                                <p class="truncate text-[11px] text-faint">
                                                    {{ $guest?->category?->name ?? 'Tanpa kategori' }}
                                                    @if ($guest?->group)
                                                        · {{ $guest->group->name }}
                                                    @endif
                                                </p>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <p class="text-xs font-medium text-ink">{{ $rsvp->event?->coupleNames() ?? '—' }}</p>
                                        <p class="text-[11px] text-faint">{{ $rsvp->event?->event_date?->format('d M Y') ?? '' }}</p>
                                    </td>
                                    <td><span class="badge {{ $rsvp->status->badge() }}">{{ $rsvp->status->label() }}</span></td>
                                    <td class="text-center font-semibold">{{ $rsvp->attendance_count }}</td>
                                    <td class="max-w-[180px]">
                                        <p class="truncate text-xs text-muted" title="{{ $rsvp->companion_names }}">{{ $rsvp->companion_names ?: '—' }}</p>
                                    </td>
                                    <td class="max-w-[140px]">
                                        <p class="truncate text-xs text-muted" title="{{ $rsvp->dietary_preference }}">{{ $rsvp->dietary_preference ?: '—' }}</p>
                                    </td>
                                    <td class="whitespace-nowrap text-xs text-muted">
                                        {{ $rsvp->responded_at?->translatedFormat('d M Y H:i') ?? 'Belum merespons' }}
                                    </td>
                                    <td class="text-right">
                                        @if ($guest)
                                            <button
                                                type="button"
                                                class="btn-secondary btn-sm"
                                                x-on:click="$dispatch('open-modal-rsvp-{{ $rsvp->id }}')"
                                            >
                                                <x-ui.icon name="edit" class="h-3.5 w-3.5" /> Update
                                            </button>

                                            <x-ui.modal name="rsvp-{{ $rsvp->id }}" title="Update RSVP" :description="$guest->name">
                                                <form method="POST" action="{{ route('admin.rsvps.update', $guest) }}" class="space-y-4">
                                                    @csrf
                                                    @method('PUT')

                                                    <div>
                                                        <label for="status-{{ $rsvp->id }}" class="label">Status kehadiran <span class="text-[#b76a73]">*</span></label>
                                                        <select id="status-{{ $rsvp->id }}" name="status" class="input appearance-none" required>
                                                            @foreach ($statuses as $status)
                                                                @continue($status === \App\Enums\RsvpStatus::Pending)
                                                                <option value="{{ $status->value }}" @selected($rsvp->status === $status)>{{ $status->label() }}</option>
                                                            @endforeach
                                                        </select>
                                                        <p class="hint">Status Pending tidak dapat dipilih manual.</p>
                                                    </div>

                                                    <div class="grid gap-4 sm:grid-cols-2">
                                                        <div>
                                                            <label for="attendance-{{ $rsvp->id }}" class="label">Jumlah hadir</label>
                                                            <input id="attendance-{{ $rsvp->id }}" type="number" name="attendance_count" min="0" max="50" value="{{ old('attendance_count', $rsvp->attendance_count) }}" class="input">
                                                        </div>
                                                        <div>
                                                            <label for="diet-{{ $rsvp->id }}" class="label">Preferensi diet</label>
                                                            <input id="diet-{{ $rsvp->id }}" type="text" name="dietary_preference" maxlength="255" value="{{ old('dietary_preference', $rsvp->dietary_preference) }}" class="input" placeholder="Contoh: vegetarian">
                                                        </div>
                                                    </div>

                                                    <div>
                                                        <label for="companions-{{ $rsvp->id }}" class="label">Nama pendamping</label>
                                                        <textarea id="companions-{{ $rsvp->id }}" name="companion_names" rows="2" maxlength="2000" class="input resize-y" placeholder="Satu nama per baris">{{ old('companion_names', $rsvp->companion_names) }}</textarea>
                                                    </div>

                                                    <div>
                                                        <label for="notes-{{ $rsvp->id }}" class="label">Catatan</label>
                                                        <textarea id="notes-{{ $rsvp->id }}" name="notes" rows="2" maxlength="2000" class="input resize-y">{{ old('notes', $rsvp->notes) }}</textarea>
                                                    </div>

                                                    <div class="flex flex-wrap items-center justify-end gap-2 pt-1">
                                                        <button type="button" class="btn-ghost" x-on:click="$dispatch('close-modal-rsvp-{{ $rsvp->id }}')">Batal</button>
                                                        <button type="submit" class="btn-primary">
                                                            <x-ui.icon name="check" class="h-4 w-4" /> Simpan
                                                        </button>
                                                    </div>
                                                </form>
                                            </x-ui.modal>
                                        @else
                                            <span class="text-xs text-faint">—</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="px-4 pb-4">
                    {{ $rsvps->links() }}
                </div>
            @endif
        </div>
    </div>
@endsection
