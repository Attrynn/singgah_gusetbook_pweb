@extends('layouts.owner')

@section('title', 'Detail Ucapan')

@section('content')
    <div class="mb-5">
        <a href="{{ route('owner.wishes.index') }}" class="link text-sm flex items-center gap-1">
            <x-ui.icon name="chevron-left" class="h-4 w-4" />
            Kembali ke ucapan
        </a>
    </div>

    <div class="card p-5 mb-5">
        <div class="flex items-center gap-2 mb-3">
            <span class="text-3xl">{{ $message->emoji ?? '💌' }}</span>
            <div>
                <h1 class="font-serif text-xl font-semibold text-ink">{{ $message->sender_name }}</h1>
                <div class="flex items-center gap-2 mt-1">
                    <x-ui.badge :variant="$message->status->badge()">{{ $message->status->label() }}</x-ui.badge>
                    <x-ui.badge variant="muted">{{ $message->visibility->label() }}</x-ui.badge>
                </div>
            </div>
        </div>

        <blockquote class="prose-wedding border-l-2 border-accent/40 pl-4 my-4">
            <p class="font-serif text-lg leading-relaxed text-ink">{{ $message->message }}</p>
        </blockquote>

        <div class="flex items-center justify-between pt-3 border-t border-line/60">
            <p class="text-[10px] text-faint">{{ $message->created_at?->translatedFormat('l, d M Y H:i') }}</p>
            <form action="{{ route('owner.wishes.favorite', $message) }}" method="POST" class="inline" data-confirm data-confirm-title="Ubah favorit?" data-confirm="Tandai sebagai {{ $isFavorite ? 'bukan' : '' }}favorit?">
                @csrf
                <button type="submit" class="icon-btn h-9 w-9 {{ $isFavorite ? 'bg-blue-soft text-ink' : '' }}" aria-label="Favoritkan">
                    <x-ui.icon name="heart" class="h-4 w-4" />
                </button>
            </form>
        </div>
    </div>

    @if ($message->guest)
        <div class="card p-5">
            <p class="section-kicker">Dari tamu</p>
            <div class="mt-3 flex items-center gap-3">
                <x-ui.avatar :name="$message->guest->name" :src="$message->guest->avatar_path ? Illuminate\Support\Facades\Storage::disk('public')->url($message->guest->avatar_path) : null" size="md" />
                <div>
                    <p class="font-semibold text-ink">{{ $message->guest->name }}</p>
                    <p class="text-[10px] text-muted">{{ $message->guest->rsvp_status->label() }}</p>
                </div>
            </div>
        </div>
    @endif
@endsection