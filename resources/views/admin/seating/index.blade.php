@extends('layouts.admin')

@section('title', 'Seating')
@section('subtitle', 'Penempatan tamu di meja')

@section('content')
    @php
        $eventId = request()->integer('event_id') ?: null;
        $event = $events->firstWhere('id', $eventId) ?? $events->first();
    @endphp

    <x-ui.page-header
        eyebrow="Wedding"
        title="Penempatan Tamu"
        subtitle="Susun tamu di meja-acara"
    >
        <div class="flex items-center gap-2">
            <select id="event_id" name="event_id" class="input appearance-none" x-on="change"="$dispatch('update-event')" aria-label="Filter event">
                <option value="">Semua event</option>
                @foreach ($events as $e)
                    <option value="{{ $e->id }}" @selected($eventId === $e->id)>
                        {{ $e->coupleNames() }} · {{ $e->event_date?->format('d M Y') }}
                    </option>
                @endforeach
            </select>
        </div>
    </x-ui.page-header>

    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <x-ui.stat-card label="Meja" :value="$stats['tables'] ?? 0" icon="grid" tone="accent" hint="Total meja" />
        <x-ui.stat-card label="Kapasitas" :value="$stats['capacity'] ?? 0" icon="users" tone="success" hint="Total tempat duduk" />
        <x-ui.stat-card label="Tersedia" :value="$stats['seated'] ?? 0" icon="check" tone="success" hint="Tamu sudah ditempatkan" />
    </div>

    <div class="card p-0">
        <div class="flex items-center justify-between gap-2 border-b border-line/70 px-5 py-4">
            <div>
                <h2 class="section-title">Meja</h2>
                <p class="mt-0.5 text-xs text-faint">Buat meja baru atau edit meja yang ada.</p>
            </div>
            <div class="flex items-center gap-2">
                <button type="button" class="btn-primary" x-on:click="$dispatch('open-create-table')">
                    <x-ui.icon name="plus" class="h-4 w-4" /> Buat Meja
                </button>
            </div>
        </div>

        <div class="p-4">
            <div x-show="!isCreatingTable" class="space-y-3">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th scope="col">Nama</th>
                            <th scope="col" class="text-center">Kapasitas</th>
                            <th scope="col" class="text-center">Terisi</th>
                            <th scope="col" class="text-center">Sisa</th>
                            <th scope="col" class="text-center">Zone</th>
                            <th scope="col" class="text-right">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($tables as $table)
                            <tr>
                                <td class="font-medium text-ink">{{ $table->name }}</td>
                                <td class="text-center">{{ $table->capacity }}</td>
                                <td class="text-center">{{ $table->occupiedCount() }}</td>
                                <td class="text-center">{{ $table->remainingCapacity() }}</td>
                                <td class="text-sm text-muted">{{ $table->zone ?? '—' }}</td>
                                <td class="text-right">
                                    <button type="button" class="btn-ghost btn-xs" x-on:click="$dispatch('open-edit-table-{{ $table->id }}')" aria-label="Edit meja">
                                        <x-ui.icon name="edit" class="h-3 w-3" />
                                    </button>
                                    <form method="POST" action="{{ route('admin.seating.tables.destroy', $table) }}" class="inline" data-confirm="Yakin menghapus meja ini?">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn-ghost btn-xs text-danger" aria-label="Hapus">
                                            <x-ui.icon name="trash" class="h-3 w-3" />
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>

                <template x-if="tables->isEmpty()">
                    <x-ui.empty-state icon="grid" title="Belum ada meja" description="Buat meja pertama dengan tombol di atas." />
                </template>
            </div>

            <template x-show="isCreatingTable">
                <form method="POST" action="{{ route('admin.seating.tables.store') }}" class="space-y-4">
                    @csrf
                    @method('POST')

                    <div class="grid gap-3">
                        <div>
                            <label for="table_name" class="label">Nama meja <span class="text-danger">*</span></label>
                            <input type="text" id="table_name" name="name" required class="input">
                        </div>

                        <div class="grid gap-3 sm:grid-cols-2">
                            <div>
                                <label for="table_capacity" class="label">Kapasitas <span class="text-danger">*</span></label>
                                <input type="number" id="table_capacity" name="capacity" min="1" max="100" required class="input">
                            </div>
                            <div>
                                <label for="table_zone" class="label">Zone</label>
                                <input type="text" id="table_zone" name="zone" class="input" placeholder="Contoh: A, VVIP, Romansa">
                            </div>
                        </div>

                        <div>
                            <label for="table_notes" class="label">Catatan</label>
                            <textarea id="table_notes" name="notes" rows="2" class="input resize-y" placeholder="Catatan tambahan…"></textarea>
                        </div>

                        <div class="flex items-center justify-end gap-2">
                            <button type="button" class="btn-ghost" x-on:click="$dispatch('close-create-table')">Batal</button>
                            <button type="submit" class="btn-primary">Buat Meja</button>
                        </div>
                    </div>
                </form>
            </template>
        </div>
    </div>

    @if ($event)
        <div class="card p-0">
            <div class="flex items-center justify-between gap-2 border-b border-line/70 px-5 py-4">
                <div>
                    <h2 class="section-title">Tamu Tanpa Meja</h2>
                    <p class="mt-0.5 text-xs text-faint">{{ $unassigned->count() }} tamu belum ditempatkan</p>
                </div>
            </div>

            <div class="p-4">
                @if ($unassigned->isEmpty())
                    <div class="p-4 text-center text-faint">
                        <x-ui.icon name="check" class="h-8 w-8 mx-auto text-success mb-3" />
                        <p>Semua tamu sudah ditempatkan di meja!</p>
                    </div>
                @else
                    <form method="POST" action="{{ route('admin.seating.assign') }}" class="space-y-3">
                        @csrf

                        <div class="grid gap-3">
                            <div>
                                <label for="assign_table" class="label">Pilih meja <span class="text-danger">*</span></label>
                                <select id="assign_table" name="seating_table_id" class="input appearance-none" required>
                                    <option value="">-- Pilih meja --</option>
                                    @foreach ($tables as $table)
                                        <option value="{{ $table->id }}" @selected($unassigned->firstWhere('tableAssignment', fn ($a) => $a->seating_table_id === $table->id))>
                                            {{ $table->name }} ({{ $table->remainingCapacity() }} tempat)
                                        </option>
                                    @endforeach
                                </select>
                            </div>

                            <div>
                                <label for="assign_guest" class="label">Pilih tamu <span class="text-danger">*</span></label>
                                <select id="assign_guest" name="guest_id" class="input appearance-none" required>
                                    <option value="">-- Pilih tamu --</option>
                                    @foreach ($unassigned as $guest)
                                        <option value="{{ $guest->id }}">{{ $guest->name }}</option>
                                    @endforeach
                                </select>
                            </div>

                            <div>
                                <label for="assign_seat" class="label">Nomor kursi</label>
                                <input type="number" id="assign_seat" name="seat_number" min="1" class="input" placeholder="Opsional">
                            </div>

                            <div class="flex items-center justify-end gap-2">
                                <button type="submit" class="btn-primary">Tetapkan Tempat</button>
                                <button type="button" class="btn-ghost" x-on:click="$dispatch('close-assign')">Batal</button>
                            </div>
                        </div>
                    </form>

                    <div class="mt-4">
                        <h3 class="section-kicker">Daftar tamu tanpa meja</h3>
                        <div class="table-wrap">
                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <th scope="col">Tamu</th>
                                        <th scope="col" class="text-center">Event</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($unassigned as $guest)
                                        <tr>
                                            <td>
                                                <div class="flex items-center gap-3">
                                                    <x-ui.avatar :name="$guest->name" size="sm" />
                                                    <div class="min-w-0">
                                                        <p class="truncate font-semibold text-ink">{{ $guest->name }}</p>
                                                        <p class="truncate text-[11px] text-faint">{{ $event->coupleNames() ?? '—' }}</p>
                                                    </div>
                                                </div>
                                            </td>
                                            <td class="text-xs text-muted">Belum ditempatkan</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                @endif
            </div>
        </div>
    @endif
@endsection

@push('scripts')
    <script nonce="{{ request()->attributes->get('csp_nonce') }}">
        Alpine.data('seating', () => ({
            isCreatingTable: false,
            isAssigning: false,

            openCreateTable() {
                this.isCreatingTable = true;
            },

            closeCreateTable() {
                this.isCreatingTable = false;
            },

            openEditTable(tableId) {
                // Edit table via modal PUT
                const table = document.querySelector(`[data-table-id="${tableId}"]`);
                // Show edit form
                this.openCreateTable();
            },

            closeAssign() {
                this.isAssigning = false;
            },

            updateEvent() {
                const eventId = document.getElementById('event_id').value;
                this.$dispatch('update-event', { eventId });
            }
        }));

        // Emit event from parent
        document.addEventListener('update-event', (e) => {
            const { eventId } = e.detail || {};
            window.location.href = '{{ route('admin.seating.index', ['event_id' => '']) }}?event_id=' + eventId;
        });
    </script>
@endpush