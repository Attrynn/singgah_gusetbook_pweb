@extends('layouts.guest')
@section('content')
<div class="px-5 pt-5 pb-12">
    <x-ui.page-header title="Interaksi" subtitle="Musik, Polling, & Game" />

    <div class="card p-6 mb-10" x-data="{ tab: 'music' }">
        <div class="flex gap-2 mb-6 border-b border-line/60 pb-4">
            <button type="button" class="flex-1 py-2 text-sm font-semibold text-muted hover:text-ink transition" :class="{ 'text-ink border-b-2 border-accent': tab === 'music' }" @click="tab = 'music'">Musik</button>
            <button type="button" class="flex-1 py-2 text-sm font-semibold text-muted hover:text-ink transition" :class="{ 'text-ink border-b-2 border-accent': tab === 'polls' }" @click="tab = 'polls'">Polling</button>
            <button type="button" class="flex-1 py-2 text-sm font-semibold text-muted hover:text-ink transition" :class="{ 'text-ink border-b-2 border-accent': tab === 'game' }" @click="tab = 'game'">Game</button>
        </div>

        <!-- Music Tab -->
        <div x-show="tab === 'music'" x-transition>
            <p class="section-kicker mb-4">Permintaan Lagu</p>
            <form method="POST" action="{{ route('guest.song.store', $token) }}" class="space-y-4" @if (!$event->allowsGuestActions()) style="display:none" @endif>
                @csrf
                <div class="flex items-start gap-3">
                    <input type="text" name="song_title" placeholder="Judul Lagu" class="input" required>
                </div>
                <div class="flex items-start gap-3">
                    <input type="text" name="artist" placeholder="Artis (opsional)" class="input">
                </div>
                <div class="flex items-start gap-3">
                    <textarea name="note" placeholder="Catatan (opsional)" class="input" rows="2"></textarea>
                </div>
                <button type="submit" class="btn-primary">Kirim Permintaan</button>
            </form>
            @if (!$event->allowsGuestActions())
                <p class="prose-wedding">Permintaan lagu tidak tersedia saat ini.</p>
            @endif

            <p class="section-kicker mt-6">Lagu Disetujui</p>
            <div class="space-y-3">
                @forelse ($songs as $song)
                    <div class="flex items-start gap-3">
                        <span class="flex shrink-0 h-10 w-10 items-center justify-center rounded-2xl bg-blue-soft text-[#4f7592]">
                            <x-ui.icon name="music" class="h-5 w-5" />
                        </span>
                        <div class="flex-1">
                            <p class="font-semibold text-ink">{{ $song->song_title }}</p>
                            <p class="text-sm text-muted">{{ $song->artist }}</p>
                            @if ($song->note)
                                <p class="text-xs text-faint mt-1">{{ $song->note }}</p>
                            @endif
                            <span class="{{ $song->status->badge() }} mt-2">{{ $song->status->label() }}</span>
                        </div>
                    </div>
                @empty
                    <x-ui.empty-state icon="music" title="Belum ada lagu" description="Jadilah yang pertama meminta lagu." />
                @endforelse
            </div>
        </div>

        <!-- Polls Tab -->
        <div x-show="tab === 'polls'" x-transition>
            <p class="section-kicker mb-4">Polling</p>
            @if ($polls->count())
                @foreach ($polls as $item)
                    <div class="card p-4 mb-4">
                        <p class="font-semibold text-ink mb-3">{{ $item['poll']->question }}</p>
                        @if ($item['poll']->description)
                            <p class="text-sm text-muted mb-3">{{ $item['poll']->description }}</p>
                        @endif
                        @if ($item['myResponse'])
                            <div class="space-y-2">
                                <p class="text-sm text-muted">Anda memilih: <span class="font-semibold text-ink">{{ $item['myResponse']->option?->label }}</span></p>
                                @if ($item['poll']->show_results)
                                    @foreach ($item['poll']->options as $option)
                                        @php
                                            $count = $option->responses()->count();
                                            $percent = $item['total'] > 0 ? round(($count / $item['total']) * 100) : 0;
                                        @endphp
                                        <div class="flex items-center gap-2">
                                            <span class="text-sm text-ink w-1/3">{{ $option->label }}</span>
                                            <div class="flex-1 h-2 bg-surface-2/60 rounded-full overflow-hidden">
                                                <div class="h-full bg-accent rounded-full" style="width: {{ $percent }}%"></div>
                                            </div>
                                            <span class="text-sm text-muted w-12 text-right">{{ $percent }}%</span>
                                        </div>
                                    @endforeach
                                @endif
                            </div>
                        @else
                            <form method="POST" action="{{ route('guest.poll.vote', [$token, $item['poll']]) }}" class="space-y-2">
                                @csrf
                                @foreach ($item['poll']->options as $option)
                                    <label class="flex items-center gap-3 rounded-xl border border-line bg-surface p-3 cursor-pointer transition hover:bg-surface-2/60">
                                        <input type="radio" name="poll_option_id" value="{{ $option->id }}" class="peer sr-only" required>
                                        <span class="text-sm text-muted peer-checked:text-ink">{{ $option->label }}</span>
                                    </label>
                                @endforeach
                                <button type="submit" class="btn-primary w-full">Kirim Suara</button>
                            </form>
                        @endif
                    </div>
                @endforeach
            @else
                <x-ui.empty-state icon="chart" title="Tidak ada polling" description="Tidak ada polling aktif saat ini." />
            @endif
        </div>

        <!-- Game Tab -->
        <div x-show="tab === 'game'" x-transition>
            <p class="section-kicker mb-4">Game</p>
            @if ($game)
                <div class="card p-6">
                    <p class="font-semibold text-ink mb-2">{{ $game->title }}</p>
                    @if ($game->description)
                        <p class="text-sm text-muted mb-4">{{ $game->description }}</p>
                    @endif
                    <a href="{{ route('guest.game.show', [$token, $game]) }}" class="btn-primary w-full">
                        <x-ui.icon name="play" class="h-5 w-5 mr-2" /> Mainkan
                    </a>
                </div>
            @else
                <x-ui.empty-state icon="game" title="Tidak ada game" description="Tidak ada game aktif saat ini." />
            @endif
        </div>
    </div>

    <p class="text-center text-[11px] text-faint mt-10">Dibuat dengan tenang oleh <span class="font-semibold text-ink">VOWSPACE</span></p>
</div>
</section>
</body>
</html>