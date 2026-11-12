@extends('layouts.owner')

@section('title', 'Kategori Vendor')

@php
    use App\Enums\VendorStatus;
@endphp

@section('content')
    <x-ui.page-header
        title="Kategori vendor"
        eyebrow="Workspace"
        :subtitle="'Kelola kategori vendor untuk organisasi '.$organization->name.'.'"
    >
        @if ($canManage)
            <x-ui.button icon="plus" x-on:click="$dispatch('open-modal-category-create')">Tambah kategori</x-ui.button>
        @endif
        <x-ui.button variant="secondary" :href="route('workspace.vendors.index', $organization)" icon="archive">Direktori vendor</x-ui.button>
    </x-ui.page-header>

    @if ($categories->isEmpty())
        <x-ui.empty-state icon="filter" title="Belum ada kategori" description="Buat kategori untuk mengelompokkan vendor berdasarkan layanan atau tipe." />
    @else
        <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
            @foreach ($categories as $category)
                <article class="card p-5">
                    <div class="flex items-start justify-between gap-2">
                        <div class="min-w-0">
                            <h3 class="font-semibold text-ink">{{ $category->name }}</h3>
                            @if ($category->description)
                                <p class="mt-1 text-xs text-faint">{{ $category->description }}</p>
                            @endif
                        </div>
                        @if ($canManage)
                            <div class="flex items-center gap-1">
                                <a href="{{ route('workspace.vendor-categories.edit', [$organization, $category]) }}" class="icon-btn h-7 w-7 !rounded-lg" title="Edit">
                                    <x-ui.icon name="edit" class="h-3.5 w-3.5" />
                                </a>
                                <form method="POST" action="{{ route('workspace.vendor-categories.destroy', [$organization, $category]) }}" data-confirm="Hapus kategori {{ $category->name }}?" data-confirm-button="Ya, hapus">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="icon-btn h-7 w-7 !rounded-lg" title="Hapus">
                                        <x-ui.icon name="trash" class="h-3.5 w-3.5" />
                                    </button>
                                </form>
                            </div>
                        @endif
                    </div>
                </article>
            @endforeach
        </div>
    @endif

    @if ($canManage)
        <x-ui.modal name="category-create" title="Tambah kategori" description="Buat kategori baru untuk mengelompokkan vendor.">
            <form method="POST" action="{{ route('workspace.vendor-categories.store', $organization) }}" class="space-y-3">
                @csrf
                <x-ui.input name="name" label="Nama kategori" required placeholder="Contoh: Catering" />
                <x-ui.textarea name="description" label="Deskripsi" rows="2" placeholder="Deskripsi singkat (opsional)" />
                <x-ui.input name="sort_order" type="number" label="Urutan" placeholder="0" min="0" />
                <div class="flex items-center gap-2">
                    <label class="flex cursor-pointer items-center gap-2 text-sm">
                        <input type="checkbox" name="is_active" value="1" class="checkbox" checked>
                        Aktifkan kategori
                    </label>
                </div>
                <div class="flex items-center justify-end gap-2 pt-2">
                    <button type="button" class="btn btn-ghost" x-on:click="$dispatch('close-modal-category-create')">Batal</button>
                    <button type="submit" class="btn btn-primary"><x-ui.icon name="save" class="h-4 w-4" /> Simpan</button>
                </div>
            </form>
        </x-ui.modal>
    @endif
@endsection
