@extends('layouts.owner')

@section('title', 'Rundown')

@section('content')
    <x-ui.page-header title="Rundown" subtitle="Jadwal lengkap hari pernikahan Anda." />

    @if ($schedules->isNotEmpty())
        <div class="relative space-y-6 pl-4">
            <span class="absolute top-0 bottom-0 left-0 w-px bg-line/60"></span>

            @foreach ($schedules as $schedule)
                <div class="relative group">
                    {{-- Time --}}
                    <div class="absolute -left-4 top-2 flex h-8 w-8 items-center justify-center rounded-full border border-line bg-surface z-10">
                        <span class="text-[10px] font-semibold text-muted">{{ $schedule->start_time?->translatedFormat('H:i') }}</span>
                    </div>

                    {{-- Line connector --}}
                    <span class="absolute top-8 bottom-2 left-0 w-px bg-line/40"></span>

                    {{-- Card --}}
                    <div class="card p-4">
                        <div class="flex items-center justify-between gap-3 mb-2">
                            <h3 class="font-serif text-lg font-semibold text-ink">{{ $schedule->title }}</h3>
                            <x-ui.badge :variant="$schedule->status->badge()" class="text-[10px]">{{ $schedule->status->label() }}</x-ui.badge>
                        </div>
                        @if ($schedule->description)
                            <p class="text-sm text-muted line-clamp-2">{{ $schedule->description }}</p>
                        @endif
                        <div class="mt-3 flex items-center gap-3">
                            <p class="text-[10px] font-semibold uppercase tracking-[0.12em] text-faint">Mulai</p>
                            <p class="text-sm text-ink">{{ $schedule->start_time?->translatedFormat('d M Y H:i') }}</p>
                            @if ($schedule->end_time)
                                <p class="text-[10px] font-semibold uppercase tracking-[0.12em] text-faint">Selesai</p>
                                <p class="text-sm text-ink">{{ $schedule->end_time?->translatedFormat('d M Y H:i') }}</p>
                            @endif
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    @else
        <x-ui.empty-state icon="calendar" title="Belum ada rundown" description="Jadwal hari pernikahan akan muncul di sini." />
    @endif
@endsection