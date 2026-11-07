@extends('layouts.guest')
@section('content')
@php
    $preview = $preview ?? false;
    $shareUrl = $token ? route('guest.invitation', $token) : null;
    $shareText = $shareUrl ? 'Undangan pernikahan '.$event->coupleNames().': '.$shareUrl : null;
    $waShareUrl = $shareText ? 'https://wa.me/?text='.urlencode($shareText) : null;
    $eventDateTime = $event->localStartsAt()?->translatedFormat('l, d F Y · H:i T')
        ?? $event->event_date?->translatedFormat('l, d F Y');
    $eventDateShort = $event->event_date?->translatedFormat('d F Y');
    $countdownTarget = $event->countdownTarget()?->getTimestampMs();
@endphp

<div class="px-4 pt-6 sm:px-6 lg:px-8">
    <div class="mx-auto max-w-5xl">

        <!-- 1. Hero — identity of the couple + invitation heading -->
        <section class="animate-rise relative overflow-hidden rounded-[2rem] border border-line/60 bg-surface-2/40 shadow-soft sm:rounded-[2.5rem]">
            <div class="relative aspect-[4/5] bg-gradient-to-br from-blue-soft via-teal-soft to-blue-soft/60 sm:aspect-[16/10]">
                @if ($cover = $event->coverUrl())
                    <img src="{{ $cover }}" alt="" class="absolute inset-0 h-full w-full object-cover opacity-75">
                @endif
                <div class="absolute inset-0 bg-gradient-to-t from-scrim/90 via-scrim/35 to-transparent"></div>
                <div class="absolute inset-0 flex flex-col justify-end p-6 pb-8 sm:p-10">
                    <p class="text-[10px] font-semibold uppercase tracking-[0.22em] text-on-scrim/80">The Wedding of</p>
                    <h1 class="mt-2 font-serif text-3xl font-bold leading-tight text-on-scrim sm:text-5xl">
                        {{ $event->groom_name }} &amp; {{ $event->bride_name }}
                    </h1>
                    <p class="mt-3 max-w-md text-sm leading-relaxed text-on-scrim/85">
                        Dengan penuh sukacita kami mengundang Anda untuk hadir di hari bahagia kami.
                    </p>
                </div>
            </div>

            <!-- Date, time & venue -->
            <div class="grid gap-4 border-t border-line/60 p-6 sm:grid-cols-2 sm:p-8">
                <div class="flex items-start gap-3">
                    <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-2xl bg-blue-soft text-ink">
                        <x-ui.icon name="calendar" class="h-5 w-5" />
                    </span>
                    <div>
                        <p class="guest-eyebrow">Tanggal &amp; waktu</p>
                        <p class="text-sm font-semibold text-ink">{{ $eventDateTime }}</p>
                    </div>
                </div>
                <div class="flex items-start gap-3">
                    <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-2xl bg-blue-soft text-ink">
                        <x-ui.icon name="map-pin" class="h-5 w-5" />
                    </span>
                    <div>
                        <p class="guest-eyebrow">Tempat</p>
                        <p class="text-sm font-semibold text-ink">{{ $event->venue_name ?: 'Detail lokasi menyusul' }}</p>
                        @if ($event->venue_address)
                            <p class="mt-0.5 text-xs text-muted">{{ $event->venue_address }}</p>
                        @endif
                    </div>
                </div>
            </div>

            @if ($countdownTarget)
                <div class="grid grid-cols-4 gap-2 border-t border-line/60 bg-surface-2/30 px-6 py-5 sm:px-8"
                     x-data="{
                        target: {{ $countdownTarget }},
                        days: '00', hours: '00', minutes: '00', seconds: '00',
                        timer: null,
                        init() { this.tick(); this.timer = setInterval(() => this.tick(), 1000); },
                        destroy() { if (this.timer) { clearInterval(this.timer); } },
                        tick() {
                            const diff = this.target - Date.now();
                            if (diff <= 0) { this.days = this.hours = this.minutes = this.seconds = '00'; return; }
                            this.days = String(Math.floor(diff / 86400000)).padStart(2, '0');
                            this.hours = String(Math.floor((diff % 86400000) / 3600000)).padStart(2, '0');
                            this.minutes = String(Math.floor((diff % 3600000) / 60000)).padStart(2, '0');
                            this.seconds = String(Math.floor((diff % 60000) / 1000)).padStart(2, '0');
                        }
                     }" x-init="init()">
                    @foreach ([['days', 'Hari'], ['hours', 'Jam'], ['minutes', 'Menit'], ['seconds', 'Detik']] as [$key, $label])
                        <div class="flex flex-col items-center gap-1">
                            <span class="text-[10px] font-semibold uppercase tracking-wide text-faint">{{ $label }}</span>
                            <span x-text="{{ $key }}" class="font-serif text-xl font-bold text-ink">00</span>
                        </div>
                    @endforeach
                </div>
            @endif
        </section>

        @if ($preview)
            <div class="mt-6 flex flex-wrap items-center gap-2">
                <x-ui.badge variant="info" class="text-sm">Preview mode</x-ui.badge>
                <span class="text-sm text-faint">Ini pratinjau admin; formulir tidak aktif.</span>
            </div>
        @endif

        <!-- 2. Personal note — invitation content -->
        <section class="animate-rise mt-8 card p-6 sm:p-8">
            <div class="flex items-start gap-3">
                <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-2xl bg-accent-soft text-ink">
                    <x-ui.icon name="user" class="h-5 w-5" />
                </span>
                <div>
                    <p class="text-sm font-semibold text-ink">Selamat datang, {{ $guest->name }}.</p>
                    <p class="mt-1 max-w-prose text-sm leading-relaxed text-muted">
                        Kami senang mengundang Anda bergabung dalam pernikahan kami. Kehadiran dan doa restu Anda berarti bagi kami.
                    </p>
                </div>
            </div>
        </section>

        <!-- Two-column composition on lg: details & stories beside actions -->
        <div class="mt-8 lg:grid lg:grid-cols-[minmax(0,1.6fr)_minmax(0,1fr)] lg:items-start lg:gap-8">

            <!-- MAIN COLUMN -->
            <div class="space-y-8">

                <!-- 3. RSVP (primary action) -->
                @if (!$preview && $rsvpEnabled && $rsvpOpen)
                    @php
                        $rsvpStatus = $guest->rsvp_status?->value;
                        $rsvpDefault = old('status', ($rsvpStatus === 'pending' || ! $rsvpStatus) ? 'confirmed' : $rsvpStatus);
                        $rsvpDefault = in_array($rsvpDefault, ['confirmed', 'maybe', 'declined'], true) ? $rsvpDefault : 'confirmed';
                    @endphp
                    <section id="rsvp" class="guest-section card p-6 sm:p-8" x-data="{
                        status: '{{ $rsvpDefault }}',
                        showForm: {{ $rsvpStatus === 'pending' || ! $rsvpStatus ? 'true' : 'false' }}
                    }">
                        <div class="mb-5 flex items-center justify-between gap-3">
                            <h2 class="section-kicker">Konfirmasi Kehadiran</h2>
                            <span class="{{ $guest->rsvp_status?->badge() ?? 'badge-neutral' }}">
                                {{ $guest->rsvp_status?->label() ?? 'Belum diisi' }}
                            </span>
                        </div>

                        @if ($rsvpStatus && $rsvpStatus !== 'pending')
                            <div x-show="!showForm" class="mb-5 rounded-2xl border border-line/70 bg-surface-2/40 p-4">
                                <p class="text-sm font-semibold text-ink">Kehadiran: {{ $guest->rsvp_status?->label() }}</p>
                                @if ($guest->rsvp?->attendance_count)
                                    <p class="mt-1 text-xs text-muted">Jumlah: {{ $guest->rsvp->attendance_count }} orang</p>
                                @endif
                                @if ($guest->rsvp?->companion_names)
                                    <p class="mt-0.5 text-xs text-muted">Pendamping: {{ $guest->rsvp->companion_names }}</p>
                                @endif
                                @if ($guest->rsvp?->dietary_preference)
                                    <p class="mt-0.5 text-xs text-muted">Kebutuhan makanan: {{ $guest->rsvp->dietary_preference }}</p>
                                @endif
                                @if ($guest->rsvp?->notes)
                                    <p class="mt-1.5 text-xs italic text-faint">&ldquo;{{ $guest->rsvp->notes }}&rdquo;</p>
                                @endif
                                <div class="mt-4 border-t border-line/60 pt-3">
                                    <button type="button" @click="showForm = true" class="btn btn-secondary btn-sm w-full">
                                        <x-ui.icon name="edit" class="h-3.5 w-3.5" /> Ubah konfirmasi RSVP
                                    </button>
                                </div>
                            </div>
                        @endif

                        <form method="POST" action="{{ route('guest.rsvp.store', $token) }}" class="space-y-5" x-show="showForm">
                            @csrf

                            <fieldset>
                                <legend class="label">Pilihan kehadiran <span class="text-danger" aria-hidden="true">*</span></legend>
                                <div class="grid grid-cols-3 gap-2">
                                    @foreach ([['confirmed', 'Hadir'], ['maybe', 'Mungkin'], ['declined', 'Tidak hadir']] as [$value, $label])
                                        <label class="flex cursor-pointer flex-col items-center justify-center rounded-2xl border border-line p-3 text-center transition"
                                               :class="status === '{{ $value }}' ? 'border-accent bg-accent/15 font-semibold text-ink' : 'bg-surface text-muted hover:bg-surface-2/60'">
                                            <input type="radio" name="status" value="{{ $value }}" class="sr-only" x-model="status" required>
                                            <span class="text-xs">{{ $label }}</span>
                                        </label>
                                    @endforeach
                                </div>
                                @error('status')
                                    <p class="error-text" role="alert">{{ $message }}</p>
                                @enderror
                            </fieldset>

                            <div x-show="status !== 'declined'">
                                @php
                                    $descCount = 'rsvp-count-hint'.($errors->has('attendance_count') ? ' rsvp-count-error' : '');
                                @endphp
                                <label for="rsvp-count" class="label">Jumlah tamu</label>
                                <input id="rsvp-count" type="number" name="attendance_count"
                                       value="{{ old('attendance_count', $guest->rsvp?->attendance_count ?? 1) }}"
                                       class="input" min="1" max="{{ max(1, (int) $guest->max_attendees) }}"
                                       aria-describedby="{{ $descCount }}"
                                       @error('attendance_count') aria-invalid="true" @enderror>
                                <p id="rsvp-count-hint" class="hint">Maksimal {{ $guest->max_attendees }} orang sesuai undangan.</p>
                                @error('attendance_count')
                                    <p id="rsvp-count-error" class="error-text">{{ $message }}</p>
                                @enderror
                            </div>

                            <div x-show="status !== 'declined'">
                                <label for="rsvp-companions" class="label">Nama pendamping <span class="text-faint">(opsional)</span></label>
                                <input id="rsvp-companions" type="text" name="companion_names"
                                       value="{{ old('companion_names', $guest->rsvp?->companion_names ?? '') }}"
                                       class="input" placeholder="Nama pendamping (pisahkan dengan koma)">
                            </div>

                            <div x-show="status !== 'declined'">
                                <label for="rsvp-dietary" class="label">Preferensi makanan <span class="text-faint">(opsional)</span></label>
                                <input id="rsvp-dietary" type="text" name="dietary_preference"
                                       value="{{ old('dietary_preference', $guest->rsvp?->dietary_preference ?? '') }}"
                                       class="input" placeholder="Contoh: halal, vegetarian, alergi kacang">
                            </div>

                            <div>
                                <label for="rsvp-notes" class="label">Pesan atau catatan <span class="text-faint">(opsional)</span></label>
                                <textarea id="rsvp-notes" name="notes" class="input" rows="2"
                                          placeholder="Catatan untuk kedua mempelai">{{ old('notes', $guest->rsvp?->notes ?? '') }}</textarea>
                            </div>

                            <div class="flex items-center gap-2 pt-1">
                                <button type="submit" class="btn btn-primary flex-1">Kirim konfirmasi</button>
                                @if ($rsvpStatus && $rsvpStatus !== 'pending')
                                    <button type="button" @click="showForm = false" class="btn btn-secondary">Batal</button>
                                @endif
                            </div>
                        </form>
                    </section>
                @elseif (!$preview && $rsvpEnabled && !$rsvpOpen)
                    <section class="card p-6 sm:p-8">
                        <h2 class="section-kicker mb-3">Konfirmasi Kehadiran</h2>
                        <p class="max-w-prose text-sm leading-relaxed text-muted">
                            Konfirmasi kehadiran sudah ditutup. Jika perlu perubahan, silakan hubungi pasangan atau admin yang mengirim undangan ini.
                        </p>
                    </section>
                @endif

                <!-- 4. Event details -->
                <section class="card p-6 sm:p-8">
                    <h2 class="section-kicker mb-5">Rincian Acara</h2>
                    <div class="space-y-5">
                        <div class="flex items-start gap-3">
                            <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-2xl bg-blue-soft text-ink">
                                <x-ui.icon name="calendar" class="h-5 w-5" />
                            </span>
                            <div>
                                <p class="guest-eyebrow">Tanggal &amp; waktu</p>
                                <p class="text-sm font-semibold text-ink">{{ $eventDateTime }}</p>
                            </div>
                        </div>
                        <div class="flex items-start gap-3">
                            <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-2xl bg-blue-soft text-ink">
                                <x-ui.icon name="map-pin" class="h-5 w-5" />
                            </span>
                            <div>
                                <p class="guest-eyebrow">Tempat</p>
                                <p class="text-sm font-semibold text-ink">{{ $event->venue_name ?: 'Detail lokasi menyusul' }}</p>
                                @if ($event->venue_address)
                                    <p class="mt-0.5 text-xs text-muted">{{ $event->venue_address }}</p>
                                @endif
                            </div>
                        </div>
                        @if ($event->dress_code)
                            <div class="flex items-start gap-3">
                                <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-2xl bg-blue-soft text-ink">
                                    <x-ui.icon name="user" class="h-5 w-5" />
                                </span>
                                <div>
                                    <p class="guest-eyebrow">Dress code</p>
                                    <p class="text-sm font-semibold text-ink">{{ $event->dress_code }}</p>
                                </div>
                            </div>
                        @endif
                    </div>
                    @if ($event->google_maps_url)
                        <a href="{{ $event->google_maps_url }}" target="_blank" rel="noopener" class="mt-5 block w-full btn-soft text-center">
                            Buka di Google Maps
                            <x-ui.icon name="arrow-up-right" class="ml-1.5 inline h-4 w-4" />
                        </a>
                    @endif
                </section>

                <!-- 5. Story & timeline -->
                @php
                    $previewSchedules = $event->schedules->take(6);
                @endphp
                @if ($event->couple_description || $event->description)
                    <section class="card p-6 sm:p-8">
                        <h2 class="section-kicker mb-4">Cerita Kami</h2>
                        <p class="max-w-prose text-sm leading-relaxed text-muted">{{ $event->couple_description ?? $event->description }}</p>
                    </section>
                @endif

                @if ($previewSchedules->count())
                    <section class="card p-6 sm:p-8">
                        <h2 class="section-kicker mb-5">Susunan Acara</h2>
                        <ol class="space-y-4">
                            @foreach ($previewSchedules as $schedule)
                                <li class="flex items-start gap-3">
                                    <span class="mt-1.5 h-2 w-2 shrink-0 rounded-full bg-accent"></span>
                                    <div>
                                        <p class="text-[11px] font-semibold uppercase tracking-wide text-faint">
                                            {{ $schedule->start_time ? \Carbon\Carbon::parse($schedule->start_time)->format('H:i') : '' }}
                                        </p>
                                        <p class="text-sm font-semibold text-ink">{{ $schedule->title }}</p>
                                        @if ($schedule->description)
                                            <p class="mt-0.5 max-w-prose text-xs leading-relaxed text-muted">{{ $schedule->description }}</p>
                                        @endif
                                    </div>
                                </li>
                            @endforeach
                        </ol>
                    </section>
                @endif

                <!-- 6. Gallery preview -->
                @if (!$preview && $galleryEnabled)
                    <section class="card p-6 sm:p-8">
                        <div class="mb-4 flex items-center justify-between gap-3">
                            <h2 class="section-kicker">Galeri Foto</h2>
                            @if ($allowsGuestActions)
                                <a href="{{ route('guest.gallery', $token) }}" class="text-xs font-semibold text-accent hover:text-ink">Lihat semua</a>
                            @endif
                        </div>
                        @if ($photos->isNotEmpty())
                            <div class="grid grid-cols-3 gap-2 sm:grid-cols-4">
                                @foreach ($photos as $photo)
                                    <a href="{{ route('guest.gallery', $token) }}" class="group relative aspect-square overflow-hidden rounded-2xl bg-surface-2">
                                        <img src="{{ $photo->thumbnailUrl() }}" alt="{{ $photo->caption ?: 'Foto undangan' }}"
                                             class="h-full w-full object-cover transition duration-300 group-hover:scale-105">
                                    </a>
                                @endforeach
                            </div>
                        @else
                            <x-ui.empty-state icon="image" title="Belum ada foto" description="Tunggu foto resmi dipublikasikan." />
                        @endif
                    </section>
                @endif

                <!-- 7. Gift teaser -->
                @if (!$preview && $giftEnabled && $gifts->isNotEmpty())
                    <section class="card p-6 sm:p-8">
                        <h2 class="section-kicker mb-4">Hadiah Pernikahan</h2>
                        <p class="max-w-prose text-sm leading-relaxed text-muted">
                            Terima kasih atas perhatian Anda. Detail rekening dan QRIS tersedia di halaman hadiah.
                        </p>
                        <a href="{{ route('guest.gift', $token) }}" class="mt-5 block w-full btn-soft text-center">
                            <x-ui.icon name="gift" class="h-5 w-5" /> Lihat detail hadiah
                        </a>
                    </section>
                @endif
            </div>

            <!-- ASIDE COLUMN -->
            <aside class="mt-8 space-y-8 lg:mt-0">

                <!-- Digital pass -->
                @if (!$preview && $allowsGuestActions)
                    <section class="card p-6 sm:p-8">
                        <h2 class="section-kicker mb-3">Kartu Tamu Digital</h2>
                        <p class="text-sm leading-relaxed text-muted">
                            Simpan pass Anda dan tunjukkan QR-nya di meja registrasi saat kedatangan.
                        </p>
                        <a href="{{ route('guest.pass', $token) }}" class="mt-5 block w-full btn-primary text-center">
                            <x-ui.icon name="qr" class="h-5 w-5" /> Buka pass
                        </a>
                    </section>
                @endif

                <!-- Guestbook -->
                @if (!$preview && $guestbookEnabled)
                    <section id="guestbook" class="guest-section card p-6 sm:p-8" x-data="{ emoji: '' }">
                        <h2 class="section-kicker mb-4">Ucapan &amp; Doa Restu</h2>
                        <form method="POST" action="{{ route('guest.guestbook.store', $token) }}" class="space-y-4">
                            @csrf
                            <div>
                                <label for="guestbook-message" class="label">
                                    Pesan ucapan <span class="text-danger" aria-hidden="true">*</span>
                                    <span class="sr-only">(wajib)</span>
                                </label>
                                <textarea id="guestbook-message" name="message" rows="3" required
                                          class="input" placeholder="Tuliskan ucapan dan doa hangat untuk kedua mempelai..."
                                          @error('message') aria-invalid="true" aria-describedby="guestbook-message-error" @enderror>{{ old('message') }}</textarea>
                                @error('message')
                                    <p id="guestbook-message-error" class="error-text">{{ $message }}</p>
                                @enderror
                            </div>
                            <div>
                                <span class="label" id="guestbook-emoji-label">Sertakan emoji <span class="text-faint">(opsional)</span></span>
                                <div class="flex flex-wrap gap-2" role="group" aria-labelledby="guestbook-emoji-label">
                                    @foreach (['❤️', '✨', '😊', '🎉', '🥂', '💍', '🕊️', '🌸'] as $em)
                                        <button type="button"
                                                class="btn btn-secondary btn-sm h-9 w-9 rounded-xl p-0 text-base"
                                                :class="emoji === '{{ $em }}' ? 'border-accent bg-accent/20' : ''"
                                                :aria-pressed="emoji === '{{ $em }}' ? 'true' : 'false'"
                                                @click="emoji = emoji === '{{ $em }}' ? '' : '{{ $em }}'">
                                            {{ $em }}
                                        </button>
                                    @endforeach
                                </div>
                                <input type="hidden" name="emoji" :value="emoji">
                            </div>
                            <button type="submit" class="btn btn-primary w-full">Kirim ucapan</button>
                        </form>

                        <h3 class="section-kicker mt-6 mb-3">Pesan Terkini</h3>
                        <div class="space-y-4">
                            @forelse ($messages as $message)
                                <div class="flex items-start gap-3">
                                    <x-ui.avatar :name="$message->sender_name" size="sm" class="h-8 w-8" />
                                    <div class="min-w-0 flex-1">
                                        <p class="text-sm font-semibold text-ink">{{ $message->sender_name }}</p>
                                        <p class="mt-0.5 text-sm leading-relaxed text-muted">{{ $message->message }}</p>
                                        <div class="mt-0.5 flex items-center gap-2">
                                            @if ($message->emoji)
                                                <span class="text-lg">{{ $message->emoji }}</span>
                                            @endif
                                            <span class="text-[10px] text-faint">{{ $message->created_at?->diffForHumans() }}</span>
                                        </div>
                                    </div>
                                </div>
                            @empty
                                <x-ui.empty-state icon="message" title="Belum ada pesan" description="Kirim pesan pertama Anda di atas." />
                            @endforelse
                        </div>
                    </section>
                @endif

                <!-- Poll -->
                @if (!$preview && $poll && $allowsGuestActions)
                    <section id="poll" class="guest-section card p-6 sm:p-8">
                        <h2 class="section-kicker mb-2">Polling</h2>
                        <p class="mb-4 text-sm font-semibold text-ink">{{ $poll->question }}</p>
                        @if ($myPollResponse)
                            <p class="badge badge-success mb-4">Anda memilih: {{ $myPollResponse->option?->label }}</p>
                            <div class="space-y-3">
                                @foreach ($poll->options as $option)
                                    @php $optionCount = $option->responses()->count(); @endphp
                                    <div class="flex items-center gap-2">
                                        <span class="w-1/3 text-xs font-semibold text-ink">{{ $option->label }}</span>
                                        <div class="h-2 flex-1 overflow-hidden rounded-full bg-surface-2/60">
                                            <div class="h-full rounded-full bg-accent" style="width: {{ min(100, $optionCount * 10) }}%"></div>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        @else
                            <form method="POST" action="{{ route('guest.poll.vote', [$token, $poll]) }}" class="space-y-3">
                                @csrf
                                <fieldset>
                                    <legend class="label">Pilih opsi <span class="text-danger" aria-hidden="true">*</span></legend>
                                    <div class="space-y-2">
                                        @foreach ($poll->options as $option)
                                            <label class="flex cursor-pointer items-center gap-3 rounded-2xl border border-line bg-surface px-3 py-3 text-sm font-medium text-muted transition hover:bg-surface-2/60 has-[:checked]:border-accent has-[:checked]:bg-blue-soft has-[:checked]:text-ink">
                                                <input type="radio" name="poll_option_id" value="{{ $option->id }}" class="sr-only" required>
                                                <span>{{ $option->label }}</span>
                                            </label>
                                        @endforeach
                                    </div>
                                </fieldset>
                                @error('poll')
                                    <p class="error-text" role="alert">{{ $message }}</p>
                                @enderror
                                <button type="submit" class="btn btn-primary w-full">Kirim suara</button>
                            </form>
                        @endif
                    </section>
                @endif

                <!-- Mini game -->
                @if (!$preview && $game && $allowsGuestActions)
                    <section id="game" class="guest-section card p-6 sm:p-8">
                        <h2 class="section-kicker mb-3">Game: {{ $game->title }}</h2>
                        <p class="text-sm leading-relaxed text-muted">Uji seberapa kenal Anda dengan kami lewat kuis singkat ini.</p>
                        <a href="{{ route('guest.game.show', [$token, $game]) }}" class="mt-5 block w-full btn-soft text-center">
                            <x-ui.icon name="game" class="h-5 w-5" /> Mainkan {{ $game->title }}
                        </a>
                    </section>
                @endif

                <!-- Live wall -->
                @if ($event->live_wall_enabled)
                    <section class="card p-6 sm:p-8">
                        <h2 class="section-kicker mb-3">Tembok Ucapan Langsung</h2>
                        <p class="text-sm leading-relaxed text-muted">Lihat ucapan dari tamu lain tampil di layar acara.</p>
                        <a href="{{ route('live-wall.show', $event->slug) }}" class="mt-5 block w-full btn-soft text-center">
                            <x-ui.icon name="tv" class="h-5 w-5" /> Buka tembok ucapan
                        </a>
                    </section>
                @endif

                <!-- Share -->
                @if ($shareUrl)
                    <section class="card p-6 sm:p-8" data-share-url="{{ $shareUrl }}" x-data="{
                        copied: false,
                        async copyLink() {
                            const url = this.$el.dataset.shareUrl;
                            try {
                                await navigator.clipboard.writeText(url);
                                this.copied = true;
                                if (window.Swal) {
                                    Swal.fire({ toast: true, position: 'top-end', icon: 'success', title: 'Tautan disalin', showConfirmButton: false, timer: 1800 });
                                }
                                setTimeout(() => this.copied = false, 2500);
                            } catch (e) {
                                if (window.Swal) {
                                    Swal.fire('Gagal menyalin', 'Salin manual: ' + url, 'error');
                                }
                            }
                        }
                    }">
                        <h2 class="section-kicker mb-3">Bagikan Undangan</h2>
                        <div class="space-y-2">
                            @if ($waShareUrl)
                                <a href="{{ $waShareUrl }}" target="_blank" rel="noopener" class="btn btn-secondary w-full">
                                    <x-ui.icon name="share" class="h-4 w-4" /> Bagikan via WhatsApp
                                </a>
                            @endif
                            <button type="button" class="btn btn-ghost w-full" @click="copyLink()">
                                <x-ui.icon name="copy" class="h-4 w-4" />
                                <span x-text="copied ? 'Tautan tersalin' : 'Salin tautan'">Salin tautan</span>
                            </button>
                        </div>
                    </section>
                @endif
            </aside>
        </div>

        <p class="mt-12 text-center text-[11px] text-faint">
            Dibuat dengan tenang oleh <span class="font-semibold text-ink">VOWSPACE</span>
        </p>
    </div>
</div>
@endsection
