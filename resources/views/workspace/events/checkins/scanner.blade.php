@extends('layouts.owner')

@section('title', 'Scanner QR Check-in')

@section('content')
    @php
        $selectedEventId = $selectedEventId ?? $event->id;
    @endphp

    <div class="space-y-6"
         x-data="scanner"
         data-selected-event-id="{{ $selectedEventId }}"
         data-scan-url="{{ route('workspace.events.checkins.scan', [$organization, $event]) }}">
        @include('workspace.events.partials.event-tabs', ['organization' => $organization, 'event' => $event, 'active' => 'overview'])

        <x-ui.page-header
            eyebrow="Check-in"
            title="Scanner QR Check-in"
            subtitle="Arahkan kamera ke QR tamu. Hasil scan akan diproses secara langsung dan ditampilkan di bawah."
        >
            <a href="{{ route('workspace.events.checkins.index', [$organization, $event]) }}" class="btn btn-secondary">
                <x-ui.icon name="arrow-left" class="h-4 w-4" /> Kembali ke Riwayat
            </a>
        </x-ui.page-header>

        <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-3">
            <div class="rounded-2xl border border-line/70 bg-surface-2/40 px-3 py-2.5">
                <div class="flex items-center gap-2 text-[11px] font-semibold uppercase tracking-[0.1em] text-muted">
                    <x-ui.icon name="list" class="h-3.5 w-3.5 text-accent" /> Undangan
                </div>
                <p class="mt-1 text-xl font-semibold text-ink">{{ $stats['invitations']['parties'] ?? 0 }}</p>
            </div>
            <div class="rounded-2xl border border-line/70 bg-surface-2/40 px-3 py-2.5">
                <div class="flex items-center gap-2 text-[11px] font-semibold uppercase tracking-[0.1em] text-muted">
                    <x-ui.icon name="user-check" class="h-3.5 w-3.5 text-accent" /> Tamu
                </div>
                <p class="mt-1 text-xl font-semibold text-ink">{{ $stats['guests']['identified'] ?? 0 }}</p>
            </div>
            <div class="rounded-2xl border border-line/70 bg-surface-2/40 px-3 py-2.5">
                <div class="flex items-center gap-2 text-[11px] font-semibold uppercase tracking-[0.1em] text-muted">
                    <x-ui.icon name="check" class="h-3.5 w-3.5 text-success" /> RSVP
                </div>
                <p class="mt-1 text-xl font-semibold text-ink">{{ $stats['rsvp']['confirmed'] ?? 0 }}</p>
            </div>
            <div class="rounded-2xl border border-line/70 bg-surface-2/40 px-3 py-2.5">
                <div class="flex items-center gap-2 text-[11px] font-semibold uppercase tracking-[0.1em] text-muted">
                    <x-ui.icon name="users" class="h-3.5 w-3.5 text-accent" /> Check-in
                </div>
                <p class="mt-1 text-xl font-semibold text-ink">{{ $stats['checkins']['total'] ?? 0 }}</p>
            </div>
            <div class="rounded-2xl border border-line/70 bg-surface-2/40 px-3 py-2.5">
                <div class="flex items-center gap-2 text-[11px] font-semibold uppercase tracking-[0.1em] text-muted">
                    <x-ui.icon name="clock" class="h-3.5 w-3.5 text-warning" /> Belum
                </div>
                <p class="mt-1 text-xl font-semibold text-ink">{{ $stats['checkins']['not_checked_in'] ?? 0 }}</p>
            </div>
        </div>

        <div id="reader"
             class="flex min-h-[360px] w-full flex-col items-center justify-center gap-3 rounded-3xl border-2 border-dashed border-line bg-surface/90 p-6 text-center">
            <div x-show="!isScanning" class="flex flex-col items-center gap-2 text-center">
                <x-ui.icon name="qr" class="h-12 w-12 text-faint" />
                <p class="text-sm font-semibold text-ink">Kamera belum aktif</p>
                <button type="button" class="btn btn-primary btn-lg" x-on:click="startCamera" :disabled="isScanning">
                    <x-ui.icon name="camera" class="h-6 w-6" /> Mulai Kamera
                </button>
            </div>

            <div x-show="isScanning" class="flex flex-col items-center gap-2 text-center">
                <p class="text-xs text-muted">Memindai QR... Arahkan kamera ke kode QR tamu.</p>
                <button type="button" class="btn btn-secondary btn-lg" x-on:click="stopCamera">
                    <x-ui.icon name="close" class="h-4 w-4" /> Hentikan
                </button>
            </div>
        </div>

        <div class="card mt-4 p-5">
            <div class="flex items-center gap-3">
                <input type="text" id="manualToken" placeholder="Tempel token QR..." class="input w-full max-w-md" x-model="manualToken" @keydown.enter.window="pasteToken()" aria-label="Token QR manual">
                <button type="button" class="btn btn-secondary" x-on:click="pasteToken">
                    <x-ui.icon name="send" class="h-4 w-4" /> Kirim
                </button>
            </div>
            <p class="hint">Tip: tekan Enter di mana saja untuk memproses token yang ditempel.</p>
        </div>

        <div x-show="lastResult"
             class="card mt-4 p-4"
             :class="lastResult.status === 'success' ? 'border-success/30 bg-success/5' : lastResult.status === 'duplicate' ? 'border-warning/30 bg-warning/5' : 'border-danger/30 bg-danger/5'">
            <div class="flex items-start gap-3">
                <span class="mt-0.5 shrink-0" :class="lastResult.status === 'success' ? 'text-success' : lastResult.status === 'duplicate' ? 'text-warning' : 'text-danger'">
                    <x-ui.icon name="alert" class="h-5 w-5" />
                </span>
                <div class="min-w-0 flex-1">
                    <p class="font-semibold text-ink" x-text="lastResult.message"></p>
                    <template x-if="lastResult.warning">
                        <p class="mt-1 text-xs text-warning" x-text="lastResult.warning"></p>
                    </template>
                    <template x-if="lastResult.guest">
                        <p class="mt-1 text-xs text-muted" x-text="lastResult.guest.name"></p>
                    </template>
                    <p class="mt-0.5 text-[11px] text-faint"><span x-text="lastResult.time"></span></p>
                </div>
            </div>
        </div>

        <div x-init="$watch('lastResult', (val) => { if (val && val.status === 'success') releaseCamera(); })"></div>

        {{-- Inline result panel: invalid / duplicate / success --}}
        <div x-show="lastResult"
             class="card mt-4 p-4"
             :class="lastResult.status === 'success' ? 'border-success/30 bg-success/5' : lastResult.status === 'duplicate' ? 'border-warning/30 bg-warning/5' : 'border-danger/30 bg-danger/5'">
            <div class="flex items-start gap-3">
                <span class="mt-0.5 shrink-0" :class="lastResult.status === 'success' ? 'text-success' : lastResult.status === 'duplicate' ? 'text-warning' : 'text-danger'">
                    <x-ui.icon name="alert" class="h-5 w-5" />
                </span>
                <div class="min-w-0 flex-1">
                    <p class="font-semibold text-ink" x-text="lastResult.message"></p>
                    <template x-if="lastResult.warning">
                        <p class="mt-1 text-xs text-warning" x-text="lastResult.warning"></p>
                    </template>
                    <template x-if="lastResult.guest">
                        <p class="mt-1 text-xs text-muted" x-text="lastResult.guest.name"></p>
                    </template>
                    <p class="mt-0.5 text-[11px] text-faint"><span x-text="lastResult.time"></span></p>
                </div>
            </div>
        </div>

        {{-- Auto-stop camera once a successful result lands --}}
        <div x-init="$watch('lastResult', (val) => { if (val && val.status === 'success') releaseCamera(); })"></div>

        {{-- Confirmation popup on success --}}
        <div x-show="lastResult && lastResult.status === 'success'"
             x-cloak
             x-transition:enter="transition ease-out duration-200"
             x-transition:enter-start="opacity-0 translate-y-4"
             x-transition:enter-end="opacity-100 translate-y-0"
             class="fixed inset-x-4 bottom-4 z-50 mx-auto max-w-md sm:inset-x-auto sm:right-6">
            <div class="flex items-center gap-3 rounded-2xl border border-success/30 bg-surface p-4 shadow-soft">
                <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl bg-success/15 text-success">
                    <x-ui.icon name="check" class="h-6 w-6" />
                </span>
                <div class="min-w-0">
                    <p class="text-sm font-semibold text-ink" x-text="lastResult.message"></p>
                    <template x-if="lastResult.guest">
                        <p class="truncate text-xs text-muted" x-text="lastResult.guest.name"></p>
                    </template>
                    <p class="mt-0.5 text-[11px] text-faint"><span x-text="lastResult.time"></span></p>
                </div>
                <button type="button" class="icon-btn ml-auto h-8 w-8 !rounded-lg" @click="lastResult = null" aria-label="Tutup">
                    <x-ui.icon name="close" class="h-3.5 w-3.5" />
                </button>
            </div>
        </div>

        {{-- Session log --}}
        <div class="card mt-6 p-0">
            <div class="flex items-center justify-between gap-2 border-b border-line/70 px-5 py-4">
                <div>
                    <h2 class="section-title">Log Scan</h2>
                    <p class="mt-0.5 text-xs text-faint"><span x-text="scans.length">0</span> pemindaian dalam sesi ini.</p>
                </div>
                <button type="button" class="btn btn-ghost btn-sm" x-on:click="clearLog">
                    <x-ui.icon name="trash" class="h-4 w-4" /> Kosongkan
                </button>
            </div>

            <div class="p-4 max-h-[300px] overflow-y-auto space-y-2">
                <template x-for="scan in scans" :key="scan.id">
                    <div class="flex items-start gap-3 p-2 rounded-xl border border-line/60 bg-surface/60" :class="scan.status === 'success' ? 'border-success/25 bg-success/5' : scan.status === 'duplicate' ? 'border-warning/25 bg-warning/5' : 'border-danger/25 bg-danger/5'">
                        <x-ui.icon name="clock" class="mt-0.5 h-4 w-4 shrink-0 text-faint" />
                        <div class="min-w-0 flex-1">
                            <p class="text-xs font-semibold text-ink" x-text="scan.message"></p>
                            <p class="text-[10px] text-faint"><span x-text="scan.time"></span> · <span x-text="scan.status"></span></p>
                        </div>
                        <span class="text-[10px] font-semibold" :class="scan.status === 'success' ? 'text-success' : scan.status === 'duplicate' ? 'text-warning' : 'text-danger'" x-text="scan.status"></span>
                    </div>
                </template>
                <div x-show="scans.length === 0" class="p-4 text-center text-xs text-faint">Belum ada pemindaian.</div>
            </div>
        </div>
    </div>
@endsection
