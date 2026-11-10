@extends('layouts.owner')

@section('title', 'Impor Tamu - Acara')

@section('content')
    <x-ui.page-header
        title="Impor party dari CSV"
        eyebrow="Undangan"
        :subtitle="'Unggah berkas CSV untuk membuat invitation party beserta individu pertamanya di '.$event->name.'.'"
    >
        <x-ui.button variant="secondary" :href="route('workspace.events.guests.index', [$organization, $event])" icon="arrow-left">Kembali ke party</x-ui.button>
    </x-ui.page-header>

    @include('workspace.events.partials.event-tabs', ['organization' => $organization, 'event' => $event, 'setup' => $setup ?? null, 'active' => 'guests'])

    <div class="mt-6 grid gap-5 lg:grid-cols-3">
        {{-- Form impor --}}
        <div class="card p-5 lg:col-span-2">
            <h2 class="section-title">Unggah berkas</h2>
            <p class="mt-1 text-xs text-faint">Gunakan berkas CSV dengan pemisah koma dan baris pertama sebagai nama kolom.</p>

            <form method="POST" action="{{ route('workspace.events.guests.import.store', [$organization, $event]) }}" enctype="multipart/form-data" class="mt-5 space-y-4">
                @csrf

                <div>
                    <label for="file" class="label">
                        Berkas CSV
                        <span class="text-danger" aria-hidden="true">*</span>
                        <span class="sr-only">(wajib)</span>
                    </label>
                    <input id="file" name="file" type="file" accept=".csv,.txt" required class="input" @error('file') aria-invalid="true" @enderror>
                    @error('file')
                        <p class="error-text">{{ $message }}</p>
                    @enderror
                    <p class="hint">Format yang didukung: .csv atau .txt, ukuran maksimal 2 MB.</p>
                </div>

                <div class="flex flex-wrap items-center gap-2">
                    <button type="submit" class="btn btn-primary">
                        <x-ui.icon name="file-text" class="h-4 w-4" /> Impor sekarang
                    </button>
                    <a href="{{ route('workspace.events.guests.index', [$organization, $event]) }}" class="btn btn-ghost">Batal</a>
                </div>
            </form>
        </div>

        <div class="space-y-5">
            {{-- Format CSV --}}
            <div class="card p-5">
                <div class="flex items-center gap-3">
                    <span class="flex h-10 w-10 items-center justify-center rounded-2xl bg-blue-soft text-ink">
                        <x-ui.icon name="list" class="h-5 w-5" />
                    </span>
                    <div>
                        <h2 class="section-title">Format CSV</h2>
                        <p class="mt-0.5 text-xs text-faint">Kolom wajib dan opsional.</p>
                    </div>
                </div>

                <dl class="mt-4 space-y-3 text-xs">
                    <div>
                        <dt class="font-semibold text-ink">Wajib</dt>
                        <dd class="mt-0.5 text-muted"><code class="font-mono">name</code> atau <code class="font-mono">nama</code> — nama invitation party.</dd>
                    </div>
                    <div>
                        <dt class="font-semibold text-ink">Opsional</dt>
                        <dd class="mt-0.5 space-y-1 text-muted">
                            <p><code class="font-mono">email</code> — alamat email party.</p>
                            <p><code class="font-mono">phone</code> atau <code class="font-mono">telepon</code> — nomor telepon party.</p>
                            <p><code class="font-mono">max_attendees</code>, <code class="font-mono">kuota</code>, atau <code class="font-mono">quota</code> — kuota undangan.</p>
                            <p><code class="font-mono">notes</code> atau <code class="font-mono">catatan</code> — catatan internal.</p>
                        </dd>
                    </div>
                </dl>

                <div class="mt-4 rounded-xl bg-surface-2/60 p-3">
                    <p class="text-[10px] font-semibold uppercase tracking-[0.1em] text-faint">Contoh baris</p>
                    <pre class="mt-1 overflow-x-auto text-[11px] leading-relaxed text-muted">name,email,phone,max_attendees
Keluarga Hartono,hartono@contoh.com,08123456789,4</pre>
                </div>
            </div>

            {{-- Cara kerja --}}
            <div class="card p-5">
                <div class="flex items-start gap-3 rounded-xl bg-blue-soft/60 p-3 text-xs text-ink">
                    <x-ui.icon name="info" class="mt-0.5 h-4 w-4 shrink-0 text-accent" />
                    <div class="space-y-2">
                        <p>Setiap baris membuat <strong>satu invitation party</strong> dengan individu bernama sesuai kolom <code class="font-mono">name</code> sebagai anggota pertama yang teridentifikasi.</p>
                        <p>Data lama dapat diselaraskan melalui perintah migrasi berikut:</p>
                        <pre class="overflow-x-auto rounded-lg bg-surface p-2 text-[11px] text-muted">php artisan guests:migrate-m4 --dry-run</pre>
                        <p>Tinjau hasilnya, lalu jalankan kembali tanpa <code class="font-mono">--dry-run</code> untuk menerapkan perubahan.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
