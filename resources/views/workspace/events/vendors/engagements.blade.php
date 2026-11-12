@extends('layouts.owner')

@section('title', 'Engagement Vendor · '.$event->name)

@php
    use App\Enums\VendorEngagementStatus;
@endphp

@section('content')
<div class="container mx-auto px-4 py-8">
    <x-ui.page-header
        title="Engagement Vendor"
        eyebrow="Vendor"
        :subtitle="'Kelola engagement vendor untuk acara '.$event->name.'.'"
    >
        <x-ui.button variant="secondary" :href="route('workspace.events.vendors.index', [$organization, $event])" icon="arrow-left">Kembali</x-ui.button>
    </x-ui.page-header>

    @include('workspace.events.partials.event-tabs', ['organization' => $organization, 'event' => $event, 'active' => 'vendors-engagements'])

    {{-- Filter bar --}}
    <form method="GET" action="{{ route('workspace.events.vendors.engagements.index', [$organization, $event]) }}" class="mt-5 flex flex-wrap items-end gap-3">
        <div class="flex-1 min-w-0 max-w-sm">
            <x-ui.select name="status" label="Status">
                <option value="">Semua status</option>
                @foreach (VendorEngagementStatus::cases() as $case)
                    <option value="{{ $case->value }}" @selected($filters['status'] === $case->value)>{{ $case->label() }}</option>
                @endforeach
            </x-ui.select>
        </div>
        <button type="submit" class="btn btn-primary btn-sm">
            <x-ui.icon name="filter" class="h-3.5 w-3.5" /> Terapkan
        </button>
        @if (array_filter(array_values($filters)))
            <a href="{{ route('workspace.events.vendors.engagements.index', [$organization, $event]) }}" class="btn btn-ghost btn-sm">Reset</a>
        @endif
    </form>

    {{-- Engagement list --}}
    <div class="mt-5 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
        @if ($engagements->isEmpty())
            <x-ui.empty-state
                icon="calendar-check"
                title="Belum ada engagement"
                description="Engagement vendor dibuat setelah vendor dipilih dari proposal yang masuk."
            />
        @else
            @foreach ($engagements as $engagement)
                <div class="card p-4 rounded-2xl border border-line/70 bg-surface transition hover:bg-surface-2/40">
                    <div class="flex items-start justify-between gap-2">
                        <div class="min-w-0 flex-1">
                            <p class="text-sm font-semibold text-ink">{{ $engagement->title }}</p>
                            <p class="text-[10px] text-muted mt-1">
                                {{ $engagement->vendor?->name ?? 'Vendor' }} · {{ $engagement->coordinator?->user?->name ?? 'Belum ada koordinator' }}
                            </p>
                            @if ($canManageCommercial && $engagement->agreed_amount !== null)
                                <p class="mt-1 font-serif text-sm font-semibold text-ink">{{ $formatMoney($engagement->agreed_amount, $engagement->currency) }}</p>
                            @endif
                        </div>
                        <span class="badge {{ $engagement->status->badge() }} shrink-0 text-[9px]">{{ $engagement->status->label() }}</span>
                    </div>
                    <div class="mt-3 flex items-center gap-2">
                        <a href="{{ route('workspace.events.vendors.engagements.show', [$organization, $event, $engagement]) }}" class="btn btn-primary btn-sm w-full">
                            <x-ui.icon name="eye" class="h-3.5 w-3.5" /> Lihat
                        </a>
                    </div>
                </div>
            @endforeach
        @endif
    </div>

    @include('owner.partials.pagination', ['paginator' => $engagements])

    {{-- Create form/modal --}}
    @if ($canManage)
        <x-ui.modal name="engagement-create" title="Buat Engagement" description="Buat engagement vendor baru untuk acara ini.">
            <form method="POST" action="{{ route('workspace.events.vendors.engagements.store', [$organization, $event]) }}" class="space-y-3">
                @csrf
                <x-ui.input name="vendor_id" label="Vendor" required placeholder="Pilih vendor" />
                <x-ui.input name="title" label="Judul" required placeholder="Judul engagement" />
                <x-ui.input name="event_vendor_requirement_id" label="Kebutuhan Vendor" placeholder="Pilih kebutuhan (opsional)" />
                <x-ui.input name="event_vendor_proposal_id" label="Proposal Vendor" placeholder="Pilih proposal (opsional)" />
                <x-ui.input name="coordinator_membership_id" label="Koordinator" placeholder="Pilih koordinator (opsional)" />
                <x-ui.input name="vendor_contact_id" label="Kontak Vendor" placeholder="Pilih kontak (opsional)" />
                <x-ui.input name="start_date" label="Tanggal Mulai" type="date" placeholder="Tanggal mulai" />
                <x-ui.input name="end_date" label="Tanggal Selesai" type="date" placeholder="Tanggal selesai" />
                <div class="flex items-center justify-end gap-2 pt-2">
                    <button type="button" class="btn btn-ghost" x-on:click="$dispatch('close-modal-engagement-create')">Batal</button>
                    <button type="submit" class="btn btn-primary"><x-ui.icon name="save" class="h-4 w-4" /> Simpan</button>
                </div>
            </form>
        </x-ui.modal>
    @endif
</div>
@endsection