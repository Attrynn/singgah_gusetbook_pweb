@extends('layouts.admin')

@section('title', 'Buat Game')
@section('subtitle', 'Game kuis baru untuk tamu')

@section('content')
    <x-ui.page-header title="Buat Game" eyebrow="Mini Games" subtitle="Setelah game dibuat, tambahkan pertanyaan dan pilihan jawaban.">
        <x-ui.button variant="secondary" href="{{ route('admin.interactions.games.index') }}" icon="arrow-left">
            Kembali
        </x-ui.button>
    </x-ui.page-header>

    <form method="POST" action="{{ route('admin.interactions.games.store') }}" class="card mt-6 max-w-2xl space-y-5 p-6">
        @csrf

        <x-ui.select name="event_id" label="Event" :options="$events->mapWithKeys(fn ($event) => [$event->id => $event->name])" placeholder="Pilih event" required />

        <x-ui.input name="title" label="Judul game" required placeholder="Contoh: Seberapa kenal kamu dengan kami?" />

        <x-ui.textarea name="description" label="Deskripsi" rows="3" placeholder="Jelaskan singkat aturan atau tema kuis." />

        <label for="is_active" class="flex items-center gap-3 rounded-2xl border border-line bg-surface-2/40 px-4 py-3">
            <input id="is_active" type="checkbox" name="is_active" value="1" class="h-4 w-4 rounded border-line text-accent focus:ring-accent" @checked(old('is_active', true))>
            <span>
                <span class="block text-sm font-medium text-ink">Aktifkan game</span>
                <span class="block text-xs text-muted">Game aktif langsung tampil di halaman interaksi tamu.</span>
            </span>
        </label>

        <div class="flex flex-wrap items-center justify-between gap-3 border-t border-line/70 pt-5">
            <button type="submit" class="btn btn-primary">
                <x-ui.icon name="save" class="h-4 w-4" /> Simpan &amp; lanjut
            </button>
            <a href="{{ route('admin.interactions.games.index') }}" class="btn btn-ghost">Batal</a>
        </div>
    </form>
@endsection
