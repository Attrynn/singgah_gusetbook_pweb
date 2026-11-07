@extends('layouts.guest')
@section('content')
<div class="px-4 pt-6 pb-12 sm:px-6 lg:px-8">
    <div class="mx-auto max-w-3xl">
        <x-ui.page-header title="Hadiah Pernikahan" subtitle="Terima kasih atas perhatian Anda" />

        @if ($event->gift_enabled && $gifts->isNotEmpty())
            <div class="mt-6 card p-6 sm:p-8">
                <h2 class="section-kicker mb-3">Terima Kasih</h2>
                <p class="max-w-prose text-sm leading-relaxed text-muted">
                    Kami mengucapkan terima kasih atas perhatian Anda. Hadiah di bawah ini adalah bentuk apresiasi kami atas kehadiran Anda.
                </p>
            </div>

            @foreach ($gifts as $gift)
                <div class="card mt-8 p-6 sm:p-8">
                    <h2 class="section-kicker mb-5">{{ $gift->bank_name }}</h2>
                    <div class="space-y-5">
                        <div class="flex items-start gap-3">
                            <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-2xl bg-blue-soft text-ink">
                                <x-ui.icon name="banknote" class="h-5 w-5" />
                            </span>
                            <div class="flex-1">
                                <p class="guest-eyebrow">Nomor rekening</p>
                                <p class="font-mono text-sm text-ink">{{ $gift->account_number }}</p>
                                <button type="button" class="btn-ghost btn-sm mt-2" data-copy="{{ $gift->account_number }}">
                                    <x-ui.icon name="copy" class="h-4 w-4" /> Salin nomor
                                </button>
                            </div>
                        </div>
                        <div class="flex items-start gap-3">
                            <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-2xl bg-blue-soft text-ink">
                                <x-ui.icon name="user" class="h-5 w-5" />
                            </span>
                            <div class="flex-1">
                                <p class="guest-eyebrow">Pemilik rekening</p>
                                <p class="text-sm text-ink">{{ $gift->account_owner }}</p>
                            </div>
                        </div>
                        @if ($gift->qrisUrl())
                            <div class="flex items-start gap-3">
                                <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-2xl bg-blue-soft text-ink">
                                    <x-ui.icon name="qr" class="h-5 w-5" />
                                </span>
                                <div class="flex-1">
                                    <p class="guest-eyebrow">QRIS</p>
                                    <img src="{{ $gift->qrisUrl() }}" alt="QRIS {{ $gift->bank_name }}"
                                         class="mt-2 h-24 w-24 cursor-pointer rounded-xl" onclick="this.classList.toggle('h-48','h-24')">
                                    <p class="hint">Ketuk untuk memperbesar.</p>
                                </div>
                            </div>
                        @endif
                        @if ($gift->gift_address)
                            <div class="flex items-start gap-3">
                                <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-2xl bg-blue-soft text-ink">
                                    <x-ui.icon name="map-pin" class="h-5 w-5" />
                                </span>
                                <div class="flex-1">
                                    <p class="guest-eyebrow">Alamat pengiriman</p>
                                    <p class="text-sm text-ink">{{ $gift->gift_address }}</p>
                                    <button type="button" class="btn-ghost btn-sm mt-2" data-copy="{{ $gift->gift_address }}">
                                        <x-ui.icon name="copy" class="h-4 w-4" /> Salin alamat
                                    </button>
                                </div>
                            </div>
                        @endif
                        @if ($gift->note)
                            <div class="flex items-start gap-3">
                                <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-2xl bg-blue-soft text-ink">
                                    <x-ui.icon name="file-text" class="h-5 w-5" />
                                </span>
                                <div class="flex-1">
                                    <p class="guest-eyebrow">Catatan</p>
                                    <p class="text-sm text-ink">{{ $gift->note }}</p>
                                </div>
                            </div>
                        @endif
                    </div>
                </div>
            @endforeach

            <div class="card mt-8 p-6 sm:p-8">
                <h2 class="section-kicker mb-3">Etiket</h2>
                <p class="max-w-prose text-sm leading-relaxed text-muted">
                    Jika Anda ingin memberikan hadiah, silakan transfer ke rekening di atas. Mohon cantumkan nama dan tanggal pernikahan Anda pada berita transfer agar kami mudah mencocokkannya.
                </p>
            </div>
        @else
            <div class="mt-6">
                <x-ui.empty-state icon="gift" title="Tidak Ada Hadiah" description="Pasangan ini tidak memiliki rekening hadiah yang diaktifkan saat ini.">
                    <a href="{{ route('guest.invitation', $token) }}" class="btn-primary mt-4">Kembali ke undangan</a>
                </x-ui.empty-state>
            </div>
        @endif

        <p class="mt-10 text-center text-[11px] text-faint">
            Dibuat dengan tenang oleh <span class="font-semibold text-ink">VOWSPACE</span>
        </p>
    </div>
</div>
@endsection
