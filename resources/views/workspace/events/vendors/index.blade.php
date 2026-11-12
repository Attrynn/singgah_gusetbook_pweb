@extends('layouts.owner')

@section('title', 'Vendor · '.$event->name)

@php
    use App\Enums\VendorDeliverableStatus;
    use App\Enums\VendorEngagementStatus;
    use App\Enums\VendorIssueSeverity;

    $vendorMetrics = $data['vendors'];
    $engagementMetrics = $data['engagements'];
    $proposalMetrics = $data['proposals'];
    $documentMetrics = $data['documents'];
    $deliverableMetrics = $data['deliverables'];
    $logisticsMetrics = $data['logistics'];
    $issueMetrics = $data['issues'];
    $changeMetrics = $data['change_requests'];
    $rating = $data['performance']['average_rating'];
    $commercial = $data['commercial'] ?? null;
    $upcomingArrivals = collect($data['upcoming_arrivals'] ?? []);

    $timezone = $event->timezone ?: 'Asia/Jakarta';
    $toLocal = fn ($datetime) => $datetime?->copy()->setTimezone($timezone);

    $engagementCounts = collect($engagementMetrics['by_status'] ?? []);
    $activeEngagementCount = collect([
        VendorEngagementStatus::Confirmed,
        VendorEngagementStatus::InPreparation,
        VendorEngagementStatus::InProgress,
    ])->sum(fn (VendorEngagementStatus $status): int => (int) ($engagementCounts[$status->value] ?? 0));

    $deliverableCounts = collect($deliverableMetrics['by_status'] ?? []);
    $deliverableStatusRows = collect(VendorDeliverableStatus::cases())
        ->map(fn (VendorDeliverableStatus $status): array => [
            'status' => $status,
            'count' => (int) ($deliverableCounts[$status->value] ?? 0),
        ])
        ->filter(fn (array $row): bool => $row['count'] > 0);

    $openDeliverables = $deliverables->filter(fn ($deliverable): bool => $deliverable->status->isOpen());
    $completedDeliverables = $deliverables->count() - $openDeliverables->count();

    $openLogistics = $logisticsRequirements->filter(fn ($requirement): bool => $requirement->status->isOpen());
    $openIssues = $issues->filter(fn ($issue): bool => $issue->status->isOpen());

    $issuesBySeverity = collect($issueMetrics['by_severity'] ?? []);
    $criticalIssues = (int) ($issuesBySeverity[VendorIssueSeverity::Critical->value] ?? 0);
    $highIssues = (int) ($issuesBySeverity[VendorIssueSeverity::High->value] ?? 0);
    $severityRows = collect(VendorIssueSeverity::cases())
        ->map(fn (VendorIssueSeverity $severity): array => [
            'severity' => $severity,
            'count' => (int) ($issuesBySeverity[$severity->value] ?? 0),
        ])
        ->filter(fn (array $row): bool => $row['count'] > 0);

    $canViewEngagements = auth()->user()->can('viewVendorEngagements', $event);
    $canViewReports = auth()->user()->can('viewVendorReports', $event);
    $canViewDirectory = auth()->user()->can('viewVendors', $organization);

    $engagementHref = fn ($engagement) => $canViewEngagements
        ? route('workspace.events.vendors.engagements.show', [$organization, $event, $engagement])
        : null;

    $formatMoney = function ($amount, ?string $currency = null): string {
        if ($amount === null || $amount === '') {
            return '—';
        }

        $currency = strtoupper($currency ?: 'IDR');

        if ($currency === 'IDR') {
            return 'Rp'.number_format((float) $amount, 0, ',', '.');
        }

        return $currency.' '.number_format((float) $amount, 2, ',', '.');
    };
@endphp

