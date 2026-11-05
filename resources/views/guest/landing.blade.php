<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#F1F5F9">
    <title>{{ $event->coupleNames() }} · Undangan Pernikahan · VOWSPACE</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-dvh bg-canvas">
    <div class="mobile-shell shadow-soft">
        <header class="px-5 pt-5 pb-3 flex items-center justify-between">
            <div class="flex items-center gap-2">
                <span class="flex h-8 w-8 items-center justify-center rounded-xl bg-blue-soft text-[#4f7592] font-semibold text-xs">V</span>
                <span class="font-serif text-sm font-semibold text-ink tracking-tight">VOWSPACE</span>
            </div>
            <a href="{{ route('login') }}" class="text-[11px] font-semibold text-faint transition hover:text-ink">Masuk</a>
        </header>

        <main class="px-5 pb-12">
            <!-- Hero -->
            <div class="relative rounded-[2.25rem] overflow-hidden border border-line/60 bg-surface-2/40 shadow-soft mb-10">
                <div class="aspect-[4/5] relative bg-gradient-to-br from-blue-soft via-teal-soft to-blue-soft/60">
                    @if ($cover = $event->coverUrl())
                        <img src="{{ $cover }}" alt="" class="absolute inset-0 w-full h-full object-cover opacity-70">
                    @endif
                    <div class="absolute inset-0 bg-gradient-to-t from-ink/75 via-ink/20 to-transparent"></div>
                    <div class="absolute inset-0 flex items-end p-6 pb-10">
                        <p class="section-kicker text-white/90">The Wedding of</p>
                        <h1 class="mt-1 font-serif text-3xl font-bold text-white leading-tight">{{ $event->groom_name }} & {{ $event->bride_name }}</h1>
                    </div>
                </div>
                <div class="px-6 pt-6 pb-8">
                    <div class="flex items-center justify-between gap-3 mb-6">
                        <span class="flex shrink-0 h-9 w-9 items-center justify-center rounded-2xl bg-blue-soft text-[#4f7592]">
                            <x-ui.icon name="calendar" class="h-4 w-4" />
                        </span>
                        <p class="text-sm font-semibold text-ink">{{ $event->event_date?->translatedFormat('l, d F Y') }}</p>
                    </div>
                    <div class="grid grid-cols-4 gap-2" x-data="{ target: {{ $event->countdownTarget()?->getTimestampMs() ?? 'null' }}, days:'00', hours:'00', minutes:'00', seconds:'00', init(){ this.tick(); setInterval(()=>this.tick(),1000); }, tick(){ if(!this.target){this.days=this.hours=this.minutes=this.seconds='--';return;} const diff=this.target-Date.now(); if(diff<=0){this.days=this.hours=this.minutes=this.seconds='00';return;} const d=Math.floor(diff/86400000); const h=Math.floor((diff%86400000)/3600000); const m=Math.floor((diff%3600000)/60000); const s=Math.floor((diff%60000)/1000); this.days=String(d).padStart(2,'0'); this.hours=String(h).padStart(2,'0'); this.minutes=String(m).padStart(2,'0'); this.seconds=String(s).padStart(2,'0'); } }" x-init="init()">
                        <div class="flex flex-col items-center gap-1">
                            <span class="text-[10px] font-semibold text-faint uppercase">Hari</span>
                            <span x-text="days" class="font-serif text-xl font-bold text-ink">00</span>
                        </div>
                        <div class="flex flex-col items-center gap-1">
                            <span class="text-[10px] font-semibold text-faint uppercase">Jam</span>
                            <span x-text="hours" class="font-serif text-xl font-bold text-ink">00</span>
                        </div>
                        <div class="flex flex-col items-center gap-1">
                            <span class="text-[10px] font-semibold text-faint uppercase">Menit</span>
                            <span x-text="minutes" class="font-serif text-xl font-bold text-ink">00</span>
                        </div>
                        <div class="flex flex-col items-center gap-1">
                            <span class="text-[10px] font-semibold text-faint uppercase">Detik</span>
                            <span x-text="seconds" class="font-serif text-xl font-bold text-ink">00</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Personal note -->
            <div class="card p-6 mb-10">
                <div class="flex items-start gap-3">
                    <span class="flex shrink-0 h-10 w-10 items-center justify-center rounded-2xl bg-blue-soft text-[#4f7592]">
                        <x-ui.icon name="lock" class="h-5 w-5" />
                    </span>
                    <div>
                        <p class="text-sm font-semibold text-ink">Undangan pribadi Anda</p>
                        <p class="mt-1 text-sm text-muted">Buka undangan ini melalui tautan pribadi yang dikirimkan kepada Anda, atau pindai QR undangan. Tautan ini hanya untuk Anda dan tamu yang diundang.</p>
                    </div>
                </div>
            </div>

            <!-- Event details -->
            <div class="card p-6 mb-10">
                <p class="section-kicker mb-4">Rincian Acara</p>
                <div class="space-y-4">
                    <div class="flex items-start gap-3">
                        <span class="flex shrink-0 h-10 w-10 items-center justify-center rounded-2xl bg-blue-soft text-[#4f7592]">
                            <x-ui.icon name="calendar" class="h-5 w-5" />
                        </span>
                        <div>
                            <p class="text-[10px] font-semibold text-faint uppercase">Tanggal & waktu</p>
                            <p class="text-sm font-semibold text-ink">{{ $event->event_date?->translatedFormat('d F Y') }} · {{ \Carbon\Carbon::parse($event->start_time)->format('H:i') }} WIB</p>
                        </div>
                    </div>
                    <div class="flex items-start gap-3">
                        <span class="flex shrink-0 h-10 w-10 items-center justify-center rounded-2xl bg-blue-soft text-[#4f7592]">
                            <x-ui.icon name="map-pin" class="h-5 w-5" />
                        </span>
                        <div>
                            <p class="text-[10px] font-semibold text-faint uppercase">Tempat</p>
                            <p class="text-sm font-semibold text-ink">{{ $event->venue_name }}</p>
                            <p class="mt-0.5 text-xs text-muted">{{ $event->venue_address }}</p>
                        </div>
                    </div>
                    @if ($event->dress_code)
                    <div class="flex items-start gap-3">
                        <span class="flex shrink-0 h-10 w-10 items-center justify-center rounded-2xl bg-blue-soft text-[#4f7592]">
                            <x-ui.icon name="user" class="h-5 w-5" />
                        </span>
                        <div>
                            <p class="text-[10px] font-semibold text-faint uppercase">Dress code</p>
                            <p class="text-sm font-semibold text-ink">{{ $event->dress_code }}</p>
                        </div>
                    </div>
                    @endif
                </div>
                @if ($event->google_maps_url)
                    <a href="{{ $event->google_maps_url }}" target="_blank" rel="noopener" class="mt-4 block w-full btn-soft text-center">
                        Buka di Google Maps <x-ui.icon name="arrow-up-right" class="h-4 w-4 inline ml-1.5" />
                    </a>
                @endif
            </div>

            <!-- Schedule preview -->
            @php
                $previewSchedules = $event->schedules->take(4);
            @endphp
            @if ($previewSchedules->count())
                <div class="mb-10">
                    <p class="section-kicker mb-4">Jadwal Acara</p>
                    <div class="space-y-3">
                        @foreach ($previewSchedules as $schedule)
                            <div class="flex items-start gap-3">
                                <span class="mt-0.5 shrink-0 w-1 h-3 rounded-full bg-accent"></span>
                                <div>
                                    <p class="text-[10px] font-semibold text-faint uppercase">{{ \Carbon\Carbon::parse($schedule->start_time)->format('H:i') }}</p>
                                    <p class="text-sm font-semibold text-ink">{{ $schedule->title }}</p>
                                    @if ($schedule->description)
                                        <p class="mt-0.5 text-xs text-muted">{{ $schedule->description }}</p>
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif

            <!-- Story -->
            @if ($event->couple_description || $event->description)
                <div class="card p-6 mb-10">
                    <p class="section-kicker mb-4">Cerita Kami</p>
                    <p class="prose-wedding">{{ $event->couple_description ?? $event->description }}</p>
                </div>
            @endif

            <!-- Live wall -->
            @if ($event->live_wall_enabled)
                <a href="{{ route('live-wall.show', $event->slug) }}" class="block w-full btn-soft text-center mb-10">
                    <x-ui.icon name="tv" class="h-4 w-4 mr-2" /> Tembok Ucapan Langsung
                </a>
            @endif

            <p class="text-center text-[11px] text-faint">Dibuat dengan tenang oleh <span class="font-semibold text-ink">VOWSPACE</span></p>
        </main>
    </div>
</body>
</html>