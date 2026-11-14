@extends('layouts.owner')

@section('title', 'Floor Plan - '.$floorPlan->name)

@php
    use App\Enums\FloorPlanVersionStatus;

    $versions = collect($floorPlan->versions ?? []);
    $currentDraft = $floorPlan->currentDraft;
    $publishedVersion = $floorPlan->publishedVersion;
    $draftVersion = $currentDraft;
    $published = $publishedVersion;
@endphp

@section('content')
    <x-ui.page-header
        :title="$floorPlan->name"
        :subtitle="$floorPlan->venue?->name ?: 'Venue belum ditentukan'.($floorPlan->venueSpace ? ' · '.$floorPlan->venueSpace->name : '')"
        eyebrow="Floor Plan"
    >
        <x-ui.badge :variant="$floorPlan->status === \App\Enums\FloorPlanStatus::Archived ? 'muted' : 'success'">{{ $floorPlan->status->label() }}</x-ui.badge>
        <x-ui.button variant="secondary" :href="route('workspace.events.floor-plans.index', [$organization, $event])" icon="arrow-left">Daftar Floor Plan</x-ui.button>
    </x-ui.page-header>

    @include('workspace.events.partials.event-tabs', ['organization' => $organization, 'event' => $event, 'active' => 'floor-plans'])

    <div class="mt-6 grid gap-5 lg:grid-cols-3">
        {{-- Detail plan --}}
        <div class="card p-5 lg:col-span-2">
            <div class="flex items-center justify-between gap-3">
                <h2 class="section-title">Detail Floor Plan</h2>
                <x-ui.badge :variant="$floorPlan->status === \App\Enums\FloorPlanStatus::Archived ? 'muted' : 'success'" class="text-[10px]">{{ $floorPlan->status->label() }}</x-ui.badge>
            </div>

            @if ($floorPlan->description)
                <p class="mt-3 text-sm text-muted">{{ $floorPlan->description }}</p>
            @endif

            <dl class="mt-4 grid grid-cols-2 gap-4 text-sm">
                <div>
                    <dt class="text-[11px] font-semibold uppercase tracking-[0.12em] text-muted">Venue</dt>
                    <dd class="mt-1 font-medium text-ink">{{ $floorPlan->venue?->name ?? 'Belum ditentukan' }}</dd>
                </div>
                <div>
                    <dt class="text-[11px] font-semibold uppercase tracking-[0.12em] text-muted">Ruang</dt>
                    <dd class="mt-1 font-medium text-ink">{{ $floorPlan->venueSpace?->name ?? 'Belum ditentukan' }}</dd>
                </div>
                <div>
                    <dt class="text-[11px] font-semibold uppercase tracking-[0.12em] text-muted">Draf</dt>
                    <dd class="mt-1 font-medium text-ink">{{ $draftVersion ? 'v'.$draftVersion->version_number : 'Belum ada draf' }}</dd>
                </div>
                <div>
                    <dt class="text-[11px] font-semibold uppercase tracking-[0.12em] text-muted">Terbit</dt>
                    <dd class="mt-1 font-medium text-ink">{{ $published ? 'v'.$published->version_number : 'Belum ada versi terbit' }}</dd>
                </div>
            </dl>

            @if ($canManage)
                <div class="mt-5 flex flex-wrap items-center gap-2 border-t border-line/60 pt-4">
                    @if ($draftVersion)
                        <a href="{{ route('workspace.events.floor-plans.editor', [$organization, $event, $floorPlan]) }}" class="btn btn-primary btn-sm">
                            <x-ui.icon name="edit" class="h-3.5 w-3.5" /> Buka Editor
                        </a>
                    @endif

                    @if ($canPublish && $draftVersion && $draftVersion->status === FloorPlanVersionStatus::Draft)
                        <form method="POST" action="{{ route('workspace.events.floor-plans.versions.publish', [$organization, $event, $floorPlan, $draftVersion]) }}" class="inline" data-confirm="Terbitkan draf versi {{ $draftVersion->version_number }}?">
                            @csrf
                            <button type="submit" class="btn btn-success btn-sm">
                                <x-ui.icon name="check" class="h-3.5 w-3.5" /> Terbitkan
                            </button>
                        </form>
                    @endif

                    @if ($canManage && $draftVersion === null && $published !== null)
                        <form method="POST" action="{{ route('workspace.events.floor-plans.versions.store', [$organization, $event, $floorPlan]) }}" class="inline" data-confirm="Buat draf baru dari versi terbit terakhir?">
                            @csrf
                            <button type="submit" class="btn btn-secondary btn-sm">
                                <x-ui.icon name="plus" class="h-3.5 w-3.5" /> Buat Draf Baru
                            </button>
                        </form>
                    @endif

                    @if ($canDelete && $published === null)
                        <form method="POST" action="{{ route('workspace.events.floor-plans.destroy', [$organization, $event, $floorPlan]) }}" class="inline" data-confirm="Hapus floor plan '{{ $floorPlan->name }}'? Tindakan ini tidak dapat dibatalkan." data-confirm-button="Ya, hapus">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="btn btn-danger btn-sm">
                                <x-ui.icon name="trash" class="h-3.5 w-3.5" /> Hapus Floor Plan
                            </button>
                        </form>
                    @endif
                </div>
            @endif
        </div>

        {{-- Version summary --}}
        <div class="card p-5">
            <h2 class="section-title">Ringkasan</h2>
            <dl class="mt-4 space-y-3 text-sm">
                <div class="flex items-center justify-between">
                    <dt class="text-muted">Objek</dt>
                    <dd class="font-semibold text-ink">{{ $draftVersion?->object_count ?? 0 }}</dd>
                </div>
                <div class="flex items-center justify-between">
                    <dt class="text-muted">Meja</dt>
                    <dd class="font-semibold text-ink">{{ $draftVersion?->table_count ?? 0 }}</dd>
                </div>
                <div class="flex items-center justify-between">
                    <dt class="text-muted">Kursi</dt>
                    <dd class="font-semibold text-ink">{{ $draftVersion?->chair_count ?? 0 }}</dd>
                </div>
                <div class="flex items-center justify-between">
                    <dt class="text-muted">Zona</dt>
                    <dd class="font-semibold text-ink">{{ $draftVersion?->zone_count ?? 0 }}</dd>
                </div>
                <div class="flex items-center justify-between">
                    <dt class="text-muted">Tempat Duduk</dt>
                    <dd class="font-semibold text-ink">{{ $draftVersion?->seat_capacity ?? 0 }}</dd>
                </div>
            </dl>

            @if ($draftVersion && $published)
                <div class="mt-5 border-t border-line/60 pt-4">
                    <h3 class="text-sm font-semibold text-ink">Perbandingan Versi Terbit vs Draf</h3>
                    <div class="grid grid-cols-2 gap-4">
                        <div class="rounded-xl border border-line/60 bg-surface/50 p-4">
                            <p class="text-xs text-faint uppercase tracking-[0.1em] text-muted">Terbit (v{{ $published->version_number }})</p>
                            <ul class="mt-2 space-y-1 text-sm">
                                <li>Objek: {{ $published->object_count }}</li>
                                <li>Meja: {{ $published->table_count }}</li>
                                <li>Kursi: {{ $published->chair_count }}</li>
                                <li>Zona: {{ $published->zone_count }}</li>
                                <li>Tempat Duduk: {{ $published->seat_capacity }}</li>
                            </ul>
                        </div>
                        <div class="rounded-xl border border-line/60 bg-surface/50 p-4">
                            <p class="text-xs text-faint uppercase tracking-[0.1em] text-muted">Draf (v{{ $draftVersion->version_number }})</p>
                            <ul class="mt-2 space-y-1 text-sm">
                                <li>Objek: {{ $draftVersion->object_count }}</li>
                                <li>Meja: {{ $draftVersion->table_count }}</li>
                                <li>Kursi: {{ $draftVersion->chair_count }}</li>
                                <li>Zona: {{ $draftVersion->zone_count }}</li>
                                <li>Tempat Duduk: {{ $draftVersion->seat_capacity }}</li>
                            </ul>
                        </div>
                    </div>
                </div>
            @endif
        </div>
    </div>

    {{-- Version history --}}
    <div class="mt-6">
        <div class="card p-5">
            <div class="flex items-center justify-between gap-3">
                <h2 class="section-title">Riwayat Versi</h2>
                <span class="text-xs text-faint">{{ $versions->count() }} versi</span>
            </div>

            @if ($versions->isEmpty())
                <x-ui.empty-state icon="history" title="Belum ada versi" description="Buat draf baru untuk memulai menyusun layout." />
            @else
                <div class="mt-4 overflow-x-auto">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Versi</th>
                                <th>Status</th>
                                <th>Objek</th>
                                <th>Meja</th>
                                <th>Kursi</th>
                                <th>Zona</th>
                                <th>Tempat Duduk</th>
                                <th>Penyusun</th>
                                <th>Penerbit</th>
                                <th>Terbit</th>
                                <th>Dibuat</th>
                                @if ($canManage)
                                    <th class="text-right">Aksi</th>
                                @endif
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($versions as $version)
                                <tr>
                                    <td class="font-medium text-ink">v{{ $version->version_number }}</td>
                                    <td>
                                        <x-ui.badge :variant="$version->status === FloorPlanVersionStatus::Draft ? 'info' : ($version->status === FloorPlanVersionStatus::Published ? 'success' : 'muted')" class="text-[10px]">
                                            {{ $version->status->label() }}
                                        </x-ui.badge>
                                    </td>
                                    <td>{{ $version->object_count ?: '—' }}</td>
                                    <td>{{ $version->table_count ?: '—' }}</td>
                                    <td>{{ $version->chair_count ?: '—' }}</td>
                                    <td>{{ $version->zone_count ?: '—' }}</td>
                                    <td>{{ $version->seat_capacity ?: '—' }}</td>
                                    <td>{{ $version->creator?->name ?? '—' }}</td>
                                    <td>{{ $version->publisher?->name ?? '—' }}</td>
                                    <td>{{ $version->published_at?->translatedFormat('d M Y, H:i') ?? '—' }}</td>
                                    <td>{{ $version->created_at?->translatedFormat('d M Y, H:i') ?? '—' }}</td>
                                    @if ($canManage)
                                        <td class="text-right">
                                            @if ($version->status === FloorPlanVersionStatus::Draft)
                                                <a href="{{ route('workspace.events.floor-plans.editor', [$organization, $event, $floorPlan]) }}" class="btn btn-ghost btn-sm" aria-label="Buka editor versi draf">
                                                    <x-ui.icon name="edit" class="h-3.5 w-3.5" />
                                                </a>
                                            @endif
                                            @if ($version->status === FloorPlanVersionStatus::Published && $canPublish)
                                                <form method="POST" action="{{ route('workspace.events.floor-plans.versions.archive', [$organization, $event, $floorPlan, $version]) }}" class="inline" data-confirm="Arsipkan versi {{ $version->version_number }}?">
                                                    @csrf
                                                    <button type="submit" class="btn btn-danger btn-sm" aria-label="Arsipkan versi">
                                                        <x-ui.icon name="archive" class="h-3.5 w-3.5" />
                                                    </button>
                                                </form>
                                            @endif
                                        </td>
                                    @endif
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>
@endsection