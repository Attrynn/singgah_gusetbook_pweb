@extends('layouts.admin')

@section('title', 'Import Tamu')
@section('subtitle', 'Impor daftar tamu dari CSV')

@section('content')
    <x-ui.page-header title="Import Tamu" eyebrow="Guest List" subtitle="Unggah file CSV untuk menambahkan banyak tamu sekaligus.">
        <x-ui.button variant="secondary" href="{{ route('admin.guests.index') }}" icon="arrow-left">
            Kembali
        </x-ui.button>
    </x-ui.page-header>

    @if (session('import_errors') && count(session('import_errors')))
        <div class="mt-6 rounded-2xl border border-warning/40 bg-warning/10 p-4 text-sm text-warning">
            <p class="font-semibold">Sebagian baris dilewati:</p>
            <ul class="mt-2 list-inside list-disc space-y-1 text-xs">
                @foreach (session('import_errors') as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="mt-6 grid gap-6 xl:grid-cols-[minmax(0,1fr)_360px]">
        <form method="POST" action="{{ route('admin.guests.import.store') }}" enctype="multipart/form-data" class="card space-y-5 p-6">
            @csrf

            <x-ui.select name="event_id" label="Event tujuan" :options="$events->mapWithKeys(fn ($event) => [$event->id => $event->name])" placeholder="Pilih event" required />

            <div>
                <label for="file" class="label">File CSV <span class="text-danger">*</span></label>
                <input id="file" name="file" type="file" accept=".csv,.txt" required class="input py-2 file:mr-3 file:rounded-xl file:border-0 file:bg-blue-soft file:px-3 file:py-1.5 file:text-xs file:font-semibold file:text-ink" aria-invalid="true" aria-describedby="file-error">
                @error('file')
                    <p id="file-error" class="error-text">{{ $message }}</p>
                @enderror
                <p class="hint">Maksimal 2 MB. Gunakan encoding UTF-8.</p>
            </div>

            <div class="flex flex-wrap items-center justify-between gap-3 border-t border-line/70 pt-5">
                <button type="submit" class="btn btn-primary">
                    <x-ui.icon name="download" class="h-4 w-4" /> Mulai import
                </button>
                <a href="{{ route('admin.guests.index') }}" class="btn btn-ghost">Batal</a>
            </div>
        </form>

        <aside class="card p-6">
            <h2 class="section-title">Format CSV</h2>
            <p class="mt-2 text-xs leading-relaxed text-muted">
                Baris pertama adalah header. Kolom <strong class="text-ink">name</strong> wajib; kolom lain opsional.
                Kategori dan kelompok akan dibuat otomatis bila belum ada.
            </p>
            <div class="mt-4 overflow-x-auto rounded-xl border border-line bg-surface-2/50 p-3 text-xs">
                <code class="whitespace-pre">name,email,phone,category,group,max_attendees,notes
Budi Santoso,budi@example.com,08123456789,Keluarga,Memoriam,2,
Sari Dewi,,,Teman,Kantor,1,Vegetarian</code>
            </div>
            <ul class="mt-4 space-y-1.5 text-xs text-muted">
                <li>• Duplikat nama/email pada event yang sama akan dilewati.</li>
                <li>• Email harus valid bila diisi.</li>
                <li>• Setiap tamu otomatis mendapatkan token undangan dan QR pass.</li>
            </ul>
        </aside>
    </div>
@endsection
