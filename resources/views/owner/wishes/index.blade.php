@extends('layouts.owner')

@section('title', 'Ucapan Tamu')

@section('content')
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
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 mb-5">
        <div class="card p-4 text-center">
            <p class="text-2xl font-semibold text-ink">{{ $stats['total'] ?? 0 }}</p>
            <p class="text-[10px] font-semibold uppercase tracking-[0.12em] text-muted">Total</p>
        </div>
        <div class="card p-4 text-center">
            <p class="text-2xl font-semibold text-success">{{ $stats['approved'] ?? 0 }}</p>
            <p class="text-[10px] font-semibold uppercase tracking-[0.12em] text-muted">Disetujui</p>
        </div>
        <div class="card p-4 text-center">
            <p class="text-2xl font-semibold text-warning">{{ $stats['pending'] ?? 0 }}</p>
            <p class="text-[10px] font-semibold uppercase tracking-[0.12em] text-muted">Pending</p>
        </div>
        <div class="card p-4 text-center">
            <p class="text-2xl font-semibold text-accent">{{ $stats['favorites'] ?? 0 }}</p>
            <p class="text-[10px] font-semibold uppercase tracking-[0.12em] text-muted">Favorit</p>
        </div>
    </div>

    {{-- Filters --}}
    <div class="card p-5 mb-5">
        <form method="GET" action="{{ route('owner.wishes.index') }}" class="flex flex-col sm:flex-row gap-3">
            <div class="flex-1">
                <x-ui.input name="search" label="Cari" placeholder="Nama atau pesan..." value="{{ $filters['search'] ?? '' }}" />
            </div>
            <div class="sm:w-44">
                <label for="status" class="label">Status</label>
                <select id="status" name="status" class="input">
                    <option value="">Semua Status</option>
                    @foreach (\App\Enums\MessageStatus::cases() as $case)
                        <option value="{{ $case->value }}" @selected(($filters['status'] ?? '') === $case->value)>
                            {{ $case->label() }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="sm:w-44">
                <label for="date" class="label">Tanggal</label>
                <input type="text" id="date" name="date" class="input" placeholder="dd M yyyy" value="{{ $filters['date'] ?? '' }}" data-datepicker aria-label="Cari tanggal" />
            </div>
            <div class="flex gap-2 self-end">
                <button type="submit" class="btn btn-primary">Cari</button>
                <a href="{{ route('owner.wishes.index') }}" class="btn btn-ghost">Reset</a>
            </div>
        </form>
    </div>

    {{-- Wish Cards --}}
    <div class="space-y-4">
        @forelse ($messages as $message)
            <div class="card p-5 hover:bg-surface-2/40 transition">
                <div class="flex items-start justify-between gap-4">
                    <div class="flex-1 min-w-0">
                        <div class="flex items-center gap-2">
                            <span class="text-xl">{{ $message->emoji ?? '💌' }}</span>
                            <h3 class="font-semibold text-ink truncate">{{ $message->sender_name }}</h3>
                            <x-ui.badge :variant="$message->status->badge()" class="text-[10px]">{{ $message->status->label() }}</x-ui.badge>
                            <x-ui.badge variant="muted" class="text-[10px]">{{ $message->visibility->label() }}</x-ui.badge>
                        </div>
                        <p class="mt-2 text-sm text-muted line-clamp-2">{{ $message->message }}</p>
                        <p class="mt-1 text-[10px] text-faint">{{ $message->created_at?->translatedFormat('d M Y H:i') }}</p>
                    </div>
                    <div class="flex flex-col items-end gap-2">
                        <form action="{{ route('owner.wishes.favorite', $message) }}" method="POST" class="inline" data-confirm data-confirm-title="Ubah favorit?" data-confirm="Tandai sebagai {{ in_array($message->id, $favoriteIds ?? [], true) ? 'bukan' : '' }}favorit?">
                            @csrf
                            <button type="submit" class="icon-btn h-9 w-9 {{ in_array($message->id, $favoriteIds ?? [], true) ? 'bg-blue-soft text-ink' : '' }}" aria-label="Favoritkan">
                                <x-ui.icon name="heart" class="h-4 w-4" />
                            </button>
                        </form>
                        <a href="{{ route('owner.wishes.show', $message) }}" class="link text-[10px]">Lihat detail</a>
                    </div>
                </div>
            </div>
        @empty
            <x-ui.empty-state icon="message" title="Belum ada ucapan" description="Tamu belum meninggalkan ucapan di ruang ini.">
                <a href="{{ route('owner.wishes.index') }}" class="btn btn-soft btn-sm">Mulai dari awal</a>
            </x-ui.empty-state>
        @endforelse
    </div>

    @include('owner.partials.pagination', ['paginator' => $messages])
@endsection