<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Undangan Pernikahan') · VOWSPACE</title>
    <script>
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
        $navToken = $token ?? request()->route('token');
        $ev = $currentEvent ?? (isset($event) ? $event : null);

        $guestNav = [];
        if ($navToken) {
            $guestNav[] = [
                'href' => route('guest.invitation', $navToken),
                'active' => request()->routeIs('guest.invitation'),
                'label' => 'Undangan',
                'icon' => 'home'
            ];

            if ($ev && $ev->rsvp_enabled) {
                $guestNav[] = [
                    'href' => route('guest.invitation', $navToken).'#rsvp',
                    'active' => false,
                    'label' => 'RSVP',
                    'icon' => 'calendar'
                ];
            }

            if ($ev && $ev->guestbook_enabled) {
                $guestNav[] = [
                    'href' => route('guest.invitation', $navToken).'#guestbook',
                    'active' => false,
                    'label' => 'Ucapan',
                    'icon' => 'message'
                ];
            }

            if ($ev && $ev->gallery_enabled) {
                $guestNav[] = [
                    'href' => route('guest.gallery', $navToken),
                    'active' => request()->routeIs('guest.gallery'),
                    'label' => 'Galeri',
                    'icon' => 'image'
                ];
            }

            if ($ev && $ev->allowsGuestActions()) {
                $guestNav[] = [
                    'href' => route('guest.pass', $navToken),
                    'active' => request()->routeIs('guest.pass'),
                    'label' => 'Pass',
                    'icon' => 'qr'
                ];
            }
        }
    @endphp

    <div class="mobile-shell">
        <!-- Guest Top Header -->
        <header class="sticky top-0 z-30 border-b border-line/60 bg-surface/90 px-5 py-3 backdrop-blur flex items-center justify-between">
            <div class="min-w-0">
                <p class="text-[9px] font-bold uppercase tracking-[0.2em] text-accent">Wedding Invitation</p>
                <p class="font-serif text-sm font-bold text-ink truncate">
                    {{ $ev ? $ev->coupleNames() : 'VOWSPACE' }}
                </p>
            </div>

            <div class="flex items-center gap-2">
                @if ($ev && $ev->event_date)
                    <span class="hidden sm:inline-flex rounded-full border border-line bg-surface-2/60 px-2.5 py-0.5 text-[10px] font-medium text-muted">
                        {{ $ev->event_date->translatedFormat('d M Y') }}
                    </span>
                @endif
                <x-ui.theme-switcher size="sm" />
            </div>
        </header>

        <!-- Main Content -->
        <main class="pb-28">
            <div class="px-5 pt-4">
                <x-ui.flash />
            </div>
            @yield('content')
        </main>

        <!-- Capabilities-Aware Bottom Navigation (VWS-036) -->
        @if (count($guestNav) > 0)
            <nav class="bottom-nav safe-bottom">
                <div class="flex items-center justify-between">
                    @foreach ($guestNav as $item)
                        <a href="{{ $item['href'] }}" class="bottom-nav-item {{ $item['active'] ? 'bottom-nav-item-active' : '' }}">
                            <span class="flex h-8 w-8 items-center justify-center rounded-xl {{ $item['active'] ? 'bg-accent/15 text-accent' : 'text-faint' }}">
                                <x-ui.icon :name="$item['icon']" class="h-4 w-4" />
                            </span>
                            <span>{{ $item['label'] }}</span>
                        </a>
                    @endforeach
                </div>
            </nav>
        @endif
    </div>

    @stack('scripts')
</body>
</html>
