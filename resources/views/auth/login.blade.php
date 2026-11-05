@extends('layouts.auth')

@section('title', 'Masuk')

@section('content')
    <div class="card p-6 sm:p-8">
        <p class="section-kicker">Welcome back</p>
        <h2 class="mt-2 font-serif text-3xl text-ink">Masuk ke ruang Anda</h2>
        <p class="mt-2 text-sm leading-relaxed text-muted">
            Superadmin mengelola seluruh ekosistem. Owner melihat ruang pernikahan pribadinya.
        </p>

        @if (session('status'))
            <div class="mt-5 rounded-2xl border border-success/25 bg-success/10 px-4 py-3 text-sm text-[#3f8069]">
                {{ session('status') }}
            </div>
        @endif

        <form method="POST" action="{{ route('login.attempt') }}" class="mt-7 space-y-5">
            @csrf

            <x-ui.input
                name="email"
                label="Email"
                type="email"
                placeholder="nama@email.com"
                required
                autocomplete="email"
                autofocus
            />

            <x-ui.input
                name="password"
                label="Password"
                type="password"
                placeholder="••••••••"
                required
                autocomplete="current-password"
            />

            <label class="flex cursor-pointer items-center gap-2.5 text-sm text-muted">
                <input
                    type="checkbox"
                    name="remember"
                    value="1"
                    class="h-4 w-4 rounded border-line bg-surface-2 text-accent focus:ring-accent/30"
                >
                Ingat saya di perangkat ini
            </label>

            <x-ui.button type="submit" icon="arrow-up-right" class="w-full">
                Masuk
            </x-ui.button>
        </form>

        <div class="mt-7 flex items-start gap-3 rounded-2xl border border-line/70 bg-surface-2/50 px-4 py-3">
            <x-ui.icon name="shield" class="mt-0.5 h-4 w-4 shrink-0 text-[#5c86a8]" />
            <p class="text-xs leading-relaxed text-muted">
                Akses tamu tidak memerlukan login. Setiap tamu menerima tautan undangan personal dengan token unik.
            </p>
        </div>
    </div>

    <p class="mt-6 text-center text-xs text-faint">
        <a href="{{ route('home') }}" class="link">Kembali ke halaman utama</a>
    </p>
@endsection
