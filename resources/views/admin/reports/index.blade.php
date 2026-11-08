@extends('layouts.admin')

@section('title', 'Laporan')
@section('subtitle', 'Statistik dan ekspor data event')

@section('content')
    <x-ui.page-header title="Laporan" eyebrow="Reports" subtitle="Ringkasan performa undangan, RSVP, kehadiran, dan interaksi tamu.">
        @if ($event)
            <x-ui.button variant="secondary" :href="route('admin.reports.pdf', ['event_id' => $event->id])" icon="file-text">
                Unduh PDF
            </x-ui.button>
        @endif
    </x-ui.page-header>

    <form method="GET" action="{{ route('admin.reports.index') }}" class="card-flat mt-6 p-4">
        <div class="grid gap-3 md:grid-cols-[minmax(0,320px)_auto]">
            <div>
                <label for="event_id" class="label">Event</label>
                <select id="event_id" name="event_id" class="input appearance-none" data-auto-submit>
                    @foreach ($events as $item)
                        <option value="{{ $item->id }}" @selected($event?->id === $item->id)>{{ $item->name }} · {{ $item->event_date?->format('d M Y') }}</option>
                    @endforeach
                </select>
            </div>
            <div class="flex items-end">
                <button type="submit" class="btn-primary">
                    <x-ui.icon name="search" class="h-4 w-4" /> Tampilkan
                </button>
            </div>
        </div>
    </form>

    @if (! $event)
        <div class="mt-6">
            <x-ui.empty-state icon="calendar" title="Belum ada event" description="Buat event terlebih dahulu untuk melihat laporan.">
                <x-ui.button :href="route('admin.events.create')" variant="primary" icon="plus">Buat event</x-ui.button>
            </x-ui.empty-state>
        </div>
    @else
        <div class="mt-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <x-ui.stat-card label="Total tamu" :value="$stats['total_guests']" icon="users" hint="RSVP rate {{ $stats['rsvp_rate'] }}%" />
            <x-ui.stat-card label="Konfirmasi hadir" :value="$stats['confirmed']" icon="check" tone="success" hint="Perkiraan {{ $stats['expected_attendance'] }} orang" />
            <x-ui.stat-card label="Sudah check-in" :value="$stats['checked_in']" icon="calendar-check" tone="teal" hint="{{ $stats['attendance_rate'] }}% dari konfirmasi" />
            <x-ui.stat-card label="Belum merespons" :value="$stats['pending']" icon="clock" tone="warning" hint="Dari {{ $stats['total_guests'] }} tamu" />
        </div>

        <div class="mt-4 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <x-ui.stat-card label="Ucapan" :value="$stats['total_messages']" icon="message" :hint="$stats['pending_messages'].' menunggu moderasi'" />
            <x-ui.stat-card label="Foto" :value="$stats['total_photos']" icon="image" :hint="$stats['approved_photos'].' disetujui'" />
            <x-ui.stat-card label="Request lagu" :value="$stats['song_requests']" icon="music" />
            <x-ui.stat-card label="Tempat duduk" :value="$stats['seated_guests'].'/'.$stats['seat_capacity']" icon="grid" :hint="$stats['tables'].' meja tersedia'" />
        </div>

        <div class="mt-6 grid gap-6 xl:grid-cols-2">
            <div class="card p-6">
                <h2 class="section-title">Timeline check-in</h2>
                <p class="mt-0.5 text-xs text-faint">Jumlah check-in per jam</p>
                @if (count($timeline))
                    <div class="mt-4 h-64"><canvas id="timelineChart"></canvas></div>
                @else
                    <div class="mt-4">
                        <x-ui.empty-state icon="chart" title="Belum ada check-in" description="Grafik muncul setelah tamu mulai check-in." />
                    </div>
                @endif
            </div>

            <div class="card p-6">
                <h2 class="section-title">Distribusi RSVP</h2>
                <p class="mt-0.5 text-xs text-faint">Status respons seluruh tamu</p>
                <div class="mt-4 h-64"><canvas id="rsvpChart"></canvas></div>
            </div>
        </div>

        <div class="mt-6 grid gap-6 xl:grid-cols-2">
            <div class="card p-6">
                <h2 class="section-title">Hasil polling</h2>
                @if ($pollResults->isEmpty())
                    <div class="mt-4">
                        <x-ui.empty-state icon="chart" title="Belum ada polling" description="Buat polling untuk melihat hasilnya di sini." />
                    </div>
                @else
                    <div class="mt-4 space-y-6">
                        @foreach ($pollResults as $result)
                            <div>
                                <div class="flex items-start justify-between gap-3">
                                    <p class="text-sm font-medium text-ink">{{ $result['poll']->question }}</p>
                                    <span class="badge badge-neutral text-[10px]">{{ $result['total'] }} respons</span>
                                </div>
                                <ul class="mt-3 space-y-2">
                                    @foreach ($result['options'] as $option)
                                        <li>
                                            <div class="flex items-center justify-between gap-3 text-xs">
                                                <span class="text-muted">{{ $option['label'] }}</span>
                                                <span class="text-ink">{{ $option['count'] }} · {{ $option['percent'] }}%</span>
                                            </div>
                                            <div class="mt-1 h-2 overflow-hidden rounded-full bg-surface-2">
                                                <div class="h-full rounded-full bg-accent" style="width: {{ $option['percent'] }}%"></div>
                                            </div>
                                        </li>
                                    @endforeach
                                </ul>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>

            <div class="card p-6">
                <h2 class="section-title">Hasil mini games</h2>
                @if ($gameResults->isEmpty())
                    <div class="mt-4">
                        <x-ui.empty-state icon="sparkles" title="Belum ada game" description="Buat game untuk melihat statistik percobaan tamu." />
                    </div>
                @else
                    <div class="table-wrap mt-4">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th scope="col" class="text-left">Game</th>
                                    <th scope="col" class="text-right">Percobaan</th>
                                    <th scope="col" class="text-right">Rata-rata</th>
                                    <th scope="col" class="text-right">Terbaik</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($gameResults as $result)
                                    <tr>
                                        <td class="text-ink">{{ $result['game']->title }}</td>
                                        <td class="text-right text-ink">{{ $result['attempts'] }}</td>
                                        <td class="text-right text-ink">{{ $result['average_score'] }}</td>
                                        <td class="text-right text-ink">{{ $result['best_score'] }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        </div>

        <div class="card mt-6 p-6">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h2 class="section-title">Ekspor data</h2>
                    <p class="mt-0.5 text-xs text-faint">Unduh data event ini dalam format CSV atau PDF.</p>
                </div>
                <a href="{{ route('admin.reports.pdf', ['event_id' => $event->id]) }}" class="btn btn-primary btn-sm">
                    <x-ui.icon name="file-text" class="h-3.5 w-3.5" /> PDF ringkasan
                </a>
            </div>
            <div class="mt-4 flex flex-wrap gap-2">
                @foreach (['summary' => 'Ringkasan', 'guests' => 'Tamu', 'rsvps' => 'RSVP', 'attendance' => 'Kehadiran', 'messages' => 'Ucapan', 'media' => 'Media', 'interactions' => 'Interaksi'] as $type => $label)
                    <a href="{{ route('admin.reports.export', ['event_id' => $event->id, 'type' => $type]) }}" class="btn btn-ghost btn-sm">
                        <x-ui.icon name="download" class="h-3.5 w-3.5" /> {{ $label }} (CSV)
                    </a>
                @endforeach
            </div>
        </div>

        <div class="mt-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <x-ui.stat-card label="Event total" :value="$system['total_events']" icon="calendar" />
            <x-ui.stat-card label="Event aktif" :value="$system['active_events']" icon="play" tone="success" />
            <x-ui.stat-card label="Tamu sistem" :value="$system['total_guests']" icon="users" />
            <x-ui.stat-card label="Check-in sistem" :value="$system['total_checked_in']" icon="calendar-check" tone="teal" />
        </div>
    @endif
@endsection

@push('scripts')
    @if ($event)
        <script nonce="{{ request()->attributes->get('csp_nonce') }}">
            document.addEventListener('DOMContentLoaded', async () => {
                const Chart = await window.loadChart();
                const css = (name) => getComputedStyle(document.documentElement).getPropertyValue(name).trim();
                @if (count($timeline))
                    new Chart(document.getElementById('timelineChart'), {
                        type: 'bar',
                        data: {
                            labels: @json(array_column($timeline, 'label')),
                            datasets: [{
                                label: 'Check-in',
                                data: @json(array_column($timeline, 'count')),
                                backgroundColor: css('--color-accent'),
                                borderRadius: 8,
                                maxBarThickness: 42,
                            }],
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: { legend: { display: false } },
                            scales: {
                                x: { grid: { display: false }, ticks: { color: css('--color-muted'), font: { size: 10 } } },
                                y: { beginAtZero: true, ticks: { color: css('--color-muted'), precision: 0 }, grid: { color: css('--color-line') } },
                            },
                        },
                    });
                @endif

                new Chart(document.getElementById('rsvpChart'), {
                    type: 'doughnut',
                    data: {
                        labels: @json(array_column($rsvpBreakdown, 'label')),
                        datasets: [{
                            data: @json(array_column($rsvpBreakdown, 'count')),
                            backgroundColor: [css('--color-muted'), css('--color-success'), css('--color-danger'), css('--color-warning')],
                            borderWidth: 0,
                        }],
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        cutout: '62%',
                        plugins: { legend: { position: 'bottom', labels: { color: css('--color-muted'), boxWidth: 10, font: { size: 11 } } } },
                    },
                });
            });
        </script>
    @endif
@endpush
