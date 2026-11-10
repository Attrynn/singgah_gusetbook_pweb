@extends('layouts.owner')

@section('title', 'Penutupan operasional '.$event->name)

@php
    use App\Enums\OperationsStatus;
    use App\Enums\ReadinessStatus;

    $operationStatus = $operation?->status ?? OperationsStatus::Draft;
    $summary = $review['summary'];
    $blockers = collect($review['blockers']);
    $warnings = collect($review['warnings']);
    $canClose = $review['can_close'];
    $isCompleted = $operationStatus === OperationsStatus::Completed;
    $snapshot = $operation?->closure_snapshot;
    $snapshotSummary = is_array($snapshot) ? ($snapshot['summary'] ?? []) : [];

    $canCloseOperations = auth()->user()->can('closeOperations', $event);
    $readinessStatus = $readiness['status'];

    $timezone = $event->timezone ?: 'Asia/Jakarta';
    $toLocal = fn ($datetime) => $datetime?->copy()->setTimezone($timezone);
@endphp

@section('content')
    <x-ui.page-header
        title="Penutupan operasional"
        eyebrow="Operasi"
        :subtitle="'Tinjau sisa pekerjaan sebelum menutup operasional '.$event->name.'.'"
    >
        <x-ui.badge :variant="$operationStatus->badge()">{{ $operationStatus->label() }}</x-ui.badge>
        <x-ui.button variant="secondary" :href="route('workspace.events.operations.index', [$organization, $event])" icon="arrow-left">Ruang kendali</x-ui.button>
    </x-ui.page-header>

    @include('workspace.events.partials.event-tabs', ['organization' => $organization, 'event' => $event, 'setup' => $setup ?? null, 'active' => 'operations-closure'])

    @if ($isCompleted)
        <div class="mt-6 flex items-start gap-3 rounded-2xl border border-success/30 bg-success/10 p-4 text-sm text-success">
            <x-ui.icon name="check" class="mt-0.5 h-4 w-4 shrink-0" />
            <div>
                <p class="font-semibold">Operasional sudah ditutup.</p>
                <p class="mt-0.5 text-xs leading-relaxed">
                    Ditutup pada {{ $toLocal($operation?->completed_at)?->translatedFormat('l, d M Y, H:i') ?? '—' }}.
                    @if ($operation?->closure_notes)
                        Catatan penutupan: {{ $operation->closure_notes }}
                    @endif
                </p>
            </div>
        </div>
    @elseif ($operationStatus === OperationsStatus::Cancelled)
        <div class="mt-6 flex items-start gap-3 rounded-2xl border border-danger/30 bg-danger/10 p-4 text-sm text-danger">
            <x-ui.icon name="alert" class="mt-0.5 h-4 w-4 shrink-0" />
            <p>Operasional acara ini dibatalkan dan tidak dapat ditutup. Buka kembali status berjalan terlebih dahulu bila acara tetap dilaksanakan.</p>
        </div>
    @endif

    <div class="mt-6 grid gap-5 xl:grid-cols-3">
        <div class="space-y-5 xl:col-span-2">
            {{-- Ringkasan sisa pekerjaan --}}
            <div class="card p-5">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <div>
                        <h2 class="section-title">Sisa pekerjaan</h2>
                        <p class="mt-0.5 text-xs text-faint">Dihitung ulang setiap halaman ini dibuka.</p>
                    </div>
                    <x-ui.badge :variant="$canClose ? 'success' : 'danger'">
                        {{ $canClose ? 'Siap ditutup' : 'Masih terhambat' }}
                    </x-ui.badge>
                </div>

                <div class="mt-4 grid grid-cols-2 gap-3 md:grid-cols-3">
                    @foreach ([
                        ['label' => 'Tugas terbuka', 'value' => $summary['open_tasks'], 'tone' => 'text-ink'],
                        ['label' => 'Tugas kritis', 'value' => $summary['critical_open_tasks'], 'tone' => 'text-danger'],
                        ['label' => 'Insiden terbuka', 'value' => $summary['open_incidents'], 'tone' => 'text-warning'],
                        ['label' => 'Insiden serius', 'value' => $summary['serious_open_incidents'], 'tone' => 'text-danger'],
                        ['label' => 'Item run of show', 'value' => $summary['incomplete_run_sheet_items'], 'tone' => 'text-ink'],
                        ['label' => 'Stasiun belum selesai', 'value' => $summary['incomplete_stations'], 'tone' => 'text-ink'],
                    ] as $row)
                        <div class="rounded-xl border border-line/70 bg-surface-2/40 px-3 py-3">
                            <p class="text-[10px] font-semibold uppercase tracking-[0.1em] text-faint">{{ $row['label'] }}</p>
                            <p class="mt-1 text-xl font-semibold tabular-nums {{ $row['tone'] }}">{{ $row['value'] }}</p>
                        </div>
                    @endforeach
                </div>
            </div>

            {{-- Penghambat --}}
            <div class="card overflow-hidden">
                <div class="border-b border-line/70 px-5 py-4">
                    <h2 class="section-title">Penghambat penutupan</h2>
                    <p class="mt-0.5 text-xs text-faint">Penghambat wajib diselesaikan kecuali penutupan dilakukan dengan override.</p>
                </div>

                @if ($blockers->isEmpty())
                    <div class="flex items-center gap-3 px-5 py-4 text-sm text-success">
                        <x-ui.icon name="check" class="h-4 w-4 shrink-0" />
                        Tidak ada penghambat. Operasional dapat ditutup dengan aman.
                    </div>
                @else
                    <ul class="divide-y divide-line/60">
                        @foreach ($blockers as $blocker)
                            <li class="flex items-start gap-3 px-5 py-3.5">
                                <span class="mt-0.5 flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-danger/15 text-danger">
                                    <x-ui.icon name="alert" class="h-3.5 w-3.5" />
                                </span>
                                <div class="min-w-0">
                                    <p class="text-xs font-semibold text-ink">{{ $blocker['message'] }}</p>
                                    <p class="mt-0.5 text-[11px] text-faint">Selesaikan atau tutup item terkait sebelum menutup operasional.</p>
                                </div>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>

            {{-- Peringatan --}}
            <div class="card overflow-hidden">
                <div class="border-b border-line/70 px-5 py-4">
                    <h2 class="section-title">Peringatan</h2>
                    <p class="mt-0.5 text-xs text-faint">Peringatan tidak memblokir penutupan, tetapi sebaiknya ditinjau.</p>
                </div>

                @if ($warnings->isEmpty())
                    <div class="px-5 py-4 text-sm text-muted">Tidak ada peringatan tersisa.</div>
                @else
                    <ul class="divide-y divide-line/60">
                        @foreach ($warnings as $warning)
                            <li class="flex items-start gap-3 px-5 py-3.5">
                                <span class="mt-0.5 flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-warning/15 text-warning">
                                    <x-ui.icon name="info" class="h-3.5 w-3.5" />
                                </span>
                                <p class="text-xs font-semibold text-ink">{{ $warning['message'] }}</p>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>

            {{-- Riwayat penutupan --}}
            @if ($isCompleted && is_array($snapshot))
                <div class="card p-5">
                    <h2 class="section-title">Ringkasan saat ditutup</h2>
                    <p class="mt-0.5 text-xs text-faint">Rekaman kondisi operasional pada saat penutupan.</p>

                    <div class="mt-4 grid grid-cols-2 gap-3 md:grid-cols-3">
                        @foreach ([
                            ['label' => 'Tugas terbuka', 'value' => $snapshotSummary['open_tasks'] ?? 0],
                            ['label' => 'Insiden terbuka', 'value' => $snapshotSummary['open_incidents'] ?? 0],
                            ['label' => 'Item run of show', 'value' => $snapshotSummary['incomplete_run_sheet_items'] ?? 0],
                        ] as $row)
                            <div class="rounded-xl border border-line/70 bg-surface-2/40 px-3 py-2.5">
                                <p class="text-[10px] font-semibold uppercase tracking-[0.1em] text-faint">{{ $row['label'] }}</p>
                                <p class="mt-1 text-lg font-semibold tabular-nums text-ink">{{ $row['value'] }}</p>
                            </div>
                        @endforeach
                    </div>

                    @if (! empty($snapshot['stations']))
                        <div class="mt-4 flex flex-wrap gap-1.5">
                            @foreach ($snapshot['stations'] as $station)
                                <span class="inline-flex items-center gap-1.5 rounded-full border border-line/70 bg-surface-2/40 px-2.5 py-1 text-[10px] text-muted">
                                    <x-ui.icon name="pin" class="h-3 w-3 text-faint" />
                                    {{ $station['name'] }}
                                    <span class="text-faint">· {{ str_replace('_', ' ', $station['status']) }}</span>
                                </span>
                            @endforeach
                        </div>
                    @endif
                </div>
            @endif
        </div>

        <div class="space-y-5">
            {{-- Form penutupan --}}
            @if (! $isCompleted && $operationStatus !== OperationsStatus::Cancelled && $canCloseOperations)
                <div class="card p-5">
                    <div class="flex items-center gap-3">
                        <span class="flex h-10 w-10 items-center justify-center rounded-2xl bg-accent/15 text-accent">
                            <x-ui.icon name="check" class="h-5 w-5" />
                        </span>
                        <div>
                            <h2 class="section-title">Tutup operasional</h2>
                            <p class="mt-0.5 text-xs text-faint">Status akan menjadi selesai.</p>
                        </div>
                    </div>

                    <form method="POST"
                          action="{{ route('workspace.events.operations.close', [$organization, $event]) }}"
                          class="mt-5 space-y-4"
                          data-confirm="Tutup operasional {{ $event->name }} sekarang?"
                          data-confirm-button="Ya, tutup">
                        @csrf

                        <x-ui.textarea name="notes" label="Catatan penutupan" rows="3" placeholder="Ringkasan hasil acara, kendala, dan tindak lanjut" />

                        @if (! $canClose)
                            <x-ui.input name="reason" label="Alasan override" placeholder="Wajib saat masih ada penghambat" hint="Masih ada penghambat aktif. Penutupan memerlukan override dan alasan." />
                            <label class="flex items-start gap-2.5 rounded-xl border border-warning/30 bg-warning/10 px-3 py-2.5">
                                <input type="checkbox" name="override" value="1" class="mt-0.5 h-4 w-4 rounded border-line text-accent focus:ring-accent/40">
                                <span class="text-[11px] leading-relaxed text-warning">
                                    <strong class="font-semibold">Override penghambat.</strong>
                                    Saya sudah meninjau sisa pekerjaan dan menyetujui penutupan.
                                </span>
                            </label>
                        @endif

                        <button type="submit" class="btn btn-primary w-full justify-center">
                            <x-ui.icon name="check" class="h-4 w-4" /> Tutup operasional
                        </button>
                    </form>

                    <p class="mt-3 text-[11px] leading-relaxed text-faint">
                        Penutupan menyimpan snapshot kondisi operasional dan tidak menghapus data acara.
                    </p>
                </div>
            @endif

            {{-- Buka kembali --}}
            @if ($isCompleted && $canCloseOperations)
                <div class="card p-5">
                    <div class="flex items-center gap-3">
                        <span class="flex h-10 w-10 items-center justify-center rounded-2xl bg-blue-soft text-ink">
                            <x-ui.icon name="refresh" class="h-5 w-5" />
                        </span>
                        <div>
                            <h2 class="section-title">Buka kembali</h2>
                            <p class="mt-0.5 text-xs text-faint">Kembalikan operasional ke status berjalan.</p>
                        </div>
                    </div>

                    <form method="POST"
                          action="{{ route('workspace.events.operations.reopen', [$organization, $event]) }}"
                          class="mt-5 space-y-4"
                          data-confirm="Buka kembali operasional {{ $event->name }}?"
                          data-confirm-button="Ya, buka kembali">
                        @csrf
                        <x-ui.input name="reason" label="Alasan membuka kembali" required placeholder="Contoh: penambahan sesi susulan" />
                        <button type="submit" class="btn btn-secondary w-full justify-center">
                            <x-ui.icon name="play" class="h-4 w-4" /> Buka kembali operasional
                        </button>
                    </form>
                </div>
            @endif

            {{-- Kesiapan --}}
            <div class="card p-5">
                <div class="flex items-center justify-between gap-2">
                    <h2 class="section-title">Kesiapan saat ini</h2>
                    <x-ui.badge :variant="$readinessStatus->badge()">{{ $readinessStatus->label() }}</x-ui.badge>
                </div>

                <ul class="mt-3 space-y-1.5">
                    @foreach (collect($readiness['items'] ?? []) as $item)
                        <li class="flex items-start justify-between gap-2 rounded-lg px-2 py-1.5 text-[11px] hover:bg-surface-2/40">
                            <span class="min-w-0 text-muted">{{ $item['label'] }}</span>
                            <span class="shrink-0 font-medium {{ $item['status'] === ReadinessStatus::Ready ? 'text-success' : ($item['status'] === ReadinessStatus::NotReady ? 'text-danger' : ($item['status'] === ReadinessStatus::Warning ? 'text-warning' : 'text-faint')) }}">
                                {{ $item['status']->label() }}
                            </span>
                        </li>
                    @endforeach
                </ul>
            </div>

            <div class="card p-5">
                <h2 class="section-title">Sebelum menutup</h2>
                <ul class="mt-3 space-y-2 text-[11px] leading-relaxed text-muted">
                    <li class="flex items-start gap-2"><x-ui.icon name="check" class="mt-0.5 h-3.5 w-3.5 shrink-0 text-success" /> Pastikan seluruh insiden serius sudah teratasi.</li>
                    <li class="flex items-start gap-2"><x-ui.icon name="check" class="mt-0.5 h-3.5 w-3.5 shrink-0 text-success" /> Tandai stasiun dan item run of show yang sudah selesai.</li>
                    <li class="flex items-start gap-2"><x-ui.icon name="check" class="mt-0.5 h-3.5 w-3.5 shrink-0 text-success" /> Catat hasil akhir dan tindak lanjut di catatan penutupan.</li>
                </ul>
            </div>
        </div>
    </div>
@endsection
