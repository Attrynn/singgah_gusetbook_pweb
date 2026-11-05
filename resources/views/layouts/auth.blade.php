<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Masuk') · VOWSPACE</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-dvh bg-canvas">
    <div class="grid min-h-dvh lg:grid-cols-2">
        <section class="relative hidden overflow-hidden border-r border-line/70 bg-surface lg:flex lg:flex-col lg:justify-between lg:p-12">
            <div class="absolute -right-24 top-24 h-72 w-72 rounded-full bg-blue-soft/70 blur-3xl"></div>
            <div class="absolute -left-20 bottom-10 h-64 w-64 rounded-full bg-teal-soft/70 blur-3xl"></div>

            <div class="relative flex items-center gap-3">
                <span class="flex h-11 w-11 items-center justify-center rounded-2xl bg-accent text-sm font-bold text-white shadow-raised">V</span>
                <div>
                    <p class="text-sm font-semibold tracking-[0.2em] text-ink">VOWSPACE</p>
                    <p class="text-[10px] uppercase tracking-[0.16em] text-faint">Wedding Experience Ecosystem</p>
                </div>
            </div>

            <div class="relative max-w-md">
                <p class="section-kicker">Beyond the Guestbook</p>
                <h1 class="mt-3 font-serif text-4xl leading-tight text-ink">One wedding. One ecosystem. Every guest. Every memory.</h1>
                <p class="mt-5 text-sm leading-7 text-muted">
                    Kelola undangan, RSVP, QR check-in, ucapan, galeri, interaksi, dan arsip pernikahan dalam satu ruang digital yang tenang dan terintegrasi.
                </p>

                <div class="mt-8 grid grid-cols-2 gap-3">
                    @foreach ([
                        ['icon' => 'mail', 'label' => 'Personal invitation'],
                        ['icon' => 'qr', 'label' => 'QR guest check-in'],
                        ['icon' => 'heart', 'label' => 'Love wall & wishes'],
                        ['icon' => 'archive', 'label' => 'Digital wedding archive'],
                    ] as $feature)
                        <div class="flex items-center gap-2.5 rounded-2xl border border-line/80 bg-canvas/70 px-3 py-3">
                            <x-ui.icon :name="$feature['icon']" class="h-4 w-4 text-[#5c86a8]" />
                            <span class="text-xs font-medium text-muted">{{ $feature['label'] }}</span>
                        </div>
                    @endforeach
                </div>
            </div>

            <p class="relative text-[11px] text-faint">© {{ date('Y') }} VOWSPACE · Cold Minimalist Soft Neumorphism</p>
        </section>

        <section class="flex min-h-dvh items-center justify-center px-5 py-10 sm:px-10">
            <div class="w-full max-w-md">
                <div class="mb-8 flex items-center gap-3 lg:hidden">
                    <span class="flex h-10 w-10 items-center justify-center rounded-2xl bg-accent text-sm font-bold text-white shadow-raised">V</span>
                    <p class="text-sm font-semibold tracking-[0.2em] text-ink">VOWSPACE</p>
                </div>

                @yield('content')
            </div>
        </section>
    </div>
</body>
</html>
