@extends('layouts.owner')

@section('title', 'Interaksi')

@section('content')
    <x-ui.page-header title="Interaksi" subtitle="Polling, game, dan permintaan lagu dari tamu." />

    {{-- Polls --}}
    <div class="card p-5 mb-5">
        <p class="section-kicker">Polling</p>
        @if ($polls->isNotEmpty())
            <div class="mt-4 space-y-5">
                @foreach ($polls as $pollData)
                    <div class="border-t border-line/60 pt-5 first:border-0 first:pt-0">
                        <div class="flex items-start justify-between gap-3 mb-3">
                            <div>
                                <h3 class="font-serif text-lg font-semibold text-ink">{{ $pollData['poll']->question }}</h3>
                                @if ($pollData['poll']->description)
                                    <p class="mt-1 text-sm text-muted">{{ $pollData['poll']->description }}</p>
                                @endif
                            </div>
                            <x-ui.badge variant="{{ $pollData['poll']->isOpen() ? 'info' : 'muted' }}" class="text-[10px]">
                                {{ $pollData['poll']->isOpen() ? 'Aktif' : 'Ditutup' }}
                            </x-ui.badge>
                        </div>

                        <p class="text-[10px] text-faint mb-3">Total respons: {{ $pollData['total'] }}</p>

                        <div class="space-y-3">
                            @foreach ($pollData['options'] as $option)
                                <div>
                                    <div class="flex items-center justify-between text-sm mb-1">
                                        <span class="font-medium text-ink truncate pr-2">{{ $option['label'] }}</span>
                                        <span class="text-muted shrink-0">{{ $option['count'] }} ({{ $option['percent'] }}%)</span>
                                    </div>
                                    <div class="h-2 bg-surface-2 rounded-full overflow-hidden">
                                        <div class="h-full rounded-full bg-blue-soft transition-all duration-500" style="width: {{ $option['percent'] }}%"></div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endforeach
            </div>
        @else
            <x-ui.empty-state icon="chart" title="Belum ada polling" description="Polling yang dibuat akan muncul di sini." />
        @endif
    </div>

    {{-- Games --}}
    <div class="card p-5 mb-5">
        <p class="section-kicker">Game</p>
        @if ($games->isNotEmpty())
            <div class="mt-4 space-y-4">
                @foreach ($games as $gameData)
                    <div class="p-4 rounded-2xl bg-surface-2/60">
                        <div class="flex items-center justify-between gap-3 mb-3">
                            <h3 class="font-serif text-lg font-semibold text-ink">{{ $gameData['game']->title }}</h3>
                            <x-ui.badge variant="{{ $gameData['game']->isPlayable() ? 'success' : 'muted' }}" class="text-[10px]">
                                {{ $gameData['game']->isPlayable() ? 'Bisa dimainkan' : 'Belum siap' }}
                            </x-ui.badge>
                        </div>
                        @if ($gameData['game']->description)
                            <p class="text-sm text-muted mb-3">{{ $gameData['game']->description }}</p>
                        @endif
                        <div class="grid grid-cols-3 gap-3 text-center">
                            <div class="p-3 rounded-2xl bg-surface border border-line/60">
                                <p class="text-2xl font-bold text-ink">{{ $gameData['attempts'] }}</p>
                                <p class="text-[10px] font-semibold uppercase tracking-[0.12em] text-muted">Percobaan</p>
                            </div>
                            <div class="p-3 rounded-2xl bg-surface border border-line/60">
                                <p class="text-2xl font-bold text-ink">{{ $gameData['average_score'] }}</p>
                                <p class="text-[10px] font-semibold uppercase tracking-[0.12em] text-muted">Rata-rata</p>
                            </div>
                            <div class="p-3 rounded-2xl bg-surface border border-line/60">
                                <p class="text-2xl font-bold text-ink">{{ $gameData['best_score'] }}</p>
                                <p class="text-[10px] font-semibold uppercase tracking-[0.12em] text-muted">Terbaik</p>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        @else
            <x-ui.empty-state icon="gift" title="Belum ada game" description="Game yang dibuat akan muncul di sini." />
        @endif
    </div>

    {{-- Song Requests --}}
    <div class="card p-5">
        <p class="section-kicker">Permintaan lagu</p>
        @if ($songs->isNotEmpty())
            <div class="mt-4 space-y-3">
                @foreach ($songs as $song)
                    <div class="p-4 rounded-2xl bg-surface-2/60 flex items-start justify-between gap-3">
                        <div class="flex-1 min-w-0">
                            <p class="font-semibold text-sm text-ink">{{ $song->song_title }}</p>
                            <p class="text-sm text-muted">{{ $song->artist }}</p>
                            @if ($song->note)
                                <p class="mt-1 text-sm text-muted line-clamp-1">{{ $song->note }}</p>
                            @endif
                            <p class="mt-1 text-[10px] text-faint">{{ $song->guest?->name ?? 'Tamu' }} · {{ $song->created_at?->translatedFormat('d M Y H:i') }}</p>
                        </div>
                        <x-ui.badge :variant="$song->status->badge()" class="text-[10px] shrink-0">{{ $song->status->label() }}</x-ui.badge>
                    </div>
                @endforeach
            </div>
        @else
            <x-ui.empty-state icon="music" title="Belum ada permintaan lagu" description="Tamu belum meminta lagu." />
        @endif
    </div>
@endsection