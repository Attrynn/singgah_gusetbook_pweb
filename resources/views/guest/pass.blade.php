@extends('layouts.guest')
@section('content')
<div class="px-5 pt-5 pb-12">
    <div class="card p-6 mb-10">
        <p class="section-kicker mb-4">VOWSPACE PASS</p>
        <h1 class="font-serif text-2xl font-semibold text-ink">{{ $event->groom_name }} & {{ $event->bride_name }}</h1>
        <p class="mt-1 text-sm text-muted">Undangan Pernikahan</p>
    </div>

    <div class="card p-6 mb-10">
        <p class="section-kicker mb-4">Detail Tamu</p>
        <div class="space-y-4">
            <div class="flex items-start gap-3">
                <span class="flex shrink-0 h-10 w-10 items-center justify-center rounded-2xl bg-blue-soft text-[#4f7592]">
                    <x-ui.icon name="user" class="h-5 w-5" />
                </span>
                <div>
                    <p class="text-[10px] font-semibold text-faint uppercase">Nama Tamu</p>
                    <p class="text-sm font-semibold text-ink">{{ $guest->name }}</p>
                </div>
            </div>
            @if ($guest->category || $guest->group)
            <div class="flex items-start gap-3">
                <span class="flex shrink-0 h-10 w-10 items-center justify-center rounded-2xl bg-blue-soft text-[#4f7592]">
                    <x-ui.icon name="users" class="h-5 w-5" />
                </span>
                <div>
                    <p class="text-[10px] font-semibold text-faint uppercase">Kategori / Grup</p>
                    <div class="flex gap-2 mt-1">
                        @if ($guest->category)
                            <span class="badge badge-info">{{ $guest->category->name }}</span>
                        @endif
                        @if ($guest->group)
                            <span class="badge badge-muted">{{ $guest->group->name }}</span>
                        @endif
                    </div>
                </div>
            </div>
            @endif
            <div class="flex items-start gap-3">
                <span class="flex shrink-0 h-10 w-10 items-center justify-center rounded-2xl bg-blue-soft text-[#4f7592]">
                    <x-ui.icon name="calendar" class="h-5 w-5" />
                </span>
                <div>
                    <p class="text-[10px] font-semibold text-faint uppercase">Tanggal Acara</p>
                    <p class="text-sm font-semibold text-ink">{{ $event->event_date?->translatedFormat('d F Y') }}</p>
                </div>
            </div>
            <div class="flex items-start gap-3">
                <span class="flex shrink-0 h-10 w-10 items-center justify-center rounded-2xl bg-blue-soft text-[#4f7592]">
                    <x-ui.icon name="map-pin" class="h-5 w-5" />
                </span>
                <div>
                    <p class="text-[10px] font-semibold text-faint uppercase">Tempat</p>
                    <p class="text-sm font-semibold text-ink">{{ $event->venue_name }}</p>
                </div>
            </div>
        </div>
    </div>

    <div class="card p-6 mb-10">
        <p class="section-kicker mb-4">QR Code Pass</p>
        <div class="flex flex-col items-center gap-4">
            <div class="relative h-52 w-52 rounded-3xl border border-line bg-white p-4 shadow-raised">
                {!! str_replace('<svg', '<svg class="w-full h-full"', $qrSvg) !!}
            </div>
            <p class="text-xs text-muted">Pindai QR ini di meja registrasi</p>
        </div>
    </div>

    <div class="card p-6 mb-10">
        <p class="section-kicker mb-4">Status Pass</p>
        <div class="flex items-center gap-3">
            <span class="{{ $guest->rsvp_status?->badge() ?? 'badge-neutral' }}">{{ $guest->rsvp_status?->label() }}</span>
            @if ($guest->isCheckedIn())
                <span class="badge badge-success">Sudah tercheck-in</span>
            @endif
        </div>
    </div>

    <div class="card p-6 mb-10">
        <p class="section-kicker mb-4">Aksi</p>
        <div class="flex flex-col gap-3">
            <a href="{{ route('guest.pass.download', $token) }}" class="btn-primary text-center">
                <x-ui.icon name="download" class="h-5 w-5 mr-2" /> Unduh Pass (PNG)
            </a>
            <button type="button" class="btn-secondary" x-data="{ copied: false }" @click="if (navigator.share) { navigator.share({ title: 'Wedding Pass {{ $event->coupleNames() }}', url: window.location.href }); } else { navigator.clipboard.writeText(window.location.href); copied = true; setTimeout(() => copied = false, 2500); }">
                <x-ui.icon name="share" class="h-5 w-5 mr-2" />
                <span x-text="copied ? 'Tautan Pass Tersalin!' : 'Bagikan / Simpan Tautan Pass'"></span>
            </button>
        </div>
    </div>

    <p class="text-center text-[11px] text-faint">Dibuat dengan tenang oleh <span class="font-semibold text-ink">VOWSPACE</span></p>
</div>
@endsection