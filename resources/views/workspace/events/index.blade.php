@extends('layouts.owner')

@section('title', 'Portofolio Acara')

@php
    use App\Enums\EventStatus;

    $summary = array_merge([
        'total' => 0,
        'draft' => 0,
        'upcoming' => 0,
        'in_progress' => 0,
        'completed' => 0,
        'archived' => 0,
        'setup_incomplete' => 0,
        'no_manager' => 0,
    ], $summary ?? []);

    $filters = array_merge([
        'search' => '',
        'status' => '',
        'date_from' => '',
        'date_to' => '',
        'sort' => 'event_date',
    ], $filters ?? []);

    $statusOptions = collect($statuses ?? EventStatus::cases())
        ->mapWithKeys(fn ($status) => [$status->value => $status->stageLabel()]);

    $sortOptions = [
        'event_date' => 'Tanggal terlama',
        'event_date_desc' => 'Tanggal terbaru',
        'name' => 'Nama A–Z',
        'created_at_desc' => 'Baru dibuat',
    ];

    /* Group the current page of results under the five lifecycle stages. */
    $stageOrder = [
        EventStatus::Draft->value => ['label' => 'Draft', 'hint' => 'Belum dipublikasikan'],
        EventStatus::Published->value => ['label' => 'Akan datang', 'hint' => 'Sudah dipublikasikan'],
        EventStatus::Live->value => ['label' => 'Berlangsung', 'hint' => 'Sedang berjalan'],
        EventStatus::Completed->value => ['label' => 'Selesai', 'hint' => 'Sudah selesai'],
        EventStatus::Archived->value => ['label' => 'Diarsipkan', 'hint' => 'Disimpan sebagai arsip'],
    ];

    $grouped = ($events->getCollection())->groupBy(fn ($item) => $item->status->value);

    $hasActiveFilters = filled($filters['search'] ?? null)
        || filled($filters['status'] ?? null)
        || filled($filters['date_from'] ?? null)
        || filled($filters['date_to'] ?? null);

    $setupBadge = function ($config): array {
        if (! $config) {
            return ['variant' => 'muted', 'label' => 'Belum dikonfigurasi'];
        }

        return ['variant' => str_replace('badge-', '', $config->setup_status->badge()), 'label' => $config->setup_status->label()];
    };
@endphp

