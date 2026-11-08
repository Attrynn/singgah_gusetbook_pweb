@extends('layouts.owner')

@section('title', $playbook->name)

@php
    use App\Enums\PlaybookStatus;

    $versions = collect($versions ?? [])->sortByDesc('version')->values();
    $latestVersion = $latestVersion ?? $playbook->latestVersion;
    $canManage = auth()->user()->can('managePlaybooks', $organization);
    $isPlatform = $playbook->isPlatform();

    $configLabels = [
        'phases' => 'Fase',
        'checklist' => 'Daftar periksa',
        'public_schedule' => 'Jadwal publik',
        'run_of_show' => 'Run of show',
        'team_roles' => 'Peran tim',
        'vendor_categories' => 'Kategori vendor',
        'reception' => 'Resepsi',
        'invitation_defaults' => 'Default undangan',
        'guestbook_defaults' => 'Default buku tamu',
    ];

    $eventTypeLabel = fn ($type) => $type ? ucfirst(str_replace('_', ' ', $type)) : 'Umum';
@endphp

@section('content')
    <x-ui.page-header
        :title="$playbook->name"
        eyebrow="Playbook"
        :subtitle="$playbook->description ?: 'Playbook struktur acara yang dapat dipakai ulang.'"
    >
        <x-ui.badge :variant="$isPlatform ? 'info' : 'neutral'">{{ $isPlatform ? 'Template platform' : 'Workspace' }}</x-ui.badge>
        <x-ui.badge :variant="$playbook->status->badge()">{{ $playbook->status->label() }}</x-ui.badge>
        <x-ui.button variant="secondary" :href="route('workspace.playbooks.index', $organization)" icon="arrow-left">Kembali</x-ui.button>
    </x-ui.page-header>

    <div class="mt-6 grid gap-5 lg:grid-cols-3">
        {{-- Ringkasan --}}
        <div class="card p-5 lg:col-span-2">
            <h2 class="section-title">Ringkasan</h2>
            <dl class="mt-4 grid gap-4 sm:grid-cols-2">
                <div>
                    <dt class="text-[11px] font-semibold uppercase tracking-[0.12em] text-muted">Jenis acara</dt>
                    <dd class="mt-1 text-sm text-ink">{{ $eventTypeLabel($playbook->event_type) }}</dd>
                </div>
                <div>
                    <dt class="text-[11px] font-semibold uppercase tracking-[0.12em] text-muted">Durasi</dt>
                    <dd class="mt-1 text-sm text-ink">{{ $playbook->days }} hari</dd>
                </div>
                <div>
                    <dt class="text-[11px] font-semibold uppercase tracking-[0.12em] text-muted">Versi terbaru</dt>
                    <dd class="mt-1 text-sm text-ink">v{{ $latestVersion?->version ?? '—' }}</dd>
                </div>
                <div>
                    <dt class="text-[11px] font-semibold uppercase tracking-[0.12em] text-muted">Dibuat oleh</dt>
                    <dd class="mt-1 text-sm text-ink">{{ $playbook->creator?->name ?? 'Sistem' }}</dd>
                </div>
            </dl>

            @if ($canManage)
                <div class="mt-5 flex flex-wrap items-center gap-2 border-t border-line/60 pt-4">
                    <form method="POST" action="{{ route('workspace.playbooks.duplicate', [$organization, $playbook]) }}" data-confirm="Duplikat playbook ini menjadi playbook workspace?" data-confirm-button="Ya, duplikat">
                        @csrf
                        <button type="submit" class="btn btn-secondary btn-sm">
                            <x-ui.icon name="copy" class="h-3.5 w-3.5" /> Duplikat
                        </button>
                    </form>
                    @if (! $isPlatform && $playbook->status === PlaybookStatus::Active)
                        <form method="POST" action="{{ route('workspace.playbooks.deactivate', [$organization, $playbook]) }}" data-confirm="Nonaktifkan playbook ini?" data-confirm-button="Ya, nonaktifkan">
                            @csrf
                            <button type="submit" class="btn btn-danger btn-sm">
                                <x-ui.icon name="close" class="h-3.5 w-3.5" /> Nonaktifkan
                            </button>
                        </form>
                    @endif
                </div>
            @endif
        </div>

        {{-- Pratinjau konfigurasi versi terbaru --}}
        <div class="card p-5">
            <h2 class="section-title">Pratinjau konfigurasi</h2>
            <p class="mt-1 text-xs text-faint">Isi versi terbaru (v{{ $latestVersion?->version ?? '—' }}).</p>

            @php $config = $latestVersion?->config ?? []; @endphp

            @if (empty($config))
                <p class="mt-3 text-xs text-faint">Belum ada konfigurasi pada versi ini.</p>
            @else
                <ul class="mt-3 space-y-2">
                    @foreach ($configLabels as $key => $label)
                        @php $value = $config[$key] ?? []; @endphp
                        <li class="flex items-center justify-between gap-2 text-xs">
                            <span class="text-muted">{{ $label }}</span>
                            <span class="font-semibold text-ink">{{ is_array($value) ? count($value) : 0 }}</span>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>
    </div>

    {{-- Daftar versi --}}
    <section class="mt-8">
        <div class="mb-3 flex items-center gap-2">
            <h2 class="section-title">Riwayat versi</h2>
            <x-ui.badge variant="neutral" class="text-[10px]">{{ $versions->count() }}</x-ui.badge>
        </div>

        @if ($versions->isEmpty())
            <x-ui.empty-state icon="file-text" title="Belum ada versi" description="Versi playbook akan tercatat setiap kali konfigurasi diperbarui." />
        @else
            <div class="space-y-3">
                @foreach ($versions as $version)
                    @php $versionConfig = $version->config ?? []; @endphp
                    <article class="card p-5" x-data="{ open: false }">
                        <div class="flex flex-wrap items-center justify-between gap-3">
                            <div class="flex items-center gap-3">
                                <span class="flex h-10 w-10 items-center justify-center rounded-2xl bg-blue-soft text-sm font-bold text-ink">v{{ $version->version }}</span>
                                <div>
                                    <p class="text-sm font-semibold text-ink">
                                        Versi {{ $version->version }}
                                        @if ($latestVersion && $latestVersion->id === $version->id)
                                            <x-ui.badge variant="success" class="ml-1 text-[10px]">Terbaru</x-ui.badge>
                                        @endif
                                    </p>
                                    <p class="text-[11px] text-faint">{{ $version->created_at?->translatedFormat('d M Y, H:i') ?? '—' }}</p>
                                </div>
                            </div>
                            <button type="button" class="btn btn-ghost btn-sm" @click="open = ! open">
                                <x-ui.icon name="chevron-down" :class="'h-3.5 w-3.5 ' + (open ? 'rotate-180' : '')" />
                                <span x-text="open ? 'Tutup' : 'Lihat konfigurasi'">Lihat konfigurasi</span>
                            </button>
                        </div>

                        @if ($version->notes)
                            <p class="mt-3 text-xs text-muted">{{ $version->notes }}</p>
                        @endif

                        <div class="mt-4 border-t border-line/60 pt-4" x-show="open" x-cloak>
                            @if (empty($versionConfig))
                                <p class="text-xs text-faint">Versi ini tidak memiliki konfigurasi.</p>
                            @else
                                <div class="grid gap-3 sm:grid-cols-3">
                                    @foreach ($configLabels as $key => $label)
                                        @php $value = $versionConfig[$key] ?? []; @endphp
                                        <div class="rounded-xl border border-line/70 bg-surface-2/30 p-3">
                                            <p class="text-[10px] font-semibold uppercase tracking-[0.1em] text-faint">{{ $label }}</p>
                                            <p class="mt-1 text-sm font-semibold text-ink">{{ is_array($value) ? count($value) : 0 }} item</p>
                                        </div>
                                    @endforeach
                                </div>

                                <details class="mt-3">
                                    <summary class="cursor-pointer text-xs font-medium text-accent">Lihat data mentah</summary>
                                    <pre class="mt-2 max-h-64 overflow-auto rounded-xl border border-line/70 bg-surface-2/40 p-3 text-[11px] leading-relaxed text-muted">{{ json_encode($versionConfig, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}</pre>
                                </details>
                            @endif
                        </div>
                    </article>
                @endforeach
            </div>
        @endif
    </section>
@endsection