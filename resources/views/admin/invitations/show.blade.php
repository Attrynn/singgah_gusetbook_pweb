@extends('layouts.admin')

@section('title', 'Detail Undangan')
@section('subtitle', 'Tautan personal dan QR pass')

@section('content')
    <x-ui.page-header title="{{ $guest->name }}" eyebrow="Invitation" subtitle="{{ $guest->event?->name }} · {{ $guest->category?->name ?? 'Tanpa kategori' }}">
        <x-ui.button variant="secondary" href="{{ route('admin.invitations.preview', $guest) }}" icon="external-link">
            Pratinjau
        </x-ui.button>
        <x-ui.button variant="secondary" href="{{ route('admin.guests.show', $guest) }}" icon="user">
            Profil tamu
        </x-ui.button>
        <x-ui.button variant="secondary" href="{{ route('admin.invitations.index') }}" icon="arrow-left">
            Kembali
        </x-ui.button>
    </x-ui.page-header>

    <div class="mt-6 grid gap-6 xl:grid-cols-[minmax(0,1fr)_380px]">
        <div class="space-y-6">
            <div class="card p-6">
                <h2 class="section-title">Tautan undangan</h2>
                <p class="mt-1 text-xs text-muted">Kirim tautan ini ke tamu. Tautan bersifat personal dan aman.</p>

                <div class="mt-4 break-all rounded-xl border border-line bg-surface-2/50 p-4 text-xs text-muted">
                    {{ $invitationUrl ?: 'Tautan belum tersedia.' }}
                </div>

                <div class="mt-4 flex flex-wrap gap-2">
                    @if ($invitationUrl)
                        <a href="{{ $invitationUrl }}" target="_blank" rel="noopener" class="btn btn-soft btn-sm">
                            <x-ui.icon name="external-link" class="h-3.5 w-3.5" /> Buka undangan
                        </a>
                        <button type="button" class="btn btn-ghost btn-sm" data-copy="{{ $invitationUrl }}">
                            <x-ui.icon name="copy" class="h-3.5 w-3.5" /> Salin tautan
                        </button>
                    @endif
                    @if ($whatsappUrl)
                        <a href="{{ route('admin.invitations.whatsapp', $guest) }}" class="btn btn-primary btn-sm">
                            <x-ui.icon name="send" class="h-3.5 w-3.5" /> Bagikan via WhatsApp
                        </a>
                    @endif
                </div>

                <div class="mt-5 flex flex-wrap gap-2 border-t border-line/70 pt-5">
                    <form method="POST" action="{{ route('admin.invitations.shared', $guest) }}">
                        @csrf
                        <button type="submit" class="btn btn-ghost btn-sm">
                            <x-ui.icon name="check" class="h-3.5 w-3.5" /> Tandai sudah dibagikan
                        </button>
                    </form>
                    <form method="POST" action="{{ route('admin.invitations.regenerate', $guest) }}" data-confirm="Buat ulang tautan undangan? Tautan lama tidak akan berlaku lagi." data-confirm-title="Regenerate Undangan">
                        @csrf
                        <button type="submit" class="btn btn-ghost btn-sm">
                            <x-ui.icon name="refresh" class="h-3.5 w-3.5" /> Buat ulang tautan
                        </button>
                    </form>
                </div>
            </div>

            <div class="card p-6">
                <h2 class="section-title">Status undangan</h2>
                <dl class="mt-4 grid gap-x-6 gap-y-4 text-sm sm:grid-cols-2">
                    <div>
                        <dt class="text-xs uppercase tracking-[0.12em] text-faint">Status</dt>
                        <dd class="mt-1"><x-ui.badge :variant="$guest->invitation?->status->badge()">{{ $guest->invitation?->status->label() ?? 'Belum dibuat' }}</x-ui.badge></dd>
                    </div>
                    <div>
                        <dt class="text-xs uppercase tracking-[0.12em] text-faint">Jumlah dibuka</dt>
                        <dd class="mt-1 text-ink">{{ $guest->invitation?->open_count ?? 0 }}×</dd>
                    </div>
                    <div>
                        <dt class="text-xs uppercase tracking-[0.12em] text-faint">Pertama dibuka</dt>
                        <dd class="mt-1 text-ink">{{ $guest->invitation?->opened_at?->translatedFormat('d M Y H:i') ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs uppercase tracking-[0.12em] text-faint">Terakhir dibagikan</dt>
                        <dd class="mt-1 text-ink">{{ $guest->invitation?->last_shared_at?->translatedFormat('d M Y H:i') ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs uppercase tracking-[0.12em] text-faint">Dikirim pada</dt>
                        <dd class="mt-1 text-ink">{{ $guest->invitation?->sent_at?->translatedFormat('d M Y H:i') ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs uppercase tracking-[0.12em] text-faint">Event</dt>
                        <dd class="mt-1 text-ink">{{ $guest->event?->name ?? '—' }}</dd>
                    </div>
                </dl>
            </div>
        </div>

        <aside class="space-y-4">
            <div class="card p-5 text-center">
                <h2 class="section-title">QR pass check-in</h2>
                <p class="mt-1 text-xs text-muted">Tunjukkan QR ini ke petugas saat check-in.</p>

                <div class="mx-auto mt-4 flex w-full max-w-[260px] items-center justify-center rounded-2xl border border-line bg-white p-4">
                    @if ($qrSvg)
                        <div class="w-full [&>svg]:h-auto [&>svg]:w-full">{!! $qrSvg !!}</div>
                    @else
                        <div class="py-10 text-xs text-muted">QR belum tersedia.</div>
                    @endif
                </div>

                @if (! $qrSvg)
                    <form method="POST" action="{{ route('admin.invitations.regenerate', $guest) }}" class="mt-4" data-confirm="Buat ulang token untuk menghasilkan QR pass?" data-confirm-title="Generate QR">
                        @csrf
                        <button type="submit" class="btn btn-primary btn-sm w-full">
                            <x-ui.icon name="qr" class="h-3.5 w-3.5" /> Generate QR pass
                        </button>
                    </form>
                @else
                    <p class="mt-4 text-[11px] text-faint">QR ini hanya untuk check-in dan tidak dapat dipakai dua kali.</p>
                @endif
            </div>

            <div class="card p-5">
                <h2 class="section-title">Aksi cepat</h2>
                <div class="mt-3 space-y-2">
                    <a href="{{ route('admin.guests.edit', $guest) }}" class="btn btn-ghost btn-sm w-full justify-start">
                        <x-ui.icon name="edit" class="h-3.5 w-3.5" /> Edit data tamu
                    </a>
                    <a href="{{ route('admin.invitations.preview', $guest) }}" target="_blank" rel="noopener" class="btn btn-ghost btn-sm w-full justify-start">
                        <x-ui.icon name="eye" class="h-3.5 w-3.5" /> Lihat undangan
                    </a>
                </div>
            </div>
        </aside>
    </div>
@endsection
