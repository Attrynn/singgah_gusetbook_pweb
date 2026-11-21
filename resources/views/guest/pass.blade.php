@extends('layouts.guest')
@section('title', 'Wedding Pass · ' . $guest->name)

@section('content')
<div class="px-4 pt-6 pb-16 sm:px-6 lg:px-8">
    <div class="mx-auto max-w-xl">

        <!-- Top Navigation / Breadcrumb -->
        <div class="mb-6 flex items-center justify-between text-xs">
            <a href="{{ route('guest.invitation', $token) }}" class="inline-flex items-center gap-2 text-muted hover:text-ink transition">
                <x-ui.icon name="arrow-left" class="h-4 w-4 text-accent" />
                <span>Kembali ke Undangan</span>
            </a>
            <span class="badge border-accent/40 bg-accent/10 text-accent font-serif tracking-wider">
                ACCESS PASS
            </span>
        </div>

        <!-- Luxury Wedding Pass Card (Stationery Voucher Aesthetic) -->
        <div class="relative overflow-hidden rounded-2xl border border-accent/40 bg-surface shadow-2xl transition duration-300">
            
            <!-- Luxury Gold Foil Header Bar -->
            <div class="relative bg-gradient-to-r from-[#1A1714] via-[#2A231C] to-[#1A1714] px-6 py-8 text-center text-white">
                <!-- Subtle Gold Ornaments -->
                <div class="mx-auto mb-3 flex items-center justify-center gap-3">
                    <span class="h-px w-8 bg-gradient-to-r from-transparent to-[#D4AF7A]"></span>
                    <span class="font-serif text-xs uppercase tracking-[0.3em] text-[#D4AF7A]">Pass Masuk Resmi</span>
                    <span class="h-px w-8 bg-gradient-to-l from-transparent to-[#D4AF7A]"></span>
                </div>

                <p class="font-serif text-2xl sm:text-3xl font-semibold tracking-wide text-[#FAF8F5]">
                    {{ $event->groom_name }} &amp; {{ $event->bride_name }}
                </p>
                <p class="mt-1.5 text-[11px] uppercase tracking-[0.2em] text-[#D4AF7A]/80">
                    The Wedding Celebration
                </p>

                <!-- Monogram Emblem watermark -->
                <div class="pointer-events-none absolute right-4 top-1/2 -translate-y-1/2 opacity-10 select-none">
                    <span class="font-serif text-7xl font-bold italic text-[#D4AF7A]">V</span>
                </div>
            </div>

            <!-- Card Body / Guest Identification -->
            <div class="p-6 sm:p-8 text-center space-y-6">
                <!-- Honored Guest Header -->
                <div>
                    <span class="text-[10px] font-semibold uppercase tracking-[0.25em] text-accent">Tamu Kehormatan</span>
                    <h1 class="mt-2 font-serif text-2xl sm:text-3xl font-medium tracking-tight text-ink">
                        {{ $guest->name }}
                    </h1>
                    
                    <!-- Categories & Badges -->
                    <div class="mt-3 flex flex-wrap items-center justify-center gap-2">
                        @if ($guest->category)
                            <span class="badge border-accent/40 bg-accent/15 text-accent font-medium">
                                {{ $guest->category->name }}
                            </span>
                        @endif
                        @if ($guest->group)
                            <span class="badge border-line bg-surface-2 text-muted">
                                {{ $guest->group->name }}
                            </span>
                        @endif
                        @if ($guest->isCheckedIn())
                            <span class="badge border-success/40 bg-success/15 text-success">
                                ✓ Sudah Check-in
                            </span>
                        @else
                            <span class="{{ $guest->rsvp_status?->badge() ?? 'badge-neutral' }}">
                                RSVP: {{ $guest->rsvp_status?->label() ?? 'Belum diisi' }}
                            </span>
                        @endif
                    </div>
                </div>

                <!-- Perforated Stub Line with Notches -->
                <div class="relative my-6 -mx-6 sm:-mx-8 flex items-center">
                    <div class="h-5 w-5 rounded-r-full border-r border-t border-b border-accent/40 bg-canvas"></div>
                    <div class="flex-1 border-t-2 border-dashed border-accent/30 mx-2"></div>
                    <div class="h-5 w-5 rounded-l-full border-l border-t border-b border-accent/40 bg-canvas"></div>
                </div>

                <!-- QR Section -->
                <div class="flex flex-col items-center">
                    <div class="relative rounded-2xl border-2 border-accent/50 bg-white p-4 shadow-raised">
                        <!-- Corner accents -->
                        <div class="absolute -top-1.5 -left-1.5 h-3.5 w-3.5 border-t-2 border-l-2 border-accent"></div>
                        <div class="absolute -top-1.5 -right-1.5 h-3.5 w-3.5 border-t-2 border-r-2 border-accent"></div>
                        <div class="absolute -bottom-1.5 -left-1.5 h-3.5 w-3.5 border-b-2 border-l-2 border-accent"></div>
                        <div class="absolute -bottom-1.5 -right-1.5 h-3.5 w-3.5 border-b-2 border-r-2 border-accent"></div>

                        {!! str_replace('<svg', '<svg class="h-44 w-44 sm:h-52 sm:w-52"', $qrSvg) !!}
                    </div>

                    <p class="mt-4 text-xs font-medium text-muted">
                        Tunjukkan QR ini kepada petugas registrasi saat tiba
                    </p>
                    @if (! empty($checkinCode))
                        <p class="mt-2 font-mono text-sm font-semibold tracking-[0.25em] text-ink">{{ $checkinCode }}</p>
                    @endif
                    <p class="mt-0.5 text-[10px] text-faint">
                        Kode unik autentik &bull; Berlaku untuk {{ $guest->max_attendees ?? 1 }} orang
                    </p>
                </div>

                <!-- Event Details Grid -->
                <div class="rounded-xl border border-line/80 bg-surface-2/40 p-4 text-left grid grid-cols-1 sm:grid-cols-2 gap-3 text-xs">
                    <div class="flex items-start gap-2.5">
                        <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-lg border border-accent/30 bg-accent/10 text-accent">
                            <x-ui.icon name="calendar" class="h-3.5 w-3.5" />
                        </span>
                        <div>
                            <p class="text-[10px] uppercase tracking-wider text-faint">Tanggal Acara</p>
                            <p class="font-medium text-ink mt-0.5">{{ $event->event_date?->translatedFormat('d F Y') }}</p>
                        </div>
                    </div>

                    <div class="flex items-start gap-2.5">
                        <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-lg border border-accent/30 bg-accent/10 text-accent">
                            <x-ui.icon name="map-pin" class="h-3.5 w-3.5" />
                        </span>
                        <div>
                            <p class="text-[10px] uppercase tracking-wider text-faint">Lokasi Resepsi</p>
                            <p class="font-medium text-ink mt-0.5 truncate">{{ $event->venue_name }}</p>
                        </div>
                    </div>
                </div>

            </div>

            <!-- Card Footer -->
            <div class="border-t border-line/70 bg-surface-2/30 px-6 py-3 text-center">
                <p class="text-[10px] uppercase tracking-[0.2em] text-faint font-serif">
                    VOWSPACE Concierge &bull; Official Digital Stationery
                </p>
            </div>
        </div>

        <!-- Action Buttons -->
        <div class="mt-6 flex flex-col sm:flex-row gap-3">
            <a href="{{ route('guest.pass.download', $token) }}" class="btn-primary flex-1 py-3 text-center text-sm font-semibold">
                <x-ui.icon name="download" class="h-4 w-4" /> Unduh Pass (PNG)
            </a>
            <button type="button" class="btn-secondary flex-1 py-3 text-center text-sm" x-data="{ copied: false }"
                    @click="if (navigator.share) { navigator.share({ title: 'Wedding Pass {{ $event->coupleNames() }}', url: window.location.href }); } else { navigator.clipboard.writeText(window.location.href); copied = true; setTimeout(() => copied = false, 2500); }">
                <x-ui.icon name="share" class="h-4 w-4 text-accent" />
                <span x-text="copied ? 'Tautan Pass Tersalin!' : 'Bagikan / Salin Tautan'"></span>
            </button>
        </div>

        <div class="mt-8 text-center">
            <a href="{{ route('guest.invitation', $token) }}" class="text-xs text-muted hover:text-accent transition underline underline-offset-4">
                &larr; Lihat Halaman Undangan Lengkap &amp; Kirim Ucapan
            </a>
        </div>

    </div>
</div>
@endsection
