@extends('layouts.admin')

@section('title', 'Event Detail')
@section('subtitle', 'Event Details')

@section('content')
    <x-ui.page-header title="{{ $event->name }}" eyebrow="Event Details">
        <x-ui.button variant="secondary" href="{{ route('admin.events.index') }}" icon="arrow-left">
            Kembali
        </x-ui.button>
        <x-ui.button variant="secondary" href="{{ route('admin.events.edit', $event) }}" icon="edit">
            Edit
        </x-ui.button>
        <x-ui.button variant="secondary" href="{{ route('admin.events.preview', $event) }}" icon="external-link">
            Preview
        </x-ui.button>
        <x-ui.button variant="secondary" href="{{ route('admin.events.gifts.index', $event) }}" icon="gift">
            Gifts
        </x-ui.button>
        <form method="POST" action="{{ route('admin.events.destroy', $event) }}" class="inline" data-confirm="Yakin ingin menghapus event ini?" data-confirm-title="Hapus Event">
            <button type="submit" class="btn btn-danger" icon="trash">
                Hapus
            </button>
        </form>
    </x-ui.page-header>

    <div class="mt-6 grid grid-cols-1 gap-5 md:grid-cols-2 lg:grid-cols-4">
        <x-ui.stat-card label="Total Guests" :value="$stats['total_guests']" icon="users" tone="teal" />
        <x-ui.stat-card label="Confirmed RSVP" :value="$stats['confirmed']" icon="clipboard" tone="warning" />
        <x-ui.stat-card label="Checked In" :value="$stats['checked_in']" icon="qr" tone="danger" />
        <x-ui.stat-card label="Messages" :value="$stats['total_messages']" icon="message" tone="info" />
        <x-ui.stat-card label="Photos" :value="$stats['total_photos']" icon="image" tone="success" />
        <x-ui.stat-card label="Invitations" :value="$stats['total_invitations']" icon="mail" tone="accent" />
        <x-ui.stat-card label="Tables" :value="$stats['tables']" icon="grid" tone="accent" />
    </div>

    <div class="mt-8 grid grid-cols-1 gap-6 lg:grid-cols-3">
        <div class="card lg:col-span-2">
            <div class="flex items-center justify-between border-b border-line/70 px-6 py-4">
                <h2 class="section-title">Details</h2>
            </div>
            <div class="p-6 space-y-4">
                <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                    <div>
                        <p class="text-[11px] font-semibold uppercase tracking-[0.12em] text-muted">Couple</p>
                        <p class="text-sm font-medium text-ink">{{ $event->groom_name }} &amp; {{ $event->bride_name }}</p>
                    </div>
                    <div>
                        <p class="text-[11px] font-semibold uppercase tracking-[0.12em] text-muted">Date / Time</p>
                        <p class="text-sm font-medium text-ink">{{ $event->event_date->format('d M Y') }} {{ $event->start_time ? $event->start_time : '' }} - {{ $event->end_time ? $event->end_time : '' }}</p>
                    </div>
                    <div>
                        <p class="text-[11px] font-semibold uppercase tracking-[0.12em] text-muted">Venue</p>
                        <p class="text-sm font-medium text-ink">{{ $event->primaryVenue->name ?? '-' }}</p>
                        <p class="text-xs text-muted">{{ $event->primaryVenue->address ?? '-' }}</p>
                    </div>
                    <div>
                        <p class="text-[11px] font-semibold uppercase tracking-[0.12em] text-muted">Dress Code</p>
                        <p class="text-sm font-medium text-ink">{{ $event->dress_code ?? '-' }}</p>
                    </div>
                    <div>
                        <p class="text-[11px] font-semibold uppercase tracking-[0.12em] text-muted">Description</p>
                        <p class="text-sm text-muted">{{ $event->description ?? '-' }}</p>
                    </div>
                    <div>
                        <p class="text-[11px] font-semibold uppercase tracking-[0.12em] text-muted">Features</p>
                        <div class="flex flex-wrap gap-2">
                            <span class="badge badge-{{ $event->rsvp_enabled ? 'success' : 'muted' }} text-[10px]">{{ $event->rsvp_enabled ? 'RSVP On' : 'RSVP Off' }}</span>
                            <span class="badge badge-{{ $event->guestbook_enabled ? 'success' : 'muted' }} text-[10px]">{{ $event->guestbook_enabled ? 'Guestbook On' : 'Guestbook Off' }}</span>
                            <span class="badge badge-{{ $event->gallery_enabled ? 'success' : 'muted' }} text-[10px]">{{ $event->gallery_enabled ? 'Gallery On' : 'Gallery Off' }}</span>
                            <span class="badge badge-{{ $event->gift_enabled ? 'success' : 'muted' }} text-[10px]">{{ $event->gift_enabled ? 'Gift On' : 'Gift Off' }}</span>
                            <span class="badge badge-{{ $event->live_wall_enabled ? 'success' : 'muted' }} text-[10px]">{{ $event->live_wall_enabled ? 'Live Wall On' : 'Live Wall Off' }}</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="flex items-center justify-between border-b border-line/70 px-6 py-4">
                <h2 class="section-title">Owners</h2>
            </div>
            <div class="p-6">
                @forelse ($event->owners as $owner)
                    <div class="flex items-center justify-between p-3 rounded-xl bg-surface-2/60">
                        <div class="flex items-center gap-3">
                            <x-ui.avatar :name="$owner->name" size="sm" />
                            <div>
                                <p class="text-sm font-medium text-ink">{{ $owner->name }}</p>
                                <p class="text-xs text-muted">{{ $owner->email }}</p>
                            </div>
                        </div>
                        <span class="text-xs text-muted">{{ $owner->pivot->role_label ?? 'partner' }}</span>
                    </div>
                @empty
                    <div class="text-center text-muted text-sm">No owners assigned</div>
                @endforelse
            </div>
        </div>

        <div class="card">
            <div class="flex items-center justify-between border-b border-line/70 px-6 py-4">
                <h2 class="section-title">Schedule</h2>
            </div>
            <div class="p-6">
                @forelse ($event->schedules as $schedule)
                    <div class="flex items-center justify-between p-3 rounded-xl bg-surface-2/60">
                        <div class="flex items-center gap-3">
                            <div class="flex flex-col">
                                <p class="text-sm font-medium text-ink">{{ $schedule->title }}</p>
                                <p class="text-xs text-muted">{{ $schedule->description ?? '-' }}</p>
                            </div>
                            <div class="text-xs text-muted">{{ $schedule->start_time->format('d M Y H:i') }} - {{ $schedule->end_time->format('d M Y H:i') }}</div>
                        </div>
                        <x-ui.badge :variant="$schedule->status->badge()" class="text-[10px]">{{ $schedule->status->label() }}</x-ui.badge>
                    </div>
                @empty
                    <div class="text-center text-muted text-sm">No schedules</div>
                @endforelse
            </div>
        </div>

        <div class="card">
            <div class="flex items-center justify-between border-b border-line/70 px-6 py-4">
                <h2 class="section-title">Gift Accounts</h2>
            </div>
            <div class="p-6">
                @forelse ($event->giftAccounts as $gift)
                    <div class="flex items-center justify-between p-3 rounded-xl bg-surface-2/60">
                        <div class="flex items-center gap-3">
                            <div class="flex flex-col">
                                <p class="text-sm font-medium text-ink">{{ $gift->bank_name }}</p>
                                <p class="text-xs text-muted">{{ $gift->account_number }} - {{ $gift->account_owner }}</p>
                            </div>
                            <div class="text-xs text-muted">{{ $gift->is_active ? 'Active' : 'Inactive' }}</div>
                        </div>
                        <x-ui.badge variant="{{ $gift->is_active ? 'success' : 'muted' }}" class="text-[10px]">{{ $gift->is_active ? 'Active' : 'Inactive' }}</x-ui.badge>
                    </div>
                @empty
                    <div class="text-center text-muted text-sm">No gift accounts</div>
                @endforelse
            </div>
        </div>
    </div>

    <div class="mt-8 card">
        <div class="flex items-center justify-between border-b border-line/70 px-6 py-4">
            <h2 class="section-title">Quick Links</h2>
        </div>
        <div class="p-6 grid grid-cols-1 gap-3 md:grid-cols-2 lg:grid-cols-4">
            <a href="{{ route('admin.guests.index', ['event_id' => $event->id]) }}" class="flex items-center gap-3 p-3 rounded-xl border border-line bg-surface hover:bg-surface-2/40 transition">
                <x-ui.icon name="users" class="h-4 w-4" />
                <span>Guests</span>
            </a>
            <a href="{{ route('admin.invitations.index', ['event_id' => $event->id]) }}" class="flex items-center gap-3 p-3 rounded-xl border border-line bg-surface hover:bg-surface-2/40 transition">
                <x-ui.icon name="mail" class="h-4 w-4" />
                <span>Invitations</span>
            </a>
            <a href="{{ route('admin.rsvps.index', ['event_id' => $event->id]) }}" class="flex items-center gap-3 p-3 rounded-xl border border-line bg-surface hover:bg-surface-2/40 transition">
                <x-ui.icon name="clipboard" class="h-4 w-4" />
                <span>RSVPs</span>
            </a>
            <a href="{{ route('admin.checkins.index', ['event_id' => $event->id]) }}" class="flex items-center gap-3 p-3 rounded-xl border border-line bg-surface hover:bg-surface-2/40 transition">
                <x-ui.icon name="qr" class="h-4 w-4" />
                <span>Check-ins</span>
            </a>
            <a href="{{ route('admin.guestbook.index', ['event_id' => $event->id]) }}" class="flex items-center gap-3 p-3 rounded-xl border border-line bg-surface hover:bg-surface-2/40 transition">
                <x-ui.icon name="message" class="h-4 w-4" />
                <span>Guestbook</span>
            </a>
            <a href="{{ route('admin.media.index', ['event_id' => $event->id]) }}" class="flex items-center gap-3 p-3 rounded-xl border border-line bg-surface hover:bg-surface-2/40 transition">
                <x-ui.icon name="image" class="h-4 w-4" />
                <span>Media</span>
            </a>
            <a href="{{ route('admin.seating.index', ['event_id' => $event->id]) }}" class="flex items-center gap-3 p-3 rounded-xl border border-line bg-surface hover:bg-surface-2/40 transition">
                <x-ui.icon name="grid" class="h-4 w-4" />
                <span>Seating</span>
            </a>
        </div>
    </div>

    <div class="mt-8 card">
        <div class="flex items-center justify-between border-b border-line/70 px-6 py-4">
            <h2 class="section-title">Public URL</h2>
        </div>
        <div class="p-6 flex items-center gap-3">
            <input type="text" value="{{ $event->publicUrl() }}" class="input flex-1" readonly>
            <button type="button" class="icon-btn" data-copy="{{ $event->publicUrl() }}" title="Salin">
                <x-ui.icon name="copy" class="h-4 w-4" />
            </button>
        </div>
    </div>
@endsection