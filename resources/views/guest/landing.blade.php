<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="description" content="Undangan pernikahan {{ $event->coupleNames() }}.">
    <title>{{ $event->coupleNames() }} · Undangan Pernikahan · VOWSPACE</title>
    <script nonce="{{ request()->attributes->get('csp_nonce') }}">
        (function() {
            const theme = localStorage.getItem('vowspace_theme') || 'system';
            const isDark = theme === 'dark' || (theme === 'system' && window.matchMedia('(prefers-color-scheme: dark)').matches);
            if (isDark) { document.documentElement.classList.add('dark'); }
        })();
    </script>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-dvh bg-canvas text-ink antialiased">
    @php
        $eventDateTime = $event->localStartsAt()?->translatedFormat('l, d F Y · H:i T')
            ?? $event->event_date?->translatedFormat('l, d F Y');
        $countdownTarget = $event->countdownTarget()?->getTimestampMs();
    @endphp

    <div class="mobile-shell">
        <header class="flex items-center justify-between px-4 pt-5 pb-3 sm:px-6">
            <div class="flex items-center gap-2">
                <span class="flex h-8 w-8 items-center justify-center rounded-xl bg-accent text-xs font-semibold text-[var(--color-accent-text)]">V</span>
                <span class="font-serif text-sm font-semibold tracking-tight text-ink">VOWSPACE</span>
            </div>
            <a href="{{ route('login') }}" class="text-[11px] font-semibold text-faint transition hover:text-ink">Masuk</a>
        </header>

        <main class="px-4 pb-16 sm:px-6">
            <div class="mx-auto max-w-3xl">
                <!-- Hero -->
                <section class="animate-rise relative overflow-hidden rounded-[2rem] border border-line/60 bg-surface-2/40 shadow-soft sm:rounded-[2.5rem]">
                    <div class="relative aspect-[4/5] bg-gradient-to-br from-blue-soft via-teal-soft to-blue-soft/60 sm:aspect-[16/10]">
                        @if ($cover = $event->coverUrl())
                            <img src="{{ $cover }}" alt="" class="absolute inset-0 h-full w-full object-cover opacity-75">
                        @endif
                        <div class="absolute inset-0 bg-gradient-to-t from-scrim/90 via-scrim/35 to-transparent"></div>
                        <div class="absolute inset-0 flex flex-col justify-end p-6 pb-8 sm:p-10">
                            <p class="text-[10px] font-semibold uppercase tracking-[0.22em] text-on-scrim/80">The Wedding of</p>
                            <h1 class="mt-2 font-serif text-3xl font-bold leading-tight text-on-scrim sm:text-5xl">
                                {{ $event->groom_name }} &amp; {{ $event->bride_name }}
                            </h1>
                            <p class="mt-3 max-w-md text-sm leading-relaxed text-on-scrim/85">
                                Kami mengundang Anda untuk hadir di hari bahagia kami.
                            </p>
                        </div>
                    </div>

                    <div class="grid gap-4 border-t border-line/60 p-6 sm:grid-cols-2 sm:p-8">
                        <div class="flex items-start gap-3">
                            <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-2xl bg-blue-soft text-ink">
                                <x-ui.icon name="calendar" class="h-5 w-5" />
                            </span>
                            <div>
                                <p class="guest-eyebrow">Tanggal &amp; waktu</p>
                                <p class="text-sm font-semibold text-ink">{{ $eventDateTime }}</p>
                            </div>
                        </div>
                        <div class="flex items-start gap-3">
                            <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-2xl bg-blue-soft text-ink">
                                <x-ui.icon name="map-pin" class="h-5 w-5" />
                            </span>
                            <div>
                                <p class="guest-eyebrow">Tempat</p>
                                <p class="text-sm font-semibold text-ink">{{ $event->venue_name ?: 'Detail lokasi menyusul' }}</p>
                                @if ($event->venue_address)
                                    <p class="mt-0.5 text-xs text-muted">{{ $event->venue_address }}</p>
                                @endif
                            </div>
                        </div>
                    </div>

                    @if ($countdownTarget)
                        <div class="grid grid-cols-4 gap-2 border-t border-line/60 bg-surface-2/30 px-6 py-5 sm:px-8"
                             x-data="{
                                target: {{ $countdownTarget }},
                                days: '00', hours: '00', minutes: '00', seconds: '00',
                                timer: null,
                                init() { this.tick(); this.timer = setInterval(() => this.tick(), 1000); },
                                destroy() { if (this.timer) { clearInterval(this.timer); } },
                                tick() {
                                    const diff = this.target - Date.now();
                                    if (diff <= 0) { this.days = this.hours = this.minutes = this.seconds = '00'; return; }
                                    this.days = String(Math.floor(diff / 86400000)).padStart(2, '0');
                                    this.hours = String(Math.floor((diff % 86400000) / 3600000)).padStart(2, '0');
                                    this.minutes = String(Math.floor((diff % 3600000) / 60000)).padStart(2, '0');
                                    this.seconds = String(Math.floor((diff % 60000) / 1000)).padStart(2, '0');
                                }
                             }" x-init="init()">
                            @foreach ([['days', 'Hari'], ['hours', 'Jam'], ['minutes', 'Menit'], ['seconds', 'Detik']] as [$key, $label])
                                <div class="flex flex-col items-center gap-1">
                                    <span class="text-[10px] font-semibold uppercase tracking-wide text-faint">{{ $label }}</span>
                                    <span x-text="{{ $key }}" class="font-serif text-xl font-bold text-ink">00</span>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </section>

                <!-- Private invitation notice -->
                <section class="card mt-8 p-6">
                    <div class="flex items-start gap-3">
                        <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-2xl bg-accent-soft text-ink">
                            <x-ui.icon name="lock" class="h-5 w-5" />
                        </span>
                        <div>
                            <p class="text-sm font-semibold text-ink">Undangan pribadi Anda</p>
                            <p class="mt-1 max-w-prose text-sm leading-relaxed text-muted">
                                Buka undangan ini melalui tautan pribadi yang dikirimkan kepada Anda, atau pindai QR undangan.
                                Tautan ini hanya untuk Anda dan tamu yang diundang.
                            </p>
                        </div>
                    </div>
                </section>

                <!-- Event details -->
                <section class="card mt-8 p-6 sm:p-8">
                    <h2 class="section-kicker mb-5">Rincian Acara</h2>
                    <div class="space-y-5">
                        <div class="flex items-start gap-3">
                            <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-2xl bg-blue-soft text-ink">
                                <x-ui.icon name="calendar" class="h-5 w-5" />
                            </span>
                            <div>
                                <p class="guest-eyebrow">Tanggal &amp; waktu</p>
                                <p class="text-sm font-semibold text-ink">{{ $eventDateTime }}</p>
                            </div>
                        </div>
                        <div class="flex items-start gap-3">
                            <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-2xl bg-blue-soft text-ink">
                                <x-ui.icon name="map-pin" class="h-5 w-5" />
                            </span>
                            <div>
                                <p class="guest-eyebrow">Tempat</p>
                                <p class="text-sm font-semibold text-ink">{{ $event->venue_name ?: 'Detail lokasi menyusul' }}</p>
                                @if ($event->venue_address)
                                    <p class="mt-0.5 text-xs text-muted">{{ $event->venue_address }}</p>
                                @endif
                            </div>
                        </div>
                        @if ($event->dress_code)
                            <div class="flex items-start gap-3">
                                <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-2xl bg-blue-soft text-ink">
                                    <x-ui.icon name="user" class="h-5 w-5" />
                                </span>
                                <div>
                                    <p class="guest-eyebrow">Dress code</p>
                                    <p class="text-sm font-semibold text-ink">{{ $event->dress_code }}</p>
                                </div>
                            </div>
                        @endif
                    </div>
                    @if ($event->google_maps_url)
                        <a href="{{ $event->google_maps_url }}" target="_blank" rel="noopener" class="mt-5 block w-full btn-soft text-center">
                            Buka di Google Maps
                            <x-ui.icon name="arrow-up-right" class="ml-1.5 inline h-4 w-4" />
                        </a>
                    @endif
                </section>

                <!-- Schedule -->
                @php $previewSchedules = $event->schedules->take(6); @endphp
                @if ($previewSchedules->count())
                    <section class="card mt-8 p-6 sm:p-8">
                        <h2 class="section-kicker mb-5">Susunan Acara</h2>
                        <ol class="space-y-4">
                            @foreach ($previewSchedules as $schedule)
                                <li class="flex items-start gap-3">
                                    <span class="mt-1.5 h-2 w-2 shrink-0 rounded-full bg-accent"></span>
                                    <div>
                                        <p class="text-[11px] font-semibold uppercase tracking-wide text-faint">
                                            {{ $schedule->start_time ? \Carbon\Carbon::parse($schedule->start_time)->format('H:i') : '' }}
                                        </p>
                                        <p class="text-sm font-semibold text-ink">{{ $schedule->title }}</p>
                                        @if ($schedule->description)
                                            <p class="mt-0.5 max-w-prose text-xs leading-relaxed text-muted">{{ $schedule->description }}</p>
                                        @endif
                                    </div>
                                </li>
                            @endforeach
                        </ol>
                    </section>
                @endif

                <!-- Story -->
                @if ($event->couple_description || $event->description)
                    <section class="card mt-8 p-6 sm:p-8">
                        <h2 class="section-kicker mb-4">Cerita Kami</h2>
                        <p class="max-w-prose text-sm leading-relaxed text-muted">{{ $event->couple_description ?? $event->description }}</p>
                    </section>
                @endif

                <!-- Live wall -->
                @if ($event->live_wall_enabled)
                    <a href="{{ route('live-wall.show', $event->slug) }}" class="mt-8 block w-full btn-soft text-center">
                        <x-ui.icon name="tv" class="h-4 w-4" /> Tembok Ucapan Langsung
                    </a>
                @endif

                <p class="mt-12 text-center text-[11px] text-faint">
                    Dibuat dengan tenang oleh <span class="font-semibold text-ink">VOWSPACE</span>
                </p>
            </div>
        </main>
    </div>
</body>
</html>
