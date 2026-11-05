@extends('layouts.admin')

@section('title', 'Polling')
@section('subtitle', 'Kelola polling wedding')

@section('content')
    @php
        $eventId = $filters['event_id'] ?? null;
    @endphp

    <x-ui.page-header
        eyebrow="Interactions"
        title="Polling Pernikahan"
        subtitle="Kelola polling dan lihat respons tamu"
    >
        <x-ui.button :href="route('admin.interactions.polls.create')" variant="primary" icon="plus">
            Buat Polling
        </x-ui.button>
    </x-ui.page-header>

    <form method="GET" action="{{ route('admin.interactions.polls.index') }}" class="card-flat p-4">
        <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-3">
            <div>
                <label for="event_id" class="label">Event</label>
                <select id="event_id" name="event_id" class="input appearance-none">
                    <option value="">Semua event</option>
                    @foreach ($events as $event)
                        <option value="{{ $event->id }}" @selected($eventId === $event->id)>
                            {{ $event->coupleNames() }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="flex items-end gap-2">
                <button type="submit" class="btn-primary flex-1">
                    <x-ui.icon name="search" class="h-4 w-4" /> Terapkan
                </button>
                <a href="{{ route('admin.interactions.polls.index') }}" class="btn-ghost">Reset</a>
            </div>
        </div>
    </form>

    <div class="card p-0">
        <div class="flex flex-wrap items-center justify-between gap-2 border-b border-line/70 px-5 py-4">
            <div>
                <h2 class="section-title">Daftar Polling</h2>
                <p class="mt-0.5 text-xs text-faint">{{ $polls->total() }} polling · halaman {{ $polls->currentPage() }} dari {{ $polls->lastPage() }}</p>
            </div>
        </div>

        @if ($polls->isEmpty())
            <div class="p-5">
                <x-ui.empty-state
                    icon="chart"
                    title="Belum ada polling"
                    description="Buat polling baru untuk mengumpulkan pendapat tamu."
                >
                    <x-ui.button :href="route('admin.interactions.polls.create')" variant="primary" icon="plus">
                        Buat Polling
                    </x-ui.button>
                </x-ui.empty-state>
            </div>
        @else
            <div class="grid gap-4 p-4 sm:grid-cols-2">
                @foreach ($polls as $poll)
                    <div class="card">
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <p class="truncate text-sm font-semibold text-ink">{{ $poll->question }}</p>
                                <p class="mt-1 text-xs text-faint">{{ $poll->event?->coupleNames() ?? '—' }}</p>
                                <div class="mt-2 flex flex-wrap items-center gap-2 text-xs text-muted">
                                    <span>{{ $poll->options_count ?? 0 }} pilihan</span>
                                    <span class="text-line">·</span>
                                    <span>{{ $poll->responses_count ?? 0 }} respons</span>
                                </div>
                            </div>
                            <span class="{{ $poll->is_active ? 'badge-success' : 'badge-muted' }}">{{ $poll->is_active ? 'Aktif' : 'Ditutup' }}</span>
                        </div>

                        <div class="mt-3 flex flex-wrap items-center gap-2">
                            <a href="{{ route('admin.interactions.polls.edit', $poll) }}" class="btn-soft btn-sm">
                                <x-ui.icon name="edit" class="h-3.5 w-3.5" /> Edit
                            </a>
                            <form method="POST" action="{{ route('admin.interactions.polls.toggle', $poll) }}" class="inline">
                                @csrf
                                <button type="submit" class="btn-ghost btn-sm">
                                    <x-ui.icon name="refresh" class="h-3.5 w-3.5" /> {{ $poll->is_active ? 'Nonaktifkan' : 'Aktifkan' }}
                                </button>
                            </form>
                            <form method="POST" action="{{ route('admin.interactions.polls.destroy', $poll) }}" class="inline" data-confirm="Yakin menghapus polling ini?">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn-danger btn-sm">
                                    <x-ui.icon name="trash" class="h-3.5 w-3.5" /> Hapus
                                </button>
                            </form>
                        </div>
                    </div>
                @endforeach
            </div>

            <div class="px-4 pb-4">
                {{ $polls->links() }}
            </div>
        @endif
    </div>
@endsection