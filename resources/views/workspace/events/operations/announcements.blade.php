@extends('layouts.owner')

@section('title', 'Pengumuman · '.$event->name)

@php
    use App\Enums\AnnouncementAudience;
    use App\Enums\AnnouncementPriority;
    use App\Enums\AnnouncementStatus;

    $announcements = collect($announcements ?? []);
    $progress = collect($progress ?? []);
    $myRecipients = collect($myRecipients ?? []);
    $stations = collect($stations ?? []);
    $staff = collect($staff ?? []);
    $roles = collect($roles ?? []);
    $audiences = collect($audiences ?? AnnouncementAudience::cases());
    $priorities = collect($priorities ?? AnnouncementPriority::cases());
    $statuses = collect($statuses ?? AnnouncementStatus::cases());

    $roleLabel = fn ($role): string => $role instanceof \App\Enums\EventStaffRole
        ? $role->label()
        : ucfirst(str_replace('_', ' ', (string) $role));

    $stationOptions = $stations->mapWithKeys(fn ($station) => [$station->id => $station->name]);
    $membershipOptions = $staff->mapWithKeys(fn ($membership) => [$membership->id => ($membership->user?->name ?? 'Anggota').' — '.$roleLabel($membership->role)]);

    $progressFor = fn (EventAnnouncement $announcement): array => $progress[$announcement->id] ?? ['total' => 0, 'read' => 0, 'acknowledged' => 0];

    $recipientFor = fn (EventAnnouncement $announcement): ?EventAnnouncementRecipient => $myRecipients[$announcement->id] ?? null;

    $hasPendingAck = $announcements->filter(function ($announcement) use ($recipientFor) {
        $recipient = $recipientFor($announcement);
        return $announcement->requires_acknowledgement && $recipient?->acknowledged_at === null;
    });

    $canManage = auth()->user()->can('manageAnnouncements', $event);
    $canPublish = auth()->user()->can('publishAnnouncements', $event);
@endphp

