@extends('layouts.admin')

@section('title', 'Mini Games')
@section('subtitle', 'Kelola kuis interaktif untuk tamu')

@section('content')
    <x-ui.page-header title="Mini Games" eyebrow="Interactions" subtitle="Kuis singkat yang bisa dimainkan tamu dari undangan digital.">
        <x-ui.button :href="route('admin.interactions.games.create')" variant="primary" icon="plus">
            Buat game
        </x-ui.button>
    </x-ui.page-header>

    <form method="GET" action="{{ route('admin.interactions.games.index') }}" class="card-flat mt-6 p-4">
        <div class="grid gap-3 md:grid-cols-[minmax(0,320px)_auto]">
            <div>
                <label for="event_id" class="label">Event</label>
                <select id="event_id" name="event_id" class="input appearance-none">
                    <option value="">Semua event</option>
                    @foreach ($events as $event)
                        <option value="{{ $event->id }}" @selected(($filters['event_id'] ?? null) == $event->id)>{{ $event->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="flex items-end gap-2">
                <button type="submit" class="btn-primary flex-1">
                    <x-ui.icon name="search" class="h-4 w-4" /> Terapkan
                </button>
                <a href="{{ route('admin.interactions.games.index') }}" class="btn-ghost">Reset</a>
            </div>
        </div>
    </form>

    <div class="card mt-6 p-0">
        <div class="flex flex-wrap items-center justify-between gap-2 border-b border-line/70 px-5 py-4">
            <div>
                <h2 class="section-title">Daftar game</h2>
                <p class="mt-0.5 text-xs text-faint">{{ $games->total() }} game · halaman {{ $games->currentPage() }} dari {{ $games->lastPage() }}</p>
            </div>
        </div>

        @if ($games->isEmpty())
            <div class="p-5">
                <x-ui.empty-state icon="sparkles" title="Belum ada game" description="Buat kuis pertama untuk menghibur tamu.">
                    <x-ui.button :href="route('admin.interactions.games.create')" variant="primary" icon="plus">Buat game</x-ui.button>
                </x-ui.empty-state>
            </div>
        @else
            <div class="table-wrap">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th scope="col" class="text-left">Judul</th>
                            <th scope="col" class="text-left">Event</th>
                            <th scope="col" class="text-left">Pertanyaan</th>
                            <th scope="col" class="text-left">Percobaan</th>
                            <th scope="col" class="text-left">Status</th>
                            <th scope="col" class="text-left">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($games as $game)
                            <tr class="hover:bg-surface-2/40">
                                <td class="text-ink">
                                    <a href="{{ route('admin.interactions.games.edit', $game) }}" class="font-medium text-ink">{{ $game->title }}</a>
                                    @if ($game->description)
                                        <p class="max-w-md truncate text-xs text-muted">{{ $game->description }}</p>
                                    @endif
                                </td>
                                <td class="text-ink">{{ $game->event?->name ?? '—' }}</td>
                                <td class="text-ink">{{ $game->questions_count }}</td>
                                <td class="text-ink">{{ $game->attempts_count }}</td>
                                <td>
                                    <x-ui.badge :variant="$game->is_active ? 'success' : 'muted'">{{ $game->is_active ? 'Aktif' : 'Nonaktif' }}</x-ui.badge>
                                </td>
                                <td>
                                    <div class="flex items-center gap-1.5">
                                        <a href="{{ route('admin.interactions.games.edit', $game) }}" class="icon-btn h-8 w-8" title="Edit">
                                            <x-ui.icon name="edit" class="h-3.5 w-3.5" />
                                        </a>
                                        <form method="POST" action="{{ route('admin.interactions.games.toggle', $game) }}">
                                            @csrf
                                            <button type="submit" class="icon-btn h-8 w-8" title="{{ $game->is_active ? 'Nonaktifkan' : 'Aktifkan' }}" aria-label="{{ $game->is_active ? 'Nonaktifkan' : 'Aktifkan' }}">
                                                <x-ui.icon name="refresh" class="h-3.5 w-3.5" />
                                            </button>
                                        </form>
                                        <form method="POST" action="{{ route('admin.interactions.games.destroy', $game) }}" data-confirm="Hapus game {{ $game->title }} beserta pertanyaannya?" data-confirm-title="Hapus Game">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="icon-btn h-8 w-8 text-danger" title="Hapus" aria-label="Hapus">
                                                <x-ui.icon name="trash" class="h-3.5 w-3.5" />
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="px-4 pb-4">{{ $games->links() }}</div>
        @endif
    </div>
@endsection
