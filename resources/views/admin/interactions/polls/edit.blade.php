@extends('layouts.admin')

@section('title', 'Edit Polling')
@section('subtitle', 'Perbarui polling dan pilihan jawaban')

@section('content')
    <x-ui.page-header title="Edit Polling" eyebrow="Interactions" subtitle="{{ $poll->question }}">
        <x-ui.button variant="secondary" href="{{ route('admin.interactions.polls.index') }}" icon="arrow-left">
            Kembali
        </x-ui.button>
    </x-ui.page-header>

    <div class="mt-6 grid gap-6 xl:grid-cols-[minmax(0,1fr)_320px]">
        <form method="POST" action="{{ route('admin.interactions.polls.update', $poll) }}" class="card space-y-5 p-6" x-data="{ options: @js($poll->options->pluck('label')->values()) }">
            @csrf
            @method('PUT')

            <x-ui.select name="event_id" label="Event" :value="$poll->event_id" :options="$events->mapWithKeys(fn ($event) => [$event->id => $event->name])" required />

            <x-ui.input name="question" label="Pertanyaan" :value="$poll->question" required />

            <x-ui.textarea name="description" label="Deskripsi" rows="3" :value="$poll->description" />

            <div class="space-y-2">
                <p class="label">Pilihan jawaban</p>
                <template x-for="(option, index) in options" :key="index">
                    <div class="flex items-center gap-2">
                        <input type="text" :name="'options[' + index + ']'" x-model="options[index]" class="input" required>
                    </div>
                </template>
                @if ($poll->responses_count > 0)
                    <p class="hint">Polling ini sudah memiliki {{ $poll->responses_count }} respons. Label pilihan masih bisa diperbarui, tetapi jumlah pilihan tidak dapat diubah.</p>
                @endif
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label class="label">Ditutup pada</label>
                    <input name="closes_at" type="text" class="input" data-datetimepicker value="{{ old('closes_at', $poll->closes_at?->format('Y-m-d H:i')) }}" placeholder="YYYY-MM-DD HH:MM">
                </div>
                <div class="space-y-3">
                    <label class="flex items-center gap-3 rounded-2xl border border-line bg-surface-2/40 px-4 py-3">
                        <input type="checkbox" name="is_active" value="1" class="h-4 w-4 rounded border-line text-accent focus:ring-accent" @checked(old('is_active', $poll->is_active))>
                        <span class="text-sm font-medium text-ink">Aktifkan polling</span>
                    </label>
                    <label class="flex items-center gap-3 rounded-2xl border border-line bg-surface-2/40 px-4 py-3">
                        <input type="checkbox" name="show_results" value="1" class="h-4 w-4 rounded border-line text-accent focus:ring-accent" @checked(old('show_results', $poll->show_results))>
                        <span class="text-sm font-medium text-ink">Tampilkan hasil ke tamu</span>
                    </label>
                </div>
            </div>

            <div class="flex flex-wrap items-center justify-between gap-3 border-t border-line/70 pt-5">
                <button type="submit" class="btn btn-primary">
                    <x-ui.icon name="save" class="h-4 w-4" /> Simpan perubahan
                </button>
                <a href="{{ route('admin.interactions.polls.index') }}" class="btn btn-ghost">Batal</a>
            </div>
        </form>

        <aside class="space-y-4">
            <div class="card p-5">
                <h2 class="section-title">Ringkasan</h2>
                <dl class="mt-3 space-y-2.5 text-sm">
                    <div class="flex items-center justify-between gap-3">
                        <dt class="text-muted">Status</dt>
                        <dd><x-ui.badge :variant="$poll->is_active ? 'success' : 'muted'">{{ $poll->is_active ? 'Aktif' : 'Ditutup' }}</x-ui.badge></dd>
                    </div>
                    <div class="flex items-center justify-between gap-3">
                        <dt class="text-muted">Respons</dt>
                        <dd class="text-ink">{{ $poll->responses_count }}</dd>
                    </div>
                    <div class="flex items-center justify-between gap-3">
                        <dt class="text-muted">Pilihan</dt>
                        <dd class="text-ink">{{ $poll->options->count() }}</dd>
                    </div>
                    <div class="flex items-center justify-between gap-3">
                        <dt class="text-muted">Dibuat</dt>
                        <dd class="text-ink">{{ $poll->created_at?->translatedFormat('d M Y') }}</dd>
                    </div>
                </dl>
            </div>

            <div class="card p-5">
                <h2 class="section-title">Aksi</h2>
                <form method="POST" action="{{ route('admin.interactions.polls.toggle', $poll) }}" class="mt-3">
                    @csrf
                    <button type="submit" class="btn btn-soft btn-sm w-full">
                        <x-ui.icon name="refresh" class="h-3.5 w-3.5" /> {{ $poll->is_active ? 'Nonaktifkan polling' : 'Aktifkan polling' }}
                    </button>
                </form>
            </div>

            <div class="card border-danger/20 p-5">
                <h2 class="section-title text-[#8f4f58]">Hapus polling</h2>
                <p class="mt-2 text-xs text-muted">Semua respons polling akan ikut terhapus.</p>
                <form method="POST" action="{{ route('admin.interactions.polls.destroy', $poll) }}" class="mt-3" data-confirm="Hapus polling ini beserta seluruh responsnya?" data-confirm-title="Hapus Polling">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="btn btn-danger btn-sm">
                        <x-ui.icon name="trash" class="h-3.5 w-3.5" /> Hapus polling
                    </button>
                </form>
            </div>
        </aside>
    </div>
@endsection
