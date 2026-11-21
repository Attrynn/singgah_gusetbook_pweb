@extends('layouts.auth')

@section('title', 'Masuk')

@section('content')
    <div class="card p-7 sm:p-10 border-line shadow-soft bg-surface">
        <div class="flex items-center gap-2">
            <span class="text-accent text-[10px]">✦</span>
            <p class="section-kicker">Welcome Back</p>
        </div>
        <h2 class="mt-2 font-serif text-3xl font-medium text-ink tracking-tight">Masuk ke Ruang Anda</h2>
        <p class="mt-2 text-xs leading-relaxed text-muted font-light">
            Portal autentikasi untuk pengantin dan superadmin VOWSPACE.
        </p>

        @if (session('status'))
            <div class="mt-5 rounded-lg border border-success/30 bg-success/10 px-4 py-3 text-xs text-success">
                {{ session('status') }}
            </div>
        @endif

        <form method="POST" action="{{ route('login.attempt') }}" class="mt-7 space-y-5">
            @csrf

            <x-ui.input
                name="email"
                label="Alamat Email"
                type="email"
                placeholder="nama@domain.com"
                required
                autocomplete="email"
                autofocus
            />

            <x-ui.input
                name="password"
                label="Kata Sandi"
                type="password"
                placeholder="••••••••"
                required
                autocomplete="current-password"
            />

            <div class="flex items-center justify-between">
                <label class="flex cursor-pointer items-center gap-2 text-xs text-muted">
                    <input
                        type="checkbox"
                        name="remember"
                        value="1"
                        class="h-3.5 w-3.5 rounded border-line bg-surface text-accent focus:ring-accent/30"
                    >
                    <span>Ingat saya di sesi ini</span>
                </label>
            </div>

            <button type="submit" class="btn-primary w-full !rounded-md py-3 text-sm shadow-raised justify-center">
                <span>Masuk ke Dashboard</span>
                <x-ui.icon name="arrow-up-right" class="h-4 w-4" />
            </button>
        </form>

        <div class="mt-7 flex items-start gap-3 rounded-lg border border-line bg-surface-2/40 p-3.5">
            <x-ui.icon name="shield" class="mt-0.5 h-4 w-4 shrink-0 text-accent" />
            <p class="text-[11px] leading-relaxed text-muted font-light">
                <strong>Catatan Tamu Undangan:</strong> Tamu tidak perlu login. Tautan undangan dan pass registrasi Anda dapat langsung diakses melalui tautan personal yang dibagikan oleh pengantin.
            </p>
        </div>
    </div>

    <p class="mt-6 text-center text-xs text-faint">
        <a href="{{ route('home') }}" class="text-accent hover:underline flex items-center justify-center gap-1.5 font-medium">
            <x-ui.icon name="arrow-left" class="h-3 w-3" />
            <span>Kembali ke Halaman Utama</span>
        </a>
    </p>
@endsection
