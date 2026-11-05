@extends('layouts.admin')

@section('title', 'Edit Game')
@section('subtitle', 'Pengaturan dan pertanyaan game')

@section('content')
    <x-ui.page-header title="{{ $game->title }}" eyebrow="Mini Games" subtitle="{{ $game->event?->name }} · {{ $game->attempts_count }} percobaan">
        <x-ui.button variant="secondary" href="{{ route('admin.interactions.games.index') }}" icon="arrow-left">
            Kembali
        </x-ui.button>
    </x-ui.page-header>

    <div class="mt-6 grid gap-6 xl:grid-cols-[minmax(0,1fr)_360px]">
        <div class="space-y-6">
            <form method="POST" action="{{ route('admin.interactions.games.update', $game) }}" class="card space-y-5 p-6">
                @csrf
                @method('PUT')

                <div class="grid gap-5 sm:grid-cols-2">
                    <x-ui.select name="event_id" label="Event" :value="$game->event_id" :options="$events->mapWithKeys(fn ($event) => [$event->id => $event->name])" required />

                    <x-ui.input name="title" label="Judul game" :value="$game->title" required />
                </div>

                <x-ui.textarea name="description" label="Deskripsi" rows="3" :value="$game->description" />

                <label class="flex items-center gap-3 rounded-2xl border border-line bg-surface-2/40 px-4 py-3">
                    <input type="checkbox" name="is_active" value="1" class="h-4 w-4 rounded border-line text-accent focus:ring-accent" @checked(old('is_active', $game->is_active))>
                    <span>
                        <span class="block text-sm font-medium text-ink">Game aktif</span>
                        <span class="block text-xs text-muted">Hanya game aktif yang bisa dimainkan tamu.</span>
                    </span>
                </label>

                <div class="flex flex-wrap items-center justify-between gap-3 border-t border-line/70 pt-5">
                    <button type="submit" class="btn btn-primary">
                        <x-ui.icon name="save" class="h-4 w-4" /> Simpan pengaturan
                    </button>
                </div>
            </form>

            <div class="card border-danger/20 p-5">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h2 class="section-title text-[#8f4f58]">Hapus game</h2>
                        <p class="mt-1 text-xs text-muted">Menghapus game juga menghapus pertanyaan dan percobaan tamu.</p>
                    </div>
                    <form method="POST" action="{{ route('admin.interactions.games.destroy', $game) }}" data-confirm="Hapus game ini beserta seluruh pertanyaannya?" data-confirm-title="Hapus Game">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn btn-danger btn-sm">
                            <x-ui.icon name="trash" class="h-3.5 w-3.5" /> Hapus game
                        </button>
                    </form>
                </div>
            </div>

            <div class="card p-6">
                <div class="flex items-center justify-between gap-3">
                    <div>
                        <h2 class="section-title">Pertanyaan</h2>
                        <p class="mt-0.5 text-xs text-faint">{{ $game->questions->count() }} pertanyaan</p>
                    </div>
                    <form method="POST" action="{{ route('admin.interactions.games.toggle', $game) }}">
                        @csrf
                        <button type="submit" class="btn btn-ghost btn-sm">
                            <x-ui.icon name="refresh" class="h-3.5 w-3.5" /> {{ $game->is_active ? 'Nonaktifkan' : 'Aktifkan' }}
                        </button>
                    </form>
                </div>

                @if ($game->questions->isEmpty())
                    <div class="mt-4">
                        <x-ui.empty-state icon="list" title="Belum ada pertanyaan" description="Tambahkan pertanyaan pertama di formulir bawah." />
                    </div>
                @else
                    <ol class="mt-4 space-y-4">
                        @foreach ($game->questions as $question)
                            <li class="rounded-2xl border border-line bg-surface-2/30 p-4">
                                <div class="flex items-start justify-between gap-3">
                                    <p class="text-sm font-medium text-ink">{{ $loop->iteration }}. {{ $question->question }}</p>
                                    <form method="POST" action="{{ route('admin.interactions.games.questions.destroy', [$game, $question]) }}" data-confirm="Hapus pertanyaan ini?" data-confirm-title="Hapus Pertanyaan">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="icon-btn h-8 w-8 text-[#a35b64]" title="Hapus pertanyaan">
                                            <x-ui.icon name="trash" class="h-3.5 w-3.5" />
                                        </button>
                                    </form>
                                </div>
                                <ul class="mt-3 space-y-1.5">
                                    @foreach ($question->options as $option)
                                        <li class="flex items-center gap-2 text-xs {{ $option->is_correct ? 'font-semibold text-[#3f8069]' : 'text-muted' }}">
                                            <x-ui.icon :name="$option->is_correct ? 'check' : 'chevron-right'" class="h-3.5 w-3.5" />
                                            {{ $option->label }}
                                            @if ($option->is_correct)
                                                <span class="badge badge-success text-[9px]">Benar</span>
                                            @endif
                                        </li>
                                    @endforeach
                                </ul>
                            </li>
                        @endforeach
                    </ol>
                @endif
            </div>
        </div>

        <aside class="card h-fit p-6" x-data="{
            options: ['', ''],
            add() { if (this.options.length < 6) this.options.push('') },
            remove(i) { if (this.options.length > 2) this.options.splice(i, 1) }
        }">
            <h2 class="section-title">Tambah pertanyaan</h2>
            <form method="POST" action="{{ route('admin.interactions.games.questions.store', $game) }}" class="mt-4 space-y-4">
                @csrf
                <div>
                    <label class="label">Pertanyaan <span class="text-[#b76a73]">*</span></label>
                    <input name="question" type="text" class="input" value="{{ old('question') }}" required placeholder="Contoh: Di mana kami pertama bertemu?">
                    @error('question')<p class="error-text">{{ $message }}</p>@enderror
                </div>

                <div class="space-y-2">
                    <p class="label">Pilihan jawaban</p>
                    <template x-for="(option, index) in options" :key="index">
                        <div class="flex items-center gap-2">
                            <input type="radio" name="correct_index" :value="index" :checked="index === 0" class="h-4 w-4 border-line text-accent focus:ring-accent" title="Tandai jawaban benar">
                            <input type="text" :name="'options[' + index + ']'" x-model="options[index]" class="input" required :placeholder="'Pilihan ' + (index + 1)">
                            <button type="button" class="icon-btn h-8 w-8 shrink-0 text-[#a35b64]" x-show="options.length > 2" x-on:click="remove(index)" title="Hapus pilihan">
                                <x-ui.icon name="close" class="h-3.5 w-3.5" />
                            </button>
                        </div>
                    </template>
                    <button type="button" class="btn btn-ghost btn-sm" x-show="options.length < 6" x-on:click="add()">
                        <x-ui.icon name="plus" class="h-3.5 w-3.5" /> Tambah pilihan
                    </button>
                    <p class="hint">Pilih radio di kiri untuk menandai jawaban benar (2–6 pilihan).</p>
                </div>

                <button type="submit" class="btn btn-primary w-full">
                    <x-ui.icon name="plus" class="h-4 w-4" /> Simpan pertanyaan
                </button>
            </form>
        </aside>
    </div>
@endsection
