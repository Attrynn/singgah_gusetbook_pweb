@extends('layouts.admin')

@section('title', 'Dashboard')
@section('subtitle', 'VOWSPACE administrative workspace')

@section('content')
    <x-ui.page-header title="Dashboard" eyebrow="Overview">
        <x-ui.button variant="secondary" href="{{ route('admin.events.create') }}" icon="plus">
            Buat event
        </x-ui.button>
    </x-ui.page-header>

    <div class="grid grid-cols-1 gap-5 md:grid-cols-2 lg:grid-cols-4">
        <x-ui.stat-card label="Total Events" :value="$stats['total_events']" icon="calendar" tone="accent" />
        <x-ui.stat-card label="Active Events" :value="$stats['active_events']" icon="chart" tone="success" />
        <x-ui.stat-card label="Total Guests" :value="$stats['total_guests']" icon="users" tone="teal" />
        <x-ui.stat-card label="Confirmed RSVP" :value="$stats['rsvp_confirmed']" icon="clipboard" tone="warning" />
        <x-ui.stat-card label="Checked In" :value="$stats['total_checked_in']" icon="qr" tone="danger" />
        <x-ui.stat-card label="Messages" :value="$stats['total_messages']" icon="message" tone="info" />
        <x-ui.stat-card label="Photos" :value="$stats['total_photos']" icon="image" tone="success" />
        <x-ui.stat-card label="Invitations" :value="$stats['total_invitations']" icon="mail" tone="accent" />
    </div>

    <div class="mt-8 grid grid-cols-1 gap-6 lg:grid-cols-3">
        <div class="card lg:col-span-2">
            <div class="flex items-center justify-between border-b border-line/70 px-6 py-4">
                <h2 class="section-title">RSVP Overview</h2>
                <div class="text-xs text-muted">6 events</div>
            </div>
            <div class="p-6">
                <canvas id="rsvpChart" height="260"></canvas>
            </div>
        </div>

        <div class="card">
            <div class="flex items-center justify-between border-b border-line/70 px-6 py-4">
                <h2 class="section-title">Event Status</h2>
                <div class="text-xs text-muted">All events</div>
            </div>
            <div class="p-6 space-y-4">
                @foreach ($statusOverview as $item)
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-2">
                            <span class="inline-block h-3 w-3 rounded-full" style="background-color: {{ $item['status']->color ?? '#8EB4D3' }}"></span>
                            <span class="text-sm text-ink">{{ $item['status']->label() }}</span>
                        </div>
                        <span class="text-sm font-semibold text-ink">{{ $item['count'] }}</span>
                    </div>
                @endforeach
            </div>
        </div>
    </div>

    <div class="mt-8 grid grid-cols-1 gap-6 lg:grid-cols-3">
        <div class="card">
            <div class="flex items-center justify-between border-b border-line/70 px-6 py-4">
                <h2 class="section-title">Upcoming Events</h2>
                <a href="{{ route('admin.events.index') }}" class="link text-xs">View all</a>
            </div>
            <div class="divide-y divide-line/70">
                @forelse ($upcomingEvents as $event)
                    <a href="{{ route('admin.events.show', $event) }}" class="block p-4 transition hover:bg-surface-2/40">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="font-medium text-ink">{{ $event->name }}</p>
                                <p class="text-xs text-muted">{{ $event->event_date->format('M d, Y') }}</p>
                            </div>
                            <div class="flex items-center gap-2">
                                <x-ui.badge :variant="$event->status->badge()" class="text-[10px]">{{ $event->status->label() }}</x-ui.badge>
                                <span class="text-xs text-muted">{{ $event->guests_count }} guests</span>
                            </div>
                        </div>
                    </a>
                @empty
                    <div class="p-8">
                        <x-ui.empty-state icon="calendar" title="No upcoming events" description="Create your first event to get started.">
                            <x-ui.button href="{{ route('admin.events.create') }}" size="sm" icon="plus">Buat event</x-ui.button>
                        </x-ui.empty-state>
                    </div>
                @endforelse
            </div>
        </div>

        <div class="card">
            <div class="flex items-center justify-between border-b border-line/70 px-6 py-4">
                <h2 class="section-title">Recent Guests</h2>
                <a href="{{ route('admin.guests.index') }}" class="link text-xs">View all</a>
            </div>
            <div class="divide-y divide-line/70">
                @forelse ($recentGuests as $guest)
                    <div class="p-4 flex items-center justify-between">
                        <div class="flex items-center gap-3">
                            <x-ui.avatar :name="$guest->name" size="sm" />
                            <div>
                                <p class="text-sm font-medium text-ink">{{ $guest->name }}</p>
                                <p class="text-xs text-muted">{{ $guest->event->name }}</p>
                            </div>
                        </div>
                        <div class="text-xs text-muted">{{ $guest->created_at->diffForHumans() }}</div>
                    </div>
                @empty
                    <div class="p-8">
                        <x-ui.empty-state icon="users" title="No recent guests" description="Add guests to your events.">
                            <x-ui.button href="{{ route('admin.guests.create') }}" size="sm" icon="plus">Tambah tamu</x-ui.button>
                        </x-ui.empty-state>
                    </div>
                @endforelse
            </div>
        </div>

        <div class="card">
            <div class="flex items-center justify-between border-b border-line/70 px-6 py-4">
                <h2 class="section-title">Latest Wishes</h2>
                <a href="{{ route('admin.guestbook.index') }}" class="link text-xs">View all</a>
            </div>
            <div class="divide-y divide-line/70">
                @forelse ($latestWishes as $wish)
                    <div class="p-4">
                        <div class="flex items-center justify-between">
                            <p class="text-sm text-ink">{{ \Illuminate\Support\Str::limit($wish->message, 60) }}</p>
                            <div class="flex items-center gap-2">
                                <x-ui.badge variant="info" class="text-[10px]">{{ $wish->event->name }}</x-ui.badge>
                                <span class="text-xs text-muted">{{ $wish->created_at->diffForHumans() }}</span>
                            </div>
                        </div>
                    </div>
                @empty
                    <div class="p-8">
                        <x-ui.empty-state icon="message" title="No wishes yet" description="Encourage guests to share their wishes.">
                            <x-ui.button href="{{ route('admin.guestbook.index') }}" size="sm" icon="message">Lihat guestbook</x-ui.button>
                        </x-ui.empty-state>
                    </div>
                @endforelse
            </div>
        </div>
    </div>

    <div class="mt-8 card">
        <div class="flex items-center justify-between border-b border-line/70 px-6 py-4">
            <h2 class="section-title">Recent Activity</h2>
            <div class="text-xs text-muted">Last 10 activities</div>
        </div>
        <div class="divide-y divide-line/70">
            @forelse ($recentActivities as $activity)
                <div class="p-4">
                    <div class="flex items-start gap-3">
                        <div class="mt-0.5">
                            <x-ui.icon name="{{ $activity->type === 'event.created' ? 'calendar' : ($activity->type === 'guest.added' ? 'users' : 'chart') }}" class="h-4 w-4 text-muted" />
                        </div>
                        <div class="flex-1">
                            <p class="text-sm text-ink">{{ $activity->description }}</p>
                            <p class="text-xs text-faint">{{ $activity->created_at->diffForHumans() }}</p>
                        </div>
                        <div class="text-xs text-muted">{{ $activity->event->name ?? '' }}</div>
                    </div>
                </div>
            @empty
                <div class="p-8">
                    <x-ui.empty-state icon="chart" title="No recent activity" description="Activities will appear here as you use the system.">
                    </x-ui.empty-state>
                </div>
            @endforelse
        </div>
    </div>

    @push('scripts')
        <script>
            const ctx = document.getElementById('rsvpChart').getContext('2d');
            new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: {{ json_encode($rsvpChart->pluck('name')) }},
                    datasets: [
                        {
                            label: 'Confirmed',
                            data: {{ json_encode($rsvpChart->pluck('confirmed')) }},
                            backgroundColor: '#5DAD91',
                        },
                        {
                            label: 'Declined',
                            data: {{ json_encode($rsvpChart->pluck('declined')) }},
                            backgroundColor: '#D88C96',
                        },
                        {
                            label: 'Pending',
                            data: {{ json_encode($rsvpChart->pluck('pending')) }},
                            backgroundColor: '#94A3B8',
                        },
                    ],
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        x: {
                            stacked: true,
                            grid: { display: false },
                        },
                        y: {
                            stacked: true,
                            beginAtZero: true,
                            grid: { color: 'rgba(220, 229, 239, 0.3)' },
                        },
                    },
                    plugins: {
                        legend: { position: 'top' },
                        title: { display: false },
                    },
                },
            });
        </script>
    @endpush
@endsection