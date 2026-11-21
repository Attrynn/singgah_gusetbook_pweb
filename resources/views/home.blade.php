<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="VOWSPACE — Beyond the Guestbook. Haute Concierge Wedding Experience.">
    <title>VOWSPACE · Haute Concierge Wedding Experience</title>
    <script nonce="{{ request()->attributes->get('csp_nonce') }}">
        (function() {
            const theme = localStorage.getItem('vowspace_theme') || 'system';
            const isDark = theme === 'dark' || (theme === 'system' && window.matchMedia('(prefers-color-scheme: dark)').matches);
            if (isDark) { document.documentElement.classList.add('dark'); }
        })();
    </script>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-dvh bg-canvas text-ink antialiased selection:bg-accent selection:text-[var(--color-accent-text)]">
    <!-- Top Navigation -->
    <header class="mx-auto flex w-full max-w-6xl items-center justify-between px-6 py-7 sm:px-10">
        <a href="{{ route('home') }}" class="group flex items-center gap-3.5">
            <span class="flex h-10 w-10 items-center justify-center rounded-lg border border-accent/40 bg-surface text-base font-serif font-bold text-accent shadow-sm transition group-hover:border-accent">V</span>
            <div class="flex flex-col">
                <span class="font-serif text-base tracking-[0.25em] font-semibold text-ink">VOWSPACE</span>
                <span class="text-[9px] uppercase tracking-[0.2em] text-accent font-medium">Haute Concierge</span>
            </div>
        </a>

        <div class="flex items-center gap-3">
            <x-ui.theme-switcher size="sm" />
            <a href="{{ route('login') }}" class="btn-secondary btn-sm !rounded-md px-4 border-accent/30 hover:border-accent text-ink">
                <x-ui.icon name="lock" class="h-3.5 w-3.5 text-accent" />
                Masuk
            </a>
        </div>
    </header>

    <main class="mx-auto w-full max-w-6xl px-6 pb-24 sm:px-10">
        <!-- Hero Section -->
        <section class="relative overflow-hidden rounded-2xl border border-line bg-gradient-to-b from-surface via-surface to-surface-2/30 px-6 py-16 shadow-soft sm:px-16 sm:py-24">
            <!-- Subtle Gold Architectural Lines -->
            <div class="pointer-events-none absolute inset-x-0 top-0 h-px bg-gradient-to-r from-transparent via-accent/30 to-transparent"></div>
            <div class="pointer-events-none absolute right-12 top-12 hidden opacity-15 sm:block font-serif text-8xl text-accent select-none">⚜</div>

            <div class="relative max-w-3xl">
                <div class="flex items-center gap-2">
                    <span class="text-accent text-xs">✦</span>
                    <p class="section-kicker">Haute Wedding Concierge</p>
                </div>
                
                <h1 class="mt-4 font-serif text-4xl leading-[1.12] text-ink sm:text-6xl md:text-7xl font-medium tracking-tight">
                    Satu Pernikahan Abadi.<br>
                    <span class="italic font-normal text-accent">Ekosistem Penuh Keanggunan.</span>
                </h1>
                
                <p class="mt-6 max-w-2xl text-sm leading-relaxed text-muted sm:text-base sm:leading-8 font-light">
                    Undangan personal berkelas, konfirmasi kehadiran terpadu, check-in QR instan, dinding cinta interaktif, hingga arsip kenangan yang abadi — disajikan dalam pengalaman yang tenang, megah, dan sempurna.
                </p>

                <div class="mt-9 flex flex-wrap items-center gap-3.5">
                    <a href="{{ route('login') }}" class="btn-primary !rounded-md px-6 py-3 text-sm shadow-raised">
                        <span>Masuk ke Dashboard</span>
                        <x-ui.icon name="arrow-up-right" class="h-4 w-4" />
                    </a>
                    @if ($events->first())
                        <a href="{{ route('guest.landing', $events->first()->slug) }}" class="btn-secondary !rounded-md px-6 py-3 text-sm">
                            <x-ui.icon name="eye" class="h-4 w-4 text-accent" />
                            <span>Lihat Undangan Publik</span>
                        </a>
                    @endif
                </div>
            </div>
        </section>

        <!-- Editorial Features -->
        <section class="mt-10 grid gap-5 sm:grid-cols-2 lg:grid-cols-4">
            @foreach ([
                ['num' => 'I', 'icon' => 'mail', 'title' => 'Personal Invitation', 'copy' => 'Undangan digital eksklusif dengan nama terpatri indah, kuota kehadiran, dan privasi penuh.'],
                ['num' => 'II', 'icon' => 'qr', 'title' => 'VIP QR Admission', 'copy' => 'Penerbitan kartu pass digital dan registrasi instan di meja resepsi tanpa kendala antrean.'],
                ['num' => 'III', 'icon' => 'heart', 'title' => 'Love Wall & Wishes', 'copy' => 'Dinding ucapan doa restu elegan secara real-time yang dapat ditampilkan di layar venue.'],
                ['num' => 'IV', 'icon' => 'archive', 'title' => 'Timeless Heritage', 'copy' => 'Kurasi momen, galeri foto terkurasi, dan buku memori digital yang tersimpan selamanya.'],
            ] as $feature)
                <article class="card group p-7 relative overflow-hidden transition-all duration-200 hover:border-accent/40 hover:-translate-y-0.5">
                    <div class="flex items-center justify-between">
                        <span class="flex h-10 w-10 items-center justify-center rounded-lg border border-line bg-surface-2/70 text-ink shadow-sm transition group-hover:border-accent/40 group-hover:text-accent">
                            <x-ui.icon :name="$feature['icon']" class="h-4 w-4" />
                        </span>
                        <span class="font-serif text-sm font-semibold text-accent/60 tracking-widest">{{ $feature['num'] }}</span>
                    </div>
                    <h2 class="mt-5 font-serif text-lg font-semibold text-ink tracking-tight">{{ $feature['title'] }}</h2>
                    <p class="mt-2 text-xs leading-relaxed text-muted">{{ $feature['copy'] }}</p>
                </article>
            @endforeach
        </section>

        @if ($events->isNotEmpty())
            <!-- Curated Events -->
            <section class="mt-16">
                <div class="flex items-end justify-between gap-4 border-b border-line pb-4">
                    <div>
                        <div class="flex items-center gap-2">
                            <span class="text-accent text-[10px]">✦</span>
                            <p class="section-kicker">Curated Celebrations</p>
                        </div>
                        <h2 class="mt-1 font-serif text-2xl text-ink sm:text-3xl font-medium">Perayaan Pernikahan</h2>
                    </div>
                </div>

                <div class="mt-8 grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach ($events as $event)
                        <a href="{{ route('guest.landing', $event->slug) }}" class="card group overflow-hidden transition-all duration-300 hover:border-accent/50 hover:shadow-soft">
                            <div class="relative h-44 bg-surface-2 overflow-hidden">
                                @if ($event->coverUrl())
                                    <img src="{{ $event->coverUrl() }}" alt="{{ $event->coupleNames() }}" class="h-full w-full object-cover transition duration-700 group-hover:scale-105">
                                @else
                                    <div class="flex h-full items-center justify-center bg-gradient-to-br from-surface via-surface-2 to-surface-3">
                                        <span class="font-serif text-3xl font-light text-accent">⚜</span>
                                    </div>
                                @endif
                                <div class="absolute inset-0 bg-gradient-to-t from-black/50 via-transparent to-transparent"></div>
                                <span class="absolute bottom-3 left-4 text-[10px] uppercase font-semibold tracking-[0.2em] text-white/90">
                                    {{ $event->status->label() }}
                                </span>
                            </div>
                            <div class="p-6">
                                <p class="text-[10px] font-semibold uppercase tracking-[0.2em] text-accent">
                                    {{ $event->event_date?->translatedFormat('l, d F Y') }}
                                </p>
                                <h3 class="mt-2 font-serif text-xl font-medium text-ink group-hover:text-accent transition">
                                    {{ $event->coupleNames() }}
                                </h3>
                                <p class="mt-1.5 text-xs text-muted truncate flex items-center gap-1.5">
                                    <x-ui.icon name="map-pin" class="h-3 w-3 text-faint shrink-0" />
                                    <span>{{ $event->venue_name ?: 'Lokasi privat' }}</span>
                                </p>
                            </div>
                        </a>
                    @endforeach
                </div>
            </section>
        @endif
    </main>

    <footer class="border-t border-line py-10 bg-surface">
        <div class="mx-auto flex w-full max-w-6xl flex-col items-center justify-between gap-4 px-6 text-xs text-faint sm:flex-row sm:px-10">
            <div class="flex items-center gap-2">
                <span class="font-serif font-bold text-accent">VOWSPACE</span>
                <span>·</span>
                <p>© {{ date('Y') }} Maison de Mariage. All rights reserved.</p>
            </div>
            <p class="tracking-widest uppercase text-[10px] text-accent/80 font-medium">Haute Wedding Concierge Platform</p>
        </div>
    </footer>
</body>
</html>
