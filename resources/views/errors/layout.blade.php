<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title') · VOWSPACE</title>
    <script>
        (function() {
            const theme = localStorage.getItem('vowspace_theme') || 'system';
            const isDark = theme === 'dark' || (theme === 'system' && window.matchMedia('(prefers-color-scheme: dark)').matches);
            if (isDark) { document.documentElement.classList.add('dark'); }
        })();
    </script>
    @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
        @vite(['resources/css/app.css'])
    @endif
</head>
<body class="min-h-dvh bg-canvas text-ink antialiased">
    <main class="flex min-h-dvh flex-col items-center justify-center px-6 py-16 text-center">
        <p class="text-[11px] font-semibold uppercase tracking-[0.2em] text-accent">VOWSPACE</p>
        <p class="mt-3 font-serif text-6xl font-bold leading-none text-ink">@yield('code')</p>
        <h1 class="mt-4 font-serif text-2xl text-ink">@yield('heading')</h1>
        <p class="mt-3 max-w-md text-sm leading-7 text-muted">@yield('message')</p>
        <a href="{{ route('home') }}"
           class="mt-8 inline-flex min-h-10 items-center justify-center gap-2 rounded-xl border border-line bg-surface px-4 py-2 text-sm font-semibold text-ink shadow-raised transition hover:bg-surface-2/60 focus:outline-none focus-visible:ring-2 focus-visible:ring-accent/70 focus-visible:ring-offset-2 focus-visible:ring-offset-canvas">
            Kembali ke beranda
        </a>
    </main>
</body>
</html>
