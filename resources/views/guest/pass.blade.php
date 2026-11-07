@extends('layouts.guest')
@section('content')
<div class="px-4 pt-6 pb-12 sm:px-6 lg:px-8">
    <div class="mx-auto max-w-3xl">
        <div class="card p-6 sm:p-8">
            <p class="section-kicker">VOWSPACE Pass</p>
            <h1 class="mt-2 font-serif text-2xl font-semibold text-ink">{{ $event->groom_name }} &amp; {{ $event->bride_name }}</h1>
            <p class="mt-1 text-sm text-muted">Undangan Pernikahan</p>
        </div>

        <div class="mt-8 grid gap-8 lg:grid-cols-2 lg:items-start">
            <div class="space-y-8">
                <div class="card p-6 sm:p-8">
                    <h2 class="section-kicker mb-5">Detail Tamu</h2>
                    <div class="space-y-5">
                        <div class="flex items-start gap-3">
                            <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-2xl bg-blue-soft text-ink">
                                <x-ui.icon name="user" class="h-5 w-5" />
                            </span>
                            <div>
                                <p class="guest-eyebrow">Nama tamu</p>
                                <p class="text-sm font-semibold text-ink">{{ $guest->name }}</p>
                            </div>
                        </div>
                        @if ($guest->category || $guest->group)
                            <div class="flex items-start gap-3">
                                <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-2xl bg-blue-soft text-ink">
                                    <x-ui.icon name="users" class="h-5 w-5" />
                                </span>
                                <div>
                                    <p class="guest-eyebrow">Kategori / grup</p>
                                    <div class="mt-1 flex flex-wrap gap-2">
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
                            <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-2xl bg-blue-soft text-ink">
                                <x-ui.icon name="calendar" class="h-5 w-5" />
                            </span>
                            <div>
                                <p class="guest-eyebrow">Tanggal acara</p>
                                <p class="text-sm font-semibold text-ink">{{ $event->event_date?->translatedFormat('d F Y') }}</p>
                            </div>
                        </div>
                        <div class="flex items-start gap-3">
                            <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-2xl bg-blue-soft text-ink">
                                <x-ui.icon name="map-pin" class="h-5 w-5" />
                            </span>
                            <div>
                                <p class="guest-eyebrow">Tempat</p>
                                <p class="text-sm font-semibold text-ink">{{ $event->venue_name }}</p>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card p-6 sm:p-8">
                    <h2 class="section-kicker mb-4">Status Pass</h2>
                    <div class="flex flex-wrap items-center gap-3">
                        <span class="{{ $guest->rsvp_status?->badge() ?? 'badge-neutral' }}">{{ $guest->rsvp_status?->label() ?? 'Belum diisi' }}</span>
                        @if ($guest->isCheckedIn())
                            <span class="badge badge-success">Sudah tercheck-in</span>
                        @endif
                    </div>
                </div>
            </div>

            <div class="space-y-8">
                <div class="card p-6 sm:p-8">
                    <h2 class="section-kicker mb-4">QR Code Pass</h2>
                    <div class="flex flex-col items-center gap-4">
                        {{-- QR codes must stay dark-on-white in every theme to remain scannable. --}}
                        <div class="rounded-3xl border border-line bg-white p-4 shadow-raised">
                            {!! str_replace('<svg', '<svg class="h-48 w-48"', $qrSvg) !!}
                        </div>
                        <p class="text-center text-xs text-muted">Pindai QR ini di meja registrasi</p>
                    </div>
                </div>

                <div class="card p-6 sm:p-8">
                    <h2 class="section-kicker mb-4">Aksi</h2>
                    <div class="flex flex-col gap-3">
                        <a href="{{ route('guest.pass.download', $token) }}" class="btn-primary text-center">
                            <x-ui.icon name="download" class="h-5 w-5" /> Unduh pass (PNG)
                        </a>
                        <button type="button" class="btn-secondary" x-data="{ copied: false }"
                                @click="if (navigator.share) { navigator.share({ title: 'Wedding Pass {{ $event->coupleNames() }}', url: window.location.href }); } else { navigator.clipboard.writeText(window.location.href); copied = true; setTimeout(() => copied = false, 2500); }">
                            <x-ui.icon name="share" class="h-5 w-5" />
                            <span x-text="copied ? 'Tautan pass tersalin' : 'Bagikan / salin tautan pass'"></span>
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <p class="mt-10 text-center text-[11px] text-faint">
            Dibuat dengan tenang oleh <span class="font-semibold text-ink">VOWSPACE</span>
        </p>
    </div>
</div>
@endsection
