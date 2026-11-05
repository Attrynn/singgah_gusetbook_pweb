@extends('layouts.admin')

@section('title', 'Scanner QR')
@section('subtitle', 'Scan QR tamu untuk check-in cepat')

@section('content')
    @php
        $selectedEventId = $selectedEventId ?? null;
    @endphp

    <div class="space-y-6">
        <x-ui.page-header
            eyebrow="Guests"
            title="Scanner QR Check-in"
            subtitle="Arahkan kamera ke QR tamu. Hasil scan akan diproses secara langsung."
        >
            <a href="{{ route('admin.checkins.index', ['event_id' => $selectedEventId]) }}" class="btn-secondary">
                <x-ui.icon name="arrow-left" class="h-4 w-4" /> Kembali ke Riwayat
            </a>
        </x-ui.page-header>

        <div class="grid gap-6 lg:grid-cols-12">
            <div class="lg:col-span-7 space-y-4">
                <div class="card p-0">
                    <div class="flex items-center justify-between gap-2 border-b border-line/70 px-5 py-4">
                        <div>
                            <h2 class="section-title">Kamera</h2>
                            <p class="mt-0.5 text-xs text-faint">Scan QR tamu untuk check-in.</p>
                        </div>
                        <div class="flex items-center gap-2">
                            <select id="event_id" name="event_id" class="input appearance-none" x-model="selectedEventId">
                                <option value="">Semua event</option>
                                @foreach ($events as $event)
                                    <option value="{{ $event->id }}" @selected($selectedEventId === $event->id)>
                                        {{ $event->coupleNames() }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <div class="p-5">
                        <div class="flex flex-col items-center justify-center gap-3 rounded-3xl border-2 border-dashed border-line bg-surface/60 p-4" id="reader" style="width: 100%; max-width: 480px; min-height: 320px;">
                            <div x-show="!isScanning" class="flex flex-col items-center gap-2 text-center">
                                <x-ui.icon name="qr" class="h-12 w-12 text-faint" />
                                <p class="text-sm font-semibold text-ink">Kamera belum aktif</p>
                                <button type="button" class="btn-primary" x-on:click="startCamera" :disabled="isScanning">
                                    <x-ui.icon name="camera" class="h-4 w-4" /> Mulai Kamera
                                </button>
                            </div>

                            <div x-show="isScanning" class="flex flex-col items-center gap-2 text-center">
                                <p class="text-xs text-muted">Memindai…</p>
                                <button type="button" class="btn-danger btn-sm" x-on:click="stopCamera">
                                    <x-ui.icon name="close" class="h-4 w-4" /> Hentikan
                                </button>
                            </div>
                        </div>

                        <div class="mt-4 flex items-center gap-3">
                            <input type="text" id="manualToken" placeholder="Tempel token QR…" class="input flex-1" x-model="manualToken" @keydown.enter="pasteToken">
                            <button type="button" class="btn-secondary" x-on:click="pasteToken">
                                <x-ui.icon name="send" class="h-4 w-4" /> Kirim
                            </button>
                        </div>

                        <p class="hint">Pastikan kamera diizinkan oleh browser. Hasil scan akan ditampilkan di bawah.</p>
                    </div>
                </div>

                <div class="card p-0">
                    <div class="flex items-center justify-between gap-2 border-b border-line/70 px-5 py-4">
                        <div>
                            <h2 class="section-title">Log Scan</h2>
                            <p class="mt-0.5 text-xs text-faint"><span x-text="scans.length">0</span> pemindaian dalam sesi ini.</p>
                        </div>
                        <button type="button" class="btn-ghost btn-sm" x-on:click="clearLog">
                            <x-ui.icon name="trash" class="h-4 w-4" /> Kosongkan
                        </button>
                    </div>

                    <div class="p-4 max-h-[300px] overflow-y-auto space-y-2">
                        <template x-for="scan in scans" :key="scan.id">
                            <div class="flex items-start gap-3 p-2 rounded-xl border border-line/60 bg-surface/60" :class="scan.status === 'success' ? 'border-success/25 bg-success/5' : 'border-danger/25 bg-danger/5'">
                                <x-ui.icon name="clock" class="mt-0.5 h-4 w-4 shrink-0 text-faint" />
                                <div class="min-w-0 flex-1">
                                    <p class="text-xs font-semibold text-ink" x-text="scan.message"></p>
                                    <p class="text-[10px] text-faint"><span x-text="scan.time"></span> · <span x-text="scan.status"></span></p>
                                    @if ($selectedEventId)
                                        <p class="text-[10px] text-faint">Event: {{ $selectedEventId }}</p>
                                    @endif
                                </div>
                                <span class="text-[10px] font-semibold" :class="scan.status === 'success' ? 'text-[#3f8069]' : 'text-[#a35b64]'" x-text="scan.status"></span>
                            </div>
                        </template>
                        <div x-show="scans.length === 0" class="p-4 text-center text-xs text-faint">Belum ada pemindaian.</div>
                    </div>
                </div>
            </div>

            <div class="lg:col-span-5 space-y-4">
                <div class="card p-0">
                    <div class="flex items-center justify-between gap-2 border-b border-line/70 px-5 py-4">
                        <div>
                            <h2 class="section-title">Hasil Terbaru</h2>
                            <p class="mt-0.5 text-xs text-faint">Detail hasil pemindaian terakhir.</p>
                        </div>
                    </div>

                    <div class="p-5">
                        <div x-show="!lastResult" class="flex flex-col items-center justify-center py-8 text-center">
                            <x-ui.icon name="qr" class="h-10 w-10 text-faint" />
                            <p class="mt-2 text-sm text-muted">Belum ada hasil scan</p>
                        </div>

                        <div x-show="lastResult" class="space-y-3 animate-float-in">
                            <div class="flex items-start gap-3">
                                <x-ui.icon name="alert" class="mt-0.5 h-4 w-4 shrink-0" x-bind:class="lastResult.status === 'success' ? 'text-[#3f8069]' : 'text-[#a35b64]'" />
                                <div class="min-w-0 flex-1">
                                    <p class="font-semibold text-ink" x-text="lastResult.message"></p>
                                    <p class="mt-0.5 text-xs text-faint" x-text="lastResult.status"></p>
                                    <template x-if="lastResult.warning">
                                        <p class="mt-1 text-xs text-[#8f6b2f]" x-text="lastResult.warning"></p>
                                    </template>
                                </div>
                            </div>

                            <template x-if="lastResult.guest">
                                <div class="pt-3 border-t border-line/60">
                                    <p class="text-[11px] font-semibold uppercase tracking-[0.12em] text-muted">Tamu</p>
                                    <div class="flex items-center gap-3 mt-1">
                                        <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-2xl bg-blue-soft text-xs font-semibold text-[#4f7592]" x-text="(lastResult.guest.name || '?').charAt(0).toUpperCase()"></span>
                                        <div class="min-w-0">
                                            <p class="font-semibold text-ink" x-text="lastResult.guest.name"></p>
                                            <p class="text-[11px] text-faint" x-text="lastResult.guest.rsvp"></p>
                                        </div>
                                    </div>
                                    <p class="mt-1 text-xs text-muted" x-show="lastResult.guest.checked_in_at">
                                        Check-in terakhir: <span x-text="lastResult.guest.checked_in_at"></span>
                                    </p>
                                </div>
                            </template>
                        </div>
                    </div>
                </div>

                <div class="card p-0">
                    <div class="p-5">
                        <h3 class="section-title">Panduan Izin Kamera</h3>
                        <ul class="mt-3 space-y-2 text-sm text-muted">
                            <li class="flex items-start gap-2">
                                <x-ui.icon name="chevron-right" class="mt-0.5 h-3 w-3 text-faint" />
                                <span>Klik <strong>Mulai Kamera</strong> dan izinkan akses kamera saat browser meminta izin.</span>
                            </li>
                            <li class="flex items-start gap-2">
                                <x-ui.icon name="chevron-right" class="mt-0.5 h-3 w-3 text-faint" />
                                <span>Pastikan QR tamu terang dan seluruh kotak QR terlihat dalam layar kamera.</span>
                            </li>
                            <li class="flex items-start gap-2">
                                <x-ui.icon name="chevron-right" class="mt-0.5 h-3 w-3 text-faint" />
                                <span>Jika kamera tidak tersedia, tempel token QR secara manual di kolom di atas.</span>
                            </li>
                            <li class="flex items-start gap-2">
                                <x-ui.icon name="chevron-right" class="mt-0.5 h-3 w-3 text-faint" />
                                <span>Pilih event di atas untuk membatasi scan hanya pada acara tersebut.</span>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>

    @push('scripts')
        <script>
            (function () {
                const csrfToken = document.querySelector('meta[name="csrf-token"]').content;
                const scanUrl = '{{ route('admin.checkins.scan') }}';

                Alpine.data('scanner', () => ({
                    isScanning: false,
                    scanner: null,
                    selectedEventId: {{ $selectedEventId ?? 'null' }},
                    manualToken: '',
                    scans: [],
                    lastResult: null,

                    init() {
                        this.selectedEventId = {{ $selectedEventId ?? 'null' }};
                    },

                    async startCamera() {
                        if (this.isScanning) return;
                        try {
                            this.scanner = new Html5Qrcode('reader');
                            await this.scanner.start(
                                { facingMode: 'environment' },
                                { fps: 10, qrbox: 250 },
                                (decodedText) => this.handleScan(decodedText),
                                (errorMessage) => { /* ignore per-frame errors */ }
                            );
                            this.isScanning = true;
                        } catch (err) {
                            Swal.fire('Kamera tidak tersedia', 'Pastikan kamera diizinkan dan tersedia. Anda masih dapat memasukkan token secara manual.', 'error');
                        }
                    },

                    stopCamera() {
                        if (!this.scanner || !this.isScanning) return;
                        this.scanner.stop().then(() => {
                            this.scanner.clear();
                            this.isScanning = false;
                        }).catch(() => {
                            this.isScanning = false;
                        });
                    },

                    async handleScan(token) {
                        this.stopCamera();
                        await this.submitToken(token);
                    },

                    async pasteToken() {
                        const token = (this.manualToken || '').trim();
                        if (!token) return;
                        this.manualToken = '';
                        await this.submitToken(token);
                    },

                    async submitToken(token) {
                        try {
                            const res = await fetch(scanUrl, {
                                method: 'POST',
                                headers: {
                                    'Content-Type': 'application/json',
                                    'Accept': 'application/json',
                                    'X-CSRF-TOKEN': csrfToken,
                                },
                                body: JSON.stringify({ token, event_id: this.selectedEventId || undefined }),
                            });
                            const data = await res.json();
                            this.addResult(data);
                        } catch (err) {
                            this.addResult({ status: 'error', message: 'Gagal menghubungi server.', time: nowStr() });
                        }
                    },

                    addResult(data) {
                        const time = nowStr();
                        const entry = {
                            id: Date.now(),
                            status: data.status || 'error',
                            message: data.message || 'Tidak ada respons.',
                            time,
                            warning: data.warning || null,
                        };
                        this.scans.unshift(entry);
                        this.lastResult = { ...entry, guest: data.guest || null };
                    },

                    clearLog() {
                        this.scans = [];
                        this.lastResult = null;
                    },
                }));

                function nowStr() {
                    const d = new Date();
                    return d.toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' });
                }

                window.addEventListener('beforeunload', () => {
                    const data = Alpine.store('scanner');
                });
            })();
        </script>
    @endpush
@endsection
