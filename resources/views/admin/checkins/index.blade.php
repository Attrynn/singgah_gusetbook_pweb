@extends('layouts.admin')

@section('title', 'Check-in')
@section('subtitle', 'Catat kehadiran tamu secara manual atau lewat scanner QR')

@section('content')
    @php
        $eventId = $filters['event_id'] ?? null;
        $search = $filters['search'] ?? null;
    @endphp

    <div class="space-y-6">
        <x-ui.page-header
            eyebrow="Guests"
            title="Check-in Tamu"
            subtitle="Catat kehadiran tamu secara manual atau buka scanner QR untuk scan cepat."
        >
            <a href="{{ route('admin.checkins.scanner', ['event_id' => $eventId]) }}" class="btn-primary">
                <x-ui.icon name="qr" class="h-4 w-4" /> Buka Scanner QR
            </a>
        </x-ui.page-header>

        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <x-ui.stat-card label="Total Check-in" :value="$stats['total'] ?? 0" icon="users" tone="accent" hint="Semua waktu" />
            <x-ui.stat-card label="Hari Ini" :value="$stats['today'] ?? 0" icon="clock" tone="success" hint="Check-in hari ini" />
            <x-ui.stat-card label="QR" :value="$stats['qr'] ?? 0" icon="qr" tone="info" hint="Via scan QR" />
            <x-ui.stat-card label="Manual" :value="$stats['manual'] ?? 0" icon="edit" tone="warning" hint="Input manual" />
        </div>

        @if ($scanResult = session('scan_result'))
            <div class="card p-4">
                <div class="flex items-start gap-3">
                    <x-ui.icon name="alert" class="mt-0.5 h-4 w-4 shrink-0" />
                    <div>
                        <p class="font-semibold text-ink">{{ $scanResult['message'] }}</p>
                        @if ($scanResult['guest_name'])
                            <p class="mt-0.5 text-xs text-faint">Tamu: {{ $scanResult['guest_name'] }}</p>
                        @endif
                        @if ($scanResult['warning'])
                            <p class="mt-1 text-xs text-warning">{{ $scanResult['warning'] }}</p>
                        @endif
                    </div>
                </div>
            </div>
        @endif

        <div class="card p-0">
            <div class="flex flex-wrap items-center justify-between gap-2 border-b border-line/70 px-5 py-4">
                <div>
                    <h2 class="section-title">Check-in Manual</h2>
                    <p class="mt-0.5 text-xs text-faint">Pilih tamu yang belum check-in dan catat catatan tambahan.</p>
                </div>
            </div>

            <div class="p-4">
                <form method="POST" action="{{ route('admin.checkins.manual') }}" class="space-y-4">
                    @csrf

                    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                        <div>
                            <label for="guest_id" class="label">Pilih tamu <span class="text-danger">*</span></label>
                            <select id="guest_id" name="guest_id" class="input appearance-none" required>
                                <option value="">Cari atau pilih tamu…</option>
                                @foreach ($guests as $guest)
                                    <option value="{{ $guest->id }}" data-event="{{ $guest->event_id }}">
                                        {{ $guest->name }}
                                        @if ($guest->event)
                                            · {{ $guest->event->coupleNames() }}
                                        @endif
                                    </option>
                                @endforeach
                            </select>
                            <p class="hint">Hanya tamu yang belum check-in ditampilkan.</p>
                        </div>

                        <div>
                            <label for="notes" class="label">Catatan</label>
                            <input id="notes" type="text" name="notes" maxlength="255" placeholder="Catatan tambahan…" class="input">
                        </div>

                        <div class="flex items-end gap-2">
                            <button type="submit" class="btn-primary flex-1">
                                <x-ui.icon name="check" class="h-4 w-4" /> Simpan Check-in
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <div class="card p-0">
            <div class="flex flex-wrap items-center justify-between gap-2 border-b border-line/70 px-5 py-4">
                <div>
                    <h2 class="section-title">Riwayat Check-in</h2>
                    <p class="mt-0.5 text-xs text-faint">{{ $checkins->total() }} catatan · halaman {{ $checkins->currentPage() }} dari {{ $checkins->lastPage() }}</p>
                </div>
            </div>

            @if ($checkins->isEmpty())
                <div class="p-5">
                    <x-ui.empty-state
                        icon="clock"
                        title="Belum ada check-in"
                        description="Catatan check-in akan muncul di sini setelah tamu check-in."
                    >
                        <a href="{{ route('admin.checkins.scanner', ['event_id' => $eventId]) }}" class="btn-primary">
                            <x-ui.icon name="qr" class="h-4 w-4" /> Buka Scanner QR
                        </a>
                    </x-ui.empty-state>
                </div>
            @else
                <div class="table-wrap m-4">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th scope="col">Tamu</th>
                                <th scope="col">Event</th>
                                <th scope="col">Metode</th>
                                <th scope="col">Officer</th>
                                <th scope="col" class="whitespace-nowrap">Waktu</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($checkins as $checkin)
                                <tr>
                                    <td>
                                        <div class="flex items-center gap-3">
                                            <x-ui.avatar :name="$checkin->guest?->name ?? 'Tamu'" size="sm" />
                                            <div class="min-w-0">
                                                <p class="truncate font-semibold text-ink">{{ $checkin->guest?->name ?? 'Tamu dihapus' }}</p>
                                                <p class="truncate text-[11px] text-faint">{{ $checkin->event?->coupleNames() ?? '—' }}</p>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="text-xs text-muted">{{ $checkin->event?->coupleNames() ?? '—' }}</td>
                                    <td>
                                        <span class="badge {{ $checkin->method->badge() }}">{{ $checkin->method->label() }}</span>
                                    </td>
                                    <td class="text-xs text-muted">{{ $checkin->officer?->name ?? '—' }}</td>
                                    <td class="whitespace-nowrap text-xs text-muted">
                                        {{ $checkin->checked_in_at?->translatedFormat('d M Y H:i') }}
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="px-4 pb-4">
                    {{ $checkins->links() }}
                </div>
            @endif
        </div>
    </div>
@endsection
