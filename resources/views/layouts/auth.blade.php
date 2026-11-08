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
        <section class="relative hidden overflow-hidden border-r border-line/70 bg-surface lg:flex lg:flex-col lg:justify-between lg:p-12">
            <div class="absolute -right-24 top-24 h-72 w-72 rounded-full bg-accent/10 blur-3xl"></div>
            <div class="absolute -left-20 bottom-10 h-64 w-64 rounded-full bg-accent/15 blur-3xl"></div>

            <div class="relative flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <span class="flex h-11 w-11 items-center justify-center rounded-2xl bg-accent text-sm font-bold text-[var(--color-accent-text)] shadow-raised">V</span>
                    <div>
                        <p class="text-sm font-bold tracking-[0.2em] text-ink">VOWSPACE</p>
                        <p class="text-[10px] font-semibold uppercase tracking-[0.16em] text-accent">Wedding Experience Ecosystem</p>
                    </div>
                </div>
                <x-ui.theme-switcher size="md" />
            </div>

            <div class="relative max-w-md">
                <p class="section-kicker">Beyond the Guestbook</p>
                <h1 class="mt-3 font-serif text-4xl font-bold leading-tight text-ink">
                    One wedding. One ecosystem. Every guest. Every memory.
                </h1>
                <p class="mt-4 text-sm leading-relaxed text-muted">
                    Kelola undangan digital personal, RSVP, QR check-in, ucapan real-time, galeri foto, interaksi, dan arsip pernikahan dalam satu sistem yang elegan dan terintegrasi.
                </p>

                <div class="mt-8 grid grid-cols-2 gap-3">
                    @foreach ([
                        ['icon' => 'mail', 'label' => 'Personal invitation'],
                        ['icon' => 'qr', 'label' => 'QR guest check-in'],
                        ['icon' => 'heart', 'label' => 'Live wall & wishes'],
                        ['icon' => 'sparkles', 'label' => 'Interactive games & poll'],
                    ] as $feature)
                        <div class="flex items-center gap-2.5 rounded-2xl border border-line/80 bg-surface-2/40 px-3.5 py-3 shadow-sm">
                            <x-ui.icon :name="$feature['icon']" class="h-4 w-4 text-accent" />
                            <span class="text-xs font-medium text-ink">{{ $feature['label'] }}</span>
                        </div>
                    @endforeach
                </div>
            </div>

            <p class="relative text-[11px] text-faint">
                © {{ date('Y') }} VOWSPACE · Midnight Slate & Champagne Design System
            </p>
        </section>

        <!-- Form Side -->
        <section class="flex min-h-dvh flex-col justify-between px-5 py-8 sm:px-10 lg:p-12">
            <div class="flex items-center justify-between lg:hidden">
                <div class="flex items-center gap-2.5">
                    <span class="flex h-10 w-10 items-center justify-center rounded-2xl bg-accent text-sm font-bold text-[var(--color-accent-text)] shadow-raised">V</span>
                    <p class="text-sm font-bold tracking-[0.2em] text-ink">VOWSPACE</p>
                </div>
                <x-ui.theme-switcher size="sm" />
            </div>

            <div class="mx-auto my-auto w-full max-w-md py-8">
                <x-ui.flash />
                @yield('content')
            </div>

            <footer class="text-center text-xs text-faint">
                <p>Platform Wedding Event Management Multi-Tenant</p>
            </footer>
        </section>
    </div>

    @stack('scripts')
</body>
</html>
