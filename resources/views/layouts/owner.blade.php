<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Our Wedding') · VOWSPACE</title>
    <script nonce="{{ request()->attributes->get('csp_nonce') }}">
        (function() {
            const theme = localStorage.getItem('vowspace_theme') || 'system';
            const isDark = theme === 'dark' || (theme === 'system' && window.matchMedia('(prefers-color-scheme: dark)').matches);
            if (isDark) { document.documentElement.classList.add('dark'); }
        })();
    </script>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-dvh bg-canvas text-ink antialiased" x-data="{ sidebar: false }">
    @php
        $navUser = auth()->user();
        $ownerEvents = $ownerEventsList ?? ($navUser ? $navUser->events()->orderBy('event_date')->get() : collect());
        $activeEventId = session('owner_active_event_id', request()->integer('event'));
        $currentEv = $currentOwnerEvent ?? ($activeEventId ? $ownerEvents->firstWhere('id', (int) $activeEventId) : null) ?? $ownerEvents->first();
    @endphp

    <div class="lg:flex">
        <!-- Mobile Drawer Backdrop -->
        <div x-show="sidebar" x-cloak class="fixed inset-0 z-40 bg-ink/40 backdrop-blur-sm lg:hidden" @click="sidebar = false"></div>

        <!-- Sidebar Navigation -->
        <aside
            class="fixed inset-y-0 left-0 z-50 flex w-72 flex-col border-r border-line/80 bg-surface transition-transform duration-200 lg:z-30 lg:translate-x-0"
            :class="sidebar ? 'translate-x-0' : '-translate-x-full lg:translate-x-0'"
        >
            <!-- Brand / Header -->
            <div class="flex h-16 items-center justify-between border-b border-line/70 px-5">
                <a href="{{ route('owner.dashboard') }}" class="flex items-center gap-3 min-w-0">
                    <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-2xl bg-accent text-sm font-bold text-[var(--color-accent-text)] shadow-raised">V</span>
                    <div class="min-w-0">
                        <p class="text-sm font-bold tracking-[0.16em] text-ink">VOWSPACE</p>
                        <p class="text-[9px] font-semibold uppercase tracking-[0.18em] text-accent">Owner Workspace</p>
                    </div>
                </a>

                <button type="button" class="icon-btn h-8 w-8 !rounded-xl lg:hidden" @click="sidebar = false" aria-label="Tutup menu">
                    <x-ui.icon name="close" class="h-4 w-4" />
                </button>
            </div>

            <!-- Navigation Links -->
            <nav class="flex-1 space-y-5 overflow-y-auto px-3 py-4 text-xs">
                <!-- Overview -->
                <div class="space-y-1">
                    <p class="px-3 pb-1 text-[10px] font-bold uppercase tracking-[0.18em] text-faint">Overview</p>
                    <a href="{{ route('owner.dashboard') }}" class="nav-item {{ request()->routeIs('owner.dashboard') ? 'nav-item-active' : '' }}">
                        <x-ui.icon name="home" class="h-4 w-4 shrink-0 text-accent" />
                        <span>Ringkasan Event</span>
                    </a>
                </div>

                <!-- Event Setup -->
                <div class="space-y-1">
                    <p class="px-3 pb-1 text-[10px] font-bold uppercase tracking-[0.18em] text-faint">Event Setup</p>
                    <a href="{{ route('owner.timeline') }}" class="nav-item {{ request()->routeIs('owner.timeline') ? 'nav-item-active' : '' }}">
                        <x-ui.icon name="calendar" class="h-4 w-4 shrink-0" />
                        <span>Timeline & Rundown</span>
                    </a>
                    <a href="{{ route('owner.story') }}" class="nav-item {{ request()->routeIs('owner.story') ? 'nav-item-active' : '' }}">
                        <x-ui.icon name="heart" class="h-4 w-4 shrink-0" />
                        <span>Our Love Story</span>
                    </a>
                    @if ($currentEv)
                        <a href="{{ route('guest.landing', $currentEv->slug) }}" target="_blank" class="nav-item text-muted hover:text-ink">
                            <x-ui.icon name="mail" class="h-4 w-4 shrink-0 text-accent" />
                            <span>Preview Undangan</span>
                            <x-ui.icon name="chevron-right" class="h-3 w-3 ml-auto text-faint" />
                        </a>
                    @endif
                </div>

                <!-- Guest Management -->
                <div class="space-y-1">
                    <p class="px-3 pb-1 text-[10px] font-bold uppercase tracking-[0.18em] text-faint">Tamu & Kehadiran</p>
                    <a href="{{ route('owner.attendance') }}" class="nav-item {{ request()->routeIs('owner.attendance') ? 'nav-item-active' : '' }}">
                        <x-ui.icon name="users" class="h-4 w-4 shrink-0" />
                        <span>Daftar Tamu & RSVP</span>
                    </a>
                </div>

                <!-- Guest Experience -->
                <div class="space-y-1">
                    <p class="px-3 pb-1 text-[10px] font-bold uppercase tracking-[0.18em] text-faint">Interaksi & Konten</p>
                    <a href="{{ route('owner.wishes.index') }}" class="nav-item {{ request()->routeIs('owner.wishes.*') ? 'nav-item-active' : '' }}">
                        <x-ui.icon name="message" class="h-4 w-4 shrink-0" />
                        <span>Ucapan & Doa</span>
                    </a>
                    <a href="{{ route('owner.memories.index') }}" class="nav-item {{ request()->routeIs('owner.memories.*') ? 'nav-item-active' : '' }}">
                        <x-ui.icon name="image" class="h-4 w-4 shrink-0" />
                        <span>Galeri Kenangan</span>
                    </a>
                    <a href="{{ route('owner.interactions') }}" class="nav-item {{ request()->routeIs('owner.interactions') ? 'nav-item-active' : '' }}">
                        <x-ui.icon name="sparkles" class="h-4 w-4 shrink-0" />
                        <span>Lagu, Poll & Games</span>
                    </a>
                    @if ($currentEv)
                        <a href="{{ route('live-wall.show', $currentEv->slug) }}" target="_blank" class="nav-item text-accent hover:opacity-90">
                            <x-ui.icon name="sparkles" class="h-4 w-4 shrink-0" />
                            <span>Live Wall Layar Penuh</span>
                            <x-ui.icon name="chevron-right" class="h-3 w-3 ml-auto text-faint" />
                        </a>
                    @endif
                </div>

                <!-- Settings -->
                <div class="space-y-1">
                    <p class="px-3 pb-1 text-[10px] font-bold uppercase tracking-[0.18em] text-faint">Akun</p>
                    <a href="{{ route('owner.profile.edit') }}" class="nav-item {{ request()->routeIs('owner.profile.edit') ? 'nav-item-active' : '' }}">
                        <x-ui.icon name="settings" class="h-4 w-4 shrink-0" />
                        <span>Profil Pengguna</span>
                    </a>
                </div>
            </nav>

            <!-- User profile footer -->
            <div class="border-t border-line/70 p-3">
                <div class="flex items-center gap-2.5 rounded-xl bg-surface-2/60 p-2">
                    <x-ui.avatar :name="$navUser->name" :src="$navUser->avatar_path ? Illuminate\Support\Facades\Storage::disk('public')->url($navUser->avatar_path) : null" size="sm" />
                    <div class="min-w-0 flex-1">
                        <p class="truncate text-xs font-semibold text-ink">{{ $navUser->name }}</p>
                        <p class="truncate text-[10px] text-faint">{{ $navUser->email }}</p>
                    </div>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button class="icon-btn h-8 w-8 !rounded-lg text-faint transition hover:text-danger hover:border-danger/30" title="Keluar">
                            <x-ui.icon name="logout" class="h-3.5 w-3.5" />
                        </button>
                    </form>
                </div>
            </div>
        </aside>

        <!-- Main Content Area -->
        <div class="min-h-dvh flex-1 lg:pl-72">
            <!-- Persistent Event Context Header -->
            <header class="sticky top-0 z-20 border-b border-line/70 bg-surface/90 backdrop-blur">
                <div class="flex h-16 items-center justify-between gap-3 px-4 sm:px-6 lg:px-8">
                    <!-- Left: Mobile Hamburger & Active Event Indicator -->
                    <div class="flex items-center gap-3 min-w-0">
                        <button type="button" class="icon-btn h-9 w-9 lg:hidden" @click="sidebar = true" aria-label="Buka menu">
                            <x-ui.icon name="menu" class="h-4 w-4" />
                        </button>

                        @if ($currentEv)
                            <div class="min-w-0">
                                <div class="flex items-center gap-2">
                                    <h1 class="font-serif text-base font-bold text-ink truncate md:text-lg">
                                        {{ $currentEv->coupleNames() }}
                                    </h1>
                                    <span class="{{ $currentEv->status->badge() }} text-[10px]">
                                        {{ $currentEv->status->label() }}
                                    </span>
                                </div>
                                <p class="text-[11px] text-faint truncate hidden sm:block">
                                    {{ $currentEv->event_date?->translatedFormat('d F Y') }} · {{ $currentEv->primaryVenue?->name ?? 'Venue' }}
                                </p>
                            </div>
                        @else
                            <p class="text-sm font-semibold text-ink">VOWSPACE Owner</p>
                        @endif
                    </div>

                    <!-- Right: Event Switcher & Theme Switcher & User Profile -->
                    <div class="flex items-center gap-2.5">
                        <!-- Multi-Event Switcher Dropdown (VWS-018) -->
                        @if ($ownerEvents->count() > 1)
                            <div class="relative" x-data="{ switcherOpen: false }">
                                <button type="button"
                                        @click="switcherOpen = !switcherOpen"
                                        class="btn btn-secondary btn-sm flex items-center gap-1.5"
                                        title="Ganti Acara yang Dikelola">
                                    <x-ui.icon name="calendar" class="h-3.5 w-3.5 text-accent" />
                                    <span class="hidden md:inline text-xs font-medium">Ganti Acara</span>
                                    <x-ui.icon name="chevron-down" class="h-3 w-3 text-faint" />
                                </button>

                                <div x-show="switcherOpen"
                                     x-cloak
                                     @click.outside="switcherOpen = false"
                                     class="absolute right-0 z-40 mt-2 w-64 rounded-2xl border border-line bg-surface p-2 shadow-soft">
                                    <p class="px-3 py-1.5 text-[10px] font-bold uppercase tracking-wider text-faint">Pilih Acara Anda</p>
                                    @foreach ($ownerEvents as $ev)
                                        <a href="?event={{ $ev->id }}"
                                           class="flex items-center justify-between rounded-xl px-3 py-2 text-xs transition {{ $currentEv && $currentEv->id === $ev->id ? 'bg-accent/15 text-ink font-semibold' : 'text-muted hover:bg-surface-2/60 hover:text-ink' }}">
                                            <div class="truncate">
                                                <p class="font-medium text-ink">{{ $ev->coupleNames() }}</p>
                                                <p class="text-[10px] text-faint">{{ $ev->event_date?->translatedFormat('d M Y') }}</p>
                                            </div>
                                            <span class="{{ $ev->status->badge() }} text-[9px]">{{ $ev->status->label() }}</span>
                                        </a>
                                    @endforeach
                                </div>
                            </div>
                        @endif

                        <!-- Theme Switcher -->
                        <x-ui.theme-switcher size="md" />

                        <!-- Profile Avatar Link -->
                        <a href="{{ route('owner.profile.edit') }}" class="shrink-0" aria-label="Profil Pengguna">
                            <x-ui.avatar :name="$navUser->name" :src="$navUser->avatar_path ? Illuminate\Support\Facades\Storage::disk('public')->url($navUser->avatar_path) : null" size="sm" />
                        </a>
                    </div>
                </div>
            </header>

            <!-- Main Canvas -->
            <main class="mx-auto w-full max-w-7xl px-4 py-6 sm:px-6 lg:px-8 pb-20">
                <x-ui.flash />
                @yield('content')
            </main>
        </div>
    </div>

    @stack('scripts')
</body>
</html>
