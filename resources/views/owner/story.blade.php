@extends('layouts.owner')

@section('title', 'Cerita Kami')

@section('content')
    {{-- Cover --}}
    <div class="relative rounded-3xl border border-line/80 bg-surface shadow-raised overflow-hidden mb-5 animate-float-in" style="animation-delay: 0.05s">
        @if ($event->coverUrl())
            <img src="{{ $event->coverUrl() }}" alt="Cover" class="w-full h-48 object-cover" loading="lazy" />
            <div class="absolute inset-0 bg-gradient-to-t from-ink/70 via-ink/20 to-transparent"></div>
        @else
            <div class="w-full h-48 bg-gradient-to-br from-blue-soft to-teal-soft flex items-center justify-center">
                <span class="text-6xl font-serif text-ink">
                    @php
                        $g = mb_substr($event->groom_name ?? '', 0, 1);
                        $b = mb_substr($event->bride_name ?? '', 0, 1);
                    @endphp
                    {{ $g ?: 'A' }} & {{ $b ?: 'B' }}
                </span>
            </div>
        @endif>
        <div class="absolute inset-0 flex flex-col items-center justify-center p-6 text-center text-[var(--color-accent-text)]">
            <p class="text-[11px] font-semibold uppercase tracking-[0.2em] text-blue-soft/90">Cerita kami</p>
            <h1 class="mt-1 font-serif text-3xl font-bold text-[var(--color-accent-text)] leading-tight">{{ $event->coupleNames() }}</h1>
            <p class="mt-1 text-sm text-blue-soft/90">{{ $event->event_date?->translatedFormat('d M Y') }} · {{ $event->venue_name ?? '-' }}</p>
        </div>
    </div>

    {{-- Couple Intro --}}
    <div class="card p-5 mb-5 animate-float-in" style="animation-delay: 0.1s">
        <p class="section-kicker">Tentang kami</p>
        <div class="mt-3 space-y-3">
            @if ($event->groom_profile)
                <div class="flex items-start gap-3">
                    <span class="shrink-0 text-2xl">🤵</span>
                    <p class="prose-wedding">{{ $event->groom_profile }}</p>
                </div>
            @endif
            @if ($event->bride_profile)
                <div class="flex items-start gap-3">
                    <span class="shrink-0 text-2xl">👰</span>
                    <p class="prose-wedding">{{ $event->bride_profile }}</p>
                </div>
            @endif
            @if ($event->couple_description)
                <div class="mt-2 border-t border-line/60 pt-3">
                    <p class="prose-wedding">{{ $event->couple_description }}</p>
                </div>
            @endif
        </div>
    </div>

    {{-- Key Numbers --}}
    <div class="grid grid-cols-2 gap-3 mb-5 animate-float-in" style="animation-delay: 0.15s">
        <div class="card p-4 text-center">
            <p class="text-2xl font-serif font-bold text-ink">{{ $stats['total_guests'] ?? 0 }}</p>
            <p class="text-[10px] font-semibold uppercase tracking-[0.12em] text-muted">Tamu undangan</p>
        </div>
        <div class="card p-4 text-center">
            <p class="text-2xl font-serif font-bold text-ink">{{ $stats['total_messages'] ?? 0 }}</p>
            <p class="text-[10px] font-semibold uppercase tracking-[0.12em] text-muted">Ucapan tamu</p>
        </div>
        <div class="card p-4 text-center">
            <p class="text-2xl font-serif font-bold text-ink">{{ $stats['total_photos'] ?? 0 }}</p>
            <p class="text-[10px] font-semibold uppercase tracking-[0.12em] text-muted">Foto kenangan</p>
        </div>
        <div class="card p-4 text-center">
            <p class="text-2xl font-serif font-bold text-ink">{{ $stats['song_requests'] ?? 0 }}</p>
            <p class="text-[10px] font-semibold uppercase tracking-[0.12em] text-muted">Permintaan lagu</p>
        </div>
    </div>

    {{-- Best Wishes --}}
    <div class="mb-5 animate-float-in" style="animation-delay: 0.2s">
        <div class="flex items-center justify-between gap-3 mb-3">
            <p class="section-kicker">Ucapan terbaik</p>
            <a href="{{ route('owner.wishes.index') }}" class="link text-sm">Lihat semua ucapan</a>
        </div>
        @if ($wishes->isNotEmpty())
            <div class="grid grid-cols-2 gap-3">
                @foreach ($wishes->take(4) as $wish)
                    <a href="{{ route('owner.wishes.show', $wish) }}" class="card p-4 block hover:bg-surface-2/40 transition">
                        <span class="text-xl block mb-2">{{ $wish->emoji ?? '💌' }}</span>
                        <p class="text-sm text-muted line-clamp-3">{{ $wish->message }}</p>
                        <p class="mt-2 text-[10px] font-semibold text-ink">{{ $wish->sender_name }}</p>
                    </a>
                @endforeach
            </div>
        @else
            <x-ui.empty-state icon="message" title="Belum ada ucapan" description="Ucapan tamu akan muncul di sini." />
        @endif
    </div>

    {{-- Photo Mosaic --}}
    <div class="mb-5 animate-float-in" style="animation-delay: 0.25s">
        <div class="flex items-center justify-between gap-3 mb-3">
            <p class="section-kicker">Galeri kenangan</p>
            <a href="{{ route('owner.memories.index') }}" class="link text-sm">Lihat semua foto</a>
        </div>
        @if ($photos->isNotEmpty())
            <div class="grid grid-cols-3 gap-2">
                @foreach ($photos->take(9) as $photo)
                    <a href="{{ route('owner.memories.show', $photo) }}" class="block h-24 rounded-2xl overflow-hidden border border-line bg-surface shadow-raised">
                        <img src="{{ $photo->thumbnailUrl() }}" alt="" class="w-full h-full object-cover" loading="lazy" />
                    </a>
                @endforeach
            </div>
        @else
            <x-ui.empty-state icon="image" title="Belum ada foto" description="Foto kenangan akan muncul di sini." />
        @endif
    </div>

    {{-- Timeline Highlights --}}
    <div class="mb-5 animate-float-in" style="animation-delay: 0.3s">
        <div class="flex items-center justify-between gap-3 mb-3">
            <p class="section-kicker">Rundown hari H</p>
            <a href="{{ route('owner.timeline') }}" class="link text-sm">Lihat seluruh rundown</a>
        </div>
        @if ($timeline->isNotEmpty())
            <div class="space-y-3">
                @foreach ($timeline->take(5) as $schedule)
                    <div class="flex items-center gap-3 p-3 rounded-2xl bg-surface-2/60">
                        <span class="shrink-0 w-10 text-center">
                            <p class="text-[10px] font-semibold text-faint">{{ $schedule->start_time?->translatedFormat('H:i') }}</p>
                        </span>
                        <div class="flex-1 min-w-0">
                            <p class="font-semibold text-sm text-ink truncate">{{ $schedule->title }}</p>
                            <p class="text-[10px] text-faint">{{ $schedule->description ?? '-' }}</p>
                        </div>
                        <x-ui.badge :variant="$schedule->status->badge()" class="text-[10px] shrink-0">{{ $schedule->status->label() }}</x-ui.badge>
                    </div>
                @endforeach
            </div>
        @else
            <x-ui.empty-state icon="calendar" title="Belum ada rundown" description="Jadwal akan muncul di sini." />
        @endif
    </div>

    {{-- Soundtrack --}}
    <div class="card p-5 animate-float-in" style="animation-delay: 0.35s">
        <div class="flex items-center justify-between gap-3 mb-3">
            <p class="section-kicker">Soundtrack pernikahan</p>
        </div>
        @if ($songs->isNotEmpty())
            <div class="space-y-3">
                @foreach ($songs as $song)
                    <div class="flex items-center gap-3 p-3 rounded-2xl bg-surface-2/60">
                        <span class="shrink-0 text-xl">🎵</span>
                        <div class="flex-1 min-w-0">
                            <p class="font-semibold text-sm text-ink truncate">{{ $song->song_title }}</p>
                            <p class="text-[10px] text-muted">{{ $song->artist }}</p>
                        </div>
                        <x-ui.badge :variant="$song->status->badge()" class="text-[10px] shrink-0">{{ $song->status->label() }}</x-ui.badge>
                    </div>
                @endforeach
            </div>
        @else
            <x-ui.empty-state icon="music" title="Belum ada permintaan lagu" description="Lagu favorit tamu akan muncul di sini." />
        @endif
    </div>

    <div class="text-center py-8">
        <p class="prose-wedding italic">Terima kasih telah menjadi bagian dari cerita kami.</p>
    </div>
@endsection