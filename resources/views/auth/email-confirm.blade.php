@extends('layouts.auth')

@section('title', 'Konfirmasi perubahan email')

@section('content')
    <div class="card p-6 sm:p-8">
        <p class="section-kicker">Verifikasi email</p>
        <h2 class="mt-2 font-serif text-3xl text-ink">Konfirmasi perubahan email</h2>

        <p class="mt-3 text-sm leading-relaxed text-muted">
            Anda meminta perubahan email login dari
            <span class="font-semibold text-ink">{{ $currentEmail }}</span>
            menjadi
            <span class="font-semibold text-ink">{{ $pendingEmail }}</span>.
        </p>

        <div class="mt-5 rounded-2xl border border-line/70 bg-surface-2/50 px-4 py-3">
            <p class="text-xs leading-relaxed text-muted">
                Email login Anda <strong>belum berubah</strong>. Perubahan hanya diterapkan setelah Anda menekan
                tombol konfirmasi di bawah. Tautan ini berlaku sekali dan kedaluwarsa pada
                {{ $expiresAt?->translatedFormat('d M Y H:i') ?? 'waktu yang telah ditentukan' }}.
            </p>
        </div>

        <form method="POST" action="{{ route('email.change.store', ['token' => $token]) }}" class="mt-7 space-y-4">
            @csrf

            <x-ui.button type="submit" icon="check" class="w-full">
                Konfirmasi perubahan email
            </x-ui.button>
        </form>

        <p class="mt-5 text-center text-xs text-faint">
            Jika Anda tidak meminta perubahan ini, tutup halaman ini. Email login Anda tetap sama.
        </p>
    </div>

    <p class="mt-6 text-center text-xs text-faint">
        <a href="{{ route('login') }}" class="link">Kembali ke halaman masuk</a>
    </p>
@endsection
