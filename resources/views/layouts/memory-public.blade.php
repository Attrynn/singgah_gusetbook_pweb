<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Kenangan') · VOWSPACE</title>
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
        $shareEvent = $event ?? null;
        $shareDate = $shareEvent?->localStartsAt()?->translatedFormat('d M Y')
            ?? $shareEvent?->event_date?->translatedFormat('d M Y');
    @endphp

    <div class="mx-auto flex min-h-dvh w-full max-w-3xl flex-col border-x border-line/40 bg-canvas shadow-soft lg:max-w-5xl">
        <!-- Public share header -->
        <header class="sticky top-0 z-30 border-b border-line/60 bg-surface/90 px-4 py-3 backdrop-blur sm:px-6">
            <div class="mx-auto flex max-w-5xl items-center justify-between gap-4">
                <div class="min-w-0">
                    <p class="guest-eyebrow inline-flex items-center gap-1.5">
                        <x-ui.icon name="heart" class="h-3 w-3 text-accent" />
                        Kenangan Pernikahan
                    </p>
                    <p class="truncate font-serif text-sm font-bold text-ink">
                        {{ $shareEvent?->coupleNames() ?? 'VOWSPACE' }}
                    </p>
                </div>

                <div class="flex shrink-0 items-center gap-2">
                    @if ($shareEvent && $shareEvent->event_date)
                        <span class="hidden rounded-full border border-line bg-surface-2/60 px-2.5 py-0.5 text-[10px] font-medium text-muted sm:inline-flex">
                            {{ $shareDate }}
                        </span>
                    @endif
                    <x-ui.theme-switcher size="sm" />
                </div>
            </div>
        </header>

        <!-- Main Content -->
        <main class="flex-1 pb-12">
            @yield('content')
        </main>

        <!-- Quiet footer -->
        <footer class="border-t border-line/40 px-4 py-6 sm:px-6">
            <div class="mx-auto flex max-w-5xl flex-col items-center gap-1 text-center">
                <p class="guest-eyebrow">VOWSPACE</p>
                <p class="text-[11px] text-faint">Dibagikan melalui VOWSPACE</p>
            </div>
        </footer>
    </div>

    @stack('scripts')
</body>
</html>
