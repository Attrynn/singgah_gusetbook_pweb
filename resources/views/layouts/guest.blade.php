<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Undangan Pernikahan') · VOWSPACE</title>
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
        $navToken = $token ?? request()->route('token');
        $ev = $currentEvent ?? (isset($event) ? $event : null);
        $caps = $capabilities ?? null;
        $can = fn (string $key, bool $fallback) => $caps !== null ? (bool) ($caps[$key] ?? false) : $fallback;

        $guestNav = [];
        if ($navToken) {
            $guestNav[] = [
                'href' => route('guest.invitation', $navToken),
                'active' => request()->routeIs('guest.invitation'),
                'label' => 'Undangan',
                'icon' => 'home'
            ];

            if ($can('rsvp', $ev !== null && $ev->rsvpOpen())) {
                $guestNav[] = [
                    'href' => route('guest.invitation', $navToken).'#rsvp',
                    'active' => false,
                    'label' => 'RSVP',
                    'icon' => 'calendar-check'
                ];
            }

            if ($can('guestbook', $ev !== null && (bool) $ev->guestbook_enabled)) {
                $guestNav[] = [
                    'href' => route('guest.invitation', $navToken).'#guestbook',
                    'active' => false,
                    'label' => 'Ucapan',
                    'icon' => 'message'
                ];
            }

            if ($can('gallery', $ev !== null && (bool) $ev->gallery_enabled)) {
                $guestNav[] = [
                    'href' => route('guest.gallery', $navToken),
                    'active' => request()->routeIs('guest.gallery'),
                    'label' => 'Galeri',
                    'icon' => 'image'
                ];
            }

            if ($can('memory_contribute', $ev !== null && $ev->allowsGuestActions())) {
                $guestNav[] = [
                    'href' => route('guest.memories', $navToken),
                    'active' => request()->routeIs('guest.memories*'),
                    'label' => 'Kenangan',
                    'icon' => 'heart'
                ];
            }

            if ($can('pass', $ev !== null && $ev->allowsGuestActions())) {
                $guestNav[] = [
                    'href' => route('guest.pass', $navToken),
                    'active' => request()->routeIs('guest.pass'),
                    'label' => 'Pass',
                    'icon' => 'qr'
                ];
            }

            if ($can('interactions', $ev !== null && $ev->allowsGuestActions())) {
                $guestNav[] = [
                    'href' => route('guest.interactions', $navToken),
                    'active' => request()->routeIs('guest.interactions') || request()->routeIs('guest.game.*'),
                    'label' => 'Interaksi',
                    'icon' => 'game'
                ];
            }
        }
    @endphp

    <div class="mobile-shell">
        <!-- Guest Top Header -->
        <header class="sticky top-0 z-30 border-b border-line/60 bg-surface/90 px-4 py-3 backdrop-blur sm:px-6">
            <div class="mx-auto flex max-w-5xl items-center justify-between gap-4">
                <div class="min-w-0">
                    <p class="guest-eyebrow">Undangan Pernikahan</p>
                    <p class="truncate font-serif text-sm font-bold text-ink">
                        {{ $ev ? $ev->coupleNames() : 'VOWSPACE' }}
                    </p>
                </div>

                <div class="flex shrink-0 items-center gap-2">
                    @if ($ev && $ev->event_date)
                        <span class="hidden rounded-full border border-line bg-surface-2/60 px-2.5 py-0.5 text-[10px] font-medium text-muted sm:inline-flex">
                            {{ $ev->event_date->translatedFormat('d M Y') }}
                        </span>
                    @endif
                    <x-ui.theme-switcher size="sm" />
                </div>
            </div>
        </header>

        <!-- Main Content -->
        <main class="pb-28">
            <div class="mx-auto max-w-5xl px-4 pt-4 sm:px-6">
                <x-ui.flash />
            </div>
            @yield('content')
        </main>

        <!-- Capabilities-Aware Bottom Navigation (VWS-036) -->
        @if (count($guestNav) > 0)
            <nav class="bottom-nav safe-bottom" aria-label="Navigasi undangan">
                <div class="mx-auto flex max-w-5xl items-center justify-between">
                    @foreach ($guestNav as $item)
                        <a href="{{ $item['href'] }}" class="bottom-nav-item {{ $item['active'] ? 'bottom-nav-item-active' : '' }}" @if ($item['active']) aria-current="page" @endif>
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
