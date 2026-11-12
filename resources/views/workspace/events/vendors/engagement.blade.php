@extends('layouts.owner')

@section('content')
<div class="container mx-auto px-4 py-8">
    <x-ui.page-header
        title="{{ $engagement->title }}"
        eyebrow="Vendor"
        :subtitle="'Engagement untuk '.$engagement->event->name.'.'"
    >
        <x-ui.badge :variant="$engagement->status->badge()" class="text-sm">
            {{ $engagement->status->label() }}
        </x-ui.badge>
    </x-ui.page-header>

    <div class="mt-5 grid gap-5 xl:grid-cols-3">
        <!-- Header -->
        <div class="col-span-2">
            <div class="flex items-center justify-between gap-4">
                <div>
                    <p class="text-sm font-semibold text-ink">Status: <span class="badge {{ $engagement->status->badge() }}">{{ $engagement->status->label() }}</span></p>
                    <p class="text-[10px] text-muted">
                        {{ $engagement->vendor?->name ?? 'Vendor' }} · 
                        {{ $engagement->coordinator?->user?->name ?? 'Belum ada koordinator' }}
                    </p>
                    @if ($engagement->agreed_amount !== null)
                        <p class="mt-1 font-serif text-sm font-semibold text-ink">
                            Rp{{ number_format($engagement->agreed_amount, 0, ',', '.') }}
                            @if(strtoupper($engagement->currency ?? 'IDR') !== 'IDR')
                                <span class="ml-1 text-[10px] font-sans font-medium uppercase tracking-[0.08em] text-faint">{{ $engagement->currency ?? 'IDR' }}</span>
                            @endif
                        </p>
                    @endif
                </div>
            </div>
        </div>

        <!-- Actions -->
        <div class="col-span-1">
            <div class="mt-4">
                <form method="POST" action="{{ route('workspace.events.vendors.engagements.status', [$organization, $event, $engagement]) }}" class="mb-3">
                    <div class="flex items-center gap-2">
                        <select name="status" class="input input-sm rounded-xl border border-line/50 px-3 py-2 text-sm text-ink" required>
                            <option value="">Pilih status</option>
                            @foreach (VendorEngagementStatus::cases() as $status)
                                <option value="{{ $status->value }}" @selected($engagement->status->value === $status->value)>
                                    {{ $status->label() }}
                                </option>
                            @endforeach
                        </select>
                        <input type="hidden" name="reason" value="{{ request('reason') ?? '' }}">
                        <button type="submit" class="btn btn-primary btn-sm">
                            <x-ui.icon name="check" class="h-3.5 w-3.5" /> Ubah Status
                        </button>
                    </div>
                </form>

                <form method="POST" action="{{ route('workspace.events.vendors.engagements.cancel', [$organization, $event, $engagement]) }}" class="mb-3">
                    <div class="flex items-center gap-2">
                        <input type="text" name="reason" class="input input-sm w-full rounded-xl border border-line/50 px-3 py-2 text-sm text-ink" placeholder="Alasan pembatalan" required>
                        <button type="submit" class="btn btn-danger btn-sm">
                            <x-ui.icon name="x" class="h-3.5 w-3.5" /> Batalkan
                        </button>
                    </div>
                </form>

                <form method="POST" action="{{ route('workspace.events.vendors.engagements.close', [$organization, $event, $engagement]) }}" class="mb-3">
                    <div class="flex items-center gap-2">
                        <input type="text" name="closure_notes" class="input input-sm w-full rounded-xl border border-line/50 px-3 py-2 text-sm text-ink" placeholder="Catatan penutupan" placeholder="Catatan penutupan (opsional)">
                        <button type="submit" class="btn btn-danger btn-sm">
                            <x-ui.icon name="check" class="h-3.5 w-3.5" /> Tutup
                        </button>
                    </div>
                </form>

                <a href="{{ route('workspace.events.vendors.portal.index', [$organization, $event, $engagement]) }}" class="btn btn-ghost btn-sm mt-3">
                    <x-ui.icon name="share" class="h-3.5 w-3.5" /> Portal Vendor
                </a>
            </div>
        </div>
    </div>

    <!-- Commercial summary (gated) -->
    @if ($canViewCommercial)
        <div class="mt-5 card p-4 rounded-2xl border border-line/70 bg-surface">
            <h2 class="text-base font-semibold text-ink mb-3">Ringkasan Kesepakatan</h2>
            <p class="text-sm text-muted mb-2">Nilai Kesepakatan: Rp{{ number_format($engagement->agreed_amount, 0, ',', '.') }}</p>
            <p class="text-sm text-muted mb-2">Lingkup: {{ $engagement->scope_snapshot['description'] ?? '—' }}</p>
            <p class="text-sm text-muted mb-2">Jaminan: {{ $engagement->agreed_amount ?? '—' }}</p>
        </div>
    @endif

    <!-- Readiness -->
    @if ($readiness['blocking'] || $readiness['warnings'])
        <div class="mt-5 card p-4 rounded-2xl border border-line/70 bg-surface">
            <h2 class="text-base font-semibold text-ink mb-3">Kesiapan</h2>
            @if ($readiness['blocking'])
                <p class="text-sm text-danger">Blocking: {{ implode(', ', $readiness['blocking']) }}</p>
            @endif
            @if ($readiness['warnings'])
                <p class="text-sm text-warning">Warning: {{ implode(', ', $readiness['warnings']) }}</p>
            @endif
        </div>
    @endif

    <!-- Assignments -->
    @if ($canManage)
        <div class="mt-5 card p-4 rounded-2xl border border-line/70 bg-surface">
            <h2 class="text-base font-semibold text-ink mb-3">Assignments</h2>
            <form method="POST" action="{{ route('workspace.events.vendors.engagements.assignments', [$organization, $event, $engagement]) }}" class="mt-3">
                <div class="grid gap-3 sm:grid-cols-2">
                    <div>
                        <label class="block text-sm font-semibold text-ink">Koordinator</label>
                        <select name="coordinator_membership_id" class="input input-sm rounded-xl border border-line/50 px-3 py-2 text-sm text-ink">
                            <option value="">Pilih koordinator</option>
                            @foreach ($staff as $membership)
                                <option value="{{ $membership->id }}" @selected($engagement->coordinator_membership_id == $membership->id)>
                                    {{ $membership->user->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-ink">Kontak Vendor</label>
                        <select name="vendor_contact_id" class="input input-sm rounded-xl border border-line/50 px-3 py-2 text-sm text-ink">
                            <option value="">Pilih kontak</option>
                            @foreach ($vendorContacts as $contact)
                                <option value="{{ $contact->id }}" @selected($engagement->vendor_contact_id == $contact->id)>
                                    {{ $contact->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <button type="submit" class="btn btn-primary btn-sm mt-3 w-full">
                    <x-ui.icon name="assign" class="h-3.5 w-3.5" /> Assign
                </button>
            </form>
        </div>
    @endif

    <!-- Documents -->
    @if ($canManageAgreements)
        <div class="mt-5 card p-4 rounded-2xl border border-line/70 bg-surface">
            <h2 class="text-base font-semibold text-ink mb-3">Dokumen</h2>
            <ul class="divide-y divide-line/60">
                @foreach ($engagement->documents as $document)
                    <li class="px-3 py-2">
                        <p class="text-sm font-medium text-ink">{{ $document->title }}</p>
                        <p class="text-[10px] text-muted">
                            {{ $document->visibility->label() }} · Versi {{ $document->version ?? '—' }}
                        </p>
                        <a href="{{ route('workspace.events.vendors.documents.download', [$organization, $event, $document]) }}" class="text-accent hover:underline mt-1 block">Unduh</a>
                    </li>
                @endforeach
            </ul>
        </div>
    @endif

    <!-- Deliverables -->
    @if ($canReviewDeliverables)
        <div class="mt-5 card p-4 rounded-2xl border border-line/70 bg-surface">
            <h2 class="text-base font-semibold text-ink mb-3">Deliverable</h2>
            <table class="w-full">
                <thead>
                    <tr>
                        <th class="text-left">Judul</th>
                        <th class="text-left">Status</th>
                        <th class="text-left">Tenggat</th>
                        <th class="text-left">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($engagement->deliverables as $deliverable)
                        <tr class="border-t border-line/60">
                            <td class="text-left">{{ $deliverable->title }}</td>
                            <td class="text-left">{{ $deliverable->status->label() }}</td>
                            <td class="text-left">{{ $deliverable->due_at?->translatedFormat('d M Y, H:i') ?? '—' }}</td>
                            <td class="text-left">
                                <a href="{{ route('workspace.events.vendors.deliverables.show', [$organization, $event, $deliverable]) }}" class="btn btn-ghost btn-sm">
                                    Lihat
                                </a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>
@endsection