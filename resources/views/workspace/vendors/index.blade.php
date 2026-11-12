@extends('layouts.owner')

@section('title', 'Direktori Vendor · '.$organization->name)

@php
    use App\Enums\VendorStatus;
    use App\Enums\VendorType;
@endphp

@section('content')
    <x-ui.page-header
        title="Direktori vendor"
        eyebrow="Workspace"
        :subtitle="'Kelola semua vendor dan penyedia layanan milik '.$organization->name.'.'"
    >
        @if ($canManage)
            <x-ui.button icon="plus" :href="route('workspace.vendors.create', $organization)">Tambah vendor</x-ui.button>
        @endif
        <x-ui.button variant="secondary" :href="route('workspace.vendor-categories.index', $organization)" icon="filter">Kategori</x-ui.button>
    </x-ui.page-header>

    {{-- Filter bar --}}
    <form method="GET" action="{{ route('workspace.vendors.index', $organization) }}" class="mt-5 flex flex-wrap items-end gap-3">
        <div class="flex-1 min-w-0 max-w-sm">
            <x-ui.input
                name="q"
                :value="$filters['q']"
                placeholder="Cari nama, email, atau alamat…"
                label="Cari"
                aria-label="Cari vendor"
            />
        </div>
        <div>
            <x-ui.select name="category_id" label="Kategori">
                <option value="">Semua kategori</option>
                @foreach ($categories as $category)
                    <option value="{{ $category->id }}" @selected((string) $filters['category_id'] === (string) $category->id)>{{ $category->name }}</option>
                @endforeach
            </x-ui.select>
        </div>
        <div>
            <x-ui.select name="status" label="Status">
                <option value="">Semua status</option>
                @foreach (VendorStatus::cases() as $case)
                    <option value="{{ $case->value }}" @selected($filters['status'] === $case->value)>{{ $case->label() }}</option>
                @endforeach
            </x-ui.select>
        </div>
        <div>
            <x-ui.select name="type" label="Jenis">
                <option value="">Semua jenis</option>
                @foreach (VendorType::cases() as $case)
                    <option value="{{ $case->value }}" @selected($filters['type'] === $case->value)>{{ $case->label() }}</option>
                @endforeach
            </x-ui.select>
        </div>
        <button type="submit" class="btn btn-secondary">
            <x-ui.icon name="search" class="h-4 w-4" /> Terapkan
        </button>
        @if (array_filter(array_values($filters)))
            <a href="{{ route('workspace.vendors.index', $organization) }}" class="btn btn-ghost">Reset</a>
        @endif
    </form>

    {{-- Vendor list --}}
    <section class="mt-5">
        @if ($vendors->isEmpty())
            <x-ui.empty-state
                icon="archive"
                title="Belum ada vendor"
                description="Tambah vendor pertama untuk memulai direktori penyedia layanan organisasi."
            >
                @if ($canManage)
                    <x-ui.button size="sm" icon="plus" :href="route('workspace.vendors.create', $organization)">Tambah vendor</x-ui.button>
                @endif
            </x-ui.empty-state>
        @else
            {{-- Mobile: card list --}}
            <div class="space-y-3 sm:hidden">
                @foreach ($vendors as $vendor)
                    <article class="card p-4">
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <a href="{{ route('workspace.vendors.show', [$organization, $vendor]) }}" class="text-sm font-semibold text-ink hover:text-accent hover:underline">{{ $vendor->name }}</a>
                                @if ($vendor->legal_name && $vendor->legal_name !== $vendor->name)
                                    <p class="text-[10px] text-faint">{{ $vendor->legal_name }}</p>
                                @endif
                            </div>
                            <span class="badge {{ $vendor->status->badge() }} shrink-0 text-[10px]">{{ $vendor->status->label() }}</span>
                        </div>
                        <div class="mt-2 flex flex-wrap items-center gap-x-3 gap-y-1 text-[11px] text-muted">
                            @if ($vendor->category)
                                <span>{{ $vendor->category->name }}</span>
                            @endif
                            <span>{{ $vendor->type->label() }}</span>
                            @if ($vendor->email)
                                <a href="mailto:{{ $vendor->email }}" class="hover:text-accent">{{ $vendor->email }}</a>
                            @endif
                        </div>
                        @if ($canManage)
                            <div class="mt-3 flex items-center gap-2 border-t border-line/60 pt-3">
                                <a href="{{ route('workspace.vendors.edit', [$organization, $vendor]) }}" class="btn btn-ghost btn-sm">
                                    <x-ui.icon name="edit" class="h-3.5 w-3.5" /> Edit
                                </a>
                            </div>
                        @endif
                    </article>
                @endforeach
            </div>

            {{-- Desktop: table --}}
            <div class="table-wrap hidden sm:block">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Vendor</th>
                            <th>Kategori</th>
                            <th>Jenis</th>
                            <th>Kontak</th>
                            <th>Status</th>
                            @if ($canManage)
                                <th class="w-24">Aksi</th>
                            @endif
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($vendors as $vendor)
                            <tr>
                                <td>
                                    <div>
                                        <a href="{{ route('workspace.vendors.show', [$organization, $vendor]) }}" class="font-semibold text-ink hover:text-accent hover:underline">{{ $vendor->name }}</a>
                                        @if ($vendor->legal_name && $vendor->legal_name !== $vendor->name)
                                            <p class="text-[10px] text-faint">{{ $vendor->legal_name }}</p>
                                        @endif
                                    </div>
                                </td>
                                <td class="text-muted text-xs">{{ $vendor->category?->name ?? '—' }}</td>
                                <td><span class="badge badge-neutral text-[10px]">{{ $vendor->type->label() }}</span></td>
                                <td class="text-xs text-muted">
                                    @if ($vendor->email)
                                        <a href="mailto:{{ $vendor->email }}" class="hover:text-accent">{{ $vendor->email }}</a>
                                    @endif
                                    @if ($vendor->phone)
                                        <p class="text-[10px] text-faint">{{ $vendor->phone }}</p>
                                    @endif
                                </td>
                                <td><span class="badge {{ $vendor->status->badge() }} text-[10px]">{{ $vendor->status->label() }}</span></td>
                                @if ($canManage)
                                    <td>
                                        <div class="flex items-center gap-1">
                                            <a href="{{ route('workspace.vendors.edit', [$organization, $vendor]) }}" class="icon-btn h-7 w-7 !rounded-lg" title="Edit">
                                                <x-ui.icon name="edit" class="h-3.5 w-3.5" />
                                            </a>
                                            <a href="{{ route('workspace.vendors.show', [$organization, $vendor]) }}" class="icon-btn h-7 w-7 !rounded-lg" title="Lihat">
                                                <x-ui.icon name="eye" class="h-3.5 w-3.5" />
                                            </a>
                                        </div>
                                    </td>
                                @endif
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @include('owner.partials.pagination', ['paginator' => $vendors])
        @endif
    </section>
@endsection
