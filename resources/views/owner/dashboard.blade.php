@extends('layouts.owner')

@section('title', 'Beranda')

@section('content')
    @php
        $target = $event->countdownTarget();
        $countdown = '-';
        if ($target) {
            if ($target->isPast()) {
                $countdown = 'Sudah berlangsung';
            } elseif ($target->isToday()) {
                $countdown = 'Hari ini';
            } else {
                $days = (int) now()->startOfDay()->diffInDays($target->startOfDay());
                $countdown = "H-{$days} hari lagi";
            }
        }
    @endphp

    {{-- Hero Card --}}
    <div class="card p-5 mb-5 animate-float-in" style="animation-delay: 0.05s">
        <div class="flex items-start justify-between gap-3">
            <div class="min-w-0">
                <p class="section-kicker">Ruang kecil kita</p>
                <h1 class="mt-1 font-serif text-2xl font-semibold tracking-tight text-ink truncate">
                    {{ $event->coupleNames() }}
                </h1>
                <p class="mt-1 text-sm text-muted truncate">
                    {{ $event->event_date?->translatedFormat('l, d M Y') }}
                    @if ($event->venue_name)
                        · {{ $event->venue_name }}
                    @endif
                </p>
            </div>
            <div class="shrink-0">
                <span class="inline-flex items-center gap-1.5 rounded-full border px-2.5 py-1 text-[10px] font-semibold {{ $event->status->badge() }}">
                    {{ $event->statusLabel() }}
                </span>
            </div>
        </div>

        <div class="mt-4 flex flex-wrap items-end gap-4">
            <div class="flex-1 min-w-[120px]">
                <p class="text-[10px] font-semibold uppercase tracking-[0.14em] text-faint">Hari-hari menuju</p>
                <p class="mt-1 font-serif text-3xl font-bold text-ink">{{ $countdown }}</p>
            </div>
            @if ($event->start_time)
                <div class="flex-1 min-w-[120px] text-right sm:text-left">
                    <p class="text-[10px] font-semibold uppercase tracking-[0.14em] text-faint">Pukul</p>
                    <p class="mt-1 font-serif text-2xl font-semibold text-ink">
                        {{ \Carbon\Carbon::parse($event->start_time)->format('H:i') }}
                    </p>
                </div>
            @endif
        </div>
    </div>

    {{-- Quick Stat Strip --}}
    <div class="grid grid-cols-4 gap-3 mb-5 animate-float-in" style="animation-delay: 0.1s">
        <div class="card p-4 text-center">
            <p class="text-2xl font-semibold text-ink">{{ $stats['total_guests'] ?? 0 }}</p>
            <p class="text-[10px] font-semibold uppercase tracking-[0.12em] text-muted">Tamu undangan</p>
        </div>
        <div class="card p-4 text-center">
            <p class="text-2xl font-semibold text-[#3f8069]">{{ $stats['confirmed'] ?? 0 }}</p>
            <p class="text-[10px] font-semibold uppercase tracking-[0.12em] text-muted">Konfirmasi</p>
        </div>
        <div class="card p-4 text-center">
            <p class="text-2xl font-semibold text-[#4f7592]">{{ $stats['checked_in'] ?? 0 }}</p>
            <p class="text-[10px] font-semibold uppercase tracking-[0.12em] text-muted">Check-in</p>
        </div>
        <div class="card p-4 text-center">
            <p class="text-2xl font-semibold text-[#8f6b2f]">{{ $stats['total_messages'] ?? 0 }}</p>
            <p class="text-[10px] font-semibold uppercase tracking-[0.12em] text-muted">Ucapan tamu</p>
        </div>
    </div>

    {{-- Next Schedule --}}
    <div class="card p-5 mb-5 animate-float-in" style="animation-delay: 0.15s">
        <div class="flex items-center justify-between gap-3 mb-3">
            <p class="section-kicker">Rundown berikutnya</p>
            <a href="{{ route('owner.timeline') }}" class="link text-sm">Lihat seluruh rundown</a>
        </div>

        @if ($nextSchedule)
            <div class="flex items-start gap-4">
                <div class="shrink-0 w-20 text-center">
                    <p class="text-[10px] font-semibold uppercase tracking-[0.12em] text-faint">
                        {{ $nextSchedule->start_time?->translatedFormat('H:i') }}
                    </p>
                    <p class="text-[10px] text-muted">
                        {{ $nextSchedule->end_time?->translatedFormat('H:i') }}
                    </p>
                </div>
                <div class="flex-1 min-w-0">
                    <h3 class="font-serif text-lg font-semibold text-ink truncate">{{ $nextSchedule->title }}</h3>
                    @if ($nextSchedule->description)
                        <p class="mt-1 text-sm text-muted line-clamp-2">{{ $nextSchedule->description }}</p>
                    @endif
                    <span class="inline-block mt-2 text-[10px] font-semibold {{ $nextSchedule->status->badge() }}">
                        {{ $nextSchedule->status->label() }}
                    </span>
                </div>
            </div>
        @else
            <div class="text-center py-4">
                <x-ui.icon name="calendar" class="mx-auto h-8 w-8 text-faint" />
                <p class="mt-2 text-sm text-muted">Belum ada jadwal yang dipandu</p>
            </div>
        @endif
    </div>

    {{-- Latest Wishes --}}
    <div class="mb-5 animate-float-in" style="animation-delay: 0.2s">
        <div class="flex items-center justify-between gap-3 mb-3">
            <p class="section-kicker">Ucapan terbaru</p>
            <a href="{{ route('owner.wishes.index') }}" class="link text-sm">Lihat semua ucapan</a>
        </div>

        @if ($latestWishes->isNotEmpty())
            <div class="space-y-3">
                @foreach ($latestWishes->take(3) as $wish)
                    <a href="{{ route('owner.wishes.show', $wish) }}" class="card p-4 block hover:bg-surface-2/40 transition">
                        <div class="flex items-start gap-3">
                            <span class="shrink-0 text-2xl">{{ $wish->emoji ?? '💌' }}</span>
                            <div class="flex-1 min-w-0">
                                <p class="font-semibold text-sm text-ink truncate">{{ $wish->sender_name }}</p>
                                <p class="mt-0.5 text-sm text-muted line-clamp-2">{{ \Illuminate\Support\Str::limit($wish->message, 100) }}</p>
                                <p class="mt-1 text-[10px] text-faint">{{ $wish->created_at?->translatedFormat('d M Y H:i') }}</p>
                            </div>
                            <x-ui.icon name="chevron-right" class="shrink-0 h-5 w-5 text-faint" />
                        </div>
                    </a>
                @endforeach
            </div>
        @else
            <x-ui.empty-state icon="message" title="Belum ada ucapan" description="Tamu belum meninggalkan ucapan di ruang ini." />
        @endif
    </div>

    {{-- Photo Strip --}}
    <div class="mb-5 animate-float-in" style="animation-delay: 0.25s">
        <div class="flex items-center justify-between gap-3 mb-3">
            <p class="section-kicker">Kenangan terbaru</p>
            <a href="{{ route('owner.memories.index') }}" class="link text-sm">Lihat semua foto</a>
        </div>

        @if ($latestPhotos->isNotEmpty())
            <div class="flex gap-3 scrollbar-none overflow-x-auto pb-2">
                @foreach ($latestPhotos->take(6) as $photo)
                    <a href="{{ route('owner.memories.index') }}" class="shrink-0 w-24 h-24 rounded-2xl overflow-hidden border border-line bg-surface shadow-raised">
                        <img src="{{ $photo->thumbnailUrl() }}" alt="" class="w-full h-full object-cover" loading="lazy" />
                    </a>
                @endforeach
            </div>
        @else
            <x-ui.empty-state icon="image" title="Belum ada foto" description="Foto tamu akan muncul di sini setelah disetujui." />
        @endif
    </div>

    {{-- Recent Activity --}}
    <div class="mb-5 animate-float-in" style="animation-delay: 0.3s">
        <div class="flex items-center justify-between gap-3 mb-3">
            <p class="section-kicker">Aktivitas terbaru</p>
        </div>

        @if ($activities->isNotEmpty())
            <div class="space-y-3">
                @foreach ($activities as $activity)
                    <div class="card p-4 flex items-start gap-3">
                        <span class="shrink-0 flex h-8 w-8 items-center justify-center rounded-xl bg-blue-soft text-[#4f7592]">
                            <x-ui.icon name="sparkles" class="h-4 w-4" />
                        </span>
                        <div class="flex-1 min-w-0">
                            <p class="text-sm text-ink">{{ $activity->description }}</p>
                            <p class="mt-0.5 text-[10px] text-faint">
                                {{ $activity->guest?->name ?? 'Sistem' }} · {{ $activity->created_at?->translatedFormat('d M Y H:i') }}
                            </p>
                        </div>
                    </div>
                @endforeach
            </div>
        @else
            <x-ui.empty-state icon="sparkles" title="Belum ada aktivitas" description="Aktivitas terbaru akan muncul di sini." />
        @endif
    </div>

    {{-- Quick Links --}}
    <div class="grid grid-cols-2 gap-3 animate-float-in" style="animation-delay: 0.35s">
        <a href="{{ route('owner.story') }}" class="card p-5 text-center hover:bg-surface-2/40 transition">
            <x-ui.icon name="archive" class="mx-auto h-7 w-7 text-[#4f7592]" />
            <p class="mt-2 font-semibold text-ink">Cerita kita</p>
            <p class="mt-1 text-[11px] text-muted">Arsip pernikahan lengkap</p>
        </a>
        <a href="{{ route('owner.attendance') }}" class="card p-5 text-center hover:bg-surface-2/40 transition">
            <x-ui.icon name="users" class="mx-auto h-7 w-7 text-[#3f8069]" />
            <p class="mt-2 font-semibold text-ink">Kehadiran</p>
            <p class="mt-1 text-[11px] text-muted">RSVP & check-in tamu</p>
        </a>
    </div>
@endsection