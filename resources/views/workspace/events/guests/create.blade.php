@extends('layouts.owner')

@section('title', 'Tambah Party - Acara')

@php
    $guestCategoryOptions = $categories->mapWithKeys(fn ($c) => [$c->id => $c->name]);
    $guestHouseholdOptions = $households->mapWithKeys(fn ($h) => [$h->id => $h->name]);
    $guestStatusOptions = [
        'active' => 'Aktif',
        'inactive' => 'Tidak aktif',
        'blocked' => 'Diblokir',
    ];

    $initialMembers = collect(old('members', []))->values();

    if ($initialMembers->isEmpty()) {
        $initialMembers = collect([
            ['name' => '', 'email' => '', 'phone' => ''],
            ['name' => '', 'email' => '', 'phone' => ''],
            ['name' => '', 'email' => '', 'phone' => ''],
        ]);
    }

    $memberErrorKeys = collect($errors->getBag('default')->keys())
        ->filter(fn ($key) => str_starts_with($key, 'members.'))
        ->values();
@endphp

@section('content')
    <x-ui.page-header
        title="Tambah invitation party"
        eyebrow="Undangan"
        :subtitle="'Buat satu invitation party untuk '.$event->name.'. Party dapat menampung beberapa individu sesuai kuota.'"
    >
        <x-ui.button variant="secondary" :href="route('workspace.events.guests.index', [$organization, $event])" icon="arrow-left">Kembali</x-ui.button>
    </x-ui.page-header>

    @include('workspace.events.partials.event-tabs', ['organization' => $organization, 'event' => $event, 'setup' => $setup ?? null, 'active' => 'guests'])

    <form method="POST" action="{{ route('workspace.events.guests.store', [$organization, $event]) }}" class="mt-6 grid gap-5 lg:grid-cols-3">
        @csrf

        {{-- Data party --}}
        <div class="card p-5 lg:col-span-2">
            <h2 class="section-title">Data party</h2>
            <p class="mt-1 text-xs text-faint">Nama party biasanya mewakili keluarga atau rombongan yang menerima satu undangan.</p>

            <div class="mt-5 grid gap-4 sm:grid-cols-2">
                <div class="sm:col-span-2">
                    <x-ui.input name="name" label="Nama party" :value="old('name')" required placeholder="Contoh: Keluarga Bapak Hartono" />
                </div>
                <x-ui.input name="max_attendees" label="Kuota undangan" type="number" :value="old('max_attendees', 2)" required min="1" max="100" hint="Jumlah maksimal individu yang dapat diidentifikasi." />
                <x-ui.select name="status" label="Status" :value="old('status', 'active')" :options="$guestStatusOptions" required />
                <x-ui.select name="guest_category_id" label="Kategori" :value="old('guest_category_id')" :options="$guestCategoryOptions" placeholder="Tanpa kategori" />
                <x-ui.select name="guest_group_id" label="Household" :value="old('guest_group_id')" :options="$guestHouseholdOptions" placeholder="Tanpa household" />
                <x-ui.input name="email" label="Email" type="email" :value="old('email')" placeholder="nama@contoh.com" />
                <x-ui.input name="phone" label="Nomor telepon" :value="old('phone')" placeholder="08xxxxxxxxxx" />
                <div class="sm:col-span-2">
                    <x-ui.textarea name="notes" label="Catatan" :value="old('notes')" rows="3" placeholder="Catatan internal mengenai party ini (opsional)" />
                </div>
            </div>
        </div>

        {{-- Individu awal --}}
        <div class="space-y-5">
            <div class="card p-5" x-data="{ members: @js($initialMembers) }">
                <div class="flex items-center gap-3">
                    <span class="flex h-10 w-10 items-center justify-center rounded-2xl bg-blue-soft text-ink">
                        <x-ui.icon name="users" class="h-5 w-5" />
                    </span>
                    <div>
                        <h2 class="section-title">Individu awal</h2>
                        <p class="mt-0.5 text-xs text-faint">Opsional. Isi nama individu yang sudah diketahui.</p>
                    </div>
                </div>

                <div class="mt-5 space-y-4">
                    <template x-for="(member, index) in members" :key="index">
                        <div class="space-y-2 rounded-xl border border-line/70 p-3">
                            <div class="flex items-center justify-between gap-2">
                                <p class="text-[10px] font-semibold uppercase tracking-[0.1em] text-faint">
                                    Individu <span x-text="index + 1"></span>
                                </p>
                                <button type="button" class="btn btn-ghost btn-sm" @click="members.splice(index, 1)" x-show="members.length > 1">
                                    <x-ui.icon name="trash" class="h-3.5 w-3.5" /> Hapus
                                </button>
                            </div>
                            <input type="text" :name="`members[${index}][name]`" x-model="members[index].name" class="input" placeholder="Nama individu">
                            <input type="email" :name="`members[${index}][email]`" x-model="members[index].email" class="input" placeholder="Email (opsional)">
                            <input type="text" :name="`members[${index}][phone]`" x-model="members[index].phone" class="input" placeholder="Nomor telepon (opsional)">
                        </div>
                    </template>

                    @if ($memberErrorKeys->isNotEmpty())
                        <div class="rounded-xl border border-danger/30 bg-danger/10 px-3 py-2 text-xs text-danger">
                            @foreach ($memberErrorKeys as $key)
                                <p>{{ $errors->first($key) }}</p>
                            @endforeach
                        </div>
                    @endif

                    <button type="button" class="btn btn-secondary btn-sm w-full justify-center" @click="members.push({ name: '', email: '', phone: '' })">
                        <x-ui.icon name="plus" class="h-4 w-4" /> Tambah baris
                    </button>
                </div>
            </div>

            <div class="card p-5">
                <div class="flex items-start gap-3 rounded-xl bg-blue-soft/60 p-3 text-xs text-ink">
                    <x-ui.icon name="info" class="mt-0.5 h-4 w-4 shrink-0 text-accent" />
                    <p>Individu lain dapat ditambahkan nanti dari halaman detail party selama kuota masih tersedia.</p>
                </div>

                <div class="mt-4 flex flex-col gap-2">
                    <button type="submit" class="btn btn-primary w-full justify-center">
                        <x-ui.icon name="plus" class="h-4 w-4" /> Simpan party
                    </button>
                    <a href="{{ route('workspace.events.guests.index', [$organization, $event]) }}" class="btn btn-ghost w-full justify-center">Batal</a>
                </div>
            </div>
        </div>
    </form>
@endsection
