@extends('layouts.admin')

@section('title', 'Audit Logs')
@section('subtitle', 'Jejak aktivitas sistem')

@section('content')
    <x-ui.page-header title="Audit Logs" eyebrow="Security" subtitle="Catatan aktivitas administratif dan perubahan data penting." />

    <form method="GET" action="{{ route('admin.audit-logs.index') }}" class="card-flat mt-6 p-4">
        <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-5">
            <div>
                <label for="action" class="label">Aksi</label>
                <select id="action" name="action" class="input appearance-none">
                    <option value="">Semua aksi</option>
                    @foreach ($actions as $action)
                        <option value="{{ $action }}" @selected(($filters['action'] ?? null) === $action)>{{ $action }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="user_id" class="label">Pengguna</label>
                <select id="user_id" name="user_id" class="input appearance-none">
                    <option value="">Semua pengguna</option>
                    @foreach ($users as $user)
                        <option value="{{ $user->id }}" @selected(($filters['user_id'] ?? null) == $user->id)>{{ $user->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="event_id" class="label">Event</label>
                <select id="event_id" name="event_id" class="input appearance-none">
                    <option value="">Semua event</option>
                    @foreach ($events as $event)
                        <option value="{{ $event->id }}" @selected(($filters['event_id'] ?? null) == $event->id)>{{ $event->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="date" class="label">Tanggal</label>
                <input id="date" name="date" type="text" class="input" data-datepicker value="{{ $filters['date'] ?? '' }}" placeholder="YYYY-MM-DD">
            </div>
            <div class="flex items-end gap-2">
                <button type="submit" class="btn-primary flex-1">
                    <x-ui.icon name="search" class="h-4 w-4" /> Terapkan
                </button>
                <a href="{{ route('admin.audit-logs.index') }}" class="btn-ghost">Reset</a>
            </div>
        </div>
    </form>

    <div class="card mt-6 p-0">
        <div class="flex flex-wrap items-center justify-between gap-2 border-b border-line/70 px-5 py-4">
            <div>
                <h2 class="section-title">Riwayat aktivitas</h2>
                <p class="mt-0.5 text-xs text-faint">{{ number_format($logs->total()) }} catatan · halaman {{ $logs->currentPage() }} dari {{ $logs->lastPage() }}</p>
            </div>
            <x-ui.badge variant="neutral">{{ $logs->perPage() }} per halaman</x-ui.badge>
        </div>

        @if ($logs->isEmpty())
            <div class="p-5">
                <x-ui.empty-state icon="shield" title="Tidak ada catatan" description="Belum ada aktivitas yang cocok dengan filter yang dipilih." />
            </div>
        @else
            <div class="table-wrap">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th scope="col" class="text-left">Waktu</th>
                            <th scope="col" class="text-left">Aksi</th>
                            <th scope="col" class="text-left">Pengguna</th>
                            <th scope="col" class="text-left">Event</th>
                            <th scope="col" class="text-left">Deskripsi</th>
                            <th scope="col" class="text-left">IP</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($logs as $log)
                            <tr class="hover:bg-surface-2/40">
                                <td class="whitespace-nowrap text-ink">
                                    {{ $log->created_at?->translatedFormat('d M Y') }}
                                    <p class="text-[11px] text-faint">{{ $log->created_at?->format('H:i:s') }}</p>
                                </td>
                                <td>
                                    <x-ui.badge :variant="str_contains($log->action, 'delete') || str_contains($log->action, 'destroy') ? 'danger' : (str_contains($log->action, 'create') || str_contains($log->action, 'store') ? 'success' : 'info')">
                                        {{ $log->action }}
                                    </x-ui.badge>
                                </td>
                                <td class="text-ink">
                                    {{ $log->user?->name ?? 'Sistem' }}
                                    @if ($log->user?->email)
                                        <p class="text-[11px] text-faint">{{ $log->user->email }}</p>
                                    @endif
                                </td>
                                <td class="text-ink">{{ $log->event?->name ?? '—' }}</td>
                                <td class="max-w-sm text-ink">
                                    <p class="text-sm">{{ $log->description ?: '—' }}</p>
                                    @if ($log->target_type)
                                        <p class="text-[11px] text-faint">{{ class_basename($log->target_type) }} #{{ $log->target_id }}</p>
                                    @endif
                                </td>
                                <td class="whitespace-nowrap text-xs text-muted">{{ $log->ip_address ?: '—' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="px-4 pb-4">{{ $logs->links() }}</div>
        @endif
    </div>
@endsection
