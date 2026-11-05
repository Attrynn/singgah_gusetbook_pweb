@extends('layouts.owner')

@section('title', 'Profil')

@section('content')
    <x-ui.page-header title="Profil" subtitle="Kelola identitas dan pengaturan akun Anda." />

    {{-- Profile Form --}}
    <form method="POST" action="{{ route('owner.profile.update') }}" enctype="multipart/form-data" class="card p-5 mb-5">
        <div class="flex items-center gap-3 mb-4">
            @if ($user->avatar_path)
                <img src="{{ $user->avatar_path ? Illuminate\Support\Facades\Storage::disk('public')->url($user->avatar_path) : null }}" alt="Avatar" class="h-16 w-16 rounded-2xl object-cover" />
            @else
                <span class="flex h-16 w-16 items-center justify-center rounded-2xl bg-blue-soft text-[#4f7592] text-lg font-semibold">
                    {{ mb_strtoupper(mb_substr($user->name ?? '', 0, 1)) }}
                </span>
            @endif
            <div class="min-w-0">
                <p class="font-semibold text-sm text-ink">{{ $user->name }}</p>
                <p class="text-[10px] text-muted">{{ $user->email }}</p>
            </div>
            <label for="avatar" class="btn btn-soft btn-sm ml-auto shrink-0 cursor-pointer">
                <x-ui.icon name="camera" class="h-4 w-4" />
                Ganti avatar
            </label>
            <input type="file" id="avatar" name="avatar" accept="image/*" class="hidden" />
        </div>

        <x-ui.input name="name" label="Nama lengkap" required placeholder="Contoh: Budi Santoso" />
        <x-ui.input name="email" label="Email" required placeholder="you@example.com" />
        <x-ui.input name="phone" label="No telepon" placeholder="+62 812-345-6789" />

        <div class="pt-3 mt-3 border-t border-line/60">
            <button type="submit" class="btn btn-primary w-full">Simpan perubahan</button>
        </div>
    </form>

    {{-- Password Form --}}
    <form method="POST" action="{{ route('owner.profile.password') }}" class="card p-5 mb-5">
        <p class="section-kicker">Ubah kata sandi</p>
        <div class="mt-3 space-y-4">
            <x-ui.input name="current_password" label="Kata sandi sekarang" type="password" required hint="Digunakan untuk memverifikasi identitas saat login." />
            <x-ui.input name="password" label="Kata sandi baru" type="password" required min="8" hint="Minimal 8 karakter." />
            <x-ui.input name="password_confirmation" label="Konfirmasi kata sandi" type="password" required hint="Masukkan lagi kata sandi baru." />
            <div class="pt-3">
                <button type="submit" class="btn btn-primary w-full">Perbarui kata sandi</button>
            </div>
        </div>
    </form>

    {{-- Connected Events --}}
    <div class="card p-5 mb-5">
        <p class="section-kicker">Acara yang terhubung</p>
        @if ($events->isNotEmpty())
            <div class="mt-4 space-y-3">
                @foreach ($events as $event)
                    <a href="{{ route('owner.dashboard') }}" class="block p-4 rounded-2xl bg-surface-2/60 hover:bg-surface-2 transition">
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <p class="font-serif text-base font-semibold text-ink truncate">{{ $event->coupleNames() }}</p>
                                <p class="text-[10px] text-muted">{{ $event->event_date?->translatedFormat('d M Y') }}</p>
                            </div>
                            <x-ui.badge :variant="$event->status->badge()" class="text-[10px] shrink-0">{{ $event->statusLabel() }}</x-ui.badge>
                        </div>
                    </a>
                @endforeach
            </div>
        @else
            <x-ui.empty-state icon="archive" title="Belum terhubung ke acara" description="Hubungkan akun Anda dengan acara pernikahan agar dapat diakses di ruang ini." />
        @endif
    </div>

    {{-- Logout --}}
    <form method="POST" action="{{ route('logout') }}" class="card p-5">
        @csrf
        <div class="text-center py-4">
            <p class="text-sm font-semibold text-ink">Keluar dari VOWSPACE</p>
            <p class="mt-1 text-[10px] text-muted">Anda akan kembali ke halaman login.</p>
            <button type="submit" class="btn btn-danger btn-sm mt-4 w-full">Keluar</button>
        </div>
    </form>
@endsection