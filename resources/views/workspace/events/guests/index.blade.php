@extends('layouts.owner')

@section('title', 'Undangan - Acara')

@php
    $filters = array_merge([
        'search' => '',
        'guest_category_id' => '',
        'guest_group_id' => '',
        'status' => '',
    ], $filters ?? []);

    $guestStatusOptions = collect([
        'active' => 'Aktif',
        'inactive' => 'Tidak aktif',
        'blocked' => 'Diblokir',
    ]);

    $guestCategoryOptions = $categories->mapWithKeys(fn ($c) => [$c->id => $c->name]);
    $guestHouseholdOptions = $households->mapWithKeys(fn ($h) => [$h->id => $h->name]);

    $hasActiveFilters = filled($filters['search'] ?? null)
        || filled($filters['guest_category_id'] ?? null)
        || filled($filters['guest_group_id'] ?? null)
        || filled($filters['status'] ?? null);
@endphp

@section('content')
    <x-ui.page-header
        title="Undangan party"
        eyebrow="Acara"
        :subtitle="'Kelola invitation party dan identifikasi individu yang diundang untuk '.$event->name"
    >
        <div class="flex flex-wrap items-center gap-2">
            <x-ui.button variant="secondary" :href="route('workspace.events.guests.create', [$organization, $event])" icon="plus">Tambah party</x-ui.button>
            <x-ui.button variant="secondary" :href="route('workspace.events.guests.individuals', [$organization, $event])" icon="users">Roster individu</x-ui.button>
            <x-ui.button variant="secondary" :href="route('workspace.events.guests.import', [$organization, $event])" icon="file-text">Impor CSV</x-ui.button>
            <x-ui.button variant="secondary" :href="route('workspace.events.guests.export', [$organization, $event, 'type' => 'parties'])" icon="download">Ekspor party</x-ui.button>
            <x-ui.button variant="secondary" :href="route('workspace.events.guests.export', [$organization, $event, 'type' => 'individuals'])" icon="download">Ekspor individu</x-ui.button>
        </div>
    </x-ui.page-header>

    @include('workspace.events.partials.event-tabs', ['organization' => $organization, 'event' => $event, 'setup' => $setup ?? null, 'active' => 'guests'])

    {{-- Statistik --}}
    <div class="mt-6 grid grid-cols-2 gap-3 sm:grid-cols-3 xl:grid-cols-6">
        <x-ui.stat-card label="Total party" :value="$stats['total_parties']" icon="list" tone="accent" />
        <x-ui.stat-card label="Individu teridentifikasi" :value="$stats['total_identified']" icon="user-check" tone="teal" />
        <x-ui.stat-card label="Total kuota" :value="$stats['total_quota']" icon="banknote" tone="accent" />
        <x-ui.stat-card label="Hadir (RSVP)" :value="$stats['total_rsvp_attendance']" icon="clipboard" tone="success" />
        <x-ui.stat-card label="Kapasitas belum teridentifikasi" :value="$stats['unidentified_capacity']" icon="pin" tone="warning" />
        <x-ui.stat-card label="Pass aktif" :value="$stats['passes_issued']" icon="ticket" tone="danger" />
    </div>

    {{-- Filter & pencarian --}}
    <div class="card mt-6 p-5 animate-float-in" style="animation-delay: 0.05s">
        <form method="GET"
              action="{{ route('workspace.events.guests.index', [$organization, $event]) }}"
              class="grid gap-3 md:grid-cols-12"
              x-data="{ loading: false }"
              x-on:submit="loading = true">
            <div class="md:col-span-4">
                <x-ui.input name="search" label="Cari" :value="$filters['search']" placeholder="Nama party, email, atau nomor telepon" />
            </div>
            <div class="md:col-span-2">
                <x-ui.select name="guest_category_id" label="Kategori" :value="$filters['guest_category_id']" :options="$guestCategoryOptions" placeholder="Semua kategori" />
            </div>
            <div class="md:col-span-2">
                <x-ui.select name="guest_group_id" label="Household" :value="$filters['guest_group_id']" :options="$guestHouseholdOptions" placeholder="Semua household" />
            </div>
            <div class="md:col-span-2">
                <x-ui.select name="status" label="Status" :value="$filters['status']" :options="$guestStatusOptions" placeholder="Semua status" />
            </div>

            <div class="flex flex-wrap items-end gap-2 md:col-span-12">
                <button type="submit" class="btn btn-primary" :disabled="loading">
                    <x-ui.icon name="search" class="h-4 w-4" x-show="!loading" />
                    <x-ui.icon name="refresh" class="h-4 w-4 animate-spin" x-show="loading" x-cloak />
                    <span x-text="loading ? 'Mencari…' : 'Terapkan filter'">Terapkan filter</span>
                </button>
                @if ($hasActiveFilters)
                    <a href="{{ route('workspace.events.guests.index', [$organization, $event]) }}" class="btn btn-ghost">
                        <x-ui.icon name="close" class="h-4 w-4" />
                        Reset
                    </a>
                @endif
            </div>
        </form>
    </div>

    {{-- Daftar party --}}
    @if ($parties->isEmpty())
        <div class="mt-6">
            @if ($hasActiveFilters)
                <x-ui.empty-state icon="search" title="Tidak ada party yang cocok" description="Coba ubah kata kunci, kategori, household, atau status, lalu terapkan filter kembali.">
                    <a href="{{ route('workspace.events.guests.index', [$organization, $event]) }}" class="btn btn-soft btn-sm">Bersihkan filter</a>
                </x-ui.empty-state>
            @else
                <x-ui.empty-state icon="users" title="Belum ada invitation party" description="Buat invitation party pertama Anda untuk mulai mengundang tamu.">
                    <x-ui.button variant="secondary" :href="route('workspace.events.guests.create', [$organization, $event])" icon="plus">Buat party</x-ui.button>
                </x-ui.empty-state>
            @endif
        </div>
    @else
        <form method="POST" action="{{ route('workspace.events.guests.bulk', [$organization, $event]) }}" class="mt-6 overflow-hidden rounded-2xl border border-line/70 bg-surface/60">
            @csrf

            <table class="w-full divide-y divide-line/60 text-sm">
                <thead class="bg-surface-2/60">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-[0.1em] text-muted">Pilih</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-[0.1em] text-muted">Nama party</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-[0.1em] text-muted">Kontak</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-[0.1em] text-muted">Identifikasi</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-[0.1em] text-muted">Kategori</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-[0.1em] text-muted">Household</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-[0.1em] text-muted">Status</th>
                        <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-[0.1em] text-muted">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($parties as $party)
                        <tr class="border-t border-line/60 hover:bg-surface-2/40">
                            <td class="px-4 py-3">
                                <input type="checkbox" name="guest_ids[]" value="{{ $party->id }}" class="h-4 w-4 rounded border-line text-accent focus:ring-accent" aria-label="{{ $party->name }}">
                            </td>
                            <td class="px-4 py-3">
                                <a href="{{ route('workspace.events.guests.show', [$organization, $event, $party]) }}" class="text-sm font-semibold text-ink hover:underline">{{ $party->name }}</a>
                            </td>
                            <td class="px-4 py-3 text-xs text-muted">
                                @if ($party->email) <div>{{ $party->email }}</div> @endif
                                @if ($party->phone) <div>{{ $party->phone }}</div> @endif
                            </td>
                            <td class="px-4 py-3 text-xs text-muted">{{ $party->active_members_count }}/{{ $party->max_attendees }} individu</td>
                            <td class="px-4 py-3 text-xs text-muted">{{ $party->category?->name ?? '—' }}</td>
                            <td class="px-4 py-3 text-xs text-muted">{{ $party->household?->name ?? '—' }}</td>
                            <td class="px-4 py-3">
                                <x-ui.badge :variant="$party->status->badge()" class="text-[10px]">{{ $party->status->label() }}</x-ui.badge>
                            </td>
                            <td class="px-4 py-3 text-right">
                                <a href="{{ route('workspace.events.guests.edit', [$organization, $event, $party]) }}" class="btn btn-ghost btn-sm">
                                    <x-ui.icon name="edit" class="h-3.5 w-3.5" /> Edit
                                </a>
                                <button type="submit" form="delete-party-{{ $party->id }}" class="btn btn-danger btn-sm">
                                    <x-ui.icon name="trash" class="h-3.5 w-3.5" /> Hapus
                                </button>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>

            <div class="flex flex-wrap items-center justify-between gap-3 border-t border-line/70 px-5 py-4">
                <div class="flex flex-wrap items-center gap-2">
                    <select name="action" class="input input-sm appearance-none" aria-label="Aksi bulk">
                        <option value="">Pilih aksi…</option>
                        <option value="archive">Arsipkan</option>
                        <option value="category">Ubah kategori</option>
                        <option value="household">Ubah household</option>
                    </select>
                    @if ($guestCategoryOptions->isNotEmpty())
                        <select name="guest_category_id" class="input input-sm appearance-none" aria-label="Kategori baru">
                            <option value="">Kategori baru…</option>
                            @foreach ($guestCategoryOptions as $id => $name)
                                <option value="{{ $id }}">{{ $name }}</option>
                            @endforeach
                        </select>
                    @endif
                    @if ($guestHouseholdOptions->isNotEmpty())
                        <select name="guest_group_id" class="input input-sm appearance-none" aria-label="Household baru">
                            <option value="">Household baru…</option>
                            @foreach ($guestHouseholdOptions as $id => $name)
                                <option value="{{ $id }}">{{ $name }}</option>
                            @endforeach
                        </select>
                    @endif
                </div>
                <button type="submit" class="btn btn-primary btn-sm">
                    <x-ui.icon name="check" class="h-4 w-4" /> Lakukan aksi
                </button>
            </div>
        </form>

        {{-- Form arsip per baris dipisah agar tidak bersarang di dalam form bulk. --}}
        @foreach ($parties as $party)
            <form id="delete-party-{{ $party->id }}"
                  method="POST"
                  action="{{ route('workspace.events.guests.destroy', [$organization, $event, $party]) }}"
                  data-confirm="Arsipkan invitation party '{{ $party->name }}'?"
                  data-confirm-button="Ya, arsipkan"
                  class="hidden">
                @csrf
                @method('DELETE')
            </form>
        @endforeach
    @endif

    @include('owner.partials.pagination', ['paginator' => $parties])
@endsection
