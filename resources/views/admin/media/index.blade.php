@extends('layouts.admin')

@section('title', 'Media Gallery')
@section('subtitle', 'Galeri foto dan video')

@section('content')
    @php
        $eventId = $filters['event_id'] ?? null;
        $status = $filters['status'] ?? null;
        $albumId = $filters['album_id'] ?? null;
    @endphp

    <x-ui.page-header
        eyebrow="Guests"
        title="Media Gallery"
        subtitle="Galeri foto dan video untuk wedding"
    >
        <x-ui.button :href="route('admin.media.store', ['event_id' => $eventId])" variant="soft" icon="upload">
            Upload Foto
        </x-ui.button>
    </x-ui.page-header>

    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <x-ui.stat-card label="Total" :value="$stats['total'] ?? 0" icon="image" tone="accent" hint="Semua media" />
        <x-ui.stat-card label="Pending" :value="$stats['pending'] ?? 0" icon="clock" tone="warning" hint="Belum disetujui" />
        <x-ui.stat-card label="Approved" :value="$stats['approved'] ?? 0" icon="check" tone="success" hint="Sudah disetujui" />
        <x-ui.stat-card label="Featured" :value="$stats['featured'] ?? 0" icon="star" tone="teal" hint="Unggulan" />
    </div>

    <form method="GET" action="{{ route('admin.media.index') }}" class="card-flat p-4">
        <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-4">
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

            <div>
                <label for="status" class="label">Status</label>
                <select id="status" name="status" class="input appearance-none">
                    <option value="">Semua status</option>
                    @foreach ($statuses as $status)
                        <option value="{{ $status->value }}" @selected($status->value === ($filters['status'] ?? null))>{{ $status->label() }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label for="album_id" class="label">Album</label>
                <select id="album_id" name="album_id" class="input appearance-none">
                    <option value="">Semua album</option>
                    @foreach ($albums as $album)
                        <option value="{{ $album->id }}" @selected($albumId === $album->id)>
                            {{ $album->name }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="flex items-end gap-2">
                <button type="submit" class="btn-primary flex-1">
                    <x-ui.icon name="search" class="h-4 w-4" /> Terapkan
                </button>
                <a href="{{ route('admin.media.index', ['event_id' => $eventId]) }}" class="btn-ghost">Reset</a>
            </div>
        </div>
    </form>

    <div class="card p-0">
        <div class="flex items-center justify-between gap-2 border-b border-line/70 px-5 py-4">
            <div>
                <h2 class="section-title">Galeri Foto</h2>
                <p class="mt-0.5 text-xs text-faint">{{ $assets->total() }} foto · halaman {{ $assets->currentPage() }} dari {{ $assets->lastPage() }}</p>
            </div>
        </div>

        <div class="table-wrap m-4">
            <table class="data-table">
                <thead>
                    <tr>
                        <th scope="col" class="text-center">Foto</th>
                        <th scope="col">Nama</th>
                        <th scope="col">Pengunggah</th>
                        <th scope="col">Status</th>
                        <th scope="col" class="text-center">Featured</th>
                        <th scope="col">Waktu</th>
                        <th scope="col" class="text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($assets as $asset)
                        <tr>
                            <td class="text-center">
                                <img src="{{ $asset->thumbnailUrl() }}" alt="{{ $asset->original_name }}" class="h-10 w-10 object-cover rounded" />
                            </td>
                            <td class="max-w-[120px]">
                                <p class="truncate text-sm text-ink" title="{{ $asset->original_name }}">{{ $asset->original_name }}</p>
                            </td>
                            <td class="text-sm text-ink">
                                {{ $asset->guest?->name ?? 'Tamu' }}
                            </td>
                            <td>
                                <span class="{{ $asset->status->badge() }}">{{ $asset->status->label() }}</span>
                            </td>
                            <td class="text-center">
                                @if ($asset->is_featured)
                                    <span class="badge-success">Yes</span>
                                @else
                                    <button
                                        type="button"
                                        class="btn-ghost btn-xs"
                                        x-on:click="$dispatch('toggle-featured-{{ $asset->id }}')"
                                        aria-label="Tandai unggulan"
                                    >
                                        <x-ui.icon name="star" class="h-3 w-3" />
                                    </button>
                                @endif
                            </td>
                            <td class="text-sm text-muted">
                                {{ $asset->created_at?->format('d M Y') }}
                            </td>
                            <td class="text-right">
                                <form method="POST" action="{{ route('admin.media.moderate', $asset) }}" class="inline">
                                    @csrf
                                    @method('POST')
                                    <button type="submit" class="btn-ghost btn-xs" data-action="approve" title="Setujui" aria-label="Setujui">
                                        <x-ui.icon name="check" class="h-3 w-3" />
                                    </button>
                                </form>
                                <form method="POST" action="{{ route('admin.media.moderate', $asset) }}" class="inline">
                                    @csrf
                                    @method('POST')
                                    <button type="submit" class="btn-ghost btn-xs" data-action="reject" title="Tolak" aria-label="Tolak">
                                        <x-ui.icon name="x" class="h-3 w-3" />
                                    </button>
                                </form>
                                <form method="POST" action="{{ route('admin.media.feature', $asset) }}" class="inline">
                                    <button type="submit" class="btn-ghost btn-xs" title="Tandai unggulan" aria-label="Tandai unggulan">
                                        <x-ui.icon name="star" class="h-3 w-3" />
                                    </button>
                                </form>
                                <form method="POST" action="{{ route('admin.media.destroy', $asset) }}" class="inline" data-confirm="Yakin menghapus foto ini?">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn-ghost btn-xs text-danger" title="Hapus" aria-label="Hapus">
                                        <x-ui.icon name="trash" class="h-3 w-3" />
                                    </button>
                                </form>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="px-4 pb-4">
            {{ $assets->links() }}
        </div>
    </div>
@endsection