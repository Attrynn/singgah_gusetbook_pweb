<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Dashboard') · VOWSPACE</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-dvh bg-canvas" x-data="{ sidebar: false, userMenu: false }">
    <div class="lg:flex">
        <div x-show="sidebar" x-cloak class="fixed inset-0 z-30 bg-ink/30 backdrop-blur-sm lg:hidden" x-on:click="sidebar = false"></div>

        <aside
            class="fixed inset-y-0 left-0 z-40 flex w-72 -translate-x-full flex-col border-r border-line/80 bg-surface transition-transform duration-200 lg:translate-x-0"
            :class="sidebar ? 'translate-x-0' : '-translate-x-full lg:translate-x-0'"
        >
            <div class="flex h-20 items-center gap-3 px-6">
                <span class="flex h-10 w-10 items-center justify-center rounded-2xl bg-accent text-sm font-bold text-white shadow-raised">V</span>
                <div>
                    <p class="text-sm font-semibold tracking-[0.18em] text-ink">VOWSPACE</p>
                    <p class="text-[10px] font-medium uppercase tracking-[0.16em] text-faint">System Controller</p>
                </div>
                <button class="icon-btn ml-auto h-9 w-9 lg:hidden" x-on:click="sidebar = false" aria-label="Tutup menu">
                    <x-ui.icon name="close" class="h-4 w-4" />
                </button>
            </div>

            <nav class="flex-1 space-y-6 overflow-y-auto px-4 pb-6">
                <div class="space-y-1">
                    <p class="px-3 pb-1 text-[10px] font-semibold uppercase tracking-[0.18em] text-faint">Overview</p>
                    <a href="{{ route('admin.dashboard') }}" class="nav-item {{ request()->routeIs('admin.dashboard') ? 'nav-item-active' : '' }}">
                        <x-ui.icon name="grid" class="h-4 w-4" /> Dashboard
                    </a>
                </div>

                <div class="space-y-1">
                    <p class="px-3 pb-1 text-[10px] font-semibold uppercase tracking-[0.18em] text-faint">Wedding</p>
                    <a href="{{ route('admin.events.index') }}" class="nav-item {{ request()->routeIs('admin.events.*') ? 'nav-item-active' : '' }}">
                        <x-ui.icon name="calendar" class="h-4 w-4" /> Events
                    </a>
                    <a href="{{ route('admin.owners.index') }}" class="nav-item {{ request()->routeIs('admin.owners.*') ? 'nav-item-active' : '' }}">
                        <x-ui.icon name="user-check" class="h-4 w-4" /> Owners
                    </a>
                    <a href="{{ route('admin.categories.index') }}" class="nav-item {{ request()->routeIs('admin.categories.*') ? 'nav-item-active' : '' }}">
                        <x-ui.icon name="list" class="h-4 w-4" /> Categories
                    </a>
                    <a href="{{ route('admin.groups.index') }}" class="nav-item {{ request()->routeIs('admin.groups.*') ? 'nav-item-active' : '' }}">
                        <x-ui.icon name="users" class="h-4 w-4" /> Guest Groups
                    </a>
                    <a href="{{ route('admin.invitations.index') }}" class="nav-item {{ request()->routeIs('admin.invitations.*') ? 'nav-item-active' : '' }}">
                        <x-ui.icon name="mail" class="h-4 w-4" /> Invitations
                    </a>
                </div>

                <div class="space-y-1">
                    <p class="px-3 pb-1 text-[10px] font-semibold uppercase tracking-[0.18em] text-faint">Guests</p>
                    <a href="{{ route('admin.guests.index') }}" class="nav-item {{ request()->routeIs('admin.guests.*') ? 'nav-item-active' : '' }}">
                        <x-ui.icon name="users" class="h-4 w-4" /> Guest List
                    </a>
                    <a href="{{ route('admin.rsvps.index') }}" class="nav-item {{ request()->routeIs('admin.rsvps.*') ? 'nav-item-active' : '' }}">
                        <x-ui.icon name="clipboard" class="h-4 w-4" /> RSVP
                    </a>
                    <a href="{{ route('admin.checkins.scanner') }}" class="nav-item {{ request()->routeIs('admin.checkins.*') ? 'nav-item-active' : '' }}">
                        <x-ui.icon name="qr" class="h-4 w-4" /> Check-in & Scanner
                    </a>
                    <a href="{{ route('admin.seating.index') }}" class="nav-item {{ request()->routeIs('admin.seating.*') ? 'nav-item-active' : '' }}">
                        <x-ui.icon name="grid" class="h-4 w-4" /> Seating
                    </a>
                </div>

                <div class="space-y-1">
                    <p class="px-3 pb-1 text-[10px] font-semibold uppercase tracking-[0.18em] text-faint">Content</p>
                    <a href="{{ route('admin.guestbook.index') }}" class="nav-item {{ request()->routeIs('admin.guestbook.*') ? 'nav-item-active' : '' }}">
                        <x-ui.icon name="message" class="h-4 w-4" /> Guestbook
                    </a>
                    <a href="{{ route('admin.media.index') }}" class="nav-item {{ request()->routeIs('admin.media.*') ? 'nav-item-active' : '' }}">
                        <x-ui.icon name="image" class="h-4 w-4" /> Media Gallery
                    </a>
                </div>

                <div class="space-y-1">
                    <p class="px-3 pb-1 text-[10px] font-semibold uppercase tracking-[0.18em] text-faint">Interactions</p>
                    <a href="{{ route('admin.interactions.songs') }}" class="nav-item {{ request()->routeIs('admin.interactions.songs*') ? 'nav-item-active' : '' }}">
                        <x-ui.icon name="music" class="h-4 w-4" /> Song Requests
                    </a>
                    <a href="{{ route('admin.interactions.polls.index') }}" class="nav-item {{ request()->routeIs('admin.interactions.polls.*') ? 'nav-item-active' : '' }}">
                        <x-ui.icon name="chart" class="h-4 w-4" /> Wedding Polls
                    </a>
                    <a href="{{ route('admin.interactions.games.index') }}" class="nav-item {{ request()->routeIs('admin.interactions.games.*') ? 'nav-item-active' : '' }}">
                        <x-ui.icon name="sparkles" class="h-4 w-4" /> Mini Games
                    </a>
                </div>

                <div class="space-y-1">
                    <p class="px-3 pb-1 text-[10px] font-semibold uppercase tracking-[0.18em] text-faint">Insights</p>
                    <a href="{{ route('admin.reports.index') }}" class="nav-item {{ request()->routeIs('admin.reports.*') ? 'nav-item-active' : '' }}">
                        <x-ui.icon name="file-text" class="h-4 w-4" /> Reports
                    </a>
                    <a href="{{ route('admin.audit-logs.index') }}" class="nav-item {{ request()->routeIs('admin.audit-logs.*') ? 'nav-item-active' : '' }}">
                        <x-ui.icon name="shield" class="h-4 w-4" /> Audit Logs
                    </a>
                </div>

                <div class="space-y-1">
                    <p class="px-3 pb-1 text-[10px] font-semibold uppercase tracking-[0.18em] text-faint">System</p>
                    <a href="{{ route('admin.settings.index') }}" class="nav-item {{ request()->routeIs('admin.settings.*') ? 'nav-item-active' : '' }}">
                        <x-ui.icon name="settings" class="h-4 w-4" /> Settings
                    </a>
                </div>
            </nav>

            <div class="border-t border-line/70 p-4">
                <div class="flex items-center gap-3 rounded-2xl bg-surface-2/60 px-3 py-2.5">
                    <x-ui.avatar :name="auth()->user()->name" :src="auth()->user()->avatar_path ? Illuminate\Support\Facades\Storage::disk('public')->url(auth()->user()->avatar_path) : null" size="sm" />
                    <div class="min-w-0 flex-1">
                        <p class="truncate text-xs font-semibold text-ink">{{ auth()->user()->name }}</p>
                        <p class="truncate text-[10px] text-faint">{{ auth()->user()->email }}</p>
                    </div>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button class="text-faint transition hover:text-[#a35b64]" title="Keluar" aria-label="Keluar">
                            <x-ui.icon name="logout" class="h-4 w-4" />
                        </button>
                    </form>
                </div>
            </div>
        </aside>

        <div class="min-h-dvh flex-1 lg:pl-72">
            <header class="sticky top-0 z-20 border-b border-line/70 bg-canvas/85 backdrop-blur">
                <div class="flex h-16 items-center gap-3 px-4 sm:px-6 lg:px-8">
                    <button class="icon-btn h-10 w-10 lg:hidden" x-on:click="sidebar = true" aria-label="Buka menu">
                        <x-ui.icon name="menu" class="h-5 w-5" />
                    </button>

                    <div class="min-w-0">
                        <p class="truncate text-sm font-semibold text-ink">@yield('title', 'Dashboard')</p>
                        <p class="text-[11px] text-faint">@yield('subtitle', 'VOWSPACE administrative workspace')</p>
                    </div>

                    <div class="ml-auto flex items-center gap-2">
                        <span class="hidden rounded-full border border-line bg-surface px-3 py-1.5 text-[11px] font-medium text-muted sm:inline-flex">
                            {{ now()->translatedFormat('d M Y') }}
                        </span>

                        <div class="relative" x-data="{ open: false }">
                            <button class="flex items-center gap-2 rounded-2xl border border-line bg-surface px-2 py-1.5 shadow-raised" x-on:click="open = !open">
                                <x-ui.avatar :name="auth()->user()->name" :src="auth()->user()->avatar_path ? Illuminate\Support\Facades\Storage::disk('public')->url(auth()->user()->avatar_path) : null" size="sm" />
                                <x-ui.icon name="chevron-down" class="h-3.5 w-3.5 text-faint" />
                            </button>

                            <div x-show="open" x-cloak x-on:click.outside="open = false" class="absolute right-0 z-30 mt-2 w-48 rounded-2xl border border-line bg-surface p-2 shadow-soft">
                                <a href="{{ route('admin.profile.edit') }}" class="nav-item text-xs">
                                    <x-ui.icon name="user" class="h-4 w-4" /> Profil saya
                                </a>
                                <a href="{{ route('admin.settings.index') }}" class="nav-item text-xs">
                                    <x-ui.icon name="settings" class="h-4 w-4" /> Pengaturan
                                </a>
                                <form method="POST" action="{{ route('logout') }}">
                                    @csrf
                                    <button class="nav-item w-full text-xs text-[#a35b64]">
                                        <x-ui.icon name="logout" class="h-4 w-4" /> Keluar
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </header>

            <main class="mx-auto w-full max-w-[1500px] px-4 py-6 sm:px-6 lg:px-8">
                <x-ui.flash />
                @yield('content')
            </main>
        </div>
    </div>

    @stack('scripts')
</body>
</html>
