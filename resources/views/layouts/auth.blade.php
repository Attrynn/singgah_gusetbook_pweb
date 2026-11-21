<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Masuk') · VOWSPACE</title>
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
    <div class="grid min-h-dvh lg:grid-cols-2">
        <!-- Brand Presentation Side -->
        <section class="relative hidden overflow-hidden border-r border-line bg-gradient-to-b from-surface via-surface to-surface-2/40 lg:flex lg:flex-col lg:justify-between lg:p-14">
            <div class="pointer-events-none absolute inset-x-0 top-0 h-px bg-gradient-to-r from-transparent via-accent/30 to-transparent"></div>
            <div class="pointer-events-none absolute right-10 bottom-10 hidden font-serif text-9xl text-accent/10 select-none lg:block">⚜</div>

            <div class="relative flex items-center justify-between">
                <a href="{{ route('home') }}" class="group flex items-center gap-3.5">
                    <span class="flex h-10 w-10 items-center justify-center rounded-lg border border-accent/40 bg-surface font-serif text-base font-bold text-accent shadow-sm transition group-hover:border-accent">V</span>
                    <div>
                        <p class="font-serif text-base font-semibold tracking-[0.25em] text-ink">VOWSPACE</p>
                        <p class="text-[9px] font-semibold uppercase tracking-[0.2em] text-accent">Haute Wedding Concierge</p>
                    </div>
                </a>
                <x-ui.theme-switcher size="md" />
            </div>

            <div class="relative max-w-md">
                <div class="flex items-center gap-2">
                    <span class="text-accent text-xs">✦</span>
                    <p class="section-kicker">Maison de Mariage</p>
                </div>
                <h1 class="mt-4 font-serif text-4xl font-medium leading-[1.15] text-ink xl:text-5xl">
                    Kemewahan Tanpa Celah. <span class="italic font-normal text-accent block mt-1">Satu Ekosistem Terpadu.</span>
                </h1>
                <p class="mt-5 text-sm leading-relaxed text-muted font-light">
                    Portal terpadu untuk pengantin dan superadmin dalam mengelola undangan personal berkelas, reservasi eksklusif, hingga check-in registrasi tamu kehormatan.
                </p>

                <div class="mt-8 grid grid-cols-2 gap-3">
                    @foreach ([
                        ['icon' => 'mail', 'label' => 'Bespoke Invitation'],
                        ['icon' => 'qr', 'label' => 'VIP Admission Pass'],
                        ['icon' => 'heart', 'label' => 'Live Love Wall'],
                        ['icon' => 'archive', 'label' => 'Timeless Heritage'],
                    ] as $feature)
                        <div class="flex items-center gap-2.5 rounded-lg border border-line bg-surface/80 px-3.5 py-3 shadow-sm transition hover:border-accent/30">
                            <x-ui.icon :name="$feature['icon']" class="h-4 w-4 text-accent shrink-0" />
                            <span class="text-xs font-medium text-ink">{{ $feature['label'] }}</span>
                        </div>
                    @endforeach
                </div>
            </div>

            <p class="relative text-[11px] text-faint tracking-wider uppercase">
                © {{ date('Y') }} VOWSPACE · Haute Concierge Architecture
            </p>
        </section>

        <!-- Form Side -->
        <section class="flex min-h-dvh flex-col justify-between px-6 py-10 sm:px-12 lg:p-14">
            <div class="flex items-center justify-between lg:hidden">
                <a href="{{ route('home') }}" class="flex items-center gap-3">
                    <span class="flex h-9 w-9 items-center justify-center rounded-lg border border-accent/40 bg-surface font-serif text-sm font-bold text-accent shadow-sm">V</span>
                    <p class="font-serif text-sm font-semibold tracking-[0.2em] text-ink">VOWSPACE</p>
                </a>
                <x-ui.theme-switcher size="sm" />
            </div>

            <div class="mx-auto my-auto w-full max-w-md py-8">
                <x-ui.flash />
                @yield('content')
            </div>

            <footer class="text-center text-xs text-faint">
                <p class="tracking-widest uppercase text-[10px] text-accent/70 font-medium">Haute Concierge Management Portal</p>
            </footer>
        </section>
    </div>

    @stack('scripts')
</body>
</html>
