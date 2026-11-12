<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Portal Vendor') · VOWSPACE</title>
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
    <div class="min-h-dvh flex flex-col">
        <!-- Header -->
        <header class="sticky top-0 z-30 border-b border-line/70 bg-surface/95 backdrop-blur">
            <div class="mx-auto w-full max-w-7xl px-4 py-3 sm:px-6 lg:px-8">
                <div class="flex items-center justify-between gap-3">
                    <!-- Brand -->
                    <a href="{{ route('vendor-portal.index') }}" class="flex items-center gap-3 min-w-0">
                        <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-2xl bg-accent text-sm font-bold text-[var(--color-accent-text)] shadow-raised">V</span>
                        <div class="min-w-0">
                            <p class="text-sm font-bold tracking-[0.16em] text-ink">VOWSPACE</p>
                            <p class="text-[9px] font-semibold uppercase tracking-[0.18em] text-accent">Vendor Portal</p>
                        </div>
                    </a>

                    <!-- User info & logout -->
                    <div class="flex items-center gap-3">
                        <div class="hidden sm:block text-right">
                            <p class="text-xs font-semibold text-ink">{{ auth()->user()->name }}</p>
                            <p class="text-[10px] text-muted">{{ auth()->user()->email }}</p>
                        </div>
                        <form method="POST" action="{{ route('logout') }}" class="inline">
                            @csrf
                            <button type="submit" class="icon-btn h-9 w-9 !rounded-lg text-muted hover:text-danger hover:border-danger/30" title="Keluar">
                                <x-ui.icon name="logout" class="h-4 w-4" />
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </header>

        <!-- Main content -->
        <main class="flex-1 mx-auto w-full max-w-7xl px-4 py-6 sm:px-6 lg:px-8 pb-20">
            <x-ui.flash />
            @yield('content')
        </main>
    </div>

    @stack('scripts')
</body>
</html>