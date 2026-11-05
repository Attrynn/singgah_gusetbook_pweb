@extends('layouts.admin')

@section('title', 'Owners')
@section('subtitle', 'Kelola akun owner')

@section('content')
    <x-ui.page-header title="Owners" eyebrow="Owner Accounts">
        <x-ui.button variant="secondary" href="{{ route('admin.owners.create') }}" icon="plus">
            Buat owner
        </x-ui.button>
    </x-ui.page-header>

    <div class="mt-6">
        <form method="GET" class="flex items-center gap-4">
            <input type="text" name="search" placeholder="Cari owner..." class="input w-64" value="{{ request('search') }}">
            <button type="submit" class="btn btn-primary">
                <x-ui.icon name="search" class="h-4 w-4" />
                Cari
            </button>
        </form>
    </div>

    <div class="mt-6">
        <div class="card">
            <div class="flex items-center justify-between border-b border-line/70 px-6 py-4">
                <h2 class="section-title">Owner List</h2>
                <div class="text-xs text-muted">{{ $owners->total() }} owners</div>
            </div>
            <div class="divide-y divide-line/70">
                @if ($owners->count())
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th class="text-left">Nama / Email / Phone</th>
                                <th class="text-left">Events</th>
                                <th class="text-left">Status</th>
                                <th class="text-left">Last Login</th>
                                <th class="text-left">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($owners as $owner)
                                <tr class="hover:bg-surface-2/40">
                                    <td class="text-ink">
                                        <div class="flex items-center gap-3">
                                            <x-ui.avatar :name="$owner->name" size="sm" />
                                            <div>
                                                <p class="font-medium text-ink">{{ $owner->name }}</p>
                                                <p class="text-xs text-muted">{{ $owner->email }}</p>
                                                <p class="text-xs text-muted">{{ $owner->phone ?? '-' }}</p>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="text-ink">{{ $owner->events_count }}</td>
                                    <td class="text-ink">
                                        <span class="badge badge-{{ $owner->is_active ? 'success' : 'muted' }}">
                                            {{ $owner->is_active ? 'Active' : 'Inactive' }}
                                        </span>
                                    </td>
                                    <td class="text-ink">{{ $owner->last_login_at ? $owner->last_login_at->diffForHumans() : '-' }}</td>
                                    <td class="text-ink">
                                        <a href="{{ route('admin.owners.edit', $owner) }}" class="text-xs text-muted" title="Edit">
                                            <x-ui.icon name="edit" class="h-4 w-4" />
                                        </a>
                                        <form method="POST" action="{{ route('admin.owners.destroy', $owner) }}" class="inline" data-confirm="Yakin ingin menghapus akun owner ini?" data-confirm-title="Hapus Owner">
                                            <button type="submit" class="text-xs text-[#a35b64]" title="Hapus">
                                                <x-ui.icon name="trash" class="h-4 w-4" />
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="text-center text-muted">Tidak ada data</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                @else
                    <div class="p-8">
                        <x-ui.empty-state icon="user-check" title="Belum ada owner" description="Buat akun owner pertama untuk memulai.">
                            <x-ui.button href="{{ route('admin.owners.create') }}" size="sm" icon="plus">Buat owner</x-ui.button>
                        </x-ui.empty-state>
                    </div>
                @endif
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