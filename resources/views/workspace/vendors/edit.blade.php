@extends('layouts.owner')

@section('title', 'Ubah Vendor')

@php
    use App\Enums\VendorStatus;
    use App\Enums\VendorType;
@endphp

@section('content')
    <x-ui.page-header
        title="Ubah vendor"
        eyebrow="Vendor"
        :subtitle="'Perbarui data vendor '.$vendor->name.' di organisasi '.$organization->name.'.'"
    >
        <x-ui.button variant="secondary" :href="route('workspace.vendors.show', [$organization, $vendor])" icon="arrow-left">Kembali</x-ui.button>
    </x-ui.page-header>

    <form method="POST" action="{{ route('workspace.vendors.update', [$organization, $vendor]) }}" class="mt-5 space-y-4">
        @csrf
        @method('PUT')
        <x-ui.input name="name" label="Nama" required :value="old('name', $vendor->name)" />
        <x-ui.input name="legal_name" label="Nama Legal" :value="old('legal_name', $vendor->legal_name)" />
        <x-ui.select name="vendor_category_id" label="Kategori" :options="$categories->pluck('name','id')" :selected="old('vendor_category_id', $vendor->vendor_category_id)" placeholder="Pilih kategori (opsional)" />
        <x-ui.select name="type" label="Jenis" :options="collect(VendorType::cases())->mapWithKeys(fn($c)=>[$c->value=>$c->label()])" :selected="old('type', $vendor->type->value)" required />
        <x-ui.textarea name="description" label="Deskripsi" rows="3" :value="old('description', $vendor->description)" />
        <x-ui.input name="email" type="email" label="Email" :value="old('email', $vendor->email)" />
        <x-ui.input name="phone" label="Telepon" :value="old('phone', $vendor->phone)" />
        <x-ui.input name="website" label="Website" :value="old('website', $vendor->website)" />
        <x-ui.textarea name="address" label="Alamat" rows="2" :value="old('address', $vendor->address)" />
        <x-ui.textarea name="internal_notes" label="Catatan internal" rows="2" :value="old('internal_notes', $vendor->internal_notes)" />
        <x-ui.button type="submit" variant="primary" icon="save">Perbarui vendor</x-ui.button>
    </form>
@endsection
