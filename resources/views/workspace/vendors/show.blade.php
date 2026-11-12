@extends('layouts.owner')

@section('title', $vendor->name.' · Vendor')

@php
    use App\Enums\VendorStatus;
    use App\Enums\VendorContactPurpose;
@endphp

@section('content')
    <x-ui.page-header
        :title="$vendor->name"
        eyebrow="Vendor"
        :subtitle="$vendor->description ?? $vendor->type->label().($vendor->category ? ' · '.$vendor->category->name : '')"
    >
        <span class="badge {{ $vendor->status->badge() }}">{{ $vendor->status->label() }}</span>
        @if ($canManage)
            <x-ui.button :href="route('workspace.vendors.edit', [$organization, $vendor])" icon="edit" variant="secondary">Edit</x-ui.button>
        @endif
        <x-ui.button :href="route('workspace.vendors.index', $organization)" icon="arrow-left" variant="ghost">Kembali</x-ui.button>
    </x-ui.page-header>

    <div class="mt-5 grid gap-5 xl:grid-cols-3">
        {{-- Left column: identity + contacts + services --}}
        <div class="space-y-5 xl:col-span-2">

            {{-- Identity card --}}
            <div class="card p-5">
                <h2 class="section-title">Informasi vendor</h2>
                <dl class="mt-4 grid gap-3 sm:grid-cols-2">
                    @if ($vendor->legal_name)
                        <div>
                            <dt class="text-[10px] font-semibold uppercase tracking-[0.12em] text-faint">Nama Legal</dt>
                            <dd class="mt-0.5 text-sm text-ink">{{ $vendor->legal_name }}</dd>
                        </div>
                    @endif
                    <div>
                        <dt class="text-[10px] font-semibold uppercase tracking-[0.12em] text-faint">Jenis</dt>
                        <dd class="mt-0.5 text-sm text-ink">{{ $vendor->type->label() }}</dd>
                    </div>
                    <div>
                        <dt class="text-[10px] font-semibold uppercase tracking-[0.12em] text-faint">Kategori</dt>
                        <dd class="mt-0.5 text-sm text-ink">{{ $vendor->category?->name ?? '—' }}</dd>
                    </div>
                    @if ($vendor->email)
                        <div>
                            <dt class="text-[10px] font-semibold uppercase tracking-[0.12em] text-faint">Email</dt>
                            <dd class="mt-0.5 text-sm"><a href="mailto:{{ $vendor->email }}" class="text-accent hover:underline">{{ $vendor->email }}</a></dd>
                        </div>
                    @endif
                    @if ($vendor->phone)
                        <div>
                            <dt class="text-[10px] font-semibold uppercase tracking-[0.12em] text-faint">Telepon</dt>
                            <dd class="mt-0.5 text-sm text-ink">{{ $vendor->phone }}</dd>
                        </div>
                    @endif
                    @if ($vendor->website)
                        <div>
                            <dt class="text-[10px] font-semibold uppercase tracking-[0.12em] text-faint">Website</dt>
                            <dd class="mt-0.5 text-sm"><a href="{{ $vendor->website }}" target="_blank" rel="noopener" class="text-accent hover:underline">{{ $vendor->website }}</a></dd>
                        </div>
                    @endif
                    @if ($vendor->address)
                        <div class="sm:col-span-2">
                            <dt class="text-[10px] font-semibold uppercase tracking-[0.12em] text-faint">Alamat</dt>
                            <dd class="mt-0.5 whitespace-pre-line text-sm text-ink">{{ $vendor->address }}</dd>
                        </div>
                    @endif
                </dl>

                @if ($canManage && $vendor->internal_notes)
                    <div class="mt-4 rounded-xl border border-line/60 bg-surface-2/40 p-4">
                        <p class="text-[10px] font-semibold uppercase tracking-[0.12em] text-faint">Catatan internal</p>
                        <p class="mt-1 whitespace-pre-line text-xs leading-relaxed text-muted">{{ $vendor->internal_notes }}</p>
                    </div>
                @endif
            </div>

            {{-- Contacts --}}
            <div class="card overflow-hidden">
                <div class="flex flex-wrap items-center justify-between gap-2 border-b border-line/70 px-5 py-4">
                    <div>
                        <h2 class="section-title">Kontak vendor</h2>
                        <p class="mt-0.5 text-xs text-faint">{{ $vendor->contacts->count() }} kontak terdaftar</p>
                    </div>
                    @if ($canManageContacts)
                        <x-ui.button size="sm" icon="plus" x-on:click="$dispatch('open-modal-contact-add')">Tambah kontak</x-ui.button>
                    @endif
                </div>

                @if ($vendor->contacts->isEmpty())
                    <div class="p-5">
                        <x-ui.empty-state icon="user" title="Belum ada kontak" description="Tambah kontak untuk memudahkan koordinasi operasional." />
                    </div>
                @else
                    <ul class="divide-y divide-line/60">
                        @foreach ($vendor->contacts as $contact)
                            <li class="px-5 py-4">
                                <div class="flex flex-wrap items-start justify-between gap-2">
                                    <div class="min-w-0">
                                        <div class="flex items-center gap-2">
                                            <p class="text-sm font-semibold text-ink">{{ $contact->name }}</p>
                                            @if ($contact->is_primary)
                                                <span class="badge badge-success text-[9px]">Utama</span>
                                            @endif
                                            @if (!$contact->is_active)
                                                <span class="badge badge-muted text-[9px]">Nonaktif</span>
                                            @endif
                                        </div>
                                        @if ($contact->position)
                                            <p class="text-[11px] text-faint">{{ $contact->position }}</p>
                                        @endif
                                        <div class="mt-1 flex flex-wrap items-center gap-x-3 gap-y-0.5 text-[11px] text-muted">
                                            @if ($contact->email)
                                                <a href="mailto:{{ $contact->email }}" class="hover:text-accent">{{ $contact->email }}</a>
                                            @endif
                                            @if ($contact->phone)
                                                <span>{{ $contact->phone }}</span>
                                            @endif
                                        </div>
                                    </div>
                                    @if ($contact->purpose)
                                        <span class="badge {{ $contact->purpose->badge() }} shrink-0 text-[9px]">{{ $contact->purpose->label() }}</span>
                                    @endif
                                </div>
                                @if ($canManageContacts)
                                    <div class="mt-2 flex items-center gap-2">
                                        <button type="button"
                                            class="btn btn-ghost btn-sm"
                                            x-on:click="$dispatch('open-modal-contact-edit-{{ $contact->id }}')"
                                        >
                                            <x-ui.icon name="edit" class="h-3.5 w-3.5" /> Edit
                                        </button>
                                        <form method="POST" action="{{ route('workspace.vendors.contacts.destroy', [$organization, $vendor, $contact]) }}"
                                            data-confirm="Hapus kontak {{ $contact->name }}?"
                                            data-confirm-button="Ya, hapus"
                                        >
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-danger btn-sm">
                                                <x-ui.icon name="trash" class="h-3.5 w-3.5" /> Hapus
                                            </button>
                                        </form>
                                    </div>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>

            {{-- Services --}}
            @if ($canManageCatalog || $vendor->services->isNotEmpty())
                <div class="card overflow-hidden">
                    <div class="flex flex-wrap items-center justify-between gap-2 border-b border-line/70 px-5 py-4">
                        <div>
                            <h2 class="section-title">Layanan & paket</h2>
                            <p class="mt-0.5 text-xs text-faint">{{ $vendor->services->count() }} layanan</p>
                        </div>
                        @if ($canManageCatalog)
                            <x-ui.button size="sm" icon="plus" x-on:click="$dispatch('open-modal-service-add')">Tambah layanan</x-ui.button>
                        @endif
                    </div>

                    @if ($vendor->services->isEmpty())
                        <div class="p-5">
                            <x-ui.empty-state icon="file-text" title="Belum ada layanan" description="Tambah layanan dan paket untuk katalog vendor ini." />
                        </div>
                    @else
                        <div class="divide-y divide-line/60">
                            @foreach ($vendor->services as $service)
                                <details class="group">
                                    <summary class="flex cursor-pointer items-center gap-3 px-5 py-4 hover:bg-surface-2/40">
                                        <x-ui.icon name="chevron-right" class="h-3.5 w-3.5 shrink-0 text-faint transition group-open:rotate-90" />
                                        <div class="min-w-0 flex-1">
                                            <p class="text-sm font-semibold text-ink">{{ $service->name }}</p>
                                            <p class="text-[10px] text-faint">{{ $service->packages->count() }} paket</p>
                                        </div>
                                        @if (!$service->is_active)
                                            <span class="badge badge-muted text-[9px]">Nonaktif</span>
                                        @endif
                                    </summary>
                                    <div class="bg-surface-2/20 px-5 pb-4 pt-2">
                                        @if ($service->description)
                                            <p class="text-xs leading-relaxed text-muted">{{ $service->description }}</p>
                                        @endif
                                        @if ($service->packages->isNotEmpty())
                                            <ul class="mt-3 space-y-2">
                                                @foreach ($service->packages as $package)
                                                    <li class="rounded-xl border border-line/60 bg-surface px-4 py-3">
                                                        <div class="flex items-start justify-between gap-2">
                                                            <p class="text-xs font-semibold text-ink">{{ $package->name }}</p>
                                                            @if ($package->price_amount !== null)
                                                                <p class="shrink-0 font-serif text-sm font-semibold text-accent">
                                                                    @php $curr = strtoupper($package->currency ?: 'IDR'); @endphp
                                                                    @if ($curr === 'IDR')
                                                                        Rp{{ number_format($package->price_amount, 0, ',', '.') }}
                                                                    @else
                                                                        {{ $curr }} {{ number_format($package->price_amount, 2, ',', '.') }}
                                                                    @endif
                                                                </p>
                                                            @endif
                                                        </div>
                                                        @if ($package->description)
                                                            <p class="mt-1 text-[11px] text-muted">{{ $package->description }}</p>
                                                        @endif
                                                    </li>
                                                @endforeach
                                            </ul>
                                        @else
                                            <p class="mt-2 text-[11px] text-faint">Belum ada paket.</p>
                                        @endif
                                    </div>
                                </details>
                            @endforeach
                        </div>
                    @endif
                </div>
            @endif
        </div>

        {{-- Right column: status transitions + recent engagements --}}
        <div class="space-y-5">
            {{-- Status actions --}}
            @if ($canManage)
                <div class="card p-5">
                    <h2 class="section-title">Status vendor</h2>
                    <div class="mt-3 flex items-center gap-2">
                        <span class="badge {{ $vendor->status->badge() }}">{{ $vendor->status->label() }}</span>
                    </div>
                    @php $transitions = $vendor->status->allowedTransitions(); @endphp
                    @if ($transitions)
                        <div class="mt-3 space-y-2">
                            @foreach ($transitions as $target)
                                <form method="POST" action="{{ route('workspace.vendors.status', [$organization, $vendor]) }}">
                                    @csrf
                                    <input type="hidden" name="status" value="{{ $target->value }}">
                                    <button type="submit" class="btn btn-secondary btn-sm w-full text-left">
                                        Ubah ke: {{ $target->label() }}
                                    </button>
                                </form>
                            @endforeach
                        </div>
                    @endif

                    @if ($vendor->status !== \App\Enums\VendorStatus::Archived)
                        <form method="POST" action="{{ route('workspace.vendors.archive', [$organization, $vendor]) }}"
                            class="mt-3"
                            data-confirm="Arsipkan vendor {{ $vendor->name }}? Vendor tidak akan muncul di pencarian aktif."
                            data-confirm-button="Ya, arsipkan"
                        >
                            @csrf
                            <button type="submit" class="btn btn-danger btn-sm w-full">
                                <x-ui.icon name="archive" class="h-3.5 w-3.5" /> Arsipkan vendor
                            </button>
                        </form>
                    @else
                        <form method="POST" action="{{ route('workspace.vendors.restore', [$organization, $vendor]) }}" class="mt-3">
                            @csrf
                            <button type="submit" class="btn btn-secondary btn-sm w-full">
                                <x-ui.icon name="refresh" class="h-3.5 w-3.5" /> Pulihkan dari arsip
                            </button>
                        </form>
                    @endif
                </div>
            @endif

            {{-- Recent engagements --}}
            <div class="card overflow-hidden">
                <div class="border-b border-line/70 px-5 py-4">
                    <h2 class="section-title">Riwayat engagement</h2>
                    <p class="mt-0.5 text-xs text-faint">{{ $vendor->engagements->count() }} engagement</p>
                </div>
                @if ($vendor->engagements->isEmpty())
                    <div class="p-5">
                        <x-ui.empty-state icon="calendar-check" title="Belum ada engagement" description="Vendor ini belum pernah dilibatkan dalam acara." />
                    </div>
                @else
                    <ul class="divide-y divide-line/60">
                        @foreach ($vendor->engagements as $engagement)
                            <li class="px-5 py-3.5">
                                <div class="flex items-start justify-between gap-2">
                                    <div class="min-w-0">
                                        <p class="truncate text-xs font-semibold text-ink">{{ $engagement->title }}</p>
                                        <p class="text-[10px] text-faint">{{ $engagement->event?->name ?? '—' }}</p>
                                        <p class="text-[10px] text-faint">{{ $engagement->created_at?->translatedFormat('d M Y') }}</p>
                                    </div>
                                    <span class="badge {{ $engagement->status->badge() }} shrink-0 text-[9px]">{{ $engagement->status->label() }}</span>
                                </div>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>
        </div>
    </div>

    {{-- Modals --}}
    @if ($canManageContacts)
        <x-ui.modal name="contact-add" title="Tambah kontak vendor" description="Tambah kontak person untuk vendor ini.">
            <form method="POST" action="{{ route('workspace.vendors.contacts.store', [$organization, $vendor]) }}" class="space-y-3">
                @csrf
                <x-ui.input name="name" label="Nama" required placeholder="Nama kontak" />
                <x-ui.input name="position" label="Jabatan" placeholder="Contoh: Sales Manager" />
                <div class="grid gap-3 sm:grid-cols-2">
                    <x-ui.input name="email" type="email" label="Email" placeholder="email@domain.com" />
                    <x-ui.input name="phone" label="Telepon" placeholder="+62 812…" />
                </div>
                <x-ui.select name="purpose" label="Tujuan kontak">
                    <option value="">Umum</option>
                    @foreach (\App\Enums\VendorContactPurpose::cases() as $purpose)
                        <option value="{{ $purpose->value }}">{{ $purpose->label() }}</option>
                    @endforeach
                </x-ui.select>
                <x-ui.textarea name="notes" label="Catatan" rows="2" placeholder="Catatan (opsional)" />
                <div class="flex items-center gap-4">
                    <label class="flex cursor-pointer items-center gap-2 text-sm">
                        <input type="checkbox" name="is_primary" value="1" class="checkbox">
                        Kontak utama
                    </label>
                </div>
                <div class="flex items-center justify-end gap-2 pt-2">
                    <button type="button" class="btn btn-ghost" x-on:click="$dispatch('close-modal-contact-add')">Batal</button>
                    <button type="submit" class="btn btn-primary"><x-ui.icon name="save" class="h-4 w-4" /> Simpan</button>
                </div>
            </form>
        </x-ui.modal>
    @endif

    @if ($canManageCatalog)
        <x-ui.modal name="service-add" title="Tambah layanan" description="Tambah layanan untuk vendor ini.">
            <form method="POST" action="{{ route('workspace.vendors.services.store', [$organization, $vendor]) }}" class="space-y-3">
                @csrf
                <x-ui.input name="name" label="Nama layanan" required placeholder="Contoh: Fotografer Pernikahan" />
                <x-ui.select name="vendor_category_id" label="Kategori layanan" :options="collect([])->pluck('name','id')" placeholder="Pilih kategori (opsional)" />
                <x-ui.textarea name="description" label="Deskripsi" rows="2" />
                <x-ui.textarea name="scope" label="Lingkup layanan" rows="2" />
                <x-ui.input name="capacity" type="number" label="Kapasitas" placeholder="Misal: 1" min="1" />
                <div class="flex items-center justify-end gap-2 pt-2">
                    <button type="button" class="btn btn-ghost" x-on:click="$dispatch('close-modal-service-add')">Batal</button>
                    <button type="submit" class="btn btn-primary"><x-ui.icon name="save" class="h-4 w-4" /> Simpan layanan</button>
                </div>
            </form>
        </x-ui.modal>
    @endif
@endsection
