@extends('layouts.admin')

@section('title', 'Buat Polling')
@section('subtitle', 'Polling baru untuk tamu')

@section('content')
    <x-ui.page-header title="Buat Polling" eyebrow="Interactions" subtitle="Kumpulkan pendapat tamu dengan pertanyaan pilihan ganda.">
        <x-ui.button variant="secondary" href="{{ route('admin.interactions.polls.index') }}" icon="arrow-left">
            Kembali
        </x-ui.button>
    </x-ui.page-header>

    <form method="POST" action="{{ route('admin.interactions.polls.store') }}" class="card mt-6 max-w-3xl space-y-5 p-6" x-data="{
        options: ['', ''],
        add() { if (this.options.length < 10) this.options.push('') },
        remove(i) { if (this.options.length > 2) this.options.splice(i, 1) }
    }">
        @csrf

        <x-ui.select name="event_id" label="Event" :options="$events->mapWithKeys(fn ($event) => [$event->id => $event->name])" placeholder="Pilih event" required />

        <x-ui.input name="question" label="Pertanyaan" required placeholder="Contoh: Lagu apa yang harus diputar di resepsi?" />

        <x-ui.textarea name="description" label="Deskripsi" rows="3" placeholder="Konteks tambahan untuk tamu (opsional)." />

        <div class="space-y-2">
            <p class="label">Pilihan jawaban <span class="text-danger">*</span></p>
            <template x-for="(option, index) in options" :key="index">
                <div class="flex items-center gap-2">
                    <input type="text" :name="'options[' + index + ']'" x-model="options[index]" class="input" required :placeholder="'Pilihan ' + (index + 1)" aria-describedby="options-error">
                    <button type="button" class="icon-btn h-8 w-8 shrink-0 text-danger" x-show="options.length > 2" x-on:click="remove(index)" title="Hapus pilihan" aria-label="Hapus pilihan">
                        <x-ui.icon name="close" class="h-3.5 w-3.5" />
                    </button>
                </div>
            </template>
            <button type="button" class="btn btn-ghost btn-sm" x-show="options.length < 10" x-on:click="add()">
                <x-ui.icon name="plus" class="h-3.5 w-3.5" /> Tambah pilihan
            </button>
            @error('options')<p id="options-error" class="error-text">{{ $message }}</p>@enderror
        </div>

        <div class="grid gap-4 sm:grid-cols-2">
            <div>
                <label for="closes_at" class="label">Ditutup pada</label>
                <input id="closes_at" name="closes_at" type="text" class="input" data-datetimepicker value="{{ old('closes_at') }}" placeholder="YYYY-MM-DD HH:MM">
                <p class="hint">Kosongkan bila polling dibiarkan terbuka.</p>
            </div>
            <div class="space-y-3">
                <label for="is_active" class="flex items-center gap-3 rounded-2xl border border-line bg-surface-2/40 px-4 py-3">
                    <input id="is_active" type="checkbox" name="is_active" value="1" class="h-4 w-4 rounded border-line text-accent focus:ring-accent" @checked(old('is_active', true))>
                    <span class="text-sm font-medium text-ink">Aktifkan polling</span>
                </label>
                <label for="show_results" class="flex items-center gap-3 rounded-2xl border border-line bg-surface-2/40 px-4 py-3">
                    <input id="show_results" type="checkbox" name="show_results" value="1" class="h-4 w-4 rounded border-line text-accent focus:ring-accent" @checked(old('show_results', true))>
                    <span class="text-sm font-medium text-ink">Tampilkan hasil ke tamu</span>
                </label>
            </div>
        </div>

        <div class="flex flex-wrap items-center justify-between gap-3 border-t border-line/70 pt-5">
            <button type="submit" class="btn btn-primary">
                <x-ui.icon name="save" class="h-4 w-4" /> Simpan polling
            </button>
            <a href="{{ route('admin.interactions.polls.index') }}" class="btn btn-ghost">Batal</a>
        </div>
    </form>
@endsection
