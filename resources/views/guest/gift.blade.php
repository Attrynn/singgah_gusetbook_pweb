@extends('layouts.guest')
@section('content')
<div class="px-5 pt-5 pb-12">
    <x-ui.page-header title="Hadiah Pernikahan" subtitle="Terima kasih atas perhatian Anda" />

    @if ($event->gift_enabled && $gifts->isNotEmpty())
        <div class="card p-6 mb-10">
            <p class="section-kicker mb-4">Terima Kasih</p>
            <p class="prose-wedding">Kami mengucapkan terima kasih atas perhatian Anda. Hadiah di bawah ini adalah bentuk apresiasi kami atas kehadiran Anda.</p>
        </div>

        @foreach ($gifts as $gift)
            <div class="card p-6 mb-10">
                <p class="section-kicker mb-4">{{ $gift->bank_name }}</p>
                <div class="space-y-4">
                    <div class="flex items-start gap-3">
                        <span class="flex shrink-0 h-10 w-10 items-center justify-center rounded-2xl bg-blue-soft text-[#4f7592]">
                            <x-ui.icon name="banknote" class="h-5 w-5" />
                        </span>
                        <div class="flex-1">
                            <p class="text-[10px] font-semibold text-faint uppercase">Nomor Rekening</p>
                            <p class="font-mono text-sm text-ink">{{ $gift->account_number }}</p>
                            <button type="button" class="btn-ghost btn-sm mt-2" data-copy="{{ $gift->account_number }}">
                                <x-ui.icon name="copy" class="h-4 w-4 mr-1" /> Salin
                            </button>
                        </div>
                    </div>
                    <div class="flex items-start gap-3">
                        <span class="flex shrink-0 h-10 w-10 items-center justify-center rounded-2xl bg-blue-soft text-[#4f7592]">
                            <x-ui.icon name="user" class="h-5 w-5" />
                        </span>
                        <div class="flex-1">
                            <p class="text-[10px] font-semibold text-faint uppercase">Pemilik Rekening</p>
                            <p class="text-sm text-ink">{{ $gift->account_owner }}</p>
                        </div>
                    </div>
                    @if ($gift->qrisUrl())
                    <div class="flex items-start gap-3">
                        <span class="flex shrink-0 h-10 w-10 items-center justify-center rounded-2xl bg-blue-soft text-[#4f7592]">
                            <x-ui.icon name="qr" class="h-5 w-5" />
                        </span>
                        <div class="flex-1">
                            <p class="text-[10px] font-semibold text-faint uppercase">QRIS</p>
                            <img src="{{ $gift->qrisUrl() }}" alt="QRIS" class="h-24 w-24 rounded-xl mt-2 cursor-pointer" onclick="this.classList.toggle('h-48','h-24')" />
                        </div>
                    </div>
                    @endif
                    @if ($gift->gift_address)
                    <div class="flex items-start gap-3">
                        <span class="flex shrink-0 h-10 w-10 items-center justify-center rounded-2xl bg-blue-soft text-[#4f7592]">
                            <x-ui.icon name="map-pin" class="h-5 w-5" />
                        </span>
                        <div class="flex-1">
                            <p class="text-[10px] font-semibold text-faint uppercase">Alamat Pengiriman</p>
                            <p class="text-sm text-ink">{{ $gift->gift_address }}</p>
                            <button type="button" class="btn-ghost btn-sm mt-2" data-copy="{{ $gift->gift_address }}">
                                <x-ui.icon name="copy" class="h-4 w-4 mr-1" /> Salin
                            </button>
                        </div>
                    </div>
                    @endif
                    @if ($gift->note)
                    <div class="flex items-start gap-3">
                        <span class="flex shrink-0 h-10 w-10 items-center justify-center rounded-2xl bg-blue-soft text-[#4f7592]">
                            <x-ui.icon name="file-text" class="h-5 w-5" />
                        </span>
                        <div class="flex-1">
                            <p class="text-[10px] font-semibold text-faint uppercase">Catatan</p>
                            <p class="text-sm text-ink">{{ $gift->note }}</p>
                        </div>
                    </div>
                    @endif
                </div>
            </div>
        @endforeach

        <div class="card p-6 mb-10">
            <p class="section-kicker mb-4">Etiket</p>
            <p class="prose-wedding">Jika Anda ingin memberikan hadiah, silakan transfer ke rekening di atas. Mohon cantumkan nama dan tanggal pernikahan Anda pada berita transfer agar kami mudah mencocokkannya.</p>
        </div>
    @else
        <x-ui.empty-state icon="gift" title="Tidak Ada Hadiah" description="Pasangan ini tidak memiliki rekening hadiah yang diaktifkan saat ini.">
            <a href="{{ route('guest.invitation', $token) }}" class="btn-primary mt-4">Kembali ke Undangan</a>
        </x-ui.empty-state>
    @endif

    <p class="text-center text-[11px] text-faint mt-10">Dibuat dengan tenang oleh <span class="font-semibold text-ink">VOWSPACE</span></p>
</div>
@endsection