<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Admin Dashboard') · VOWSPACE</title>
    <script nonce="{{ request()->attributes->get('csp_nonce') }}">
        (function() {
            const theme = localStorage.getItem('vowspace_theme') || 'system';
            const isDark = theme === 'dark' || (theme === 'system' && window.matchMedia('(prefers-color-scheme: dark)').matches);
            if (isDark) { document.documentElement.classList.add('dark'); }
        })();
    </script>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-dvh bg-canvas text-ink antialiased" x-data="{ sidebar: false, sidebarCollapsed: localStorage.getItem('vowspace_admin_collapsed') === 'true' }">
    <div class="lg:flex">
        <!-- Mobile Drawer Backdrop -->
        <div x-show="sidebar" x-cloak class="fixed inset-0 z-40 bg-ink/40 backdrop-blur-sm lg:hidden" @click="sidebar = false"></div>

        <!-- Sidebar Navigation -->
        <aside
            class="fixed inset-y-0 left-0 z-50 flex flex-col border-r border-line/80 bg-surface transition-all duration-200 lg:z-30"
            :class="[
                sidebar ? 'translate-x-0' : '-translate-x-full lg:translate-x-0',
                sidebarCollapsed ? 'lg:w-20' : 'lg:w-72',
                'w-72'
            ]"
        >
            <!-- Brand / Header -->
            <div class="flex h-16 items-center justify-between border-b border-line/70 px-4 md:px-5">
                <a href="{{ route('admin.dashboard') }}" class="flex items-center gap-3 min-w-0">
                    <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-2xl bg-accent text-sm font-bold text-[var(--color-accent-text)] shadow-raised">V</span>
                    <div x-show="!sidebarCollapsed" class="min-w-0 transition-opacity duration-150">
                        <p class="text-sm font-bold tracking-[0.16em] text-ink">VOWSPACE</p>
                        <p class="text-[9px] font-semibold uppercase tracking-[0.18em] text-accent">Superadmin</p>
                    </div>
                </a>

                <div class="flex items-center gap-1">
                    <button type="button"
                            @click="sidebarCollapsed = !sidebarCollapsed; localStorage.setItem('vowspace_admin_collapsed', sidebarCollapsed)"
                            class="icon-btn hidden h-8 w-8 !rounded-xl lg:inline-flex"
                            :title="sidebarCollapsed ? 'Perlebar Sidebar' : 'Ciutkan Sidebar'">
                        <x-ui.icon name="menu" class="h-4 w-4" />
                    </button>
                    <button type="button"
                            class="icon-btn h-8 w-8 !rounded-xl lg:hidden"
                            @click="sidebar = false"
                            aria-label="Tutup menu">
                        <x-ui.icon name="close" class="h-4 w-4" />
                    </button>
                </div>
            </div>

            <!-- Navigation Links -->
            <nav class="flex-1 space-y-5 overflow-y-auto px-3 py-4 text-xs">
                <!-- Overview -->
                <div class="space-y-1">
                    <p x-show="!sidebarCollapsed" class="px-3 pb-1 text-[10px] font-bold uppercase tracking-[0.18em] text-faint">Overview</p>
                    <a href="{{ route('admin.dashboard') }}" class="nav-item {{ request()->routeIs('admin.dashboard') ? 'nav-item-active' : '' }}" title="Dashboard">
                        <x-ui.icon name="grid" class="h-4 w-4 shrink-0 text-accent" />
                        <span x-show="!sidebarCollapsed" class="truncate">Dashboard</span>
                    </a>
                </div>

                <!-- Event Management -->
                <div class="space-y-1">
                    <p x-show="!sidebarCollapsed" class="px-3 pb-1 text-[10px] font-bold uppercase tracking-[0.18em] text-faint">Event Management</p>
                    <a href="{{ route('admin.events.index') }}" class="nav-item {{ request()->routeIs('admin.events.*') ? 'nav-item-active' : '' }}" title="Events">
                        <x-ui.icon name="calendar" class="h-4 w-4 shrink-0" />
                        <span x-show="!sidebarCollapsed" class="truncate">All Events</span>
                    </a>
                    <a href="{{ route('admin.invitations.index') }}" class="nav-item {{ request()->routeIs('admin.invitations.*') ? 'nav-item-active' : '' }}" title="Invitations">
                        <x-ui.icon name="mail" class="h-4 w-4 shrink-0" />
                        <span x-show="!sidebarCollapsed" class="truncate">Invitations</span>
                    </a>
                </div>

                <!-- People & Access -->
                <div class="space-y-1">
                    <p x-show="!sidebarCollapsed" class="px-3 pb-1 text-[10px] font-bold uppercase tracking-[0.18em] text-faint">People & Access</p>
                    <a href="{{ route('admin.owners.index') }}" class="nav-item {{ request()->routeIs('admin.owners.*') ? 'nav-item-active' : '' }}" title="Owners">
                        <x-ui.icon name="user-check" class="h-4 w-4 shrink-0" />
                        <span x-show="!sidebarCollapsed" class="truncate">Owners</span>
                    </a>
                    <a href="{{ route('admin.categories.index') }}" class="nav-item {{ request()->routeIs('admin.categories.*') ? 'nav-item-active' : '' }}" title="Categories">
                        <x-ui.icon name="list" class="h-4 w-4 shrink-0" />
                        <span x-show="!sidebarCollapsed" class="truncate">Guest Categories</span>
                    </a>
                    <a href="{{ route('admin.groups.index') }}" class="nav-item {{ request()->routeIs('admin.groups.*') ? 'nav-item-active' : '' }}" title="Guest Groups">
                        <x-ui.icon name="users" class="h-4 w-4 shrink-0" />
                        <span x-show="!sidebarCollapsed" class="truncate">Guest Groups</span>
                    </a>
                </div>

                <!-- Guest Operations -->
                <div class="space-y-1">
                    <p x-show="!sidebarCollapsed" class="px-3 pb-1 text-[10px] font-bold uppercase tracking-[0.18em] text-faint">Guest Operations</p>
                    <a href="{{ route('admin.guests.index') }}" class="nav-item {{ request()->routeIs('admin.guests.*') ? 'nav-item-active' : '' }}" title="Guests">
                        <x-ui.icon name="users" class="h-4 w-4 shrink-0" />
                        <span x-show="!sidebarCollapsed" class="truncate">Guest List</span>
                    </a>
                    <a href="{{ route('admin.rsvps.index') }}" class="nav-item {{ request()->routeIs('admin.rsvps.*') ? 'nav-item-active' : '' }}" title="RSVP">
                        <x-ui.icon name="clipboard" class="h-4 w-4 shrink-0" />
                        <span x-show="!sidebarCollapsed" class="truncate">RSVP</span>
                    </a>
                    <a href="{{ route('admin.checkins.scanner') }}" class="nav-item {{ request()->routeIs('admin.checkins.*') ? 'nav-item-active' : '' }}" title="Check-in">
                        <x-ui.icon name="qr" class="h-4 w-4 shrink-0" />
                        <span x-show="!sidebarCollapsed" class="truncate">Check-in & Scanner</span>
                    </a>
                    <a href="{{ route('admin.seating.index') }}" class="nav-item {{ request()->routeIs('admin.seating.*') ? 'nav-item-active' : '' }}" title="Seating">
                        <x-ui.icon name="grid" class="h-4 w-4 shrink-0" />
                        <span x-show="!sidebarCollapsed" class="truncate">Seating</span>
                    </a>
                </div>

                <!-- Content & Experience -->
                <div class="space-y-1">
                    <p x-show="!sidebarCollapsed" class="px-3 pb-1 text-[10px] font-bold uppercase tracking-[0.18em] text-faint">Content & Experience</p>
                    <a href="{{ route('admin.media.index') }}" class="nav-item {{ request()->routeIs('admin.media.*') ? 'nav-item-active' : '' }}" title="Media">
                        <x-ui.icon name="image" class="h-4 w-4 shrink-0" />
                        <span x-show="!sidebarCollapsed" class="truncate">Media Gallery</span>
                    </a>
                    <a href="{{ route('admin.guestbook.index') }}" class="nav-item {{ request()->routeIs('admin.guestbook.*') ? 'nav-item-active' : '' }}" title="Guestbook">
                        <x-ui.icon name="message" class="h-4 w-4 shrink-0" />
                        <span x-show="!sidebarCollapsed" class="truncate">Guestbook Wishes</span>
                    </a>
                    <a href="{{ route('admin.interactions.polls.index') }}" class="nav-item {{ request()->routeIs('admin.interactions.polls.*') ? 'nav-item-active' : '' }}" title="Polls">
                        <x-ui.icon name="chart" class="h-4 w-4 shrink-0" />
                        <span x-show="!sidebarCollapsed" class="truncate">Wedding Polls</span>
                    </a>
                    <a href="{{ route('admin.interactions.games.index') }}" class="nav-item {{ request()->routeIs('admin.interactions.games.*') ? 'nav-item-active' : '' }}" title="Games">
                        <x-ui.icon name="sparkles" class="h-4 w-4 shrink-0" />
                        <span x-show="!sidebarCollapsed" class="truncate">Mini Games</span>
                    </a>
                    <a href="{{ route('admin.interactions.songs') }}" class="nav-item {{ request()->routeIs('admin.interactions.songs*') ? 'nav-item-active' : '' }}" title="Song Requests">
                        <x-ui.icon name="music" class="h-4 w-4 shrink-0" />
                        <span x-show="!sidebarCollapsed" class="truncate">Song Requests</span>
                    </a>
                </div>

                <!-- Analytics -->
                <div class="space-y-1">
                    <p x-show="!sidebarCollapsed" class="px-3 pb-1 text-[10px] font-bold uppercase tracking-[0.18em] text-faint">Analytics</p>
                    <a href="{{ route('admin.reports.index') }}" class="nav-item {{ request()->routeIs('admin.reports.*') ? 'nav-item-active' : '' }}" title="Reports">
                        <x-ui.icon name="file-text" class="h-4 w-4 shrink-0" />
                        <span x-show="!sidebarCollapsed" class="truncate">System Reports</span>
                    </a>
                </div>

                <!-- System -->
                <div class="space-y-1">
                    <p x-show="!sidebarCollapsed" class="px-3 pb-1 text-[10px] font-bold uppercase tracking-[0.18em] text-faint">System</p>
                    <a href="{{ route('admin.settings.index') }}" class="nav-item {{ request()->routeIs('admin.settings.*') ? 'nav-item-active' : '' }}" title="Settings">
                        <x-ui.icon name="settings" class="h-4 w-4 shrink-0" />
                        <span x-show="!sidebarCollapsed" class="truncate">Settings</span>
                    </a>
                    <a href="{{ route('admin.audit-logs.index') }}" class="nav-item {{ request()->routeIs('admin.audit-logs.*') ? 'nav-item-active' : '' }}" title="Audit Logs">
                        <x-ui.icon name="shield" class="h-4 w-4 shrink-0" />
                        <span x-show="!sidebarCollapsed" class="truncate">Audit Logs</span>
                    </a>
                </div>
            </nav>

            <!-- User footer -->
            <div class="border-t border-line/70 p-3">
                <div class="flex items-center gap-2.5 rounded-xl bg-surface-2/60 p-2">
                    <x-ui.avatar :name="auth()->user()->name" :src="auth()->user()->avatar_path ? Illuminate\Support\Facades\Storage::disk('public')->url(auth()->user()->avatar_path) : null" size="sm" />
                    <div x-show="!sidebarCollapsed" class="min-w-0 flex-1">
                        <p class="truncate text-xs font-semibold text-ink">{{ auth()->user()->name }}</p>
                        <p class="truncate text-[10px] text-faint">{{ auth()->user()->email }}</p>
                    </div>
                    <form method="POST" action="{{ route('logout') }}" x-show="!sidebarCollapsed">
                        @csrf
                        <button class="icon-btn h-8 w-8 !rounded-lg text-faint transition hover:text-danger hover:border-danger/30" title="Keluar">
                            <x-ui.icon name="logout" class="h-3.5 w-3.5" />
                        </button>
                    </form>
                </div>
            </div>
        </aside>

        <!-- Main Content Area -->
        <div class="min-h-dvh flex-1 transition-all duration-200" :class="sidebarCollapsed ? 'lg:pl-20' : 'lg:pl-72'">
            <!-- Topbar -->
            <header class="sticky top-0 z-20 border-b border-line/70 bg-surface/90 backdrop-blur">
                <div class="flex h-16 items-center justify-between gap-3 px-4 sm:px-6 lg:px-8">
                    <div class="flex items-center gap-3 min-w-0">
                        <button type="button" class="icon-btn h-9 w-9 lg:hidden" @click="sidebar = true" aria-label="Buka menu">
                            <x-ui.icon name="menu" class="h-4 w-4" />
                        </button>
                        <div class="min-w-0">
                            <div class="flex items-center gap-2 text-xs text-faint">
                                <span>Superadmin</span>
                                <span>/</span>
                                <span class="font-semibold text-ink truncate">@yield('title', 'Dashboard')</span>
                            </div>
                        </div>
                    </div>

                    <div class="flex items-center gap-2.5">
                        <!-- Theme Switcher -->
                        <x-ui.theme-switcher size="md" />

                        <!-- User Profile Dropdown -->
                        <div class="relative" x-data="{ open: false }">
                            <button type="button" class="flex items-center gap-2 rounded-xl border border-line bg-surface p-1.5 shadow-raised transition hover:border-accent/40" @click="open = !open">
                                <x-ui.avatar :name="auth()->user()->name" :src="auth()->user()->avatar_path ? Illuminate\Support\Facades\Storage::disk('public')->url(auth()->user()->avatar_path) : null" size="sm" />
                                <span class="hidden text-xs font-semibold text-ink sm:inline-block max-w-[120px] truncate">{{ auth()->user()->name }}</span>
                                <x-ui.icon name="chevron-down" class="h-3.5 w-3.5 text-faint" />
                            </button>

                            <div x-show="open" x-cloak @click.outside="open = false" class="absolute right-0 z-30 mt-2 w-52 rounded-2xl border border-line bg-surface p-2 shadow-soft">
                                <div class="px-3 py-2 border-b border-line/60 mb-1">
                                    <p class="text-xs font-semibold text-ink truncate">{{ auth()->user()->name }}</p>
                                    <p class="text-[10px] text-faint truncate">{{ auth()->user()->email }}</p>
                                </div>
                                <a href="{{ route('admin.profile.edit') }}" class="nav-item text-xs">
                                    <x-ui.icon name="user" class="h-3.5 w-3.5 text-accent" /> Profil Saya
                                </a>
                                <a href="{{ route('admin.settings.index') }}" class="nav-item text-xs">
                                    <x-ui.icon name="settings" class="h-3.5 w-3.5 text-muted" /> Pengaturan Sistem
                                </a>
                                <form method="POST" action="{{ route('logout') }}">
                                    @csrf
                                    <button class="nav-item w-full text-xs text-danger hover:bg-danger/10">
                                        <x-ui.icon name="logout" class="h-3.5 w-3.5" /> Keluar
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </header>

            <!-- Main Canvas -->
            <main class="mx-auto w-full max-w-7xl px-4 py-6 sm:px-6 lg:px-8">
                <x-ui.flash />
                @yield('content')
            </main>
        </div>
    </div>

    @stack('scripts')
</body>
</html>
