@extends('layouts.owner')

@section('title', 'Floor Plan - '.$event->name)

@section('content')
    <x-ui.page-header
        title="Floor Plan"
        eyebrow="Layout"
        :subtitle="'Kelola layout lantai untuk '.$event->name.'.'"
    >
        <x-ui.badge variant="info">{{ $floorPlans->count() }} floor plan</x-ui.badge>
        <x-ui.button variant="secondary" :href="route('workspace.events.show', [$organization, $event])" icon="arrow-left">Ringkasan</x-ui.button>
    </x-ui.page-header>

    @include('workspace.events.partials.event-tabs', ['organization' => $organization, 'event' => $event, 'active' => 'floor-plans'])

    <div class="mt-6 flex items-start gap-3 rounded-2xl border border-line/70 bg-blue-soft/50 p-4 text-sm text-ink">
        <x-ui.icon name="info" class="mt-0.5 h-4 w-4 shrink-0 text-accent" />
        <p>
            Halaman ini mengatur layout lantai untuk acara ini. Setiap floor plan memiliki versi draf dan terbit.
        </p>
    </div>

    <div class="mt-6 grid gap-5 lg:grid-cols-3">
        {{-- Buat floor plan --}}
        @if ($canManage)
            <div class="card p-5 lg:col-span-1">
                <div class="flex items-center gap-3">
                    <span class="flex h-10 w-10 items-center justify-center rounded-2xl bg-blue-soft text-ink">
                        <x-ui.icon name="plus" class="h-5 w-5" />
                    </span>
                    <div>
                        <h2 class="section-title">Buat Floor Plan</h2>
                        <p class="mt-0.5 text-xs text-faint">Susun layout tamu di canvas.</p>
                    </div>
                </div>

                <form method="POST" action="{{ route('workspace.events.floor-plans.store', [$organization, $event]) }}" class="mt-5 space-y-4">
                    @csrf
                    <x-ui.input name="name" label="Nama floor plan" required placeholder="Contoh: Layout Utama" />
                    <x-ui.textarea name="description" label="Deskripsi" rows="2" placeholder="Deskripsi layout (opsional)" />
                    <div class="grid grid-cols-2 gap-3">
                        <x-ui.input name="canvas_width" label="Lebar canvas" type="number" min="100" placeholder="2000" />
                        <x-ui.input name="canvas_height" label="Tinggi canvas" type="number" min="100" placeholder="1500" />
                    </div>
                    <x-ui.input name="grid_size" label="Ukuran grid" type="number" min="1" placeholder="50" />

                    <button type="submit" class="btn btn-primary w-full justify-center">
                        <x-ui.icon name="plus" class="h-4 w-4" /> Buat Floor Plan
                    </button>
                </form>
            </div>
        @endif

        {{-- Daftar floor plan --}}
        <div class="{{ $canManage ? 'lg:col-span-2' : 'lg:col-span-3' }}">
            @if ($floorPlans->isEmpty())
                <x-ui.empty-state
                    icon="grid"
                    title="Belum ada floor plan"
                    description="Buat floor plan pertama untuk mulai menyusun layout tamu."
                >
                    @if ($canManage)
                        <a href="#floor-plan-form" class="btn btn-primary">
                            <x-ui.icon name="plus" class="h-4 w-4" /> Buat floor plan pertama
                        </a>
                    @endif
                </x-ui.empty-state>
            @else
                <div class="space-y-6">
                    @foreach ($floorPlans as $floorPlan)
                        <div class="card p-5">
                            <div class="flex items-start justify-between gap-3">
                                <div class="min-w-0 flex-1">
                                    <div class="flex items-center gap-2">
                                        <h3 class="text-lg font-semibold text-ink">{{ $floorPlan->name }}</h3>
                                        <x-ui.badge :variant="$floorPlan->status === \App\Enums\FloorPlanStatus::Archived ? 'muted' : 'success'" class="text-[10px]">{{ $floorPlan->status->label() }}</x-ui.badge>
                                    </div>

                                    @if ($floorPlan->venue)
                                        <p class="mt-1 text-sm text-muted">
                                            <x-ui.icon name="map" class="mr-1 inline h-3.5 w-3.5 text-faint" />
                                            {{ $floorPlan->venue->name }}
                                        </p>
                                    @endif

                                    @if ($floorPlan->venueSpace)
                                        <p class="mt-1 text-sm text-muted">
                                            <x-ui.icon name="box" class="mr-1 inline h-3.5 w-3.5 text-faint" />
                                            {{ $floorPlan->venueSpace->name }}
                                        </p>
                                    @endif

                                    @if ($floorPlan->currentDraft)
                                        <p class="mt-1 text-xs text-faint">
                                            <x-ui.icon name="draft" class="mr-1 inline h-3.5 w-3.5 text-faint" />
                                            Draf: v{{ $floorPlan->currentDraft->version_number }}
                                        </p>
                                    @endif

                                    @if ($floorPlan->publishedVersion)
                                        <p class="mt-1 text-xs text-faint">
                                            <x-ui.icon name="check" class="mr-1 inline h-3.5 w-3.5 text-success" />
                                            Terbit: v{{ $floorPlan->publishedVersion->version_number }}
                                        </p>
                                    @endif
                                </div>

                                <div class="flex items-center gap-2">
                                    <a href="{{ route('workspace.events.floor-plans.editor', [$organization, $event, $floorPlan]) }}" class="btn btn-ghost btn-sm">
                                        <x-ui.icon name="edit" class="h-3.5 w-3.5" />
                                        <span>Buka Editor</span>
                                    </a>

                                    @if ($canManage && $floorPlan->currentDraft === null && $floorPlan->publishedVersion !== null)
                                        <form method="POST"
                                              action="{{ route('workspace.events.floor-plans.versions.store', [$organization, $event, $floorPlan]) }}"
                                              class="inline"
                                              data-confirm="Buat draf baru dari versi terbit terakhir?"
                                              data-confirm-button="Ya, buat draf">
                                            @csrf
                                            <button type="submit" class="btn btn-primary btn-sm">
                                                <x-ui.icon name="plus" class="h-3.5 w-3.5" />
                                                <span class="text-xs">Draf baru</span>
                                            </button>
                                        </form>
                                    @endif
                                </div>
                            </div>

                            {{-- Version history --}}
                            @if ($floorPlan->currentDraft || $floorPlan->publishedVersion)
                                <div class="mt-5 border-t border-line/60 pt-5">
                                    <h4 class="text-sm font-semibold text-ink">Riwayat Versi</h4>

                                    <div class="mt-3 overflow-x-auto">
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
                                                    @if ($canManage)
                                                        <th class="text-right">Aksi</th>
                                                    @endif
                                                </tr>
                                            </thead>
                                            <tbody>
                                                @php
                                                    $versions = collect([
                                                        $floorPlan->currentDraft,
                                                        $floorPlan->publishedVersion,
                                                    ])->filter()->unique('id')->sortByDesc('version_number');
                                                @endphp

                                                @foreach ($versions as $version)
                                                    <tr>
                                                        <td class="font-medium text-ink">{{ $version->version_number }}</td>
                                                        <td>
                                                            <x-ui.badge :variant="$version->status === \App\Enums\FloorPlanVersionStatus::Draft ? 'info' : ($version->status === \App\Enums\FloorPlanVersionStatus::Published ? 'success' : 'muted')" class="text-[10px]">
                                                                {{ $version->status->label() }}
                                                            </x-ui.badge>
                                                        </td>
                                                        <td>{{ $version->object_count ?: '—' }}</td>
                                                        <td>{{ $version->table_count ?: '—' }}</td>
                                                        <td>{{ $version->chair_count ?: '—' }}</td>
                                                        <td>{{ $version->zone_count ?: '—' }}</td>
                                                        <td>{{ $version->seat_capacity ?: '—' }}</td>
                                                        @if ($canManage)
                                                            <td class="text-right">
                                                                @if ($version->status === \App\Enums\FloorPlanVersionStatus::Draft)
                                                                    <a href="{{ route('workspace.events.floor-plans.editor', [$organization, $event, $floorPlan]) }}" class="btn btn-ghost btn-sm" aria-label="Buka editor versi draf">
                                                                        <x-ui.icon name="edit" class="h-3.5 w-3.5" />
                                                                    </a>
                                                                @else
                                                                    <span class="text-xs text-faint">—</span>
                                                                @endif
                                                            </td>
                                                        @endif
                                                    </tr>
                                                @endforeach
                                            </tbody>
                                        </table>
                                    </div>

                                    {{-- Version comparison summary --}}
                                    @if ($floorPlan->currentDraft && $floorPlan->publishedVersion)
                                        <div class="mt-4 p-4 bg-surface/50 rounded-xl border border-line/60">
                                            <h4 class="text-sm font-semibold text-ink mb-3">Perbandingan Versi Terbit vs Draf</h4>
                                            <div class="grid grid-cols-2 gap-4">
                                                <div>
                                                    <p class="text-xs text-faint uppercase tracking-[0.1em] text-muted">Terbit (v{{ $floorPlan->publishedVersion->version_number }})</p>
                                                    <ul class="list-disc list-inside space-y-1 text-sm">
                                                        <li>Objek: {{ $floorPlan->publishedVersion->object_count }}</li>
                                                        <li>Meja: {{ $floorPlan->publishedVersion->table_count }}</li>
                                                        <li>Kursi: {{ $floorPlan->publishedVersion->chair_count }}</li>
                                                        <li>Zona: {{ $floorPlan->publishedVersion->zone_count }}</li>
                                                        <li>Tempat duduk: {{ $floorPlan->publishedVersion->seat_capacity }}</li>
                                                    </ul>
                                                </div>
                                                <div>
                                                    <p class="text-xs text-faint uppercase tracking-[0.1em] text-muted">Draf (v{{ $floorPlan->currentDraft->version_number }})</p>
                                                    <ul class="list-disc list-inside space-y-1 text-sm">
                                                        <li>Objek: {{ $floorPlan->currentDraft->object_count }}</li>
                                                        <li>Meja: {{ $floorPlan->currentDraft->table_count }}</li>
                                                        <li>Kursi: {{ $floorPlan->currentDraft->chair_count }}</li>
                                                        <li>Zona: {{ $floorPlan->currentDraft->zone_count }}</li>
                                                        <li>Tempat duduk: {{ $floorPlan->currentDraft->seat_capacity }}</li>
                                                    </ul>
                                                </div>
                                            </div>
                                        </div>
                                    @endif
                                </div>
                            @endif

                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    </div>
@endsection