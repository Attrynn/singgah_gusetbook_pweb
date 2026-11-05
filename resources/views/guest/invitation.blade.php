@extends('layouts.guest')
@section('content')
@php
    $preview = $preview ?? false;
    $shareUrl = $token ? route('guest.invitation', $token) : null;
@endphp
<x-ui.page-header title="Undangan Pernikahan" subtitle="Untuk {{ $event->coupleNames() }}" />
<div class="px-5 pt-5 pb-12">
    <!-- Hero -->
    <div class="relative rounded-[2.25rem] overflow-hidden border border-line/60 bg-surface-2/40 shadow-soft mb-10">
        <div class="aspect-[4/5] relative bg-gradient-to-br from-blue-soft via-teal-soft to-blue-soft/60">
            @if ($cover = $event->coverUrl())
                <img src="{{ $cover }}" alt="" class="absolute inset-0 w-full h-full object-cover opacity-70">
            @endif
            <div class="absolute inset-0 bg-gradient-to-t from-ink/75 via-ink/20 to-transparent"></div>
            <div class="absolute inset-0 flex items-end p-6 pb-10">
                <p class="section-kicker text-white/90">The Wedding of</p>
                <h1 class="mt-1 font-serif text-3xl font-bold text-white leading-tight">{{ $event->groom_name }} & {{ $event->bride_name }}</h1>
            </div>
        </div>
        <div class="px-6 pt-6 pb-8">
            <div class="flex items-center justify-between gap-3 mb-6">
                <span class="flex shrink-0 h-9 w-9 items-center justify-center rounded-2xl bg-blue-soft text-[#4f7592]">
                    <x-ui.icon name="calendar" class="h-4 w-4" />
                </span>
                <p class="text-sm font-semibold text-ink">{{ $event->event_date?->translatedFormat('l, d F Y') }}</p>
            </div>
            <div class="grid grid-cols-4 gap-2" x-data="{ target: {{ $event->countdownTarget()?->getTimestampMs() ?? 'null' }}, days:'00', hours:'00', minutes:'00', seconds:'00', init(){ this.tick(); setInterval(()=>this.tick(),1000); }, tick(){ if(!this.target){this.days=this.hours=this.minutes=this.seconds='--';return;} const diff=this.target-Date.now(); if(diff<=0){this.days=this.hours=this.minutes=this.seconds='00';return;} const d=Math.floor(diff/86400000); const h=Math.floor((diff%86400000)/3600000); const m=Math.floor((diff%3600000)/60000); const s=Math.floor((diff%60000)/1000); this.days=String(d).padStart(2,'0'); this.hours=String(h).padStart(2,'0'); this.minutes=String(m).padStart(2,'0'); this.seconds=String(s).padStart(2,'0'); } }" x-init="init()">
                <div class="flex flex-col items-center gap-1">
                    <span class="text-[10px] font-semibold text-faint uppercase">Hari</span>
                    <span x-text="days" class="font-serif text-xl font-bold text-ink">00</span>
                </div>
                <div class="flex flex-col items-center gap-1">
                    <span class="text-[10px] font-semibold text-faint uppercase">Jam</span>
                    <span x-text="hours" class="font-serif text-xl font-bold text-ink">00</span>
                </div>
                <div class="flex flex-col items-center gap-1">
                    <span class="text-[10px] font-semibold text-faint uppercase">Menit</span>
                    <span x-text="minutes" class="font-serif text-xl font-bold text-ink">00</span>
                </div>
                <div class="flex flex-col items-center gap-1">
                    <span class="text-[10px] font-semibold text-faint uppercase">Detik</span>
                    <span x-text="seconds" class="font-serif text-xl font-bold text-ink">00</span>
                </div>
            </div>
        </div>
    </div>

    <!-- Personal note -->
    <div class="card p-6 mb-10">
        <div class="flex items-start gap-3">
            <span class="flex shrink-0 h-10 w-10 items-center justify-center rounded-2xl bg-blue-soft text-[#4f7592]">
                <x-ui.icon name="user" class="h-5 w-5" />
            </span>
            <div>
                <p class="text-sm font-semibold text-ink">Selamat datang, {{ $guest->name }}!</p>
                <p class="mt-1 text-sm text-muted">Kami senang mengundang Anda bergabung dalam pernikahan kami.</p>
            </div>
        </div>
    </div>

    <!-- Event details -->
    <div class="card p-6 mb-10">
        <p class="section-kicker mb-4">Rincian Acara</p>
        <div class="space-y-4">
            <div class="flex items-start gap-3">
                <span class="flex shrink-0 h-10 w-10 items-center justify-center rounded-2xl bg-blue-soft text-[#4f7592]">
                    <x-ui.icon name="calendar" class="h-5 w-5" />
                </span>
                <div>
                    <p class="text-[10px] font-semibold text-faint uppercase">Tanggal & waktu</p>
                    <p class="text-sm font-semibold text-ink">{{ $event->event_date?->translatedFormat('d F Y') }} · {{ \Carbon\Carbon::parse($event->start_time)->format('H:i') }} WIB</p>
                </div>
            </div>
            <div class="flex items-start gap-3">
                <span class="flex shrink-0 h-10 w-10 items-center justify-center rounded-2xl bg-blue-soft text-[#4f7592]">
                    <x-ui.icon name="map-pin" class="h-5 w-5" />
                </span>
                <div>
                    <p class="text-[10px] font-semibold text-faint uppercase">Tempat</p>
                    <p class="text-sm font-semibold text-ink">{{ $event->venue_name }}</p>
                    <p class="mt-0.5 text-xs text-muted">{{ $event->venue_address }}</p>
                </div>
            </div>
            @if ($event->dress_code)
            <div class="flex items-start gap-3">
                <span class="flex shrink-0 h-10 w-10 items-center justify-center rounded-2xl bg-blue-soft text-[#4f7592]">
                    <x-ui.icon name="user" class="h-5 w-5" />
                </span>
                <div>
                    <p class="text-[10px] font-semibold text-faint uppercase">Dress code</p>
                    <p class="text-sm font-semibold text-ink">{{ $event->dress_code }}</p>
                </div>
            </div>
            @endif
        </div>
        @if ($event->google_maps_url)
            <a href="{{ $event->google_maps_url }}" target="_blank" rel="noopener" class="mt-4 block w-full btn-soft text-center">
                Buka di Google Maps <x-ui.icon name="arrow-up-right" class="h-4 w-4 inline ml-1.5" />
            </a>
        @endif
    </div>

    <!-- Schedule preview -->
    @php
        $previewSchedules = $event->schedules->take(4);
    @endphp
    @if ($previewSchedules->count())
        <div class="mb-10">
            <p class="section-kicker mb-4">Jadwal Acara</p>
            <div class="space-y-3">
                @foreach ($previewSchedules as $schedule)
                    <div class="flex items-start gap-3">
                        <span class="mt-0.5 shrink-0 w-1 h-3 rounded-full bg-accent"></span>
                        <div>
                            <p class="text-[10px] font-semibold text-faint uppercase">{{ \Carbon\Carbon::parse($schedule->start_time)->format('H:i') }}</p>
                            <p class="text-sm font-semibold text-ink">{{ $schedule->title }}</p>
                            @if ($schedule->description)
                                <p class="mt-0.5 text-xs text-muted">{{ $schedule->description }}</p>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    <!-- Story -->
    @if ($event->couple_description || $event->description)
        <div class="card p-6 mb-10">
            <p class="section-kicker mb-4">Cerita Kami</p>
            <p class="prose-wedding">{{ $event->couple_description ?? $event->description }}</p>
        </div>
    @endif

    <!-- Live wall -->
    @if ($event->live_wall_enabled)
        <a href="{{ route('live-wall.show', $event->slug) }}" class="block w-full btn-soft text-center mb-10">
            <x-ui.icon name="tv" class="h-4 w-4 mr-2" /> Tembok Ucapan Langsung
        </a>
    @endif

    @if ($preview)
        <div class="flex items-center gap-2 mb-4">
            <x-ui.badge variant="info" class="text-sm">Preview mode</x-ui.badge>
            <span class="text-sm text-faint">Ini pratinjau admin; formulir tidak aktif.</span>
        </div>
    @endif

    <!-- RSVP -->
    @if (!$preview && $rsvpEnabled && $rsvpOpen)
        <div class="card p-6 mb-10" id="rsvp" x-data="{
            status: '{{ old('status', $guest->rsvp_status?->value === 'pending' ? 'confirmed' : ($guest->rsvp_status?->value ?? 'confirmed')) }}',
            showForm: {{ $guest->rsvp_status?->value === 'pending' ? 'true' : 'false' }}
        }">
            <div class="flex items-center justify-between mb-4">
                <p class="section-kicker">Konfirmasi Kehadiran</p>
                <span class="{{ $guest->rsvp_status?->badge() ?? 'badge-neutral' }} text-xs">
                    {{ $guest->rsvp_status?->label() }}
                </span>
            </div>

            @if ($guest->rsvp_status?->value !== 'pending')
                <div x-show="!showForm" class="rounded-2xl border border-line/70 bg-surface-2/40 p-4 mb-4">
                    <p class="text-sm font-semibold text-ink">Kehadiran: {{ $guest->rsvp_status?->label() }}</p>
                    @if ($guest->rsvp?->attendance_count)
                        <p class="text-xs text-muted mt-1">Jumlah: {{ $guest->rsvp->attendance_count }} orang</p>
                    @endif
                    @if ($guest->rsvp?->companion_names)
                        <p class="text-xs text-muted mt-0.5">Pendamping: {{ $guest->rsvp->companion_names }}</p>
                    @endif
                    @if ($guest->rsvp?->dietary_preference)
                        <p class="text-xs text-muted mt-0.5">Kebutuhan Makanan: {{ $guest->rsvp->dietary_preference }}</p>
                    @endif
                    @if ($guest->rsvp?->notes)
                        <p class="text-xs text-faint italic mt-1.5">"{{ $guest->rsvp->notes }}"</p>
                    @endif
                    <div class="mt-4 pt-3 border-t border-line/60">
                        <button type="button" @click="showForm = true" class="btn btn-secondary btn-sm w-full">
                            <x-ui.icon name="edit" class="h-3.5 w-3.5 mr-1.5" /> Ubah Konfirmasi RSVP
                        </button>
                    </div>
                </div>
            @endif

            <form method="POST" action="{{ route('guest.rsvp.store', $token) }}" class="space-y-4" x-show="showForm">
                @csrf
                <div>
                    <label class="label">Pilihan Kehadiran</label>
                    <div class="grid grid-cols-3 gap-2">
                        @foreach ([['confirmed','Hadir'],['maybe','Mungkin'],['declined','Tidak Hadir']] as [$value, $label])
                            <label class="flex flex-col items-center justify-center rounded-2xl border border-line p-3 text-center cursor-pointer transition"
                                   :class="status === '{{ $value }}' ? 'bg-accent/15 border-accent text-ink font-semibold' : 'bg-surface text-muted hover:bg-surface-2/60'">
                                <input type="radio" name="status" value="{{ $value }}" class="sr-only" x-model="status" required>
                                <span class="text-xs">{{ $label }}</span>
                            </label>
                        @endforeach
                    </div>
                </div>

                <div x-show="status !== 'declined'">
                    <label class="label">Jumlah Tamu</label>
                    <input type="number" name="attendance_count" value="{{ old('attendance_count', $guest->rsvp?->attendance_count ?? 1) }}" class="input" min="1" max="{{ max(1, (int) $guest->max_attendees) }}" />
                    <p class="hint">Maksimal {{ $guest->max_attendees }} orang sesuai undangan.</p>
                </div>

                <div x-show="status !== 'declined'">
                    <label class="label">Nama Pendamping (Opsional)</label>
                    <input type="text" name="companion_names" value="{{ old('companion_names', $guest->rsvp?->companion_names ?? '') }}" class="input" placeholder="Nama pendamping (pisahkan dengan koma)" />
                </div>

                <div x-show="status !== 'declined'">
                    <label class="label">Preferensi Makanan (Opsional)</label>
                    <input type="text" name="dietary_preference" value="{{ old('dietary_preference', $guest->rsvp?->dietary_preference ?? '') }}" class="input" placeholder="Contoh: Halal, Vegetarian, Alergi kacang" />
                </div>

                <div>
                    <label class="label">Pesan / Catatan Khusus</label>
                    <textarea name="notes" class="input" rows="2" placeholder="Catatan untuk kedua mempelai">{{ old('notes', $guest->rsvp?->notes ?? '') }}</textarea>
                </div>

                <div class="flex items-center gap-2 pt-2">
                    <button type="submit" class="btn btn-primary flex-1">Kirim Konfirmasi</button>
                    @if ($guest->rsvp_status?->value !== 'pending')
                        <button type="button" @click="showForm = false" class="btn btn-secondary">Batal</button>
                    @endif
                </div>
            </form>
        </div>
    @endif

    <!-- Guestbook -->
    @if (!$preview && $guestbookEnabled)
        <div class="card p-6 mb-10" id="guestbook" x-data="{ message: '', emoji: '' }">
            <p class="section-kicker mb-4">Ucapan & Doa Restu</p>
            <form method="POST" action="{{ route('guest.guestbook.store', $token) }}" class="space-y-4">
                @csrf
                <div>
                    <label class="label">Pesan Ucapan</label>
                    <textarea name="message" placeholder="Tuliskan ucapan dan doa hangat untuk kedua mempelai..." class="input" rows="3" x-model="message" required></textarea>
                </div>
                <div>
                    <label class="label">Sertakan Emoji</label>
                    <div class="flex flex-wrap gap-2">
                        @foreach (['❤️', '✨', '😊', '🎉', '🥂', '💍', '🕊️', '🌸'] as $em)
                            <button type="button" class="btn btn-secondary btn-sm text-base h-9 w-9 p-0 rounded-xl"
                                    :class="emoji === '{{ $em }}' ? 'border-accent bg-accent/20' : ''"
                                    @click="emoji = emoji === '{{ $em }}' ? '' : '{{ $em }}'">
                                {{ $em }}
                            </button>
                        @endforeach
                    </div>
                    <input type="hidden" name="emoji" :value="emoji" />
                </div>
                <button type="submit" class="btn btn-primary w-full">Kirim Ucapan</button>
            </form>

            <p class="section-kicker mt-6">Pesan Terkini</p>
            <div class="space-y-3">
                @forelse ($messages as $message)
                    <div class="flex items-start gap-3">
                        <x-ui.avatar :name="$message->sender_name" class="h-8 w-8 rounded-full" />
                        <div class="flex-1">
                            <p class="font-semibold text-ink">{{ $message->sender_name }}</p>
                            <p class="text-sm text-muted mt-1">{{ $message->message }}</p>
                            @if ($message->emoji)
                                <span class="text-2xl mt-0.5">{{ $message->emoji }}</span>
                            @endif
                            <p class="text-[10px] text-faint mt-1">{{ $message->created_at?->diffForHumans() }}</p>
                        </div>
                    </div>
                @empty
                    <x-ui.empty-state icon="message" title="Belum ada pesan" description="Kirim pesan pertama Anda di bawah." />
                @endforelse
            </div>
        </div>
    @endif

    <!-- Gallery preview -->
    @if (!$preview && $galleryEnabled && $allowsGuestActions)
        <div class="card p-6 mb-10">
            <p class="section-kicker mb-4">Galeri Foto</p>
            <div class="grid grid-cols-2 gap-3">
                @foreach ($photos as $photo)
                    <a href="{{ route('guest.gallery', $token) }}" class="group">
                        <img src="{{ $photo->thumbnailUrl() }}" alt="" class="h-20 w-20 object-cover rounded-2xl transition hover:scale-105">
                        <div class="absolute bottom-0 right-0 bg-surface-2/80 p-1 rounded-tl rounded-br">
                            <p class="text-[9px] text-ink">{{ $photo->caption ?? '' }}</p>
                        </div>
                    </a>
                @endforeach
            </div>
            @if ($photos->isEmpty())
                <x-ui.empty-state icon="image" title="Belum ada foto" description="Tunggu foto resmi dipublikasikan." />
            @endif
        </div>
    @endif

    <!-- Gift teaser -->
    @if (!$preview && $giftEnabled && $gifts->isNotEmpty())
        <div class="card p-6 mb-10">
            <p class="section-kicker mb-4">Hadiah Pernikahan</p>
            <p class="prose-wedding">Terima kasih telah hadir. Hadiah ini adalah bentuk apresiasi kami atas kehadiran Anda.</p>
            <div class="mt-6">
                <a href="{{ route('guest.gift', $token) }}" class="block w-full btn-soft text-center">
                    <x-ui.icon name="gift" class="h-6 w-6 mr-2" /> Hadiah Pernikahan
                </a>
            </div>
        </div>
    @endif

    <!-- Poll card -->
    @if (!$preview && $poll && $event->allowsGuestActions())
        <div class="card p-6 mb-10" id="poll">
            <p class="section-kicker mb-4">Poll Ucapan</p>
            @if ($myPollResponse)
                <div class="flex items-start gap-3">
                    <span class="badge badge-success text-sm">Anda memilih: {{ $myPollResponse->option?->label }}</span>
                    <div class="flex-1 space-y-2">
                        @foreach ($poll->options as $option)
                            <div class="flex items-center gap-2 w-full">
                                <span class="text-[11px] font-semibold text-ink w-1/3">{{ $option->label }}</span>
                                <div class="flex-1 h-2 bg-surface-2/60 rounded-full overflow-hidden">
                                    <div class="h-full bg-accent rounded-full" style="width: {{ $option->responses()->count() * 10 }}%"></div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            @else
                <form method="POST" action="{{ route('guest.poll.vote', [$token, $poll]) }}" class="space-y-4">
                    @csrf
                    <p class="section-kicker mb-4">Pilih Opsi</p>
                    @foreach ($poll->options as $option)
                        <label class="flex flex-col items-center gap-2 rounded-2xl border border-line bg-surface px-3 py-3 text-sm font-medium text-muted cursor-pointer transition peer peer-checked:bg-blue-soft peer-checked:border-accent peer-checked:text-ink">
                            <input type="radio" name="poll_option_id" value="{{ $option->id }}" class="peer sr-only" required>
                            <span class="text-[10px] font-semibold uppercase tracking-wider">{{ $option->label }}</span>
                        </label>
                    @endforeach
                    <button type="submit" class="btn-primary">Kirim Suara Anda</button>
                </form>
            @endif
        </div>
    @endif

    <!-- Game teaser -->
    @if (!$preview && $game && $event->allowsGuestActions())
        <div class="card p-6 mb-10" id="game">
            <p class="section-kicker mb-4">Game {{ $game->title }}</p>
            <a href="{{ route('guest.game.show', [$token, $game]) }}" class="block w-full btn-soft text-center">
                <x-ui.icon name="game" class="h-6 w-6 mr-2" /> Mainkan {{ $game->title }}
            </a>
        </div>
    @endif

    <!-- Share section -->
    @if ($shareUrl)
    <div class="card p-6 mb-10">
        <p class="section-kicker mb-4">Bagikan Undangan</p>
        <div class="flex items-center gap-4">
            <a href="{{ $shareUrl }}" target="_blank" rel="noopener" class="flex items-center gap-2">
                <x-ui.icon name="share" class="h-6 w-6 mr-2" />
                <span class="text-sm font-semibold text-ink">Bagikan ke WhatsApp</span>
            </a>
            <button type="button" class="btn-ghost" x-on:click="copyLink">
                <x-ui.icon name="copy" class="h-6 w-6 mr-2" />
                <span class="text-sm text-ink">Salin Link</span>
            </button>
        </div>
        <script>
            document.addEventListener('click', function(e) {
                if (e.target.closest('[x-on:click="copyLink"]')) {
                    const url = "{{ $shareUrl }}";
                    navigator.clipboard.writeText(url).then(() => {
                        Swal.fire({
                            toast: true,
                            position: 'top-end',
                            icon: 'success',
                            title: 'Link disalin ke clipboard',
                            showConfirmButton: false,
                            timer: 1800,
                        });
                    }).catch(() => {
                        Swal.fire('Gagal', 'Salin manual: {{ $shareUrl }}', 'error');
                    });
                }
            });
        </script>
    </div>
    @endif

    <p class="text-center text-[11px] text-faint">Dibuat dengan tenang oleh <span class="font-semibold text-ink">VOWSPACE</span></p>
</div>
@endsection