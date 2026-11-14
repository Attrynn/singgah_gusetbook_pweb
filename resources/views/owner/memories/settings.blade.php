@extends('layouts.owner')

@section('title', 'Pengaturan Kenangan')

@section('content')
    <div class="mb-5">
        <a href="{{ route('owner.memories.index') }}" class="text-accent text-sm inline-flex items-center gap-1 hover:underline">
            <x-ui.icon name="chevron-left" class="h-4 w-4" />
            Kembali ke kenangan
        </a>
    </div>

    <x-ui.page-header title="Pengaturan Kenangan" subtitle="Atur jendela pengiriman, persetujuan otomatis, retensi, dan berbagi publik.">
        <x-ui.badge :variant="$settings->submissionsClosed() ? 'warning' : 'success'" class="text-[10px]">
            {{ $settings->submissionsClosed() ? 'Pengiriman ditutup' : 'Pengiriman dibuka' }}
        </x-ui.badge>
    </x-ui.page-header>

    @php
        $shareState = $settings->sharingActive()
            ? ['badge-success', 'Aktif']
            : ($settings->share_token_revoked_at !== null
                ? ['badge-danger', 'Dicabut']
                : ($settings->share_token_expires_at !== null && $settings->share_token_expires_at->isPast()
                    ? ['badge-warning', 'Kedaluwarsa']
                    : ['badge-muted', 'Belum aktif']));
    @endphp

    {{-- Sections 1 & 2 share one PUT form; section 3 owns its separate forms --}}
    <form method="POST" action="{{ route('owner.memories.settings.update') }}" class="space-y-5">
        @csrf
        @method('PUT')

        {{-- 1. Submission window --}}
        <section class="card p-5" aria-labelledby="memory-window-heading">
            <p id="memory-window-heading" class="section-kicker">Jendela pengiriman</p>

            <div class="mt-3 grid grid-cols-1 gap-4 sm:grid-cols-2">
                <x-ui.input name="submission_opens_at" label="Dibuka pada" type="datetime-local" :value="old('submission_opens_at', $settings->submission_opens_at?->format('Y-m-d\TH:i'))" hint="Kosongkan untuk membuka pengiriman segera." />
                <x-ui.input name="submission_closes_at" label="Ditutup pada" type="datetime-local" :value="old('submission_closes_at', $settings->submission_closes_at?->format('Y-m-d\TH:i'))" hint="Waktu penutupan tidak boleh lebih awal dari waktu pembukaan." />
            </div>

            <div class="mt-4 grid grid-cols-1 gap-3 sm:grid-cols-2">
                <div>
                    <input type="hidden" name="submissions_open" value="0">
                    <label for="submissions_open" class="flex cursor-pointer items-center justify-between gap-4 rounded-2xl border border-line/70 bg-surface-2/40 px-4 py-3 transition hover:border-accent/40">
                        <span class="text-sm font-medium text-ink">Terima pengiriman kenangan</span>
                        <span class="relative inline-flex h-6 w-11 shrink-0">
                            <input id="submissions_open" type="checkbox" name="submissions_open" value="1" class="peer sr-only" @if ($settings->submissions_open) checked @endif>
                            <span aria-hidden="true" class="absolute inset-0 rounded-full bg-surface-3 transition-colors peer-checked:bg-accent peer-focus-visible:ring-2 peer-focus-visible:ring-accent/50 peer-focus-visible:ring-offset-2 peer-focus-visible:ring-offset-surface"></span>
                            <span aria-hidden="true" class="absolute left-0.5 top-0.5 h-5 w-5 rounded-full bg-surface shadow-sm transition-transform peer-checked:translate-x-5"></span>
                        </span>
                    </label>
                    @error('submissions_open')
                        <p class="error-text">{{ $message }}</p>
                    @enderror
                </div>
                <div>
                    <input type="hidden" name="post_event_submissions" value="0">
                    <label for="post_event_submissions" class="flex cursor-pointer items-center justify-between gap-4 rounded-2xl border border-line/70 bg-surface-2/40 px-4 py-3 transition hover:border-accent/40">
                        <span class="text-sm font-medium text-ink">Izinkan pengiriman setelah acara selesai</span>
                        <span class="relative inline-flex h-6 w-11 shrink-0">
                            <input id="post_event_submissions" type="checkbox" name="post_event_submissions" value="1" class="peer sr-only" @if ($settings->post_event_submissions) checked @endif>
                            <span aria-hidden="true" class="absolute inset-0 rounded-full bg-surface-3 transition-colors peer-checked:bg-accent peer-focus-visible:ring-2 peer-focus-visible:ring-accent/50 peer-focus-visible:ring-offset-2 peer-focus-visible:ring-offset-surface"></span>
                            <span aria-hidden="true" class="absolute left-0.5 top-0.5 h-5 w-5 rounded-full bg-surface shadow-sm transition-transform peer-checked:translate-x-5"></span>
                        </span>
                    </label>
                    @error('post_event_submissions')
                        <p class="error-text">{{ $message }}</p>
                    @enderror
                </div>
            </div>

            <div class="mt-4">
                <x-ui.input name="retention_days" label="Retensi (hari)" type="number" min="1" max="3650" :value="old('retention_days', $settings->retention_days)" hint="Arsip kenangan dihapus setelah periode ini." />
            </div>
        </section>

        {{-- 2. Moderation --}}
        <section class="card p-5" aria-labelledby="memory-moderation-heading">
            <p id="memory-moderation-heading" class="section-kicker">Moderasi</p>

            <div class="mt-3">
                <input type="hidden" name="auto_approve" value="0">
                <label for="auto_approve" class="flex cursor-pointer items-center justify-between gap-4 rounded-2xl border border-line/70 bg-surface-2/40 px-4 py-3 transition hover:border-accent/40">
                    <span class="text-sm font-medium text-ink">Setujui kontribusi otomatis</span>
                    <span class="relative inline-flex h-6 w-11 shrink-0">
                        <input id="auto_approve" type="checkbox" name="auto_approve" value="1" class="peer sr-only" @if ($settings->auto_approve) checked @endif>
                        <span aria-hidden="true" class="absolute inset-0 rounded-full bg-surface-3 transition-colors peer-checked:bg-accent peer-focus-visible:ring-2 peer-focus-visible:ring-accent/50 peer-focus-visible:ring-offset-2 peer-focus-visible:ring-offset-surface"></span>
                        <span aria-hidden="true" class="absolute left-0.5 top-0.5 h-5 w-5 rounded-full bg-surface shadow-sm transition-transform peer-checked:translate-x-5"></span>
                    </span>
                </label>
                <p class="hint">Persetujuan otomatis melewati antrian pemeriksaan. Konten tetap tunduk pada batas retensi.</p>
                @error('auto_approve')
                    <p class="error-text">{{ $message }}</p>
                @enderror
            </div>
        </section>

        <div class="flex flex-wrap items-center gap-2">
            <button type="submit" class="btn btn-primary">Simpan pengaturan</button>
            @if ($settings->submissionsClosed())
                <button type="submit" form="reopen-submissions" class="btn btn-soft">Buka kembali pengiriman</button>
            @else
                <button type="submit" form="close-submissions" class="btn btn-ghost">Tutup pengiriman</button>
            @endif
        </div>
    </form>

    {{-- Close / reopen standalone forms --}}
    <form id="close-submissions" method="POST" action="{{ route('owner.memories.settings.close') }}" class="hidden" data-confirm data-confirm-title="Tutup pengiriman?" data-confirm-text="Tamu tidak dapat mengirim kenangan baru sampai pengiriman dibuka kembali.">
        @csrf
    </form>
    <form id="reopen-submissions" method="POST" action="{{ route('owner.memories.settings.reopen') }}" class="hidden" data-confirm data-confirm-title="Buka kembali pengiriman?" data-confirm-text="Tamu dapat mengirim kenangan baru kembali.">
        @csrf
    </form>

    {{-- 3. Public sharing: the risk-bearing block --}}
    <section class="card p-5" aria-labelledby="memory-sharing-heading">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div class="min-w-0">
                <p id="memory-sharing-heading" class="section-kicker">Berbagi publik</p>
                <div class="mt-2 flex flex-wrap items-center gap-2">
                    <x-ui.badge :variant="$shareState[0]" class="text-[10px]">{{ $shareState[1] }}</x-ui.badge>
                    <p class="text-sm text-muted">
                        @if ($settings->sharingActive())
                            Tautan berbagi aktif dengan awalan token <b class="text-ink">{{ $shareTokenPrefix }}</b>.
                        @else
                            Belum ada tautan berbagi yang aktif.
                        @endif
                    </p>
                </div>
                <p class="mt-1 text-[10px] text-faint">Menonaktifkan berbagi mencabut tautan yang sudah Anda bagikan sebelumnya.</p>
            </div>
            <div class="flex shrink-0 items-center gap-2">
                @if ($settings->sharingActive())
                    <form method="POST" action="{{ route('owner.memories.share.revoke') }}" data-confirm data-confirm-title="Nonaktifkan berbagi?" data-confirm-text="Tautan berbagi yang sudah dibagikan tidak akan lagi berfungsi.">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn btn-danger btn-sm">
                            <x-ui.icon name="lock" class="h-4 w-4 shrink-0" />
                            Cabut tautan
                        </button>
                    </form>
                @else
                    <form method="POST" action="{{ route('owner.memories.share.enable') }}">
                        @csrf
                        <button type="submit" class="btn btn-primary btn-sm">
                            <x-ui.icon name="share" class="h-4 w-4 shrink-0" />
                            Aktifkan berbagi
                        </button>
                    </form>
                @endif
            </div>
        </div>

        {{-- One-time token --}}
        @if (session('share_token'))
            <div class="mt-4 rounded-2xl border border-success/30 bg-success/10 p-4">
                <p class="text-xs font-semibold text-success">Tautan berbagi dibuat. Simpan sekarang; token hanya ditampilkan sekali.</p>
                <div class="mt-3 space-y-3">
                    <div>
                        <p class="text-[10px] font-semibold uppercase tracking-[0.12em] text-muted">Token</p>
                        <div class="mt-1 flex items-center gap-2">
                            <code class="min-w-0 flex-1 break-all rounded-xl border border-line bg-surface px-3 py-2 font-mono text-xs text-ink">{{ $shareToken }}</code>
                            <button type="button" class="icon-btn h-9 w-9 shrink-0" data-copy="{{ $shareToken }}" aria-label="Salin token">
                                <x-ui.icon name="copy" class="h-4 w-4" />
                            </button>
                        </div>
                    </div>
                    <div>
                        <p class="text-[10px] font-semibold uppercase tracking-[0.12em] text-muted">Tautan</p>
                        <div class="mt-1 flex items-center gap-2">
                            <a href="{{ $shareUrl }}" class="min-w-0 flex-1 break-all text-sm text-accent hover:underline">
                                <span class="inline-flex items-center gap-1">
                                    <x-ui.icon name="external-link" class="h-3.5 w-3.5 shrink-0" />
                                    {{ $shareUrl }}
                                </span>
                            </a>
                            <button type="button" class="icon-btn h-9 w-9 shrink-0" data-copy="{{ $shareUrl }}" aria-label="Salin tautan">
                                <x-ui.icon name="copy" class="h-4 w-4" />
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        @endif
    </section>

    {{-- Risk note --}}
    <div class="card mt-5 p-5">
        <div class="flex items-start gap-3">
            <x-ui.icon name="info" class="h-5 w-5 shrink-0 text-warning" />
            <div class="space-y-2">
                <p class="text-sm font-semibold text-ink">Sebelum membagikan tautan</p>
                <p class="text-xs leading-relaxed text-muted">Halaman publik hanya menampilkan kontribusi yang sudah disetujui dan diizinkan kontributor untuk tampil publik. Siapa pun yang memegang tautan dapat melihatnya, jadi cabut tautan bila sudah tidak diperlukan.</p>
                <p class="text-xs leading-relaxed text-muted">Perubahan jendela pengiriman, persetujuan otomatis, dan retensi tidak membatalkan keputusan moderasi yang sudah Anda tetapkan. Konten yang sudah disetujui tetap tampil sampai Anda menyembunyikannya atau masa retensinya berakhir.</p>
            </div>
        </div>
    </div>
@endsection
