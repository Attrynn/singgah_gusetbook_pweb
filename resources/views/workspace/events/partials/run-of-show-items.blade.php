{{-- Timeline list for a single run-of-show group. Expects:
     $runItems, $event, $organization, $toLocal, $timezone, $canManage,
     $statuses, $sessionOptions, $phaseOptions, $staffOptions --}}
<div class="space-y-3">
    @foreach ($runItems->sortBy([['sequence', 'asc'], ['planned_start', 'asc']]) as $item)
        @php
            $plannedStart = $toLocal($item->planned_start);
            $plannedEnd = $toLocal($item->planned_end);
            $actualStart = $toLocal($item->actual_start);
            $actualEnd = $toLocal($item->actual_end);
            $picName = $item->pic?->user?->name;
            $picRole = $item->pic?->role instanceof \App\Enums\EventStaffRole ? $item->pic->role->label() : null;
        @endphp
        <article class="card overflow-hidden animate-float-in" x-data="{ editing: false }">
            <div class="p-4">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div class="flex min-w-0 items-start gap-3">
                        <div class="shrink-0 text-center">
                            <p class="text-sm font-bold tabular-nums text-ink">{{ $plannedStart?->format('H:i') ?? '—' }}</p>
                            <p class="text-[10px] text-faint">{{ $plannedEnd?->format('H:i') ?? '' }}</p>
                        </div>
                        <div class="min-w-0">
                            <div class="flex flex-wrap items-center gap-2">
                                <h3 class="text-sm font-semibold text-ink">{{ $item->title }}</h3>
                                <x-ui.badge :variant="$item->status->badge()" class="text-[10px]">{{ $item->status->label() }}</x-ui.badge>
                                @if ($item->phase)
                                    <x-ui.badge variant="neutral" class="text-[10px]">{{ $item->phase->name }}</x-ui.badge>
                                @endif
                            </div>

                            @if ($item->internal_description)
                                <p class="mt-1 text-xs text-muted">{{ $item->internal_description }}</p>
                            @endif

                            <div class="mt-2 flex flex-wrap items-center gap-x-3 gap-y-1 text-[11px] text-muted">
                                <span class="inline-flex items-center gap-1">
                                    <x-ui.icon name="clock" class="h-3 w-3 text-faint" />
                                    Rencana {{ $plannedStart?->translatedFormat('d M, H:i') ?? '—' }}
                                </span>
                                @if ($actualStart)
                                    <span class="inline-flex items-center gap-1 text-success">
                                        <x-ui.icon name="play" class="h-3 w-3" />
                                        Aktual {{ $actualStart->translatedFormat('H:i') }}@if ($actualEnd)–{{ $actualEnd->format('H:i') }}@endif
                                    </span>
                                @endif
                                @if ($picName)
                                    <span class="inline-flex items-center gap-1">
                                        <x-ui.icon name="user" class="h-3 w-3 text-faint" />
                                        {{ $picName }}@if ($picRole) · {{ $picRole }}@endif
                                    </span>
                                @endif
                            </div>

                            @if ($item->internal_notes)
                                <div class="mt-2 rounded-lg border border-line/60 bg-surface-2/40 px-2.5 py-2 text-[11px] text-muted">
                                    <span class="font-semibold text-ink">Catatan internal:</span> {{ $item->internal_notes }}
                                </div>
                            @endif
                        </div>
                    </div>

                    @if ($canManage)
                        <div class="flex items-center gap-1.5">
                            <button type="button" class="icon-btn h-8 w-8" @click="editing = ! editing" :aria-expanded="editing" aria-label="Edit item">
                                <x-ui.icon name="edit" class="h-3.5 w-3.5" />
                            </button>
                            <form method="POST"
                                  action="{{ route('workspace.events.run-of-show.destroy', [$organization, $event, $item]) }}"
                                  data-confirm="Hapus item '{{ $item->title }}' dari run of show?"
                                  data-confirm-button="Ya, hapus">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="icon-btn h-8 w-8 text-danger hover:border-danger/40" aria-label="Hapus item">
                                    <x-ui.icon name="trash" class="h-3.5 w-3.5" />
                                </button>
                            </form>
                        </div>
                    @endif
                </div>
            </div>

            @if ($canManage)
                <form method="POST"
                      action="{{ route('workspace.events.run-of-show.update', [$organization, $event, $item]) }}"
                      class="grid gap-3 border-t border-line/60 bg-surface-2/20 p-4 sm:grid-cols-2"
                      x-show="editing"
                      x-cloak>
                    @csrf
                    @method('PUT')
                    <div class="sm:col-span-2">
                        <x-ui.input name="title" label="Judul item" :value="$item->title" required />
                    </div>
                    <x-ui.select name="session_id" label="Sesi" :options="$sessionOptions" placeholder="Tanpa sesi" :value="$item->event_session_id" />
                    <x-ui.select name="phase_id" label="Fase" :options="$phaseOptions" placeholder="Tanpa fase" :value="$item->phase_id" />
                    <x-ui.select name="pic_staff_membership_id" label="PIC" :options="$staffOptions" placeholder="Tanpa PIC" :value="$item->pic_staff_membership_id" />
                    <x-ui.select name="status" label="Status" :options="$statuses" :value="$item->status->value" />
                    <x-ui.input name="planned_start_local" label="Rencana mulai" type="datetime-local" :value="$plannedStart?->format('Y-m-d\TH:i')" />
                    <x-ui.input name="planned_end_local" label="Rencana selesai" type="datetime-local" :value="$plannedEnd?->format('Y-m-d\TH:i')" />
                    <x-ui.input name="actual_start_local" label="Aktual mulai" type="datetime-local" :value="$actualStart?->format('Y-m-d\TH:i')" />
                    <x-ui.input name="actual_end_local" label="Aktual selesai" type="datetime-local" :value="$actualEnd?->format('Y-m-d\TH:i')" />
                    <div class="sm:col-span-2">
                        <x-ui.textarea name="internal_notes" label="Catatan internal" :value="$item->internal_notes" rows="2" />
                    </div>
                    <div class="flex items-center justify-end gap-2 sm:col-span-2">
                        <button type="button" class="btn btn-ghost btn-sm" @click="editing = false">Batal</button>
                        <button type="submit" class="btn btn-primary btn-sm">
                            <x-ui.icon name="save" class="h-3.5 w-3.5" /> Simpan
                        </button>
                    </div>
                </form>
            @endif
        </article>
    @endforeach
</div>