@section('content')
    <x-ui.page-header
        title="Portofolio Acara"
        eyebrow="Workspace"
        :subtitle="'Semua acara yang dikelola oleh '.$organization->name.'. Pantau status, kesiapan, dan penanggung jawabnya.'"
    >
        <x-ui.button href="{{ route('workspace.events.create', $organization) }}" icon="plus">Buat acara</x-ui.button>
    </x-ui.page-header>

    {{-- Ringkasan --}}
    <div class="mt-6 grid grid-cols-2 gap-3 sm:grid-cols-3 xl:grid-cols-6">
        <x-ui.stat-card label="Total acara" :value="$summary['total']" icon="calendar" tone="accent" />
        <x-ui.stat-card label="Akan datang" :value="$summary['upcoming']" icon="calendar-check" tone="teal" />
        <x-ui.stat-card label="Berlangsung" :value="$summary['in_progress']" icon="play" tone="success" />
        <x-ui.stat-card label="Draft" :value="$summary['draft']" icon="edit" tone="warning" />
        <x-ui.stat-card label="Setup belum lengkap" :value="$summary['setup_incomplete']" icon="sliders" tone="danger" />
        <x-ui.stat-card label="Tanpa manajer" :value="$summary['no_manager']" icon="user-check" tone="accent" />
    </div>

    {{-- Filter & pencarian --}}
    <div class="card mt-6 p-5 animate-float-in" style="animation-delay: 0.05s">
        <form method="GET"
              action="{{ route('workspace.events.index', $organization) }}"
              class="grid gap-3 md:grid-cols-12"
              x-data="{ loading: false }"
              x-on:submit="loading = true">
            <div class="md:col-span-4">
                <x-ui.input name="search" label="Cari" :value="$filters['search']" placeholder="Nama acara, pasangan, atau venue" />
            </div>
            <div class="md:col-span-2">
                <x-ui.select name="status" label="Status" :value="$filters['status']" :options="$statusOptions" placeholder="Semua status" />
            </div>
            <div class="md:col-span-2">
                <x-ui.input name="date_from" label="Dari tanggal" :value="$filters['date_from']" placeholder="Pilih tanggal" data-datepicker readonly aria-label="Dari tanggal" />
            </div>
            <div class="md:col-span-2">
                <x-ui.input name="date_to" label="Sampai tanggal" :value="$filters['date_to']" placeholder="Pilih tanggal" data-datepicker readonly aria-label="Sampai tanggal" />
            </div>
            <div class="md:col-span-2">
                <x-ui.select name="sort" label="Urutkan" :value="$filters['sort'] ?: 'event_date'" :options="$sortOptions" />
            </div>

            <div class="flex flex-wrap items-end gap-2 md:col-span-12">
                <button type="submit" class="btn btn-primary" :disabled="loading">
                    <x-ui.icon name="search" class="h-4 w-4" x-show="!loading" />
                    <x-ui.icon name="refresh" class="h-4 w-4 animate-spin" x-show="loading" x-cloak />
                    <span x-text="loading ? 'Mencari…' : 'Terapkan filter'">Terapkan filter</span>
                </button>
                @if ($hasActiveFilters)
                    <a href="{{ route('workspace.events.index', $organization) }}" class="btn btn-ghost">
                        <x-ui.icon name="close" class="h-4 w-4" />
                        Reset
                    </a>
                @endif
            </div>
        </form>
    </div>

    {{-- Daftar acara --}}
    @if ($grouped->flatten()->isEmpty())
        <div class="mt-6">
            @if ($hasActiveFilters)
                <x-ui.empty-state icon="search" title="Tidak ada acara yang cocok" description="Coba ubah kata kunci atau rentang tanggal, lalu terapkan filter kembali.">
                    <a href="{{ route('workspace.events.index', $organization) }}" class="btn btn-soft btn-sm">Bersihkan filter</a>
                </x-ui.empty-state>
            @else
                <x-ui.empty-state icon="calendar" title="Belum ada acara" description="Buat acara pertama Anda untuk mulai menyusun persiapan, tim, dan run of show.">
                    <x-ui.button href="{{ route('workspace.events.create', $organization) }}" icon="plus">Buat acara</x-ui.button>
                </x-ui.empty-state>
            @endif
        </div>
    @else
        @foreach ($stageOrder as $statusValue => $stage)
            @php $group = $grouped->get($statusValue, collect()); @endphp
            @if ($group->isNotEmpty())
                <section class="mt-8 animate-float-in" style="animation-delay: 0.1s">
                    <div class="mb-3 flex items-center justify-between gap-3">
                        <div class="flex items-center gap-2">
                            <h2 class="section-title">{{ $stage['label'] }}</h2>
                            <x-ui.badge variant="muted" class="text-[10px]">{{ $group->count() }}</x-ui.badge>
                        </div>
                        <p class="hidden text-xs text-faint sm:block">{{ $stage['hint'] }}</p>
                    </div>

                    <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                        @foreach ($group as $event)
                            @php
                                $setup = $event->setupConfig;
                                $readiness = $setupBadge($setup);
                                $pic = $setup?->primaryPic?->user;
                            @endphp
                            <article class="card flex flex-col p-5 transition hover:bg-surface-2/40">
                                <div class="flex items-start justify-between gap-3">
                                    <div class="min-w-0">
                                        <p class="truncate text-sm font-semibold text-ink">{{ $event->name }}</p>
                                        <p class="truncate text-xs text-muted">{{ $event->coupleNames() }}</p>
                                    </div>
                                    <x-ui.badge :variant="$event->status->badge()" class="shrink-0 text-[10px]">{{ $event->status->stageLabel() }}</x-ui.badge>
                                </div>

                                <dl class="mt-4 space-y-2 text-xs">
                                    <div class="flex items-center gap-2 text-muted">
                                        <x-ui.icon name="calendar" class="h-3.5 w-3.5 shrink-0 text-faint" />
                                        <span>{{ $event->event_date?->translatedFormat('d F Y') ?? 'Tanggal belum ditetapkan' }}</span>
                                    </div>
                                    <div class="flex items-center gap-2 text-muted">
                                        <x-ui.icon name="map-pin" class="h-3.5 w-3.5 shrink-0 text-faint" />
                                        <span class="truncate">{{ $event->primaryVenue?->name ?? $event->venue_name ?? 'Venue belum ditetapkan' }}</span>
                                    </div>
                                    <div class="flex items-center gap-2 text-muted">
                                        <x-ui.icon name="user-check" class="h-3.5 w-3.5 shrink-0 text-faint" />
                                        <span class="truncate">
                                            @if ($pic)
                                                {{ $pic->name }}
                                            @else
                                                <span class="text-faint">Manajer acara belum ditetapkan</span>
                                            @endif
                                        </span>
                                    </div>
                                </dl>

                                <div class="mt-4 flex flex-wrap items-center gap-2 border-t border-line/60 pt-4">
                                    <x-ui.badge :variant="$readiness['variant']" class="text-[10px]">{{ $readiness['label'] }}</x-ui.badge>
                                </div>

                                <div class="mt-4 flex items-center gap-2">
                                    <a href="{{ route('workspace.events.show', [$organization, $event]) }}" class="btn btn-secondary btn-sm">
                                        <x-ui.icon name="eye" class="h-3.5 w-3.5" />
                                        Buka acara
                                    </a>
                                    @can('update', $event)
                                        <a href="{{ route('workspace.events.edit', [$organization, $event]) }}" class="btn btn-ghost btn-sm">
                                            <x-ui.icon name="edit" class="h-3.5 w-3.5" />
                                            Edit
                                        </a>
                                    @endcan
                                </div>
                            </article>
                        @endforeach
                    </div>
                </section>
            @endif
        @endforeach

        @include('owner.partials.pagination', ['paginator' => $events])
    @endif
@endsection
