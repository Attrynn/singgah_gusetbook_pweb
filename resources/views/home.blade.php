<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="VOWSPACE — Beyond the Guestbook. A Complete Wedding Experience.">
    <title>VOWSPACE · Beyond the Guestbook</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-dvh bg-canvas">
    <header class="mx-auto flex w-full max-w-6xl items-center justify-between px-5 py-6 sm:px-8">
        <a href="{{ route('home') }}" class="flex items-center gap-3">
            <span class="flex h-10 w-10 items-center justify-center rounded-2xl bg-accent text-sm font-bold text-white shadow-raised">V</span>
            <span class="text-sm font-semibold tracking-[0.2em] text-ink">VOWSPACE</span>
        </a>

        <a href="{{ route('login') }}" class="btn-secondary btn-sm">
            <x-ui.icon name="lock" class="h-4 w-4" />
            Masuk
        </a>
    </header>

    <main class="mx-auto w-full max-w-6xl px-5 pb-20 sm:px-8">
        <section class="relative overflow-hidden rounded-[2rem] border border-line/80 bg-surface px-6 py-14 shadow-raised sm:px-12 sm:py-20">
            <div class="absolute -right-24 -top-20 h-72 w-72 rounded-full bg-blue-soft/70 blur-3xl"></div>
            <div class="absolute -bottom-24 -left-16 h-64 w-64 rounded-full bg-teal-soft/70 blur-3xl"></div>

            <div class="relative max-w-2xl">
                <p class="section-kicker">Beyond the Guestbook</p>
                <h1 class="mt-4 font-serif text-4xl leading-tight text-ink sm:text-6xl">
                    Satu pernikahan.<br>Satu ekosistem digital.
                </h1>
                <p class="mt-6 max-w-xl text-sm leading-7 text-muted sm:text-base">
                    Undangan personal, RSVP, QR check-in, love wall, galeri tamu, polling, mini game, dan arsip pernikahan —
                    dalam satu pengalaman yang tenang, hangat, dan terintegrasi.
                </p>

                <div class="mt-8 flex flex-wrap gap-3">
                    <x-ui.button href="{{ route('login') }}" icon="arrow-up-right">
                        Masuk ke dashboard
                    </x-ui.button>
                    @if ($events->first())
                        <x-ui.button href="{{ route('guest.landing', $events->first()->slug) }}" variant="secondary" icon="eye">
                            Lihat undangan publik
                        </x-ui.button>
                    @endif
                </div>
            </div>
        </section>

        <section class="mt-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            @foreach ([
                ['icon' => 'mail', 'title' => 'Personal Invitation', 'copy' => 'Setiap tamu mendapat tautan unik, nama personal, dan batas kehadiran.'],
                ['icon' => 'qr', 'title' => 'QR Check-in', 'copy' => 'Scan QR pass sekali pakai dengan proteksi duplikat dan audit log.'],
                ['icon' => 'heart', 'title' => 'Love Wall', 'copy' => 'Ucapan publik dan privat, moderasi, favorit, serta live wall untuk venue.'],
                ['icon' => 'archive', 'title' => 'Wedding Archive', 'copy' => 'Galeri, timeline, statistik kehadiran, dan cerita pernikahan yang tersimpan.'],
            ] as $feature)
                <article class="card p-6">
                    <span class="flex h-11 w-11 items-center justify-center rounded-2xl bg-blue-soft text-[#5c86a8]">
                        <x-ui.icon :name="$feature['icon']" class="h-5 w-5" />
                    </span>
                    <h2 class="mt-4 text-sm font-semibold text-ink">{{ $feature['title'] }}</h2>
                    <p class="mt-2 text-xs leading-6 text-muted">{{ $feature['copy'] }}</p>
                </article>
            @endforeach
        </section>

        @if ($events->isNotEmpty())
            <section class="mt-14">
                <div class="flex items-end justify-between gap-4">
                    <div>
                        <p class="section-kicker">Public invitations</p>
                        <h2 class="mt-1 font-serif text-2xl text-ink">Pernikahan yang akan datang</h2>
                    </div>
                </div>

                <div class="mt-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach ($events as $event)
                        <a href="{{ route('guest.landing', $event->slug) }}" class="card group overflow-hidden">
                            <div class="relative h-36 bg-surface-2">
                                @if ($event->coverUrl())
                                    <img src="{{ $event->coverUrl() }}" alt="{{ $event->coupleNames() }}" class="h-full w-full object-cover transition duration-500 group-hover:scale-[1.03]">
                                @else
                                    <div class="flex h-full items-center justify-center bg-gradient-to-br from-blue-soft via-surface to-teal-soft">
                                        <x-ui.icon name="heart" class="h-8 w-8 text-[#8eb4d3]" />
                                    </div>
                                @endif
                            </div>
                            <div class="p-5">
                                <p class="text-[10px] font-semibold uppercase tracking-[0.16em] text-faint">
                                    {{ $event->event_date?->translatedFormat('d F Y') }}
                                </p>
                                <h3 class="mt-2 font-serif text-xl text-ink">{{ $event->coupleNames() }}</h3>
                                <p class="mt-1 text-xs text-muted">{{ $event->venue_name }}</p>
                            </div>
                        </a>
                    @endforeach
                </div>
            </section>
        @endif
    </main>

    <footer class="border-t border-line/70 py-8">
        <div class="mx-auto flex w-full max-w-6xl flex-col items-center justify-between gap-3 px-5 text-xs text-faint sm:flex-row sm:px-8">
            <p>© {{ date('Y') }} VOWSPACE. Beyond the Guestbook.</p>
            <p>Cold Minimalist Soft Neumorphism</p>
        </div>
    </footer>
</body>
</html>
