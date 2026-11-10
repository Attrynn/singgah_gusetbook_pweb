@extends('layouts.owner')

@section('title', 'Edit Party - Acara')

@php
    $guestCategoryOptions = $categories->mapWithKeys(fn ($c) => [$c->id => $c->name]);
    $guestHouseholdOptions = $households->mapWithKeys(fn ($h) => [$h->id => $h->name]);
    $guestStatusOptions = [
        'active' => 'Aktif',
        'inactive' => 'Tidak aktif',
        'blocked' => 'Diblokir',
    ];

    $identifiedCount = $party->identifiedCount();
@endphp

@section('content')
    <x-ui.page-header
        title="Edit invitation party"
        eyebrow="Undangan"
        :subtitle="'Perbarui data party '.$party->name.' untuk '.$event->name.'.'"
    >
        <x-ui.button variant="secondary" :href="route('workspace.events.guests.show', [$organization, $event, $party])" icon="arrow-left">Kembali ke party</x-ui.button>
    </x-ui.page-header>

    @include('workspace.events.partials.event-tabs', ['organization' => $organization, 'event' => $event, 'setup' => $setup ?? null, 'active' => 'guests'])

    <form method="POST" action="{{ route('workspace.events.guests.update', [$organization, $event, $party]) }}" class="mt-6 grid gap-5 lg:grid-cols-3">
        @csrf
        @method('PUT')

        {{-- Data party --}}
        <div class="card p-5 lg:col-span-2">
            <h2 class="section-title">Data party</h2>
            <p class="mt-1 text-xs text-faint">Perubahan berlaku untuk seluruh individu yang terhubung dengan party ini.</p>

            <div class="mt-5 grid gap-4 sm:grid-cols-2">
                <div class="sm:col-span-2">
                    <x-ui.input name="name" label="Nama party" :value="old('name', $party->name)" required placeholder="Contoh: Keluarga Bapak Hartono" />
                </div>
                <x-ui.input name="max_attendees" label="Kuota undangan" type="number" :value="old('max_attendees', $party->max_attendees)" required min="1" max="100" hint="Kuota tidak dapat lebih kecil dari jumlah individu teridentifikasi." />
                <x-ui.select name="status" label="Status" :value="old('status', $party->status->value)" :options="$guestStatusOptions" required />
                <x-ui.select name="guest_category_id" label="Kategori" :value="old('guest_category_id', $party->guest_category_id)" :options="$guestCategoryOptions" placeholder="Tanpa kategori" />
                <x-ui.select name="guest_group_id" label="Household" :value="old('guest_group_id', $party->guest_group_id)" :options="$guestHouseholdOptions" placeholder="Tanpa household" />
                <x-ui.input name="email" label="Email" type="email" :value="old('email', $party->email)" placeholder="nama@contoh.com" />
                <x-ui.input name="phone" label="Nomor telepon" :value="old('phone', $party->phone)" placeholder="08xxxxxxxxxx" />
                <div class="sm:col-span-2">
                    <x-ui.textarea name="notes" label="Catatan" :value="old('notes', $party->notes)" rows="3" placeholder="Catatan internal mengenai party ini (opsional)" />
                </div>
            </div>
        </div>

        <div class="space-y-5">
            <div class="card p-5">
                <h2 class="section-title">Kuota &amp; identifikasi</h2>
                <dl class="mt-4 space-y-3 text-sm">
                    <div class="flex items-center justify-between gap-3">
                        <dt class="text-muted">Individu teridentifikasi</dt>
                        <dd class="font-semibold text-ink">{{ $identifiedCount }}</dd>
                    </div>
                    <div class="flex items-center justify-between gap-3">
                        <dt class="text-muted">Kuota saat ini</dt>
                        <dd class="font-semibold text-ink">{{ $party->max_attendees }}</dd>
                    </div>
                    <div class="flex items-center justify-between gap-3">
                        <dt class="text-muted">Sisa kuota</dt>
                        <dd class="font-semibold text-ink">{{ $party->remainingQuota() }}</dd>
                    </div>
                </dl>

                <div class="mt-4 flex items-start gap-3 rounded-xl bg-blue-soft/60 p-3 text-xs text-ink">
                    <x-ui.icon name="info" class="mt-0.5 h-4 w-4 shrink-0 text-accent" />
                    <p>Kuota tidak dapat diturunkan di bawah <strong>{{ $identifiedCount }} individu</strong> yang sudah teridentifikasi. Hapus atau pindahkan individu terlebih dahulu bila perlu.</p>
                </div>
            </div>

            <div class="card p-5">
                <div class="flex flex-col gap-2">
                    <button type="submit" class="btn btn-primary w-full justify-center">
                        <x-ui.icon name="save" class="h-4 w-4" /> Simpan perubahan
                    </button>
                    <a href="{{ route('workspace.events.guests.show', [$organization, $event, $party]) }}" class="btn btn-ghost w-full justify-center">Batal</a>
                </div>
            </div>
        </div>
    </form>
@endsection
