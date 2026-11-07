@extends('layouts.guest')
@section('content')
@php
    $canInteract = $event->allowsGuestActions();
@endphp
<div class="px-4 pt-6 pb-12 sm:px-6 lg:px-8">
    <div class="mx-auto max-w-3xl">
        <x-ui.page-header title="Interaksi" subtitle="Musik, polling, dan game" />

        <div class="card mt-6 p-6 sm:p-8" x-data="{ tab: 'music' }">
            <div class="mb-6 flex gap-2 border-b border-line/60 pb-4" role="tablist" aria-label="Jenis interaksi">
                <button type="button" role="tab" :aria-selected="tab === 'music' ? 'true' : 'false'"
                        class="flex-1 border-b-2 border-transparent py-2 text-sm font-semibold text-muted transition hover:text-ink"
                        :class="{ 'border-accent text-ink': tab === 'music' }" @click="tab = 'music'">Musik</button>
                <button type="button" role="tab" :aria-selected="tab === 'polls' ? 'true' : 'false'"
                        class="flex-1 border-b-2 border-transparent py-2 text-sm font-semibold text-muted transition hover:text-ink"
                        :class="{ 'border-accent text-ink': tab === 'polls' }" @click="tab = 'polls'">Polling</button>
                <button type="button" role="tab" :aria-selected="tab === 'game' ? 'true' : 'false'"
                        class="flex-1 border-b-2 border-transparent py-2 text-sm font-semibold text-muted transition hover:text-ink"
                        :class="{ 'border-accent text-ink': tab === 'game' }" @click="tab = 'game'">Game</button>
            </div>

            <!-- Music Tab -->
            <div x-show="tab === 'music'" x-transition role="tabpanel">
                <h2 class="section-kicker mb-4">Permintaan Lagu</h2>
                @if ($canInteract)
                    <form method="POST" action="{{ route('guest.song.store', $token) }}" class="space-y-5">
                        @csrf
                        <div>
                            <label for="song-title" class="label">
                                Judul lagu <span class="text-danger" aria-hidden="true">*</span>
                                <span class="sr-only">(wajib)</span>
                            </label>
                            <input id="song-title" type="text" name="song_title" class="input" required
                                   placeholder="Judul lagu" @error('song_title') aria-invalid="true" @enderror>
                            @error('song_title')
                                <p class="error-text">{{ $message }}</p>
                            @enderror
                        </div>
                        <div>
                            <label for="song-artist" class="label">Artis <span class="text-faint">(opsional)</span></label>
                            <input id="song-artist" type="text" name="artist" class="input" placeholder="Nama artis">
                        </div>
                        <div>
                            <label for="song-note" class="label">Catatan <span class="text-faint">(opsional)</span></label>
                            <textarea id="song-note" name="note" class="input" rows="2" placeholder="Pesan untuk pemutaran lagu"></textarea>
                        </div>
                        <button type="submit" class="btn btn-primary">Kirim permintaan</button>
                    </form>
                @else
                    <p class="max-w-prose text-sm leading-relaxed text-muted">Permintaan lagu tidak tersedia saat ini.</p>
                @endif

                <h3 class="section-kicker mb-3 mt-6">Lagu Disetujui</h3>
                <div class="space-y-4">
                    @forelse ($songs as $song)
                        <div class="flex items-start gap-3">
                            <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-2xl bg-blue-soft text-ink">
                                <x-ui.icon name="music" class="h-5 w-5" />
                            </span>
                            <div class="min-w-0 flex-1">
                                <p class="font-semibold text-ink">{{ $song->song_title }}</p>
                                @if ($song->artist)
                                    <p class="text-sm text-muted">{{ $song->artist }}</p>
                                @endif
                                @if ($song->note)
                                    <p class="mt-1 text-xs text-faint">{{ $song->note }}</p>
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
            <div x-show="tab === 'polls'" x-transition role="tabpanel">
                <h2 class="section-kicker mb-4">Polling</h2>
                @if ($polls->count())
                    @foreach ($polls as $item)
                        <div class="card-muted mb-4 p-4">
                            <p class="mb-3 font-semibold text-ink">{{ $item['poll']->question }}</p>
                            @if ($item['poll']->description)
                                <p class="mb-3 text-sm text-muted">{{ $item['poll']->description }}</p>
                            @endif
                            @if ($item['myResponse'])
                                <div class="space-y-2">
                                    <p class="text-sm text-muted">Anda memilih: <span class="font-semibold text-ink">{{ $item['myResponse']->option?->label }}</span></p>
                                    @if ($item['poll']->show_results)
                                        @foreach ($item['poll']->options as $option)
                                            @php
                                                $count = $option->responses_count ?? $option->responses()->count();
                                                $percent = $item['total'] > 0 ? round(($count / $item['total']) * 100) : 0;
                                            @endphp
                                            <div class="flex items-center gap-2">
                                                <span class="w-1/3 text-sm text-ink">{{ $option->label }}</span>
                                                <div class="h-2 flex-1 overflow-hidden rounded-full bg-surface-2/60">
                                                    <div class="h-full rounded-full bg-accent" style="width: {{ $percent }}%"></div>
                                                </div>
                                                <span class="w-12 text-right text-sm text-muted">{{ $percent }}%</span>
                                            </div>
                                        @endforeach
                                    @endif
                                </div>
                            @else
                                <form method="POST" action="{{ route('guest.poll.vote', [$token, $item['poll']]) }}" class="space-y-3">
                                    @csrf
                                    <fieldset>
                                        <legend class="label">Pilih opsi <span class="text-danger" aria-hidden="true">*</span></legend>
                                        <div class="space-y-2">
                                            @foreach ($item['poll']->options as $option)
                                                <label class="flex cursor-pointer items-center gap-3 rounded-xl border border-line bg-surface p-3 transition hover:bg-surface-2/60 has-[:checked]:border-accent has-[:checked]:bg-blue-soft">
                                                    <input type="radio" name="poll_option_id" value="{{ $option->id }}" class="sr-only" required>
                                                    <span class="text-sm text-muted has-[:checked]:text-ink">{{ $option->label }}</span>
                                                </label>
                                            @endforeach
                                        </div>
                                    </fieldset>
                                    <button type="submit" class="btn btn-primary w-full">Kirim suara</button>
                                </form>
                            @endif
                        </div>
                    @endforeach
                @else
                    <x-ui.empty-state icon="chart" title="Tidak ada polling" description="Tidak ada polling aktif saat ini." />
                @endif
            </div>

            <!-- Game Tab -->
            <div x-show="tab === 'game'" x-transition role="tabpanel">
                <h2 class="section-kicker mb-4">Game</h2>
                @if ($game)
                    <div class="card-muted p-6">
                        <p class="mb-2 font-semibold text-ink">{{ $game->title }}</p>
                        @if ($game->description)
                            <p class="mb-4 text-sm text-muted">{{ $game->description }}</p>
                        @endif
                        <a href="{{ route('guest.game.show', [$token, $game]) }}" class="btn-primary w-full">
                            <x-ui.icon name="play" class="h-5 w-5" /> Mainkan
                        </a>
                    </div>
                @else
                    <x-ui.empty-state icon="game" title="Tidak ada game" description="Tidak ada game aktif saat ini." />
                @endif
            </div>
        </div>

        <p class="mt-10 text-center text-[11px] text-faint">
            Dibuat dengan tenang oleh <span class="font-semibold text-ink">VOWSPACE</span>
        </p>
    </div>
</div>
@endsection
