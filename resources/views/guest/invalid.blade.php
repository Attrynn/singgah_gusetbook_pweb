<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <title>Undangan Tidak Valid · VOWSPACE</title>
    <script>
        (function() {
            const theme = localStorage.getItem('vowspace_theme') || 'system';
            const isDark = theme === 'dark' || (theme === 'system' && window.matchMedia('(prefers-color-scheme: dark)').matches);
            if (isDark) { document.documentElement.classList.add('dark'); }
        })();
    </script>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-dvh bg-surface-2 text-ink antialiased">
    <main class="mobile-shell flex min-h-dvh flex-col items-center justify-center px-6 py-12 text-center">
        <span class="flex h-16 w-16 items-center justify-center rounded-3xl bg-blue-soft text-ink shadow-raised">
            <x-ui.icon name="lock" class="h-7 w-7" />
        </span>

        <h1 class="mt-6 font-serif text-2xl text-ink">Undangan tidak dapat dibuka</h1>
        <p class="mt-3 max-w-xs text-sm leading-7 text-muted">
            {{ $reason ?? 'Tautan undangan tidak valid, sudah kedaluwarsa, atau telah dicabut.' }}
        </p>

        <div class="mt-7 w-full max-w-xs space-y-2.5">
            <x-ui.button href="{{ route('home') }}" variant="secondary" icon="home" class="w-full">
                Kembali ke beranda
            </x-ui.button>
        </div>

        <p class="mt-8 text-[11px] leading-relaxed text-faint">
            Jika Anda yakin undangan ini benar, hubungi pasangan atau admin yang mengirimkannya untuk meminta tautan baru.
        </p>
    </main>
</body>
</html>
