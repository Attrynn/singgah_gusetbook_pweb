@extends('layouts.owner')

@section('title', 'Roster Individu - Acara')

@php
    $filters = array_merge([
        'search' => '',
        'status' => '',
    ], $filters ?? []);

    $memberStatusOptions = collect([
        'active' => 'Aktif',
        'inactive' => 'Tidak aktif',
        'removed' => 'Dihapus',
    ]);

    $hasActiveFilters = filled($filters['search'] ?? null) || filled($filters['status'] ?? null);
@endphp

@section('content')
    <x-ui.page-header
        title="Roster individu"
        eyebrow="Undangan"
        :subtitle="'Semua individu teridentifikasi dari invitation party di '.$event->name.'.'"
    >
        <div class="flex flex-wrap items-center gap-2">
            <x-ui.button variant="secondary" :href="route('workspace.events.guests.export', [$organization, $event, 'type' => 'individuals'])" icon="download">Ekspor individu</x-ui.button>
            <x-ui.button variant="secondary" :href="route('workspace.events.guests.index', [$organization, $event])" icon="arrow-left">Kembali ke party</x-ui.button>
        </div>
    </x-ui.page-header>

    @include('workspace.events.partials.event-tabs', ['organization' => $organization, 'event' => $event, 'setup' => $setup ?? null, 'active' => 'guests'])

    {{-- Filter & pencarian --}}
    <div class="card mt-6 p-5 animate-float-in" style="animation-delay: 0.05s">
        <form method="GET"
              action="{{ route('workspace.events.guests.individuals', [$organization, $event]) }}"
              class="grid gap-3 md:grid-cols-12"
              x-data="{ loading: false }"
              x-on:submit="loading = true">
            <div class="md:col-span-8">
                <x-ui.input name="search" label="Cari" :value="$filters['search']" placeholder="Nama individu, email, telepon, atau nama party" />
            </div>
            <div class="md:col-span-4">
                <x-ui.select name="status" label="Status" :value="$filters['status']" :options="$memberStatusOptions" placeholder="Semua status" />
            </div>

            <div class="flex flex-wrap items-end gap-2 md:col-span-12">
                <button type="submit" class="btn btn-primary" :disabled="loading">
                    <x-ui.icon name="search" class="h-4 w-4" x-show="!loading" />
                    <x-ui.icon name="refresh" class="h-4 w-4 animate-spin" x-show="loading" x-cloak />
                    <span x-text="loading ? 'Mencari…' : 'Terapkan filter'">Terapkan filter</span>
                </button>
                @if ($hasActiveFilters)
                    <a href="{{ route('workspace.events.guests.individuals', [$organization, $event]) }}" class="btn btn-ghost">
                        <x-ui.icon name="close" class="h-4 w-4" />
                        Reset
                    </a>
                @endif
            </div>
        </form>
    </div>

    {{-- Daftar individu --}}
    @if ($members->isEmpty())
        <div class="mt-6">
            @if ($hasActiveFilters)
                <x-ui.empty-state icon="search" title="Tidak ada individu yang cocok" description="Coba ubah kata kunci atau status, lalu terapkan filter kembali.">
                    <a href="{{ route('workspace.events.guests.individuals', [$organization, $event]) }}" class="btn btn-soft btn-sm">Bersihkan filter</a>
                </x-ui.empty-state>
            @else
                <x-ui.empty-state icon="users" title="Belum ada individu teridentifikasi" description="Tambahkan individu dari halaman detail invitation party untuk mulai menyusun roster.">
                    <x-ui.button variant="secondary" :href="route('workspace.events.guests.index', [$organization, $event])" icon="arrow-left">Buka daftar party</x-ui.button>
                </x-ui.empty-state>
            @endif
        </div>
    @else
        <div class="mt-6 overflow-hidden rounded-2xl border border-line/70 bg-surface/60">
            <table class="w-full divide-y divide-line/60 text-sm">
                <thead class="bg-surface-2/60">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-[0.1em] text-muted">Individu</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-[0.1em] text-muted">Party</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-[0.1em] text-muted">Kategori</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-[0.1em] text-muted">Household</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-[0.1em] text-muted">Status</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-[0.1em] text-muted">Pass</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($members as $member)
                        @php
                            $hasActivePass = $member->passes->contains(fn ($pass) => $pass->status === 'active');
                        @endphp
                        <tr class="border-t border-line/60 hover:bg-surface-2/40">
                            <td class="px-4 py-3">
                                <div class="flex items-center gap-3">
                                    <x-ui.avatar :name="$member->name" size="sm" />
                                    <div class="min-w-0">
                                        <p class="truncate text-sm font-semibold text-ink">{{ $member->name }}</p>
                                        <p class="truncate text-xs text-muted">{{ $member->email ?: 'Email belum diisi' }}</p>
                                        <p class="truncate text-xs text-faint">{{ $member->phone ?: 'Nomor telepon belum diisi' }}</p>
                                    </div>
                                </div>
                            </td>
                            <td class="px-4 py-3 text-xs">
                                @if ($member->guest)
                                    <a href="{{ route('workspace.events.guests.show', [$organization, $event, $member->guest]) }}" class="font-medium text-ink hover:underline">{{ $member->guest->name }}</a>
                                @else
                                    <span class="text-faint">—</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-xs text-muted">{{ $member->guest?->category?->name ?? '—' }}</td>
                            <td class="px-4 py-3 text-xs text-muted">{{ $member->guest?->household?->name ?? '—' }}</td>
                            <td class="px-4 py-3">
                                <x-ui.badge :variant="$member->status->badge()" class="text-[10px]">{{ $member->status->label() }}</x-ui.badge>
                            </td>
                            <td class="px-4 py-3">
                                <x-ui.badge :variant="$hasActivePass ? 'success' : 'muted'" class="text-[10px]">
                                    {{ $hasActivePass ? 'Tersedia' : 'Belum diterbitkan' }}
                                </x-ui.badge>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif

    @include('owner.partials.pagination', ['paginator' => $members])
@endsection
