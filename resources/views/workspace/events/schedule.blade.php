@extends('layouts.owner')

@section('title', 'Jadwal Publik '.$event->name)

@php
    use App\Enums\ScheduleStatus;

    $schedules = collect($schedules ?? []);
    $timezone = $event->timezone ?: 'Asia/Jakarta';
    $toLocal = fn ($datetime) => $datetime?->copy()->setTimezone($timezone);

    $canManage = auth()->user()->can('manageSchedule', $event);
@endphp

@section('content')
    <x-ui.page-header
        title="Jadwal publik"
        eyebrow="Jadwal"
        :subtitle="'Rangkaian acara yang tampil di undangan tamu untuk '.$event->name.'.'"
    >
        <x-ui.badge variant="info">{{ $schedules->count() }} item</x-ui.badge>
        <x-ui.button variant="secondary" :href="route('workspace.events.show', [$organization, $event])" icon="arrow-left">Ringkasan</x-ui.button>
    </x-ui.page-header>

    @include('workspace.events.partials.event-tabs', ['organization' => $organization, 'event' => $event, 'setup' => $setup ?? null, 'active' => 'schedule'])

    <div class="mt-6 flex items-start gap-3 rounded-2xl border border-line/70 bg-blue-soft/50 p-4 text-sm text-ink">
        <x-ui.icon name="info" class="mt-0.5 h-4 w-4 shrink-0 text-accent" />
        <p>
            Halaman ini hanya mengatur informasi yang <strong>terlihat oleh tamu</strong>.
            Catatan internal, penanggung jawab, dan detail teknis tidak ditampilkan di sini — gunakan Run of Show untuk kebutuhan internal.
        </p>
    </div>

    <div class="mt-6 grid gap-5 lg:grid-cols-3">
        {{-- Tambah jadwal --}}
        @if ($canManage)
            <div class="card p-5 lg:col-span-1">
                <div class="flex items-center gap-3">
                    <span class="flex h-10 w-10 items-center justify-center rounded-2xl bg-blue-soft text-ink">
                        <x-ui.icon name="clock" class="h-5 w-5" />
                    </span>
                    <div>
                        <h2 class="section-title">Tambah jadwal</h2>
                        <p class="mt-0.5 text-xs text-faint">Waktu dalam zona {{ $timezone }}.</p>
                    </div>
                </div>

                <form method="POST" action="{{ route('workspace.events.schedule.store', [$organization, $event]) }}" class="mt-5 space-y-4">
                    @csrf
                    <x-ui.input name="title" label="Judul" required placeholder="Contoh: Prosesi akad nikah" />
                    <x-ui.textarea name="description" label="Keterangan" rows="2" placeholder="Deskripsi singkat untuk tamu (opsional)" />
                    <x-ui.input name="start_time" label="Mulai" type="datetime-local" required :value="old('start_time')" />
                    <x-ui.input name="end_time" label="Selesai" type="datetime-local" :value="old('end_time')" />
                    <x-ui.select name="status" label="Status" :options="ScheduleStatus::cases()" :value="ScheduleStatus::Planned->value" />
                    <x-ui.input name="sort_order" label="Urutan" type="number" :value="$schedules->count() + 1" min="0" />

                    <button type="submit" class="btn btn-primary w-full justify-center">
                        <x-ui.icon name="plus" class="h-4 w-4" /> Tambah jadwal
                    </button>
                </form>
            </div>
        @endif

        {{-- Daftar jadwal --}}
        <div class="{{ $canManage ? 'lg:col-span-2' : 'lg:col-span-3' }}">
            @if ($schedules->isEmpty())
                <x-ui.empty-state icon="clock" title="Belum ada jadwal publik" description="Tambahkan rangkaian acara agar tamu mengetahui alur perayaan." />
            @else
                <div class="relative space-y-4 pl-5">
                    <span class="absolute inset-y-0 left-1.5 w-px bg-line/60" aria-hidden="true"></span>

                    @foreach ($schedules as $schedule)
                        @php
                            $start = $toLocal($schedule->start_time);
                            $end = $toLocal($schedule->end_time);
                        @endphp
                        <div class="relative animate-float-in" x-data="{ editing: false }">
                            <span class="absolute -left-[1.15rem] top-5 flex h-3 w-3 items-center justify-center rounded-full border-2 border-surface bg-accent" aria-hidden="true"></span>

                            <div class="card p-4">
                                <div class="flex flex-wrap items-start justify-between gap-3">
                                    <div class="min-w-0">
                                        <div class="flex items-center gap-2">
                                            <h3 class="text-sm font-semibold text-ink">{{ $schedule->title }}</h3>
                                            <x-ui.badge :variant="$schedule->status->badge()" class="text-[10px]">{{ $schedule->status->label() }}</x-ui.badge>
                                        </div>
                                        <p class="mt-1 text-xs text-muted">
                                            <x-ui.icon name="clock" class="mr-1 inline h-3 w-3 text-faint" />
                                            {{ $start?->translatedFormat('d M Y, H:i') ?? 'Waktu belum ditetapkan' }}
                                            @if ($end)
                                                – {{ $end->translatedFormat('H:i') }}
                                            @endif
                                            <span class="text-faint">· {{ $timezone }}</span>
                                        </p>
                                        @if ($schedule->description)
                                            <p class="mt-1 text-xs text-muted">{{ $schedule->description }}</p>
                                        @endif
                                    </div>

                                    @if ($canManage)
                                        <div class="flex items-center gap-1.5">
                                            <button type="button" class="btn btn-ghost btn-sm" @click="editing = ! editing">
                                                <x-ui.icon name="edit" class="h-3.5 w-3.5" />
                                                <span x-text="editing ? 'Tutup' : 'Edit'">Edit</span>
                                            </button>
                                            <form method="POST"
                                                  action="{{ route('workspace.events.schedule.destroy', [$organization, $event, $schedule]) }}"
                                                  data-confirm="Hapus jadwal '{{ $schedule->title }}' dari jadwal publik?"
                                                  data-confirm-button="Ya, hapus">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn btn-danger btn-sm" aria-label="Hapus jadwal">
                                                    <x-ui.icon name="trash" class="h-3.5 w-3.5" />
                                                </button>
                                            </form>
                                        </div>
                                    @endif
                                </div>

                                @if ($canManage)
                                    <form method="POST"
                                          action="{{ route('workspace.events.schedule.update', [$organization, $event, $schedule]) }}"
                                          class="mt-4 grid gap-3 border-t border-line/60 pt-4 sm:grid-cols-2"
                                          x-show="editing"
                                          x-cloak>
                                        @csrf
                                        @method('PUT')
                                        <div class="sm:col-span-2">
                                            <x-ui.input name="title" label="Judul" :value="$schedule->title" required />
                                        </div>
                                        <div class="sm:col-span-2">
                                            <x-ui.textarea name="description" label="Keterangan" :value="$schedule->description" rows="2" />
                                        </div>
                                        <x-ui.input name="start_time" label="Mulai" type="datetime-local" :value="$start?->format('Y-m-d\TH:i')" required />
                                        <x-ui.input name="end_time" label="Selesai" type="datetime-local" :value="$end?->format('Y-m-d\TH:i')" />
                                        <x-ui.select name="status" label="Status" :options="ScheduleStatus::cases()" :value="$schedule->status->value" />
                                        <x-ui.input name="sort_order" label="Urutan" type="number" :value="$schedule->sort_order" min="0" />

                                        <div class="flex items-center justify-end gap-2 sm:col-span-2">
                                            <button type="button" class="btn btn-ghost btn-sm" @click="editing = false">Batal</button>
                                            <button type="submit" class="btn btn-primary btn-sm">
                                                <x-ui.icon name="save" class="h-3.5 w-3.5" /> Simpan perubahan
                                            </button>
                                        </div>
                                    </form>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    </div>
@endsection