@section('content')
    <x-ui.page-header
        title="Pengumuman"
        eyebrow="Operasi"
        :subtitle="'Kirim dan distribusikan pengumuman kepada tim lapangan untuk '.$event->name.'.'"
    >
        <x-ui.badge :variant="$canPublish ? 'success' : 'neutral'">{{ $announcements->count() }} pengumuman</x-ui.badge>
        <x-ui.button variant="secondary" :href="route('workspace.events.operations.index', [$organization, $event])" icon="arrow-left">Ruang kendali</x-ui.button>
    </x-ui.page-header>

    @include('workspace.events.partials.event-tabs', ['organization' => $organization, 'event' => $event, 'setup' => $setup ?? null, 'active' => 'operations-announcements'])

    {{-- Stat --}}
    <div class="mt-6 grid grid-cols-2 gap-3 md:grid-cols-4">
        <x-ui.stat-card label="Diterbitkan" :value="$announcements->where('status', AnnouncementStatus::Published)->count()" icon="send" tone="accent" />
        <x-ui.stat-card label="Tertunda konfirmasi" :value="$hasPendingAck->count()" icon="shield" :tone="$hasPendingAck->isNotEmpty() ? 'danger' : 'teal'" />
        <x-ui.stat-card label="Terbaca" :value="$announcements->reduce(fn ($acc, $a) => $acc + ($progressFor($a)['read'] ?? 0), 0)" icon="eye" tone="success" />
        <x-ui.stat-card label="Dikonfirmasi" :value="$announcements->reduce(fn ($acc, $a) => $acc + ($progressFor($a)['acknowledged'] ?? 0), 0)" icon="check" tone="success" />
    </div>

    <div class="mt-5 grid gap-5 lg:grid-cols-3">
        <div class="space-y-5 lg:col-span-2">
            @if ($announcements->isEmpty())
                <x-ui.empty-state icon="send" title="Belum ada pengumuman" description="Buat pengumuman dan terbitkan agar tim lapangan mendapat notifikasi." />
            @else
                @foreach ($announcements as $announcement)
                    @php
                        $p = $progressFor($announcement);
                        $recipient = $recipientFor($announcement);
                        $isRead = $recipient?->read_at !== null;
                        $isAcknowledged = $recipient?->acknowledged_at !== null;
                        $needsAck = $announcement->requires_acknowledgement;
                        $visible = $announcement->isVisible();
                    @endphp

                    <article class="card overflow-hidden animate-float-in" x-data="{ editing: false }">
                        <div class="flex flex-wrap items-start justify-between gap-3 p-5">
                            <div class="min-w-0 flex-1">
                                <div class="flex flex-wrap items-center gap-2">
                                    <h3 class="text-sm font-semibold text-ink">{{ $announcement->title }}</h3>
                                    <x-ui.badge :variant="$announcement->priority->badge()" class="text-[10px]">{{ $announcement->priority->label() }}</x-ui.badge>
                                    <x-ui.badge :variant="$announcement->status->badge()" class="text-[10px]">{{ $announcement->status->label() }}</x-ui.badge>
                                </div>

                                <p class="mt-1.5 text-[11px] leading-relaxed text-muted">{{ $announcement->body }}</p>

                                <div class="mt-3 flex flex-wrap items-center gap-x-3 gap-y-1 text-[11px] text-muted">
                                    <span class="inline-flex items-center gap-1"><x-ui.icon name="clock" class="h-3 w-3 text-faint" /> {{ $announcement->published_at?->diffForHumans() ?? 'Belum diterbitkan' }}</span>
                                    @if ($announcement->expires_at)
                                        <span class="inline-flex items-center gap-1 text-warning"><x-ui.icon name="clock" class="h-3 w-3" /> kedaluwarsa {{ $announcement->expires_at->translatedFormat('d M Y') }}</span>
                                    @endif
                                    @if ($needsAck)
                                        <span class="inline-flex items-center gap-1 text-danger"><x-ui.icon name="shield" class="h-3 w-3" /> konfirmasi wajib</span>
                                    @endif
                                </div>

                                {{-- Progres penerima --}}
                                <div class="mt-3 space-y-1.5">
                                    <div>
                                        <div class="flex items-center justify-between text-[10px]">
                                            <span class="text-muted">Terbaca</span>
                                            <span class="font-semibold tabular-nums text-ink">{{ $p['read'] }}/{{ $p['total'] }}</span>
                                        </div>
                                        <div class="mt-1 h-1.5 w-full overflow-hidden rounded-full bg-surface-2">
                                            <div class="h-full rounded-full bg-blue-soft" style="width: {{ $p['total'] > 0 ? max(2, (int) round(($p['read'] / $p['total']) * 100)) : 0 }}%"></div>
                                        </div>
                                    </div>
                                    @if ($needsAck)
                                        <div>
                                            <div class="flex items-center justify-between text-[10px]">
                                                <span class="text-muted">Dikonfirmasi</span>
                                                <span class="font-semibold tabular-nums text-ink">{{ $p['acknowledged'] }}/{{ $p['total'] }}</span>
                                            </div>
                                            <div class="mt-1 h-1.5 w-full overflow-hidden rounded-full bg-surface-2">
                                                <div class="h-full rounded-full bg-accent" style="width: {{ $p['total'] > 0 ? max(2, (int) round(($p['acknowledged'] / $p['total']) * 100)) : 0 }}%"></div>
                                            </div>
                                        </div>
                                    @endif
                                </div>
                            </div>

                            <div class="flex shrink-0 flex-col items-end gap-2 sm:flex-row sm:items-end">
                                @if ($canManage && in_array($announcement->status, [AnnouncementStatus::Draft, AnnouncementStatus::Scheduled]))
                                    <button type="button" class="btn btn-secondary btn-sm shrink-0" @click="editing = ! editing" :aria-expanded="editing">
                                        <x-ui.icon name="edit" class="h-3.5 w-3.5" /> Edit
                                    </button>
                                @endif

                                @if ($canPublish && $visible)
                                    <form method="POST" action="{{ route('workspace.events.operations.announcements.withdraw', [$organization, $event, $announcement]) }}" data-confirm="Tarik pengumuman ini?" data-confirm-button="Ya, tarik">
                                        @csrf
                                        <button type="submit" class="btn btn-ghost btn-sm shrink-0">
                                            <x-ui.icon name="close" class="h-3.5 w-3.5" /> Tarik
                                        </button>
                                    </form>
                                @endif

                                @if ($canPublish && ! $visible)
                                    <form method="POST" action="{{ route('workspace.events.operations.announcements.publish', [$organization, $event, $announcement]) }}" data-confirm="Terbitkan pengumuman ini?" data-confirm-button="Ya, terbitkan">
                                        @csrf
                                        <button type="submit" class="btn btn-primary btn-sm shrink-0">
                                            <x-ui.icon name="send" class="h-3.5 w-3.5" /> Terbitkan
                                        </button>
                                    </form>
                                @endif
                            </div>
                        </div>

                        @if ($canManage && in_array($announcement->status, [AnnouncementStatus::Draft, AnnouncementStatus::Scheduled]))
                            <form method="POST" action="{{ route('workspace.events.operations.announcements.update', [$organization, $event, $announcement]) }}" class="grid gap-3 border-t border-line/60 bg-surface-2/20 p-4 sm:grid-cols-2" x-show="editing" x-cloak>
                                @csrf
                                @method('PUT')
                                <div class="sm:col-span-2">
                                    <x-ui.input name="title" label="Judul" :value="$announcement->title" required />
                                </div>
                                <div class="sm:col-span-2">
                                    <x-ui.textarea name="body" label="Isi pengumuman" rows="4" :value="$announcement->body" required />
                                </div>
                                <x-ui.select name="priority" label="Prioritas" :options="$priorities" :value="$announcement->priority->value" />
                                <x-ui.input name="expires_at" label="Kedaluwarsa" type="datetime-local" :value="$announcement->expires_at?->copy()->setTimezone($event->timezoneName())->format('Y-m-d\TH:i')" />
                                <label class="flex items-start gap-2 rounded-xl border border-line/60 bg-surface-2/20 px-3 py-2 text-[11px] text-muted sm:col-span-2">
                                    <input type="hidden" name="requires_acknowledgement" value="0">
                                    <input type="checkbox" name="requires_acknowledgement" value="1" class="mt-0.5 h-3.5 w-3.5 rounded border-line text-accent focus:ring-accent/40" @checked($announcement->requires_acknowledgement)>
                                    <span>Wajib konfirmasi.</span>
                                </label>
                                <div class="flex items-center justify-end gap-2 sm:col-span-2">
                                    <button type="button" class="btn btn-ghost btn-sm" @click="editing = false">Batal</button>
                                    <button type="submit" class="btn btn-secondary btn-sm">
                                        <x-ui.icon name="save" class="h-3.5 w-3.5" /> Simpan
                                    </button>
                                </div>
                            </form>
                        @endif

                        <details class="group border-t border-line/60 bg-surface-2/20">
                            <summary class="flex cursor-pointer list-none items-center justify-between gap-2 px-4 py-2.5 text-[11px] font-semibold text-muted transition hover:text-ink">
                                <span class="inline-flex items-center gap-1.5"><x-ui.icon name="list" class="h-3.5 w-3.5" /> Detail dan tindakan</span>
                                <x-ui.icon name="chevron-down" class="h-3.5 w-3.5 transition group-open:rotate-180" />
                            </summary>

                            <div class="space-y-4 border-t border-line/50 p-4">
                                <div class="rounded-xl border border-line/60 bg-surface p-3">
                                    <h4 class="text-[11px] font-semibold uppercase tracking-[0.08em] text-faint">Audience</h4>
                                    <p class="mt-1 text-[10px] text-muted">{{ $announcement->audience->label() }}</p>
                                    @if ($announcement->audience === AnnouncementAudience::Station)
                                        <ul class="mt-2 space-y-1 text-[11px] text-muted">
                                            @foreach ($announcement->audience_ref['station_ids'] ?? [] as $stationId)
                                                <li class="inline-flex items-center gap-1"><x-ui.icon name="pin" class="h-3 w-3 text-faint" /> {{ $stationOptions[$stationId] ?? 'Stasiun #'.$stationId }}</li>
                                            @endforeach
                                        </ul>
                                    @elseif ($announcement->audience === AnnouncementAudience::SelectedStaff)
                                        <ul class="mt-2 space-y-1 text-[11px] text-muted">
                                            @foreach ($announcement->audience_ref['membership_ids'] ?? [] as $membershipId)
                                                <li class="inline-flex items-center gap-1"><x-ui.icon name="user" class="h-3 w-3 text-faint" /> {{ $membershipOptions[$membershipId] ?? 'Anggota #'.$membershipId }}</li>
                                            @endforeach
                                        </ul>
                                    @elseif ($announcement->audience === AnnouncementAudience::Profile)
                                        <ul class="mt-2 space-y-1 text-[11px] text-muted">
                                            @foreach ($announcement->audience_ref['roles'] ?? [] as $roleValue)
                                                @php $roleEnum = \App\Enums\EventStaffRole::from($roleValue); @endphp
                                                <li class="inline-flex items-center gap-1"><x-ui.icon name="shield" class="h-3 w-3 text-accent" /> {{ $roleEnum->label() }}</li>
                                            @endforeach
                                        </ul>
                                    @endif
                                </div>

                                <div class="flex flex-wrap items-center gap-2">
                                    @if (! $isRead)
                                        <form method="POST" action="{{ route('workspace.events.operations.announcements.read', [$organization, $event, $announcement]) }}">
                                            @csrf
                                            <button type="submit" class="btn btn-ghost btn-sm">
                                                <x-ui.icon name="eye" class="h-3 w-3" /> Tandai dibaca
                                            </button>
                                        </form>
                                    @else
                                        <span class="inline-flex items-center gap-1 text-[10px] font-medium text-success">
                                            <x-ui.icon name="check" class="h-3 w-3" /> Sudah dibaca
                                        </span>
                                    @endif

                                    @if ($needsAck)
                                        @if ($isAcknowledged)
                                            <span class="inline-flex items-center gap-1 text-[10px] font-medium text-success">
                                                <x-ui.icon name="shield" class="h-3 w-3" /> Dikonfirmasi
                                            </span>
                                        @else
                                            <form method="POST" action="{{ route('workspace.events.operations.announcements.acknowledge', [$organization, $event, $announcement]) }}">
                                                @csrf
                                                <button type="submit" class="btn btn-primary btn-sm">
                                                    <x-ui.icon name="check" class="h-3 w-3" /> Konfirmasi
                                                </button>
                                            </form>
                                        @endif
                                    @endif
                                </div>
                            </div>
                        </details>
                    </article>
                @endforeach
            @endif
        </div>

        <div class="space-y-5">
            @if ($canManage)
                <div class="card p-5">
                    <div class="flex items-center gap-3">
                        <span class="flex h-10 w-10 items-center justify-center rounded-2xl bg-blue-soft text-ink">
                            <x-ui.icon name="send" class="h-5 w-5" />
                        </span>
                        <div>
                            <h2 class="section-title">Buat pengumuman</h2>
                            <p class="mt-0.5 text-xs text-faint">Pilih audiens, prioritas, dan opsi konfirmasi.</p>
                        </div>
                    </div>

                    <form method="POST" action="{{ route('workspace.events.operations.announcements.store', [$organization, $event]) }}" class="mt-5 space-y-4" x-data="{ audience: '', showRef: false }">
                        @csrf
                        <x-ui.input name="title" label="Judul" required placeholder="Contoh: Pengumuman hari H" />
                        <x-ui.textarea name="body" label="Isi pengumuman" rows="4" required placeholder="Isi seluruh bagian pengumuman" />

                        <div class="grid gap-3 sm:grid-cols-2">
                            <x-ui.select name="priority" label="Prioritas" :options="$priorities" :value="null" />
                            <x-ui.select name="audience" label="Audiens" :options="$audiences" :value="null" @change="audience = $el.value; showRef = audience === 'all_staff' || audience === 'selected_staff'; showRef ? $next.toggle('ref') : $next.hide('ref')" />
                        </div>

                        <div x-show="showRef" x-cloak class="space-y-3">
                            <div class="grid gap-3 sm:grid-cols-2">
                                <div>
                                    <label class="label" for="ref-station-{{ uniqid() }}">Stasiun tertentu</label>
                                    <select id="ref-station-{{ uniqid() }}" name="audience_ref[station_ids][]" class="input appearance-none pr-8" multiple aria-label="Stasiun tertentu">
                                        @foreach ($stationOptions as $stationId => $stationName)
                                            <option value="{{ $stationId }}">{{ $stationName }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div>
                                    <label class="label" for="ref-membership-{{ uniqid() }}">Anggota tertentu</label>
                                    <select id="ref-membership-{{ uniqid() }}" name="audience_ref[membership_ids][]" class="input appearance-none pr-8" multiple aria-label="Anggota tertentu">
                                        @foreach ($membershipOptions as $membershipId => $membershipName)
                                            <option value="{{ $membershipId }}">{{ $membershipName }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>

                            <div>
                                <label class="label" for="ref-roles-{{ uniqid() }}">Peran tertentu</label>
                                <select id="ref-roles-{{ uniqid() }}" name="audience_ref[roles][]" class="input appearance-none pr-8" multiple aria-label="Peran tertentu">
                                    @foreach ($roles as $role)
                                        <option value="{{ $role->value }}">{{ $role->label() }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                        <label class="flex items-start gap-2.5 rounded-xl border border-warning/30 bg-warning/10 px-3 py-2.5">
                            <input type="checkbox" name="requires_acknowledgement" value="1" class="mt-0.5 h-4 w-4 rounded border-line text-accent focus:ring-accent/40">
                            <span class="text-[11px] leading-relaxed text-warning">
                                <strong class="font-semibold">Wajib konfirmasi.</strong>
                                Pengumuman akan meminta konfirmasi pembacaan dari penerima. Gunakan prioritas kritis bila diperlukan.
                            </span>
                        </label>

                        <x-ui.input name="expires_at" label="Kedaluwarsa" type="date" hint="Kosongkan bila tanpa batas waktu." />

                        <button type="submit" class="btn btn-primary w-full justify-center">
                            <x-ui.icon name="send" class="h-4 w-4" /> Buat pengumuman
                        </button>
                    </form>
                </div>
            @endif

            <div class="card p-5">
                <h2 class="section-title">Alur pengumuman</h2>
                <ol class="mt-3 space-y-2.5 text-[11px] leading-relaxed text-muted">
                    <li class="flex items-start gap-2"><span class="mt-0.5 flex h-5 w-5 shrink-0 items-center justify-center rounded-full bg-surface-2 text-[9px] font-semibold text-ink">1</span> Buat pengumuman dan tentukan audiens.</li>
                    <li class="flex items-start gap-2"><span class="mt-0.5 flex h-5 w-5 shrink-0 items-center justify-center rounded-full bg-surface-2 text-[9px] font-semibold text-ink">2</span> Terbitkan agar tim lapangan menerima notifikasi.</li>
                    <li class="flex items-start gap-2"><span class="mt-0.5 flex h-5 w-5 shrink-0 items-center justify-center rounded-full bg-surface-2 text-[9px] font-semibold text-ink">3</span> Tandai dibaca dan konfirmasi bila wajib.</li>
                    <li class="flex items-start gap-2"><span class="mt-0.5 flex h-5 w-5 shrink-0 items-center justify-center rounded-full bg-surface-2 text-[9px] font-semibold text-ink">4</span> Tarik bila perlu agar tidak lagi diterbitkan.</li>
                </ol>
            </div>
        </div>
    </div>
@endsection
