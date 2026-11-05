@extends('layouts.admin')

@section('title', 'Event Gifts')
@section('subtitle', 'Kelola rekening hadiah')

@section('content')
    <x-ui.page-header title="Event Gifts" eyebrow="Gift Accounts">
        <x-ui.button variant="secondary" href="{{ route('admin.events.show', $event) }}" icon="arrow-left">
            Kembali
        </x-ui.button>
        <x-ui.button variant="secondary" href="{{ route('admin.events.gifts.store', $event) }}" icon="plus">
            Tambah Rekening
        </x-ui.button>
    </x-ui.page-header>

    <div class="mt-6">
        <div class="card">
            <div class="flex items-center justify-between border-b border-line/70 px-6 py-4">
                <h2 class="section-title">Gift Accounts</h2>
                <div class="text-xs text-muted">{{ $event->giftAccounts->count() }} rekening</div>
            </div>
            <div class="divide-y divide-line/70">
                @forelse ($gifts as $gift)
                    <div class="p-4 flex items-center justify-between">
                        <div class="flex items-center gap-3">
                            <div class="flex flex-col">
                                <p class="font-medium text-ink">{{ $gift->bank_name }}</p>
                                <p class="text-xs text-muted">{{ $gift->account_number }} - {{ $gift->account_owner }}</p>
                            </div>
                            <div class="text-xs text-muted">{{ $gift->is_active ? 'Active' : 'Inactive' }}</div>
                        </div>
                        <div class="flex items-center gap-2">
                            <x-ui.badge variant="{{ $gift->is_active ? 'success' : 'muted' }}" class="text-[10px]">{{ $gift->is_active ? 'Active' : 'Inactive' }}</x-ui.badge>
                            <button type="button" class="icon-btn" title="Edit">
                                <x-ui.icon name="edit" class="h-4 w-4" />
                            </button>
                            <form method="POST" action="{{ route('admin.gifts.destroy', $gift) }}" class="inline" data-confirm="Yakin ingin menghapus rekening hadiah ini?" data-confirm-title="Hapus Rekening">
                                <button type="submit" class="icon-btn" title="Hapus">
                                    <x-ui.icon name="trash" class="h-4 w-4" />
                                </button>
                            </form>
                        </div>
                    </div>
                @empty
                    <div class="p-8">
                        <x-ui.empty-state icon="gift" title="Belum ada rekening hadiah" description="Tambahkan rekening hadiah untuk event ini.">
                            <x-ui.button href="{{ route('admin.events.gifts.store', $event) }}" size="sm" icon="plus">Tambah Rekening</x-ui.button>
                        </x-ui.empty-state>
                    </div>
                @endforelse
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            document.querySelectorAll('[data-confirm]').forEach(form => {
                form.addEventListener('submit', (e) => {
                    if (form.dataset.confirmed === 'true') return;
                    e.preventDefault();
                    Swal.fire({
                        title: form.dataset.confirmTitle || 'Konfirmasi',
                        text: form.dataset.confirm,
                        icon: 'warning',
                        showCancelButton: true,
                        confirmButtonText: form.dataset.confirmButton || 'Ya, lanjutkan',
                        cancelButtonText: 'Batal',
                        confirmButtonColor: '#8EB4D3',
                        cancelButtonColor: '#94A3B8',
                        reverseButtons: true,
                    }).then(result => {
                        if (result.isConfirmed) {
                            form.dataset.confirmed = 'true';
                            form.submit();
                        }
                    });
                });
            });
        });
    </script>
@endpush