@section('content')
    <x-ui.page-header
        title="Vendor & penyedia layanan"
        eyebrow="Vendor"
        :subtitle="'Susun kebutuhan, nilai proposal, dan koordinasikan vendor untuk '.$event->name.'.'"
    >
        @if ($canViewDirectory)
            <x-ui.button variant="secondary" :href="route('workspace.vendors.index', $organization)" icon="archive">Direktori vendor</x-ui.button>
        @endif

        @if ($canViewReports)
            <x-ui.button variant="secondary" :href="route('workspace.events.vendors.reports.index', [$organization, $event])" icon="chart">Laporan vendor</x-ui.button>
        @endif
    </x-ui.page-header>

    @include('workspace.events.partials.event-tabs', ['organization' => $organization, 'event' => $event, 'active' => 'vendors'])

    {{-- Ringkasan rantai pasok --}}
    <div class="mt-6 overflow-hidden rounded-3xl border border-line/80 bg-gradient-to-br from-accent-soft/70 via-surface to-teal-soft/50 shadow-raised animate-rise">
        <div class="grid gap-6 p-6 md:grid-cols-[minmax(0,1.4fr)_minmax(0,1fr)] md:items-center md:p-8">
            <div class="min-w-0">
                <p class="section-kicker">Rantai pasok acara</p>

                <div class="mt-2 flex flex-wrap items-baseline gap-3">
                    <p class="font-serif text-3xl font-bold tracking-tight text-ink md:text-4xl">
                        {{ $engagementMetrics['total'] }} engagement
                    </p>
                    <x-ui.badge :variant="$activeEngagementCount > 0 ? 'success' : 'neutral'">
                        {{ $activeEngagementCount }} aktif
                    </x-ui.badge>
                </div>

                <p class="mt-2 max-w-xl text-sm leading-relaxed text-muted">
                    {{ $vendorMetrics['active'] }} dari {{ $vendorMetrics['total'] }} vendor di direktori organisasi sedang aktif.
                    @if ($proposalMetrics['pending'] > 0)
                        {{ $proposalMetrics['pending'] }} proposal masih menunggu evaluasi.
                    @endif
                    @if ($deliverableMetrics['overdue'] > 0)
                        {{ $deliverableMetrics['overdue'] }} deliverable sudah melewati tenggat.
                    @endif
                </p>

                <div class="mt-4 flex flex-wrap items-center gap-x-5 gap-y-2 text-[11px] text-muted">
                    <span class="inline-flex items-center gap-1.5">
                        <x-ui.icon name="calendar" class="h-3.5 w-3.5 text-faint" />
                        {{ $event->event_date?->translatedFormat('d F Y') ?? 'Tanggal belum ditetapkan' }}
                    </span>
                    <span class="inline-flex items-center gap-1.5">
                        <x-ui.icon name="clock" class="h-3.5 w-3.5 text-faint" />
                        Zona waktu {{ $timezone }}
                    </span>
                    @if ($upcomingArrivals->isNotEmpty())
                        <span class="inline-flex items-center gap-1.5">
                            <x-ui.icon name="pin" class="h-3.5 w-3.5 text-faint" />
                            Kedatangan berikutnya {{ $toLocal($upcomingArrivals->first()->expected_arrival_at)?->translatedFormat('d M, H:i') }}
                        </span>
                    @endif
                </div>
            </div>

            <div class="grid grid-cols-3 gap-2 rounded-2xl border border-line/70 bg-surface/80 p-4 backdrop-blur">
                <div class="text-center">
                    <p class="text-[10px] font-semibold uppercase tracking-[0.12em] text-faint">Proposal</p>
                    <p class="mt-1 text-xl font-semibold tabular-nums text-ink">{{ $proposalMetrics['pending'] }}</p>
                    <p class="text-[10px] text-faint">menunggu</p>
                </div>
                <div class="border-x border-line/60 text-center">
                    <p class="text-[10px] font-semibold uppercase tracking-[0.12em] text-faint">Dokumen</p>
                    <p class="mt-1 text-xl font-semibold tabular-nums {{ $documentMetrics['pending_review'] > 0 ? 'text-warning' : 'text-ink' }}">{{ $documentMetrics['pending_review'] }}</p>
                    <p class="text-[10px] text-faint">ditinjau</p>
                </div>
                <div class="text-center">
                    <p class="text-[10px] font-semibold uppercase tracking-[0.12em] text-faint">Masalah</p>
                    <p class="mt-1 text-xl font-semibold tabular-nums {{ $issueMetrics['open'] > 0 ? 'text-danger' : 'text-ink' }}">{{ $issueMetrics['open'] }}</p>
                    <p class="text-[10px] text-faint">terbuka</p>
                </div>
            </div>
        </div>

        @if ($canManageCommercial && $commercial)
            <div class="grid gap-4 border-t border-line/60 bg-surface/50 px-6 py-4 sm:grid-cols-2 md:px-8">
                <div>
                    <p class="text-[10px] font-semibold uppercase tracking-[0.12em] text-faint">Nilai kesepakatan</p>
                    @forelse ($commercial['agreed_by_currency'] ?? [] as $currency => $total)
                        <p class="mt-1 font-serif text-lg font-semibold tracking-tight text-ink">
                            {{ $formatMoney($total, $currency) }}
                            @if (strtoupper($currency) !== 'IDR')
                                <span class="ml-1 text-[10px] font-sans font-medium uppercase tracking-[0.08em] text-faint">{{ $currency }}</span>
                            @endif
                        </p>
                    @empty
                        <p class="mt-1 text-sm text-muted">Belum ada nilai kesepakatan.</p>
                    @endforelse
                </div>

                <div class="sm:border-l sm:border-line/60 sm:pl-4">
                    <p class="text-[10px] font-semibold uppercase tracking-[0.12em] text-faint">Nilai penawaran</p>
                    @forelse ($commercial['quoted_by_currency'] ?? [] as $currency => $total)
                        <p class="mt-1 font-serif text-lg font-semibold tracking-tight text-ink">
                            {{ $formatMoney($total, $currency) }}
                            @if (strtoupper($currency) !== 'IDR')
                                <span class="ml-1 text-[10px] font-sans font-medium uppercase tracking-[0.08em] text-faint">{{ $currency }}</span>
                            @endif
                        </p>
                    @empty
                        <p class="mt-1 text-sm text-muted">Belum ada nilai penawaran.</p>
                    @endforelse
                </div>
            </div>
        @endif
    </div>

    {{-- Metrik utama --}}
    <div class="mt-5 grid grid-cols-2 gap-3 sm:grid-cols-2 xl:grid-cols-4">
        <x-ui.stat-card
            label="Vendor aktif"
            :value="$vendorMetrics['active']"
            icon="archive"
            tone="accent"
            :hint="$vendorMetrics['total'].' vendor di direktori'"
        />
        <x-ui.stat-card
            label="Engagement"
            :value="$engagementMetrics['total']"
            icon="calendar-check"
            tone="teal"
            :hint="$activeEngagementCount.' sedang berjalan'"
        />
        <x-ui.stat-card
            label="Proposal menunggu"
            :value="$proposalMetrics['pending']"
            icon="list"
            :tone="$proposalMetrics['pending'] > 0 ? 'warning' : 'success'"
            :hint="$proposalMetrics['received'].' diterima · '.$proposalMetrics['selected'].' dipilih'"
        />
        <x-ui.stat-card
            label="Deliverable terlambat"
            :value="$deliverableMetrics['overdue']"
            icon="clipboard"
            :tone="$deliverableMetrics['overdue'] > 0 ? 'danger' : 'success'"
            :hint="$deliverables->count().' deliverable tercatat'"
        />
    </div>

    <div class="mt-3 grid gap-3 grid-cols-2 lg:grid-cols-3 xl:grid-cols-5">
        <x-ui.stat-card
            label="Dokumen ditinjau"
            :value="$documentMetrics['pending_review']"
            icon="file-text"
            :tone="$documentMetrics['pending_review'] > 0 ? 'warning' : 'success'"
            :hint="$documentMetrics['approved'].' disetujui · '.$documentMetrics['expired'].' kedaluwarsa'"
        />
        <x-ui.stat-card
            label="Logistik terbuka"
            :value="$logisticsMetrics['open']"
            icon="pin"
            :tone="$logisticsMetrics['open'] > 0 ? 'warning' : 'success'"
            :hint="$logisticsRequirements->count().' kebutuhan tercatat'"
        />
        <x-ui.stat-card
            label="Masalah terbuka"
            :value="$issueMetrics['open']"
            icon="alert"
            :tone="$issueMetrics['open'] > 0 ? 'danger' : 'success'"
            :hint="$criticalIssues.' kritis · '.$highIssues.' tinggi'"
        />
        <x-ui.stat-card
            label="Perubahan menunggu"
            :value="$changeMetrics['pending']"
            icon="refresh"
            :tone="$changeMetrics['pending'] > 0 ? 'accent' : 'success'"
            :hint="$changeRequests->count().' permintaan tercatat'"
        />
        @if ($canViewPerformance)
            <x-ui.stat-card
                label="Rating vendor"
                :value="$rating !== null ? number_format((float) $rating, 2, ',', '.') : '—'"
                icon="star"
                tone="success"
                hint="Skala 1–5 dari penilaian tersimpan"
            />
        @endif
    </div>

    {{-- Kedatangan berikutnya --}}
    <div class="card mt-5 overflow-hidden">
        <div class="flex flex-wrap items-center justify-between gap-2 border-b border-line/70 px-5 py-4">
            <div>
                <h2 class="section-title">Kedatangan berikutnya</h2>
                <p class="mt-0.5 text-xs text-faint">Sepuluh jadwal kedatangan vendor terdekat.</p>
            </div>
            <x-ui.badge variant="neutral" class="text-[10px]">{{ $upcomingArrivals->count() }} terjadwal</x-ui.badge>
        </div>

        @if ($upcomingArrivals->isEmpty())
            <div class="p-5">
                <x-ui.empty-state
                    icon="clock"
                    title="Belum ada kedatangan terjadwal"
                    description="Jadwal kedatangan vendor akan muncul di sini setelah referensi jadwal diisi."
                />
            </div>
        @else
            <div class="overflow-x-auto">
                <ol class="flex min-w-max gap-3 p-5">
                    @foreach ($upcomingArrivals as $arrival)
                        @php $arrivalAt = $toLocal($arrival->expected_arrival_at); @endphp
                        <li class="w-56 shrink-0 rounded-2xl border border-line/70 bg-surface-2/30 p-4">
                            <div class="flex items-center justify-between gap-2">
                                <span class="badge {{ $arrival->arrival_status->badge() }} text-[9px]">{{ $arrival->arrival_status->label() }}</span>
                                <span class="text-[10px] font-medium uppercase tracking-[0.08em] text-faint">{{ $arrivalAt?->translatedFormat('d M') }}</span>
                            </div>
                            <p class="mt-3 font-serif text-2xl font-bold tabular-nums tracking-tight text-ink">{{ $arrivalAt?->format('H:i') ?? '—' }}</p>
                            <p class="mt-1 truncate text-xs font-semibold text-ink">{{ $arrival->engagement?->vendor?->name ?? 'Vendor' }}</p>
                            <p class="truncate text-[11px] text-faint">{{ $arrival->engagement?->title ?? 'Engagement' }}</p>
                            <p class="mt-2 text-[10px] text-muted">{{ $arrivalAt?->diffForHumans() }}</p>
                        </li>
                    @endforeach
                </ol>
            </div>
        @endif
    </div>

    <div class="mt-5 grid gap-5 xl:grid-cols-3">
        <div class="space-y-5 xl:col-span-2">
            {{-- Kebutuhan & pencarian --}}
            <div class="card overflow-hidden">
                <div class="flex flex-wrap items-center justify-between gap-2 border-b border-line/70 px-5 py-4">
                    <div>
                        <h2 class="section-title">Kebutuhan & pencarian vendor</h2>
                        <p class="mt-0.5 text-xs text-faint">{{ $requirements->count() }} kebutuhan tercatat</p>
                    </div>
                    <x-ui.badge variant="neutral" class="text-[10px]">
                        {{ $requirements->filter(fn ($requirement) => $requirement->status->isOpen())->count() }} masih berjalan
                    </x-ui.badge>
                </div>

                @if ($requirements->isEmpty())
                    <div class="p-5">
                        <x-ui.empty-state
                            icon="grid"
                            title="Belum ada kebutuhan vendor"
                            description="Catat kebutuhan layanan terlebih dahulu agar kandidat dan proposal dapat dihimpun."
                        />
                    </div>
                @else
                    <ul class="divide-y divide-line/60">
                        @foreach ($requirements as $requirement)
                            <li class="px-5 py-4">
                                <div class="flex flex-wrap items-start justify-between gap-2">
                                    <div class="min-w-0">
                                        <p class="text-sm font-semibold text-ink">{{ $requirement->title }}</p>
                                        <p class="mt-0.5 text-[11px] text-faint">
                                            {{ $requirement->needed_by?->translatedFormat('d M Y') ? 'Dibutuhkan '.$requirement->needed_by->translatedFormat('d M Y') : 'Tenggat belum ditetapkan' }}
                                            @if ($requirement->quantity)
                                                · {{ $requirement->quantity }} unit
                                            @endif
                                        </p>
                                    </div>
                                    <x-ui.badge :variant="$requirement->status->badge()" class="shrink-0 text-[10px]">{{ $requirement->status->label() }}</x-ui.badge>
                                </div>

                                @if ($requirement->description)
                                    <p class="mt-2 text-[11px] leading-relaxed text-muted">{{ \Illuminate\Support\Str::limit($requirement->description, 140) }}</p>
                                @endif

                                <div class="mt-3 flex flex-wrap items-center gap-1.5">
                                    @forelse ($requirement->candidates as $candidate)
                                        <span class="inline-flex items-center gap-1.5 rounded-full border border-line/70 bg-surface-2/40 px-2 py-0.5 text-[10px] text-muted">
                                            {{ $candidate->vendor?->name ?? 'Vendor' }}
                                            <span class="badge {{ $candidate->state->badge() }} border-0 bg-transparent px-0 py-0 text-[9px]">{{ $candidate->state->label() }}</span>
                                        </span>
                                    @empty
                                        <span class="text-[11px] text-faint">Belum ada kandidat vendor.</span>
                                    @endforelse
                                </div>

                                @if ($requirement->proposals->isNotEmpty())
                                    <p class="mt-2 text-[11px] text-muted">
                                        {{ $requirement->proposals->count() }} proposal dari
                                        {{ $requirement->proposals->take(3)->map(fn ($proposal) => $proposal->vendor?->name)->filter()->implode(', ') }}
                                        @if ($requirement->proposals->count() > 3)
                                            dan lainnya
                                        @endif
                                    </p>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>

            {{-- Proposal --}}
            <div class="card overflow-hidden">
                <div class="flex flex-wrap items-center justify-between gap-2 border-b border-line/70 px-5 py-4">
                    <div>
                        <h2 class="section-title">Proposal vendor</h2>
                        <p class="mt-0.5 text-xs text-faint">{{ $proposalMetrics['received'] }} proposal diterima · {{ $proposalMetrics['selected'] }} dipilih</p>
                    </div>
                    <x-ui.badge :variant="$proposalMetrics['pending'] > 0 ? 'warning' : 'success'" class="text-[10px]">
                        {{ $proposalMetrics['pending'] }} menunggu evaluasi
                    </x-ui.badge>
                </div>

                @if ($proposals->isEmpty())
                    <div class="p-5">
                        <x-ui.empty-state
                            icon="list"
                            title="Belum ada proposal"
                            description="Proposal dari vendor akan tampil di sini setelah dikirim atau dicatat oleh tim."
                        />
                    </div>
                @else
                    <ul class="divide-y divide-line/60">
                        @foreach ($proposals as $proposal)
                            <li class="flex flex-wrap items-start justify-between gap-3 px-5 py-3.5">
                                <div class="min-w-0 flex-1">
                                    <p class="text-sm font-semibold text-ink">{{ $proposal->title }}</p>
                                    <p class="mt-0.5 text-[11px] text-faint">
                                        {{ $proposal->vendor?->name ?? 'Vendor' }}
                                        · diajukan {{ $proposal->submitted_at?->translatedFormat('d M Y') ?? 'belum' }}
                                        @if ($proposal->valid_until)
                                            · berlaku sampai {{ $proposal->valid_until->translatedFormat('d M Y') }}
                                        @endif
                                    </p>
                                    @if ($canManageCommercial && $proposal->quoted_amount !== null)
                                        <p class="mt-1 font-serif text-sm font-semibold text-ink">{{ $formatMoney($proposal->quoted_amount, $proposal->currency) }}</p>
                                    @endif
                                </div>
                                <x-ui.badge :variant="$proposal->status->badge()" class="shrink-0 text-[10px]">{{ $proposal->status->label() }}</x-ui.badge>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>

            {{-- Engagement --}}
            <div class="card overflow-hidden">
                <div class="flex flex-wrap items-center justify-between gap-2 border-b border-line/70 px-5 py-4">
                    <div>
                        <h2 class="section-title">Engagement berjalan</h2>
                        <p class="mt-0.5 text-xs text-faint">{{ $engagementMetrics['total'] }} engagement · {{ $activeEngagementCount }} aktif</p>
                    </div>
                    @if ($canViewEngagements)
                        <x-ui.button variant="ghost" size="sm" :href="route('workspace.events.vendors.engagements.index', [$organization, $event])" icon="arrow-up-right">Semua engagement</x-ui.button>
                    @endif
                </div>

                @if ($engagements->isEmpty())
                    <div class="p-5">
                        <x-ui.empty-state
                            icon="calendar-check"
                            title="Belum ada engagement"
                            description="Engagement dibuat setelah vendor dipilih dari proposal yang masuk."
                        />
                    </div>
                @else
                    <ul class="divide-y divide-line/60">
                        @foreach ($engagements as $engagement)
                            @php $href = $engagementHref($engagement); @endphp
                            <li class="relative px-5 py-3.5 transition hover:bg-surface-2/40">
                                @if ($href)
                                    <a href="{{ $href }}" class="absolute inset-0 z-0 rounded-xl focus:outline-none focus-visible:ring-2 focus-visible:ring-accent/60" aria-label="Buka engagement {{ $engagement->title }}"></a>
                                @endif

                                <div class="pointer-events-none relative z-10 flex flex-wrap items-center justify-between gap-3">
                                    <div class="min-w-0 flex-1">
                                        <div class="flex items-center gap-2">
                                            <p class="truncate text-sm font-semibold text-ink">{{ $engagement->title }}</p>
                                            @if ($href)
                                                <x-ui.icon name="arrow-up-right" class="h-3.5 w-3.5 shrink-0 text-faint" />
                                            @endif
                                        </div>
                                        <p class="mt-0.5 text-[11px] text-faint">
                                            {{ $engagement->vendor?->name ?? 'Vendor' }}
                                            · {{ $engagement->coordinator?->user?->name ?? 'Belum ada koordinator' }}
                                        </p>
                                        @if ($canManageCommercial && $engagement->agreed_amount !== null)
                                            <p class="mt-1 font-serif text-sm font-semibold text-ink">{{ $formatMoney($engagement->agreed_amount, $engagement->currency) }}</p>
                                        @endif
                                    </div>
                                    <x-ui.badge :variant="$engagement->status->badge()" class="shrink-0 text-[10px]">{{ $engagement->status->label() }}</x-ui.badge>
                                </div>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>

            {{-- Permintaan perubahan --}}
            <div class="card overflow-hidden">
                <div class="flex flex-wrap items-center justify-between gap-2 border-b border-line/70 px-5 py-4">
                    <div>
                        <h2 class="section-title">Permintaan perubahan</h2>
                        <p class="mt-0.5 text-xs text-faint">Perubahan lingkup, jadwal, atau nilai yang diajukan vendor.</p>
                    </div>
                    <x-ui.badge :variant="$changeMetrics['pending'] > 0 ? 'warning' : 'success'" class="text-[10px]">{{ $changeMetrics['pending'] }} menunggu</x-ui.badge>
                </div>

                @if ($changeRequests->isEmpty())
                    <div class="p-5">
                        <x-ui.empty-state
                            icon="refresh"
                            title="Belum ada permintaan perubahan"
                            description="Setiap perubahan dari vendor akan tercatat di sini untuk ditinjau."
                        />
                    </div>
                @else
                    <ul class="divide-y divide-line/60">
                        @foreach ($changeRequests as $changeRequest)
                            @php
                                $proposedChanges = $changeRequest->proposed_changes ?? [];
                                $hasCommercialKeys = array_intersect(array_keys($proposedChanges), ['amount', 'scope', 'schedule']) !== [];
                                $canDecide = $canApproveChanges && $changeRequest->status->isPending() && (! $hasCommercialKeys || $canManageCommercial);
                            @endphp

                            <li class="px-5 py-4">
                                <div class="flex flex-wrap items-start justify-between gap-2">
                                    <div class="min-w-0">
                                        <p class="text-sm font-semibold text-ink">{{ $changeRequest->title }}</p>
                                        <p class="mt-0.5 text-[11px] text-faint">
                                            {{ $changeRequest->engagement?->vendor?->name ?? 'Vendor' }}
                                            · {{ $changeRequest->engagement?->title ?? 'Engagement' }}
                                            · {{ $changeRequest->created_at?->diffForHumans() ?? '—' }}
                                        </p>
                                    </div>
                                    <x-ui.badge :variant="$changeRequest->status->badge()" class="shrink-0 text-[10px]">{{ $changeRequest->status->label() }}</x-ui.badge>
                                </div>

                                @if ($proposedChanges !== [])
                                    <div class="mt-3 flex flex-wrap gap-1.5">
                                        @if (isset($proposedChanges['amount']))
                                            <span class="inline-flex items-center gap-1.5 rounded-full border border-line/70 bg-surface-2/40 px-2 py-0.5 text-[10px] text-muted">
                                                <x-ui.icon name="banknote" class="h-3 w-3 text-faint" />
                                                @if ($canManageCommercial)
                                                    {{ $formatMoney($proposedChanges['amount']) }}
                                                @else
                                                    Perubahan nilai
                                                @endif
                                            </span>
                                        @endif
                                        @if (! empty($proposedChanges['scope']))
                                            <span class="inline-flex items-center gap-1.5 rounded-full border border-line/70 bg-surface-2/40 px-2 py-0.5 text-[10px] text-muted">
                                                <x-ui.icon name="clipboard" class="h-3 w-3 text-faint" />
                                                Lingkup: {{ \Illuminate\Support\Str::limit($proposedChanges['scope'], 60) }}
                                            </span>
                                        @endif
                                        @if (! empty($proposedChanges['schedule']))
                                            <span class="inline-flex items-center gap-1.5 rounded-full border border-line/70 bg-surface-2/40 px-2 py-0.5 text-[10px] text-muted">
                                                <x-ui.icon name="clock" class="h-3 w-3 text-faint" />
                                                Jadwal: {{ \Illuminate\Support\Str::limit($proposedChanges['schedule'], 60) }}
                                            </span>
                                        @endif
                                    </div>
                                @endif

                                @if ($changeRequest->impact)
                                    <p class="mt-2 text-[11px] leading-relaxed text-muted">{{ \Illuminate\Support\Str::limit($changeRequest->impact, 180) }}</p>
                                @endif

                                @if ($canDecide)
                                    <form method="POST" action="{{ route('workspace.events.vendors.change-requests.decision', [$organization, $event, $changeRequest]) }}" class="mt-3 flex flex-wrap items-center gap-2">
                                        @csrf
                                        <input
                                            type="text"
                                            name="decision_notes"
                                            class="input input-sm w-full sm:w-auto sm:flex-1"
                                            placeholder="Catatan keputusan (opsional)"
                                            aria-label="Catatan keputusan"
                                        >
                                        <button type="submit" name="status" value="approved" class="btn btn-primary btn-sm">
                                            <x-ui.icon name="check" class="h-3.5 w-3.5" /> Setujui
                                        </button>
                                        <button type="submit" name="status" value="rejected" class="btn btn-danger btn-sm">
                                            <x-ui.icon name="x" class="h-3.5 w-3.5" /> Tolak
                                        </button>
                                    </form>
                                @elseif ($changeRequest->status->isPending() && $hasCommercialKeys && ! $canManageCommercial)
                                    <p class="mt-3 flex items-start gap-2 rounded-xl border border-warning/30 bg-warning/10 px-3 py-2 text-[11px] leading-relaxed text-warning">
                                        <x-ui.icon name="lock" class="mt-0.5 h-3.5 w-3.5 shrink-0" />
                                        <span>Perubahan ini menyentuh nilai atau lingkup komersial. Keputusan memerlukan akses komersial.</span>
                                    </p>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>
        </div>

        <div class="space-y-5">
            {{-- Deliverable --}}
            <div class="card overflow-hidden">
                <div class="flex flex-wrap items-center justify-between gap-2 border-b border-line/70 px-5 py-4">
                    <div>
                        <h2 class="section-title">Deliverable</h2>
                        <p class="mt-0.5 text-xs text-faint">{{ $openDeliverables->count() }} terbuka · {{ $completedDeliverables }} selesai</p>
                    </div>
                    <x-ui.badge :variant="$deliverableMetrics['overdue'] > 0 ? 'danger' : 'success'" class="text-[10px]">
                        {{ $deliverableMetrics['overdue'] }} terlambat
                    </x-ui.badge>
                </div>

                @if ($deliverableStatusRows->isNotEmpty())
                    <div class="flex flex-wrap gap-1.5 border-b border-line/60 bg-surface-2/30 px-5 py-3">
                        @foreach ($deliverableStatusRows as $row)
                            <span class="badge {{ $row['status']->badge() }} text-[9px]">{{ $row['status']->label() }} {{ $row['count'] }}</span>
                        @endforeach
                    </div>
                @endif

                @if ($openDeliverables->isEmpty())
                    <div class="p-5">
                        <x-ui.empty-state
                            icon="clipboard"
                            title="Tidak ada deliverable terbuka"
                            description="Semua deliverable sudah diterima atau dibatalkan."
                        />
                    </div>
                @else
                    <ul class="max-h-96 divide-y divide-line/60 overflow-y-auto">
                        @foreach ($openDeliverables as $deliverable)
                            @php
                                $href = $engagementHref($deliverable->engagement);
                                $overdue = $deliverable->isOverdue();
                                $dueAt = $toLocal($deliverable->due_at);
                            @endphp
                            <li class="relative px-5 py-3.5 transition hover:bg-surface-2/40 {{ $overdue ? 'border-l-2 border-danger/70 bg-danger/5' : '' }}">
                                @if ($href)
                                    <a href="{{ $href }}" class="absolute inset-0 z-0 rounded-xl focus:outline-none focus-visible:ring-2 focus-visible:ring-accent/60" aria-label="Buka engagement untuk {{ $deliverable->title }}"></a>
                                @endif

                                <div class="pointer-events-none relative z-10 flex items-start justify-between gap-2">
                                    <div class="min-w-0">
                                        <p class="truncate text-xs font-semibold text-ink">{{ $deliverable->title }}</p>
                                        <p class="mt-0.5 text-[10px] text-faint">{{ $deliverable->engagement?->vendor?->name ?? 'Vendor' }}</p>
                                        <p class="mt-1 flex items-center gap-1 text-[10px] {{ $overdue ? 'font-semibold text-danger' : 'text-muted' }}">
                                            <x-ui.icon name="clock" class="h-3 w-3 shrink-0" />
                                            {{ $dueAt?->translatedFormat('d M Y, H:i') ?? 'Tanpa tenggat' }}
                                        </p>
                                    </div>
                                    <x-ui.badge :variant="$deliverable->status->badge()" class="shrink-0 text-[9px]">{{ $deliverable->status->label() }}</x-ui.badge>
                                </div>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>

            {{-- Dokumen menunggu tinjauan --}}
            <div class="card overflow-hidden">
                <div class="flex flex-wrap items-center justify-between gap-2 border-b border-line/70 px-5 py-4">
                    <div>
                        <h2 class="section-title">Dokumen menunggu tinjauan</h2>
                        <p class="mt-0.5 text-xs text-faint">{{ $documentMetrics['approved'] }} disetujui · {{ $documentMetrics['expired'] }} kedaluwarsa</p>
                    </div>
                    <x-ui.badge :variant="$documents->isNotEmpty() ? 'warning' : 'success'" class="text-[10px]">{{ $documents->count() }} dokumen</x-ui.badge>
                </div>

                @if ($documents->isEmpty())
                    <div class="p-5">
                        <x-ui.empty-state
                            icon="file-text"
                            title="Tidak ada dokumen menunggu"
                            description="Dokumen vendor yang perlu ditinjau akan muncul di sini."
                        />
                    </div>
                @else
                    <ul class="divide-y divide-line/60">
                        @foreach ($documents as $document)
                            @php $href = $engagementHref($document->engagement); @endphp
                            <li class="relative px-5 py-3.5 transition hover:bg-surface-2/40">
                                @if ($href)
                                    <a href="{{ $href }}" class="absolute inset-0 z-0 rounded-xl focus:outline-none focus-visible:ring-2 focus-visible:ring-accent/60" aria-label="Buka engagement untuk dokumen {{ $document->title }}"></a>
                                @endif

                                <div class="pointer-events-none relative z-10">
                                    <div class="flex items-start justify-between gap-2">
                                        <p class="min-w-0 truncate text-xs font-semibold text-ink">{{ $document->title }}</p>
                                        <span class="badge {{ $document->type->badge() }} shrink-0 text-[9px]">{{ $document->type->label() }}</span>
                                    </div>
                                    <p class="mt-0.5 truncate text-[10px] text-faint">{{ $document->engagement?->vendor?->name ?? 'Vendor' }} · versi {{ $document->version ?? '—' }}</p>
                                    <p class="mt-1 text-[10px] text-muted">Diunggah {{ $document->created_at?->diffForHumans() ?? '—' }}</p>
                                </div>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>

            {{-- Logistik --}}
            <div class="card overflow-hidden">
                <div class="flex flex-wrap items-center justify-between gap-2 border-b border-line/70 px-5 py-4">
                    <div>
                        <h2 class="section-title">Kebutuhan logistik</h2>
                        <p class="mt-0.5 text-xs text-faint">{{ $logisticsMetrics['open'] }} terbuka dari {{ $logisticsRequirements->count() }} kebutuhan</p>
                    </div>
                </div>

                @if ($openLogistics->isEmpty())
                    <div class="p-5">
                        <x-ui.empty-state
                            icon="pin"
                            title="Tidak ada logistik terbuka"
                            description="Kebutuhan listrik, akses, dan area persiapan vendor akan tampil di sini."
                        />
                    </div>
                @else
                    <ul class="max-h-80 divide-y divide-line/60 overflow-y-auto">
                        @foreach ($openLogistics as $requirement)
                            <li class="px-5 py-3.5">
                                <div class="flex items-start justify-between gap-2">
                                    <div class="min-w-0">
                                        <div class="flex items-center gap-1.5">
                                            <span class="badge {{ $requirement->type->badge() }} text-[9px]">{{ $requirement->type->label() }}</span>
                                            <span class="text-[10px] text-faint">{{ $requirement->responsibility->label() }}</span>
                                        </div>
                                        <p class="mt-1.5 text-xs leading-relaxed text-ink">{{ \Illuminate\Support\Str::limit($requirement->description, 110) }}</p>
                                        <p class="mt-0.5 truncate text-[10px] text-faint">{{ $requirement->engagement?->vendor?->name ?? 'Vendor' }}</p>
                                    </div>
                                    <x-ui.badge :variant="$requirement->status->badge()" class="shrink-0 text-[9px]">{{ $requirement->status->label() }}</x-ui.badge>
                                </div>
                                <p class="mt-1 flex items-center gap-1 text-[10px] text-muted">
                                    <x-ui.icon name="clock" class="h-3 w-3 shrink-0" />
                                    {{ $toLocal($requirement->due_at)?->translatedFormat('d M Y, H:i') ?? 'Tanpa tenggat' }}
                                </p>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>

            {{-- Masalah --}}
            <div class="card overflow-hidden">
                <div class="flex flex-wrap items-center justify-between gap-2 border-b border-line/70 px-5 py-4">
                    <div>
                        <h2 class="section-title">Masalah vendor</h2>
                        <p class="mt-0.5 text-xs text-faint">{{ $openIssues->count() }} terbuka dari {{ $issues->count() }} catatan</p>
                    </div>
                    <x-ui.badge :variant="$issueMetrics['open'] > 0 ? 'danger' : 'success'" class="text-[10px]">{{ $issueMetrics['open'] }} terbuka</x-ui.badge>
                </div>

                @if ($severityRows->isNotEmpty())
                    <div class="flex flex-wrap gap-1.5 border-b border-line/60 bg-surface-2/30 px-5 py-3">
                        @foreach ($severityRows as $row)
                            <span class="badge {{ $row['severity']->badge() }} text-[9px]">{{ $row['severity']->label() }} {{ $row['count'] }}</span>
                        @endforeach
                    </div>
                @endif

                @if ($openIssues->isEmpty())
                    <div class="p-5">
                        <x-ui.empty-state
                            icon="alert"
                            title="Tidak ada masalah terbuka"
                            description="Masalah yang dilaporkan untuk vendor acara ini akan tampil di sini."
                        />
                    </div>
                @else
                    <ul class="max-h-80 divide-y divide-line/60 overflow-y-auto">
                        @foreach ($openIssues as $issue)
                            <li class="px-5 py-3.5">
                                <div class="flex items-start justify-between gap-2">
                                    <p class="min-w-0 text-xs font-semibold leading-snug text-ink">{{ $issue->title }}</p>
                                    <x-ui.badge :variant="$issue->severity->badge()" class="shrink-0 text-[9px]">{{ $issue->severity->label() }}</x-ui.badge>
                                </div>
                                <p class="mt-0.5 truncate text-[10px] text-faint">{{ $issue->engagement?->vendor?->name ?? 'Vendor' }}</p>
                                <div class="mt-1.5 flex items-center justify-between gap-2">
                                    <span class="text-[10px] text-muted">{{ $issue->created_at?->diffForHumans() ?? '—' }}</span>
                                    <x-ui.badge :variant="$issue->status->badge()" class="text-[9px]">{{ $issue->status->label() }}</x-ui.badge>
                                </div>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>

            {{-- Tautan cepat --}}
            <div class="card p-5">
                <h2 class="section-title">Ruang kerja vendor</h2>
                <div class="mt-3 grid gap-2">
                    @if ($canViewEngagements)
                        <a href="{{ route('workspace.events.vendors.engagements.index', [$organization, $event]) }}" class="group flex items-center gap-3 rounded-xl border border-line/60 px-3 py-2.5 transition hover:border-accent/40 hover:bg-surface-2/40">
                            <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-teal-soft text-ink"><x-ui.icon name="calendar-check" class="h-4 w-4" /></span>
                            <span class="min-w-0 flex-1">
                                <span class="block text-xs font-semibold text-ink">Daftar engagement</span>
                                <span class="block text-[10px] text-faint">{{ $engagementMetrics['total'] }} engagement · {{ $activeEngagementCount }} aktif</span>
                            </span>
                            <x-ui.icon name="chevron-right" class="h-3.5 w-3.5 text-faint transition group-hover:text-accent" />
                        </a>
                    @endif

                    @if ($canViewReports)
                        <a href="{{ route('workspace.events.vendors.reports.index', [$organization, $event]) }}" class="group flex items-center gap-3 rounded-xl border border-line/60 px-3 py-2.5 transition hover:border-accent/40 hover:bg-surface-2/40">
                            <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-surface-3 text-ink"><x-ui.icon name="chart" class="h-4 w-4" /></span>
                            <span class="min-w-0 flex-1">
                                <span class="block text-xs font-semibold text-ink">Laporan vendor</span>
                                <span class="block text-[10px] text-faint">Engagement, proposal, kinerja</span>
                            </span>
                            <x-ui.icon name="chevron-right" class="h-3.5 w-3.5 text-faint transition group-hover:text-accent" />
                        </a>
                    @endif

                    @if ($canViewDirectory)
                        <a href="{{ route('workspace.vendors.index', $organization) }}" class="group flex items-center gap-3 rounded-xl border border-line/60 px-3 py-2.5 transition hover:border-accent/40 hover:bg-surface-2/40">
                            <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-accent/15 text-accent"><x-ui.icon name="archive" class="h-4 w-4" /></span>
                            <span class="min-w-0 flex-1">
                                <span class="block text-xs font-semibold text-ink">Direktori vendor</span>
                                <span class="block text-[10px] text-faint">{{ $vendorMetrics['total'] }} vendor organisasi</span>
                            </span>
                            <x-ui.icon name="chevron-right" class="h-3.5 w-3.5 text-faint transition group-hover:text-accent" />
                        </a>
                    @endif
                </div>

                @if ($canManagePortal)
                    <p class="mt-4 rounded-xl border border-line/60 bg-surface-2/40 px-3 py-2 text-[11px] leading-relaxed text-muted">
                        Akses portal vendor dikelola per engagement melalui halaman engagement masing-masing.
                    </p>
                @endif
            </div>
        </div>
    </div>
@endsection