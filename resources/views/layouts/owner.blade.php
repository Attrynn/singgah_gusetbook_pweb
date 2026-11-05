<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#F1F5F9">
    <title>@yield('title', 'Our Wedding') · VOWSPACE</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-dvh bg-surface-2">
    @php
        $navEvent = $event ?? auth()->user()->events()->orderBy('event_date')->first();
        $navUser = auth()->user();
        $ownerNav = [
            ['route' => 'owner.dashboard', 'active' => 'owner.dashboard', 'label' => 'Home', 'icon' => 'home'],
            ['route' => 'owner.wishes.index', 'active' => 'owner.wishes.*', 'label' => 'Wishes', 'icon' => 'heart'],
            ['route' => 'owner.memories.index', 'active' => 'owner.memories.*', 'label' => 'Memories', 'icon' => 'image'],
            ['route' => 'owner.attendance', 'active' => 'owner.attendance', 'label' => 'Guests', 'icon' => 'users'],
            ['route' => 'owner.story', 'active' => 'owner.story', 'label' => 'Archive', 'icon' => 'archive'],
        ];
    @endphp

    <div class="mobile-shell shadow-soft">
        <header class="safe-top sticky top-0 z-30 border-b border-line/60 bg-canvas/90 px-5 pb-3 backdrop-blur">
            <div class="flex items-center justify-between gap-3">
                <div class="min-w-0">
                    <p class="text-[10px] font-semibold uppercase tracking-[0.18em] text-faint">Our Wedding Space</p>
                    <p class="mt-0.5 truncate text-sm font-semibold text-ink">
                        {{ $navEvent?->coupleNames() ?? 'VOWSPACE' }}
                    </p>
                </div>

                <div class="flex items-center gap-2">
                    @if ($navEvent)
                        <span class="rounded-full border border-line bg-surface px-2.5 py-1 text-[10px] font-semibold text-muted">
                            {{ $navEvent->event_date?->translatedFormat('d M Y') }}
                        </span>
                    @endif
                    <a href="{{ route('owner.profile.edit') }}" class="shrink-0" aria-label="Profil">
                        <x-ui.avatar :name="$navUser->name" :src="$navUser->avatar_path ? Illuminate\Support\Facades\Storage::disk('public')->url($navUser->avatar_path) : null" size="sm" />
                    </a>
                </div>
            </div>
        </header>

        <main class="pb-28">
            <div class="px-5 pt-5">
                <x-ui.flash />
            </div>
            @yield('content')
        </main>

        <nav class="bottom-nav safe-bottom">
            <div class="flex items-center justify-between">
                @foreach ($ownerNav as $item)
                    @php $isActive = request()->routeIs($item['active']); @endphp
                    <a href="{{ route($item['route']) }}" class="bottom-nav-item {{ $isActive ? 'bottom-nav-item-active' : '' }}">
                        <span class="flex h-9 w-9 items-center justify-center rounded-2xl {{ $isActive ? 'bg-blue-soft' : '' }}">
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
