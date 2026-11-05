@extends('layouts.owner')

@section('title', 'Kehadiran')

@section('content')
    <x-ui.page-header title="Kehadiran" subtitle="Pantau RSVP dan check-in tamu secara real-time." />

    {{-- Headline Stats --}}
    <div class="grid grid-cols-2 gap-3 mb-5">
        <x-ui.stat-card label="Total tamu" :value="$stats['total_guests'] ?? 0" icon="users" tone="accent" />
        <x-ui.stat-card label="Konfirmasi" :value="$stats['confirmed'] ?? 0" icon="user-check" tone="success" />
        <x-ui.stat-card label="Diperkirakan hadir" :value="$stats['expected_attendance'] ?? 0" icon="calendar-check" tone="teal" />
        <x-ui.stat-card label="Sudah check-in" :value="$stats['checked_in'] ?? 0" icon="ticket" tone="warning" />
    </div>

    <div class="card p-5 mb-5 text-center">
        <p class="text-[10px] font-semibold uppercase tracking-[0.12em] text-muted">Tingkat kehadiran</p>
        <p class="mt-1 font-serif text-4xl font-bold text-ink">{{ number_format($stats['attendance_rate'] ?? 0, 1) }}%</p>
        <p class="mt-1 text-sm text-muted">dari {{ $stats['confirmed'] ?? 0 }} tamu yang konfirmasi</p>
    </div>

    {{-- RSVP Breakdown --}}
    <div class="card p-5 mb-5">
        <p class="section-kicker">RSVP Breakdown</p>
        <div class="mt-4 space-y-3">
            @foreach ($breakdown as $item)
                @php
                    $total = $stats['total_guests'] ?? 1;
                    $percent = $total > 0 ? round(($item['count'] / $total) * 100) : 0;
                    $badgeClass = match ($item['label']) {
                        'Confirmed' => 'badge-success',
                        'Declined' => 'badge-danger',
                        'Maybe' => 'badge-warning',
                        default => 'badge-neutral',
                    };
                @endphp
                <div>
                    <div class="flex items-center justify-between text-sm mb-1">
                        <span class="font-medium text-ink">{{ $item['label'] }}</span>
                        <span class="text-muted">{{ $item['count'] }} ({{ $percent }}%)</span>
                    </div>
                    <div class="h-2 bg-surface-2 rounded-full overflow-hidden">
                        <div class="h-full rounded-full {{ $badgeClass }} transition-all duration-500" style="width: {{ $percent }}%"></div>
                    </div>
                </div>
            @endforeach
        </div>
    </div>

    {{-- Check-in Timeline Chart --}}
    <div class="card p-5 mb-5">
        <p class="section-kicker">Timeline Check-in</p>
        @php
            $timelineLabels = collect($timeline)->pluck('label')->map(fn($l) => explode(' ', $l)[1] ?? $l)->all();
            $timelineCounts = collect($timeline)->pluck('count')->all();
        @endphp
        @if (!empty($timelineLabels))
            <div class="mt-4 h-48">
                <canvas id="checkinChart"></canvas>
            </div>
        @else
            <x-ui.empty-state icon="chart" title="Belum ada data check-in" description="Grafik akan muncul setelah tamu mulai check-in." />
        @endif
    </div>

    {{-- Latest Check-ins --}}
    <div class="card p-5 mb-5">
        <div class="flex items-center justify-between gap-3 mb-3">
            <p class="section-kicker">Check-in terbaru</p>
        </div>
        @if ($latestCheckins->isNotEmpty())
            <div class="space-y-3">
                @foreach ($latestCheckins as $checkin)
                    <div class="flex items-center justify-between gap-3 p-3 rounded-2xl bg-surface-2/60">
                        <div class="flex items-center gap-3">
                            <x-ui.avatar :name="$checkin->guest->name" size="sm" />
                            <div>
                                <p class="font-semibold text-sm text-ink">{{ $checkin->guest->name }}</p>
                                <p class="text-[10px] text-muted">{{ $checkin->method->label() }}</p>
                            </div>
                        </div>
                        <p class="text-[10px] text-faint shrink-0">{{ $checkin->checked_in_at?->translatedFormat('d M Y H:i') }}</p>
                    </div>
                @endforeach
            </div>
        @else
            <x-ui.empty-state icon="ticket" title="Belum ada check-in" description="Tamu belum melakukan check-in." />
        @endif
    </div>

    {{-- Confirmed Guests --}}
    <div class="card p-5">
        <p class="section-kicker">Tamu yang konfirmasi hadir</p>
        @if ($confirmedGuests->isNotEmpty())
            <div class="mt-4 space-y-3 max-h-96 overflow-y-auto scrollbar-none">
                @foreach ($confirmedGuests as $guest)
                    <div class="flex items-center justify-between gap-3 p-3 rounded-2xl bg-surface-2/60">
                        <div class="flex items-center gap-3">
                            <x-ui.avatar :name="$guest->name" size="sm" />
                            <div>
                                <p class="font-semibold text-sm text-ink">{{ $guest->name }}</p>
                                <p class="text-[10px] text-muted">
                                    {{ $guest->max_attendees > 1 ? '+'.($guest->max_attendees - 1).' tamu' : '1 orang' }}
                                </p>
                            </div>
                        </div>
                        <x-ui.badge variant="success" class="text-[10px]">{{ $guest->rsvp_status->label() }}</x-ui.badge>
                    </div>
                @endforeach
            </div>
        @else
            <x-ui.empty-state icon="user-check" title="Belum ada tamu konfirmasi" description="Tamu yang mengonfirmasi kehadiran akan muncul di sini." />
        @endif
    </div>

    @push('scripts')
        @if (!empty($timelineLabels))
            <script>
                const ctx = document.getElementById('checkinChart');
                if (ctx) {
                    new window.Chart(ctx.getContext('2d'), {
                        type: 'line',
                        data: {
                            labels: @json($timelineLabels),
                            datasets: [{
                                label: 'Check-in per jam',
                                data: @json($timelineCounts),
                                borderColor: 'var(--color-accent)',
                                backgroundColor: 'rgba(142, 180, 211, 0.12)',
                                borderWidth: 2,
                                pointRadius: 4,
                                pointBackgroundColor: 'var(--color-accent)',
                                fill: true,
                                tension: 0.35
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: { legend: { display: false } },
                            scales: {
                                x: { grid: { display: false }, ticks: { color: 'var(--color-faint)', font: { size: 11 } } },
                                y: { grid: { color: 'var(--color-line)' }, beginAtZero: true, ticks: { color: 'var(--color-faint)', font: { size: 11 }, stepSize: 1 } }
                            }
                        }
                    });
                }
            </script>
        @endif
    @endpush
@endsection