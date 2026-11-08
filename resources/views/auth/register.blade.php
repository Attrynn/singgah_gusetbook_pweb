@extends('layouts.auth')

@section('title', 'Daftar')

@section('content')
    <div class="card p-6 sm:p-8">
        <p class="section-kicker">Get started</p>
        <h2 class="mt-2 font-serif text-3xl text-ink">Buat ruang pernikahan Anda</h2>
        <p class="mt-2 text-sm leading-relaxed text-muted">
            Daftarkan akun dan workspace pertama Anda sekaligus. Anda akan menjadi Workspace Owner.
        </p>

        @if (session('status'))
            <div class="mt-5 rounded-2xl border border-success/25 bg-success/10 px-4 py-3 text-sm text-[#3f8069]">
                {{ session('status') }}
            </div>
        @endif

        <form method="POST" action="{{ route('register.store') }}" class="mt-7 space-y-5">
            @csrf

            <x-ui.input
                name="name"
                label="Nama lengkap"
                type="text"
                placeholder="Nama Anda"
                required
                autocomplete="name"
                autofocus
            />

            <x-ui.input
                name="email"
                label="Email"
                type="email"
                placeholder="nama@email.com"
                required
                autocomplete="email"
            />

            <x-ui.input
                name="password"
                label="Password"
                type="password"
                placeholder="••••••••"
                required
                autocomplete="new-password"
            />

            <x-ui.input
                name="password_confirmation"
                label="Konfirmasi password"
                type="password"
                placeholder="••••••••"
                required
                autocomplete="new-password"
            />

            <x-ui.input
                name="workspace_name"
                label="Nama workspace"
                type="text"
                placeholder="Contoh: Pernikahan Andi & Sari"
                required
                autocomplete="organization"
            />

            <x-ui.button type="submit" icon="arrow-up-right" class="w-full">
                Daftar & buat workspace
            </x-ui.button>
        </form>

        <div class="mt-7 flex items-start gap-3 rounded-2xl border border-line/70 bg-surface-2/50 px-4 py-3">
            <x-ui.icon name="shield" class="mt-0.5 h-4 w-4 shrink-0 text-[#5c86a8]" />
            <p class="text-xs leading-relaxed text-muted">
                Dengan mendaftar, Anda membuat akun baru dan organization pertama Anda secara atomik.
            </p>
        </div>
    </div>

    <p class="mt-6 text-center text-xs text-faint">
        Sudah punya akun? <a href="{{ route('login') }}" class="link">Masuk di sini</a>
    </p>
@endsection
