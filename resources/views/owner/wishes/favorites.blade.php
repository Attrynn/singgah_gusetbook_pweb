@extends('layouts.owner')

@section('title', 'Ucapan Favorit')

@section('content')
    <x-ui.page-header title="Ucapan Favorit" subtitle="Kumpulan ucapan yang Anda tandai sebagai favorit." />

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
                        <form action="{{ route('owner.wishes.favorite', $message) }}" method="POST" class="inline" data-confirm data-confirm-title="Hapus favorit?" data-confirm="Hapus ucapan ini dari daftar favorit?">
                            @csrf
                            <button type="submit" class="icon-btn h-9 w-9 bg-blue-soft text-[#4f7592]">
                                <x-ui.icon name="heart" class="h-4 w-4" />
                            </button>
                        </form>
                        <a href="{{ route('owner.wishes.show', $message) }}" class="link text-[10px]">Lihat detail</a>
                    </div>
                </div>
            </div>
        @empty
            <x-ui.empty-state icon="star" title="Belum ada favorit" description="Ucapan yang ditandai favorit akan muncul di sini." />
        @endforelse
    </div>

    @include('owner.partials.pagination', ['paginator' => $messages])
@endsection