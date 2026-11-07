@extends('layouts.admin')

@section('title', 'Pengaturan')
@section('subtitle', 'Konfigurasi sistem VOWSPACE')

@section('content')
    <x-ui.page-header title="Pengaturan" eyebrow="System" subtitle="Konfigurasi global yang berlaku untuk seluruh event dan pengguna." />

    @php
        $timezones = collect(\DateTimeZone::listIdentifiers())
            ->mapWithKeys(fn ($zone) => [$zone => str_replace('_', ' ', $zone)])
            ->all();
    @endphp

    <div class="mt-6 grid gap-4 sm:grid-cols-3">
        <x-ui.stat-card label="Total event" :value="$counts['events']" icon="calendar" />
        <x-ui.stat-card label="Owner terdaftar" :value="$counts['owners']" icon="user-check" tone="teal" />
        <x-ui.stat-card label="Baris audit log" :value="number_format($counts['audit_logs'])" icon="shield" tone="accent" />
    </div>

    <div class="mt-6 grid gap-6 xl:grid-cols-[minmax(0,1fr)_400px]">
        <form method="POST" action="{{ route('admin.settings.update') }}" class="card space-y-5 p-6">
            @csrf
            @method('PUT')

            <div>
                <h2 class="section-title">Identitas aplikasi</h2>
                <p class="mt-1 text-xs text-muted">Nama dan kontak dukungan yang tampil di antarmuka serta email sistem.</p>
            </div>

            <div class="grid gap-5 sm:grid-cols-2">
                <x-ui.input name="app_name" label="Nama aplikasi" :value="$settings['app_name']" required hint="Maksimal 100 karakter." />

                <x-ui.input name="support_email" type="email" label="Email dukungan" :value="$settings['support_email']" required hint="Digunakan pada halaman bantuan dan notifikasi." />

                <div class="sm:col-span-2">
                    <x-ui.select name="default_timezone" label="Zona waktu default" :value="$settings['default_timezone']" :options="$timezones" required hint="Dipakai untuk event baru dan tampilan waktu sistem." />
                </div>
            </div>

            <div class="border-t border-line/70 pt-5">
                <h2 class="section-title">Moderasi</h2>
                <label for="guestbook_auto_approve" class="mt-4 flex items-start gap-3 rounded-2xl border border-line bg-surface-2/40 px-4 py-3">
                    <input id="guestbook_auto_approve" type="checkbox" name="guestbook_auto_approve" value="1" class="mt-0.5 h-4 w-4 rounded border-line text-accent focus:ring-accent" @checked((string) $settings['guestbook_auto_approve'] === '1')>
                    <span>
                        <span class="block text-sm font-medium text-ink">Setujui ucapan otomatis</span>
                        <span class="block text-xs text-muted">Ucapan tamu langsung tampil tanpa moderasi. Nonaktifkan untuk meninjau setiap ucapan terlebih dahulu.</span>
                    </span>
                </label>
            </div>

            <div class="flex flex-wrap items-center justify-between gap-3 border-t border-line/70 pt-5">
                <button type="submit" class="btn btn-primary">
                    <x-ui.icon name="save" class="h-4 w-4" /> Simpan pengaturan
                </button>
                <a href="{{ route('admin.profile.edit') }}" class="btn btn-ghost">
                    <x-ui.icon name="user" class="h-4 w-4" /> Profil saya
                </a>
            </div>
        </form>

        <div class="space-y-4">
            <div class="card p-5">
                <h2 class="section-title">Aktivitas sistem terbaru</h2>
                @if ($recentLogs->isEmpty())
                    <div class="mt-4">
                        <x-ui.empty-state icon="shield" title="Belum ada aktivitas" description="Aktivitas administratif akan tercatat di sini." />
                    </div>
                @else
                    <ol class="mt-4 space-y-4">
                        @foreach ($recentLogs as $log)
                            <li class="flex gap-3">
                                <span class="mt-0.5 flex h-7 w-7 shrink-0 items-center justify-center rounded-xl bg-blue-soft text-ink">
                                    <x-ui.icon name="sparkles" class="h-3.5 w-3.5" />
                                </span>
                                <div class="min-w-0">
                                    <p class="text-xs font-medium text-ink">{{ $log->description ?: $log->action }}</p>
                                    <p class="mt-0.5 text-[11px] text-faint">
                                        {{ $log->user?->name ?? 'Sistem' }} · {{ $log->created_at?->translatedFormat('d M Y H:i') }}
                                    </p>
                                </div>
                            </li>
                        @endforeach
                    </ol>
                    <a href="{{ route('admin.audit-logs.index') }}" class="btn btn-ghost btn-sm mt-4 w-full">
                        <x-ui.icon name="arrow-up-right" class="h-3.5 w-3.5" /> Lihat semua log
                    </a>
                @endif
            </div>

            <div class="card p-5">
                <h2 class="section-title">Informasi runtime</h2>
                <dl class="mt-3 space-y-2.5 text-sm">
                    <div class="flex items-center justify-between gap-3">
                        <dt class="text-muted">Laravel</dt>
                        <dd class="text-ink">{{ app()->version() }}</dd>
                    </div>
                    <div class="flex items-center justify-between gap-3">
                        <dt class="text-muted">PHP</dt>
                        <dd class="text-ink">{{ PHP_VERSION }}</dd>
                    </div>
                    <div class="flex items-center justify-between gap-3">
                        <dt class="text-muted">Lingkungan</dt>
                        <dd><x-ui.badge :variant="app()->environment('production') ? 'success' : 'warning'">{{ app()->environment() }}</x-ui.badge></dd>
                    </div>
                    <div class="flex items-center justify-between gap-3">
                        <dt class="text-muted">Zona waktu aktif</dt>
                        <dd class="text-ink">{{ config('app.timezone') }}</dd>
                    </div>
                </dl>
            </div>
        </div>
    </div>
@endsection
