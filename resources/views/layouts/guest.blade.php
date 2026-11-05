<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#F1F5F9">
    <title>@yield('title', 'Wedding Invitation') · VOWSPACE</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-dvh bg-surface-2">
    @php
        $navToken = $token ?? request()->route('token');
        $guestNav = [
            ['href' => $navToken ? route('guest.invitation', $navToken) : '#', 'active' => request()->routeIs('guest.invitation'), 'label' => 'Home', 'icon' => 'home'],
            ['href' => $navToken ? route('guest.invitation', $navToken).'#rsvp' : '#', 'active' => false, 'label' => 'RSVP', 'icon' => 'calendar-check'],
            ['href' => $navToken ? route('guest.invitation', $navToken).'#guestbook' : '#', 'active' => false, 'label' => 'Wishes', 'icon' => 'message'],
            ['href' => $navToken ? route('guest.gallery', $navToken) : '#', 'active' => request()->routeIs('guest.gallery'), 'label' => 'Gallery', 'icon' => 'image'],
            ['href' => $navToken ? route('guest.pass', $navToken) : '#', 'active' => request()->routeIs('guest.pass'), 'label' => 'Pass', 'icon' => 'qr'],
        ];
    @endphp

    <div class="mobile-shell shadow-soft">
        <main class="pb-28">
            <div class="px-5 pt-5">
                <x-ui.flash />
            </div>
            @yield('content')
        </main>

        <nav class="bottom-nav safe-bottom">
            <div class="flex items-center justify-between">
                @foreach ($guestNav as $item)
                    <a href="{{ $item['href'] }}" class="bottom-nav-item {{ $item['active'] ? 'bottom-nav-item-active' : '' }}">
                        <span class="flex h-9 w-9 items-center justify-center rounded-2xl {{ $item['active'] ? 'bg-blue-soft' : '' }}">
                            <x-ui.icon :name="$item['icon']" class="h-5 w-5" />
                        </span>
                        {{ $item['label'] }}
                    </a>
                @endforeach
            </div>
        </nav>
    </div>

    @stack('scripts')
</body>
</html>
