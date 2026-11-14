@extends('layouts.owner')

@section('title', 'Ucapan Tamu')

@section('content')
    @php
        $statusLabels = [
            'pending' => 'Menunggu',
            'approved' => 'Disetujui',
            'rejected' => 'Ditolak',
            'hidden' => 'Disembunyikan',
        ];

        $visibilityLabels = [
            'public' => 'Publik',
            'private' => 'Pribadi',
        ];
    @endphp

    <x-ui.page-header title="Ucapan Tamu" subtitle="Kelola ucapan yang dikirim tamu untuk pasangan Anda.">
        <div class="flex flex-wrap items-center gap-2">
            <a href="{{ route('owner.wishes.favorites') }}" class="btn btn-soft btn-sm">
                <x-ui.icon name="star" class="h-4 w-4 shrink-0" />
                Favorit ({{ $stats['favorites'] ?? 0 }})
            </a>
            <a href="{{ route('owner.wishes.download') }}" class="btn btn-secondary btn-sm">
                <x-ui.icon name="download" class="h-4 w-4 shrink-0" />
                Unduh CSV
            </a>
        </div>
    </x-ui.page-header>

    {{-- Stats Strip --}}
    <div class="mb-5 grid grid-cols-2 gap-3 sm:grid-cols-4">
        <div class="card p-4 text-center">
            <p class="text-2xl font-semibold tabular-nums text-ink">{{ $stats['total'] ?? 0 }}</p>
            <p class="mt-0.5 text-[10px] font-semibold uppercase tracking-[0.12em] text-muted">Total</p>
        </div>
        <div class="card p-4 text-center">
            <p class="text-2xl font-semibold tabular-nums text-success">{{ $stats['approved'] ?? 0 }}</p>
            <p class="mt-0.5 text-[10px] font-semibold uppercase tracking-[0.12em] text-muted">Disetujui</p>
        </div>
        <div class="card p-4 text-center">
            <p class="text-2xl font-semibold tabular-nums text-warning">{{ $stats['pending'] ?? 0 }}</p>
            <p class="mt-0.5 text-[10px] font-semibold uppercase tracking-[0.12em] text-muted">Menunggu</p>
        </div>
        <div class="card p-4 text-center">
            <p class="text-2xl font-semibold tabular-nums text-accent">{{ $stats['favorites'] ?? 0 }}</p>
            <p class="mt-0.5 text-[10px] font-semibold uppercase tracking-[0.12em] text-muted">Favorit</p>
        </div>
    </div>

    {{-- Filters --}}
    <div class="card mb-5 p-4 sm:p-5">
        <form method="GET" action="{{ route('owner.wishes.index') }}" class="flex flex-col gap-3 sm:flex-row sm:items-end">
            <div class="flex-1">
                <x-ui.input name="search" label="Cari" placeholder="Nama atau pesan..." value="{{ $filters['search'] ?? '' }}" />
            </div>
            <div class="sm:w-44">
                <label for="status" class="label">Status</label>
                <div class="relative">
                    <select id="status" name="status" class="input appearance-none pr-9" data-auto-submit>
                        <option value="">Semua Status</option>
                        @foreach (\App\Enums\MessageStatus::cases() as $case)
                            <option value="{{ $case->value }}" @selected(($filters['status'] ?? '') === $case->value)>
                                {{ $statusLabels[$case->value] }}
                            </option>
                        @endforeach
                    </select>
                    <x-ui.icon name="chevron-down" class="pointer-events-none absolute right-3 top-1/2 h-4 w-4 -translate-y-1/2 text-faint" />
                </div>
            </div>
            <div class="sm:w-44">
                <label for="date" class="label">Tanggal</label>
                <input type="text" id="date" name="date" class="input" placeholder="YYYY-MM-DD" value="{{ $filters['date'] ?? '' }}" data-datepicker aria-label="Cari tanggal" />
            </div>
            <div class="flex gap-2 sm:self-end">
                <button type="submit" class="btn btn-primary">Cari</button>
                <a href="{{ route('owner.wishes.index') }}" class="btn btn-ghost">Reset</a>
            </div>
        </form>
    </div>

    {{-- Wish Cards --}}
    <div class="space-y-4">
        @forelse ($messages as $message)
            @php
                $isFavorite = in_array($message->id, $favoriteIds ?? [], true);

                if ($message->status === \App\Enums\MessageStatus::Hidden) {
                    $moderateActions = ['unhide' => 'Tampilkan kembali'];
                } elseif ($message->status === \App\Enums\MessageStatus::Approved) {
                    $moderateActions = ['hide' => 'Sembunyikan', 'reject' => 'Tolak'];
                } elseif ($message->status === \App\Enums\MessageStatus::Rejected) {
                    $moderateActions = ['approve' => 'Setujui', 'reopen' => 'Buka kembali ke antrian'];
                } else {
                    $moderateActions = ['approve' => 'Setujui', 'reject' => 'Tolak', 'hide' => 'Sembunyikan'];
                }

                $attachFailed = old('type') === 'guestbook_message' && in_array($message->id, (array) old('item_ids', []));
            @endphp
            <article class="card p-4 transition hover:bg-surface-2/40 sm:p-5">
                <div class="flex items-start gap-3 sm:gap-4">
                    {{-- Emoji tile --}}
                    <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl bg-accent-soft text-lg" aria-hidden="true">
                        {{ $message->emoji ?? '💌' }}
                    </span>

                    <div class="min-w-0 flex-1">
                        <div class="flex flex-wrap items-center gap-x-2 gap-y-1">
                            <h3 class="font-semibold text-ink">{{ $message->sender_name }}</h3>
                            <x-ui.badge :variant="$message->status->badge()" class="text-[10px]">{{ $statusLabels[$message->status->value] }}</x-ui.badge>
                            <x-ui.badge variant="muted" class="text-[10px]">{{ $visibilityLabels[$message->visibility->value] ?? $message->visibility->label() }}</x-ui.badge>
                        </div>
                        <p class="mt-1.5 text-sm leading-relaxed text-ink">{{ $message->message }}</p>
                        <p class="mt-1.5 text-[10px] text-faint">{{ $message->created_at?->translatedFormat('d M Y H:i') }}</p>
                    </div>

                    <div class="flex shrink-0 flex-col items-end gap-2">
                        <form action="{{ route('owner.wishes.favorite', $message) }}" method="POST" class="inline" data-confirm data-confirm-title="Ubah favorit?" data-confirm-text="Tandai sebagai {{ $isFavorite ? 'bukan' : '' }}favorit?">
                            @csrf
                            <button type="submit" class="icon-btn h-9 w-9 {{ $isFavorite ? 'bg-blue-soft text-ink' : '' }}" aria-label="Favoritkan" aria-pressed="{{ $isFavorite ? 'true' : 'false' }}">
                                <x-ui.icon name="heart" class="h-4 w-4 {{ $isFavorite ? 'fill-current' : '' }}" />
                            </button>
                        </form>
                        <a href="{{ route('owner.wishes.show', $message) }}" class="text-[10px] font-medium text-accent hover:underline">Lihat detail</a>
                    </div>
                </div>

                {{-- Moderation: compact footer row, same posted fields --}}
                <form method="POST" action="{{ route('owner.wishes.moderate', $message) }}" class="mt-4 flex flex-col gap-2 border-t border-line/60 pt-3 sm:flex-row sm:items-center">
                    @csrf
                    <div class="sm:w-48">
                        <label for="action-{{ $message->id }}" class="sr-only">Tindakan moderasi</label>
                        <div class="relative">
                            <select id="action-{{ $message->id }}" name="action" class="input input-sm appearance-none pr-8" required>
                                @foreach ($moderateActions as $value => $label)
                                    <option value="{{ $value }}" @selected(old('action') === $value)>{{ $label }}</option>
                                @endforeach
                            </select>
                            <x-ui.icon name="chevron-down" class="pointer-events-none absolute right-2.5 top-1/2 h-3.5 w-3.5 -translate-y-1/2 text-faint" />
                        </div>
                        @if ($errors->has('action'))
                            <p class="error-text">{{ $errors->first('action') }}</p>
                        @endif
                    </div>
                    <div class="min-w-0 flex-1">
                        <label for="note-{{ $message->id }}" class="sr-only">Catatan</label>
                        <input id="note-{{ $message->id }}" type="text" name="note" value="{{ old('note') }}" placeholder="Alasan tindakan (opsional)" class="input input-sm w-full">
                        @if ($errors->has('note'))
                            <p class="error-text">{{ $errors->first('note') }}</p>
                        @endif
                    </div>
                    <button type="submit" class="btn btn-primary btn-sm justify-center sm:shrink-0">Terapkan</button>
                </form>

                {{-- Attach to collection: sibling disclosure, never nested in the forms above --}}
                <details class="group mt-3" @if ($attachFailed) open @endif>
                    <summary class="btn btn-ghost btn-sm w-full list-none justify-between [&::-webkit-details-marker]:hidden">
                        <span class="inline-flex items-center gap-2">
                            <x-ui.icon name="plus" class="h-3.5 w-3.5 shrink-0" />
                            Tambahkan ke koleksi
                        </span>
                        <x-ui.icon name="chevron-down" class="h-3.5 w-3.5 shrink-0 transition-transform group-open:rotate-180" />
                    </summary>
                    @if ($collections->isNotEmpty())
                        <form method="POST" action="{{ route('owner.memories.collections.attach') }}" class="mt-3 flex flex-col gap-2 sm:flex-row sm:items-center">
                            @csrf
                            <input type="hidden" name="type" value="guestbook_message">
                            <input type="hidden" name="item_ids[]" value="{{ $message->id }}">
                            <div class="relative min-w-0 flex-1">
                                <label for="attach-collection-{{ $message->id }}" class="sr-only">Koleksi tujuan</label>
                                <select id="attach-collection-{{ $message->id }}" name="collection_id" required
                                        class="input input-sm w-full appearance-none pr-8">
                                    <option value="">Pilih koleksi</option>
                                    @foreach ($collections as $collection)
                                        <option value="{{ $collection->id }}" @selected(old('collection_id') == $collection->id)>{{ $collection->name }}</option>
                                    @endforeach
                                </select>
                                <x-ui.icon name="chevron-down" class="pointer-events-none absolute right-2.5 top-1/2 h-3.5 w-3.5 -translate-y-1/2 text-faint" />
                            </div>
                            <button type="submit" class="btn btn-soft btn-sm justify-center sm:shrink-0">Tambahkan</button>
                        </form>
                    @else
                        <p class="mt-3 text-xs text-muted">Belum ada koleksi aktif. <a href="{{ route('owner.memories.collections.index') }}" class="text-accent hover:underline">Buat koleksi</a> dulu.</p>
                    @endif
                    @if ($attachFailed)
                        @if ($errors->has('item'))
                            <p class="error-text mt-2">{{ $errors->first('item') }}</p>
                        @endif
                        @if ($errors->has('item_ids'))
                            <p class="error-text mt-2">{{ $errors->first('item_ids') }}</p>
                        @endif
                    @endif
                </details>
            </article>
        @empty
            <x-ui.empty-state icon="message" title="Belum ada ucapan" description="Tamu belum meninggalkan ucapan di ruang ini.">
                <a href="{{ route('owner.wishes.index') }}" class="btn btn-soft btn-sm">Mulai dari awal</a>
            </x-ui.empty-state>
        @endforelse
    </div>

    @include('owner.partials.pagination', ['paginator' => $messages])
@endsection
