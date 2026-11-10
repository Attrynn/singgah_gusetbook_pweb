@extends('layouts.owner')

@section('title', 'Kehadiran & Check-in')

@section('content')
    @include('workspace.events.partials.event-tabs', ['organization' => $organization, 'event' => $event, 'active' => 'overview'])

    <x-ui.page-header
        eyebrow="Check-in"
        title="Kehadiran &amp; Check-in"
        subtitle="Catat kehadiran tamu secara real time. Buka scanner QR untuk check-in cepat, atau pilih tamu dan simpan catatan manual."
    >
        <a href="{{ route('workspace.events.checkins.scanner', [$organization, $event]) }}" class="btn btn-primary">
            <x-ui.icon name="qr" class="h-4 w-4" /> Buka Scanner QR
        </a>
    </x-ui.page-header>

    {{-- Statistik dasar --}}
    <div class="mt-6 grid gap-5 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
        <x-ui.stat-card label="Jumlah undangan party" :value="$stats['invitations']['parties'] ?? 0" icon="list" tone="accent" hint="Total invitation parties" />
        <x-ui.stat-card label="Total kuota" :value="$stats['invitations']['quota'] ?? 0" icon="banknote" tone="accent" hint="Total kapasitas tamu" />
        <x-ui.stat-card label="Undangan dibuat" :value="$stats['invitations']['created'] ?? 0" icon="file-text" tone="info" hint="Invitations created" />
        <x-ui.stat-card label="Undangan terkirim" :value="$stats['invitations']['sent'] ?? 0" icon="send" tone="info" hint="Invitations sent" />
        <x-ui.stat-card label="Undangan dibuka" :value="$stats['invitations']['opened'] ?? 0" icon="eye" tone="info" hint="Invitations opened" />
        <x-ui.stat-card label="Individu teridentifikasi" :value="$stats['guests']['identified'] ?? 0" icon="user-check" tone="teal" hint="Identified individuals" />
        <x-ui.stat-card label="Kapasitas belum teridentifikasi" :value="$stats['guests']['unidentified_capacity'] ?? 0" icon="pin" tone="warning" hint="Unidentified capacity remaining" />
        <x-ui.stat-card label="RSVP konfirmasi" :value="$stats['rsvp']['confirmed'] ?? 0" icon="check" tone="success" hint="Confirmed RSVP" />
        <x-ui.stat-card label="RSVP ditolak" :value="$stats['rsvp']['declined'] ?? 0" icon="x" tone="danger" hint="Declined RSVP" />
        <x-ui.stat-card label="RSVP tunggu" :value="$stats['rsvp']['pending'] ?? 0" icon="clock" tone="warning" hint="Pending RSVP" />
        <x-ui.stat-card label="RSVP mungkin" :value="$stats['rsvp']['maybe'] ?? 0" icon="star" tone="warning" hint="Maybe RSVP" />
        <x-ui.stat-card label="Hadir (RSVP)" :value="$stats['rsvp']['attendance_count'] ?? 0" icon="clipboard" tone="success" hint="Attendance count" />
        <x-ui.stat-card label="Pass dikeluarkan" :value="$stats['passes']['issued'] ?? 0" icon="ticket" tone="danger" hint="Passes issued" />
        <x-ui.stat-card label="Pass aktif" :value="$stats['passes']['active'] ?? 0" icon="ticket" tone="success" hint="Active passes" />
        <x-ui.stat-card label="Pass dibatalkan" :value="$stats['passes']['revoked'] ?? 0" icon="lock" tone="warning" hint="Revoked passes" />
        <x-ui.stat-card label="Pass kadaluarsa" :value="$stats['passes']['expired'] ?? 0" icon="calendar" tone="danger" hint="Expired passes" />
        <x-ui.stat-card label="Total check-in" :value="$stats['checkins']['total'] ?? 0" icon="users" tone="accent" hint="All check-ins" />
        <x-ui.stat-card label="Belum check-in" :value="$stats['checkins']['not_checked_in'] ?? 0" icon="clock" tone="warning" hint="Not yet checked in" />
        <x-ui.stat-card label="Tingkat kehadiran" :value="$stats['checkins']['completion_percentage'] ?? 0" icon="chart" tone="success" hint="Completion percentage" />
    </div>

    {{-- Filter pencarian --}}
    <div class="card mt-6 p-5 animate-float-in" style="animation-delay: 0.05s">
        <form method="GET"
              action="{{ route('workspace.events.checkins.index', [$organization, $event]) }}"
              class="grid gap-3 md:grid-cols-12">
            <div class="md:col-span-4">
                <x-ui.input name="search" label="Cari tamu" :value="$filters['search'] ?? ''" placeholder="Nama tamu…" />
            </div>
            <div class="md:col-span-2"></div>
            <div class="flex flex-wrap items-end gap-2 md:col-span-6">
                <button type="submit" class="btn btn-primary">
                    <x-ui.icon name="search" class="h-4 w-4" /> Cari
                </button>
                @if (filled($filters['search'] ?? ''))
                    <a href="{{ route('workspace.events.checkins.index', [$organization, $event]) }}" class="btn btn-ghost">
                        <x-ui.icon name="close" class="h-4 w-4" /> Reset
                    </a>
                @endif
            </div>
        </form>
    </div>

    {{-- Distribusi check-in per jam --}}
    @if (! empty($distribution))
        @php
            $maxBucket = collect($distribution)->max('count') ?: 1;
        @endphp
        <div class="card mt-6 p-5">
            <div class="flex items-center justify-between gap-2">
                <div>
                    <h2 class="section-title">Distribusi Check-in per Jam</h2>
                    <p class="mt-0.5 text-xs text-faint">Jumlah check-in yang tercatat pada tiap jam acara.</p>
                </div>
                <span class="badge badge-info">{{ count($distribution) }} jam</span>
            </div>
            <div class="mt-4 space-y-2">
                @foreach ($distribution as $bucket)
                    <div class="flex items-center gap-3">
                        <span class="w-32 shrink-0 text-xs font-medium text-muted">{{ $bucket['hour'] }}</span>
                        <div class="h-2.5 flex-1 overflow-hidden rounded-full bg-surface-2">
                            <div class="h-full rounded-full bg-accent" style="width: {{ max(4, (int) round(($bucket['count'] / $maxBucket) * 100)) }}%"></div>
                        </div>
                        <span class="w-10 shrink-0 text-right text-xs font-semibold text-ink">{{ $bucket['count'] }}</span>
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    {{-- Riwayat check-in --}}
    <div class="card mt-6 p-0">
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
                    description="Catatan check-in akan muncul di sini setelah tamu check-in menggunakan scanner QR atau input manual."
                >
                    <a href="{{ route('workspace.events.checkins.scanner', [$organization, $event]) }}" class="btn btn-primary">
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
                            <th scope="col">Metode</th>
                            <th scope="col" class="whitespace-nowrap">Waktu</th>
                            <th scope="col">Officer</th>
                            <th scope="col">Catatan</th>
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
                                <td>
                                    <span class="badge {{ $checkin->method->badge() }}">{{ $checkin->method->label() }}</span>
                                </td>
                                <td class="whitespace-nowrap text-xs text-muted">
                                    {{ $checkin->checked_in_at?->translatedFormat('d M Y H:i') }}
                                </td>
                                <td class="text-xs text-muted">{{ $checkin->officer?->name ?? '—' }}</td>
                                <td class="text-xs text-muted">{{ $checkin->notes ?? '—' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @include('owner.partials.pagination', ['paginator' => $checkins])
        @endif
    </div>
@endsection
