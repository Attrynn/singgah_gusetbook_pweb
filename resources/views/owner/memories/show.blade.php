@extends('layouts.owner')

@section('title', 'Kenangan: '.$asset->original_name)

@section('content')
    <div class="mb-5">
        <a href="{{ route('owner.memories.index') }}" class="text-accent text-sm inline-flex items-center gap-1 hover:underline">
            <x-ui.icon name="chevron-left" class="h-4 w-4" />
            Kembali ke kenangan
        </a>
    </div>

    @php
        $consent = $asset->consent;
        $activeCollections = $event->memoryCollections()
            ->whereNull('archived_at')
            ->orderBy('sort_order')
            ->get(['id', 'name']);

        if ($asset->isHidden()) {
            $moderationOptions = ['unhide' => 'Tampilkan kembali'];
            $moderationDefault = 'unhide';
            $statusLabel = 'Disembunyikan';
            $statusBadge = 'neutral';
        } elseif ($asset->isApproved()) {
            $moderationOptions = ['hide' => 'Sembunyikan', 'reject' => 'Tolak'];
            $moderationDefault = 'hide';
            $statusLabel = 'Disetujui';
            $statusBadge = 'success';
        } elseif ($asset->status === App\Enums\MediaStatus::Rejected) {
            $moderationOptions = ['approve' => 'Setujui', 'reopen' => 'Buka kembali ke antrian'];
            $moderationDefault = 'approve';
            $statusLabel = 'Ditolak';
            $statusBadge = 'danger';
        } else {
            $moderationOptions = ['approve' => 'Setujui', 'reject' => 'Tolak'];
            $moderationDefault = 'approve';
            $statusLabel = 'Menunggu';
            $statusBadge = 'warning';
        }
    @endphp

    <x-ui.page-header title="Detail Kenangan" subtitle="Periksa detail kontribusi, kelola status moderasi, dan unduh berkasnya.">
        <div class="flex flex-wrap items-center gap-2">
            <x-ui.badge :variant="$statusBadge" class="text-[10px]">{{ $statusLabel }}</x-ui.badge>
            @if ($asset->isApproved())
                <a href="{{ route('owner.memories.download', $asset) }}" class="btn btn-primary btn-sm">
                    <x-ui.icon name="download" class="h-4 w-4 shrink-0" />
                    Unduh foto
                </a>
            @endif
        </div>
    </x-ui.page-header>

    <div class="grid gap-5 lg:grid-cols-[minmax(0,1fr)_360px]">
        {{-- Preview --}}
        <div class="card p-5">
            @if ($asset->isApproved())
                <img src="{{ $asset->mediumUrl(true) }}" alt="{{ $asset->original_name }}" class="w-full max-h-[420px] rounded-3xl border border-line/60 bg-surface-2/60 object-contain" loading="lazy" />
            @else
                <div class="flex h-64 w-full items-center justify-center rounded-3xl border border-line/60 bg-surface-2/60">
                    <x-ui.icon name="image" class="h-10 w-10 text-faint" />
                </div>
                <p class="mt-3 text-center text-xs text-muted">Foto belum disetujui, sehingga pratinjauannya hanya tersedia setelah moderasi.</p>
            @endif

            <div class="mt-4 border-t border-line/60 pt-4">
                <p class="font-semibold text-sm text-ink">{{ $asset->caption ?? $asset->original_name }}</p>
                <p class="mt-1 text-[10px] text-faint">
                    {{ $contributor ?? 'Tamu' }} · Dikirim {{ $asset->created_at?->timezone($event->timezoneName())->translatedFormat('l, d M Y H:i') }} ({{ $event->timezoneName() }})
                </p>
            </div>
        </div>

        {{-- Detail column --}}
        <div class="space-y-5">
            {{-- Consent summary --}}
            <div class="card p-5">
                <p class="section-kicker">Persetujuan kontributor</p>
                @if ($consent)
                    <ul class="mt-3 space-y-2 text-sm text-muted">
                        <li class="flex items-center gap-2">
                            <x-ui.icon name="{{ $consent->allow_event_display ? 'check' : 'close' }}" class="h-4 w-4 {{ $consent->allow_event_display ? 'text-success' : 'text-danger' }}" />
                            Ditampilkan di galeri acara
                        </li>
                        <li class="flex items-center gap-2">
                            <x-ui.icon name="{{ $consent->allowsPublic() ? 'check' : 'close' }}" class="h-4 w-4 {{ $consent->allowsPublic() ? 'text-success' : 'text-danger' }}" />
                            Ditampilkan di ruang publik pasangan
                        </li>
                    </ul>
                    <p class="mt-3 text-[10px] text-faint">
                        Disetujui {{ $consent->accepted_at?->timezone($event->timezoneName())->translatedFormat('d M Y H:i') }} · Ketentuan versi {{ $consent->terms_version ?? '-' }}
                    </p>
                @else
                    <p class="mt-2 text-sm text-muted">Tidak ada catatan persetujuan untuk kontribusi ini.</p>
                @endif
            </div>

            {{-- Uploader & album --}}
            <div class="card p-5">
                <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <p class="section-kicker">Uploader</p>
                        <div class="mt-2 flex items-center gap-2">
                            <x-ui.avatar :name="$asset->uploader?->name" size="sm" />
                            <p class="font-semibold text-sm text-ink">{{ $asset->uploader?->name ?? '-' }}</p>
                        </div>
                    </div>
                    <div>
                        <p class="section-kicker">Album</p>
                        @if ($asset->album)
                            <a href="{{ route('owner.memories.index', ['album_id' => $asset->album->id]) }}" class="mt-1 inline-flex items-center gap-1 text-sm text-accent hover:underline">
                                {{ $asset->album->name }}
                                <x-ui.icon name="chevron-right" class="h-3.5 w-3.5" />
                            </a>
                        @else
                            <p class="mt-1 text-sm text-muted">Tanpa album</p>
                        @endif
                    </div>
                </div>
            </div>

            {{-- Moderation --}}
            <div class="card p-5">
                <p class="section-kicker">Moderasi</p>
                <form method="POST" action="{{ route('owner.memories.moderate', $asset) }}" class="mt-3 space-y-4">
                    @csrf
                    <x-ui.select
                        name="action"
                        label="Tindakan"
                        required
                        :options="$moderationOptions"
                        :value="old('action', $moderationDefault)"
                        :hint="'Status saat ini: '.$statusLabel"
                    />
                    <x-ui.textarea name="note" label="Catatan (opsional)" rows="3" placeholder="Alasan tindakan ini dicatat di jejak audit." />
                    <div>
                        <button type="submit" class="btn btn-primary w-full">Terapkan status</button>
                    </div>
                </form>
            </div>

            {{-- Collections --}}
            <div class="card p-5">
                <p class="section-kicker">Koleksi</p>
                @if ($activeCollections->isNotEmpty())
                    <form method="POST" action="{{ route('owner.memories.collections.attach') }}" class="mt-3 space-y-4">
                        @csrf
                        <input type="hidden" name="type" value="media_asset">
                        <input type="hidden" name="item_ids[]" value="{{ $asset->id }}">

                        <x-ui.select
                            name="collection_id"
                            label="Pilih koleksi"
                            required
                            placeholder="Pilih koleksi"
                            :options="$activeCollections->pluck('name', 'id')->all()"
                        />

                        @error('item')
                            <p class="error-text">{{ $message }}</p>
                        @enderror
                        @error('item_ids')
                            <p class="error-text">{{ $message }}</p>
                        @enderror

                        <div>
                            <button type="submit" class="btn btn-secondary w-full">
                                <x-ui.icon name="plus" class="h-4 w-4 shrink-0" />
                                Tambahkan ke koleksi
                            </button>
                        </div>
                    </form>
                @else
                    <p class="mt-2 text-sm text-muted">Belum ada koleksi aktif. <a href="{{ route('owner.memories.collections.index') }}" class="text-accent hover:underline">Kelola koleksi</a>.</p>
                @endif
            </div>
        </div>
    </div>
@endsection
