@extends('layouts.owner')

@section('title', 'Tambah Vendor')

@php
    use App\Enums\VendorStatus;
    use App\Enums\VendorType;
@endphp

@section('content')
    <x-ui.page-header
        title="Tambah vendor"
        eyebrow="Vendor"
        :subtitle="'Masukkan data vendor baru untuk organisasi '.$organization->name.'.'"
    >
        <x-ui.button variant="secondary" :href="route('workspace.vendors.index', $organization)" icon="arrow-left">Kembali</x-ui.button>
    </x-ui.page-header>

    <form method="POST" action="{{ route('workspace.vendors.store', $organization) }}" class="mt-5 space-y-4">
        @csrf
        <x-ui.input name="name" label="Nama" required placeholder="Nama vendor" />
        <x-ui.input name="legal_name" label="Nama Legal" placeholder="Nama resmi (opsional)" />
        <x-ui.select name="vendor_category_id" label="Kategori" :options="$categories->pluck('name','id')" placeholder="Pilih kategori (opsional)" />
        <x-ui.select name="type" label="Jenis" :options="collect(VendorType::cases())->mapWithKeys(fn($c)=>[$c->value=>$c->label()])" required />
        <x-ui.textarea name="description" label="Deskripsi" rows="3" placeholder="Deskripsi singkat (opsional)" />
        <x-ui.input name="email" type="email" label="Email" placeholder="email@domain.com" />
        <x-ui.input name="phone" label="Telepon" placeholder="+62 812‑3456‑7890" />
        <x-ui.input name="website" label="Website" placeholder="https://example.com" />
        <x-ui.textarea name="address" label="Alamat" rows="2" placeholder="Alamat lengkap" />
        <x-ui.textarea name="internal_notes" label="Catatan internal" rows="2" placeholder="Catatan untuk tim internal (opsional)" />
        <x-ui.button type="submit" variant="primary" icon="save">Simpan vendor</x-ui.button>
    </form>
@endsection
