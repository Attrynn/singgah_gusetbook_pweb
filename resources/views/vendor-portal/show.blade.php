@extends('layouts.vendor-portal')

@section('content')
<div class="container mx-auto px-4 py-8">
    <h1 class="text-2xl font-semibold text-ink mb-6">Detail Engagement</h1>

    @if(isset($membership))
        <div class="mb-6 card p-4 rounded-2xl border border-line/70 bg-surface">
            <p class="text-sm text-muted">Vendor: {{ $membership->vendor->name }}</p>
            @if($membership->vendorContact)
                <p class="text-[10px] text-muted mt-1">Kontak: {{ $membership->vendorContact->email }}</p>
            @endif
        </div>
    @endif

    @if(isset($engagement))
        <!-- Service/Package/Scope Summary -->
        <div class="mb-6 card p-4 rounded-2xl border border-line/70 bg-surface">
            <h2 class="text-base font-semibold text-ink mb-3">Ringkasan Layanan</h2>
            <p class="text-muted mb-2">{{ $engagement->vendor->name }} · {{ $engagement->event->name }}</p>

            @if($engagement->service)
                <p class="text-sm text-muted mb-2"><strong>Layanan:</strong> {{ $engagement->service->name }}</p>
            @endif

            @if($engagement->package)
                <p class="text-sm text-muted mb-2"><strong>Paket:</strong> {{ $engagement->package->name }}</p>
            @endif

            @if($engagement->scope_snapshot)
                <p class="text-sm text-muted mb-2"><strong>Lingkup:</strong> {{ $engagement->scope_snapshot['description'] ?? '—' }}</p>
            @endif

            <!-- Agreed amount only -->
            @if($engagement->agreed_amount !== null)
                <p class="mt-3 font-serif text-lg font-semibold text-ink">
                    Rp{{ number_format($engagement->agreed_amount, 0, ',', '.') }}
                    @if(strtoupper($engagement->currency ?? 'IDR') !== 'IDR')
                        <span class="ml-1 text-[10px] font-sans font-medium uppercase tracking-[0.08em] text-faint">{{ $engagement->currency ?? 'IDR' }}</span>
                    @endif
                </p>
            @endif
        </div>

        <!-- Deliverables -->
        <div class="mb-6 card p-4 rounded-2xl border border-line/70 bg-surface">
            <h2 class="text-base font-semibold text-ink mb-3">Deliverable</h2>

            @if(isset($engagement->deliverables) && $engagement->deliverables->isNotEmpty())
                <ul class="divide-y divide-line/60">
                    @foreach($engagement->deliverables as $deliverable)
                        <li class="px-3 py-2">
                            <p class="text-sm font-semibold text-ink">{{ $deliverable->title }}</p>
                            <p class="text-[10px] text-muted">
                                Status: <span class="badge {{ $deliverable->status->badge() }} text-[9px]">{{ $deliverable->status->label() }}</span>
                            </p>
                            @if($deliverable->due_at)
                                <p class="text-[10px] text-muted mt-1">Tenggat: {{ $deliverable->due_at->translatedFormat('d M Y, H:i') }}</p>
                            @endif

                            <!-- Submit form -->
                            <form method="POST" action="{{ route('vendor-portal.deliverables.submit', [$engagement, $deliverable]) }}" class="mt-3" enctype="multipart/form-data">
                                @csrf
                                <div class="mt-2">
                                    <textarea name="notes" rows="2" class="input input-sm w-full rounded-xl border border-line/50 px-3 py-2 text-sm text-ink resize-y" placeholder="Catatan pengiriman (opsional)"></textarea>
                                </div>
                                <div class="mt-2">
                                    <input type="file" name="file" class="input input-sm w-full rounded-xl border border-line/50 px-3 py-2 text-sm text-ink" accept=".pdf,.doc,.docx,.xls,.xlsx,.jpg,.jpeg,.png">
                                </div>
                                <button type="submit" class="btn btn-primary btn-sm mt-2 w-full">
                                    <x-ui.icon name="upload" class="h-3.5 w-3.5" /> Serahkan
                                </button>
                            </form>
                        </li>
                    @endforeach
                </ul>
            @else
                <p class="text-muted">Belum ada deliverable untuk engagement ini.</p>
            @endif
        </div>

        <!-- Document download links -->
        @if(isset($engagement->documents) && $engagement->documents->isNotEmpty())
            <div class="mt-6 card p-4 rounded-2xl border border-line/70 bg-surface">
                <h2 class="text-base font-semibold text-ink mb-3">Dokumen</h2>
                <ul class="divide-y divide-line/60">
                    @foreach($engagement->documents as $document)
                        @if(in_array($document->visibility, ['vendor_visible', 'shared']))
                            <li class="px-3 py-2">
                                <p class="text-sm font-medium text-ink">{{ $document->title }}</p>
                                <p class="text-[10px] text-muted">
                                    Versi: {{ $document->version ?? '—' }} · <span class="badge {{ $document->visibility->badge() }} text-[9px]">{{ $document->visibility->label() }}</span>
                                </p>
                                <a href="{{ route('vendor-portal.documents.download', $document) }}" class="text-accent hover:underline mt-1 block">Unduh</a>
                            </li>
                        @endif
                    @endforeach
                </ul>
            </div>
        @endif

        <!-- Schedule confirm -->
        @if(isset($engagement->scheduleReferences) && $engagement->scheduleReferences->isNotEmpty())
            <div class="mt-6 card p-4 rounded-2xl border border-line/70 bg-surface">
                <h2 class="text-base font-semibold text-ink mb-3">Jadwal</h2>
                <ul class="divide-y divide-line/60">
                    @foreach($engagement->scheduleReferences as $reference)
                        <li class="px-3 py-2">
                            <p class="text-sm font-medium text-ink">{{ $reference->runOfShowItem?->title ?? 'Jadwal' }}</p>
                            <p class="text-[10px] text-muted">
                                Status: <span class="badge {{ $reference->arrival_status->badge() }} text-[9px]">{{ $reference->arrival_status->label() }}</span>
                            </p>
                            @if($reference->expected_arrival_at)
                                <p class="text-[10px] text-muted mt-1">Diharapkan: {{ $reference->expected_arrival_at->translatedFormat('d M Y, H:i') }}</p>
                            @endif
                            <form method="POST" action="{{ route('vendor-portal.schedule.confirm', $reference) }}" class="mt-2">
                                @csrf
                                <button type="submit" class="btn btn-primary btn-sm w-full">
                                    <x-ui.icon name="check" class="h-3.5 w-3.5" /> Konfirmasi
                                </button>
                            </form>
                        </li>
                    @endforeach
                </ul>
            </div>
        @endif

        <!-- Logistics confirm -->
        @if(isset($engagement->logisticsRequirements) && $engagement->logisticsRequirements->isNotEmpty())
            <div class="mt-6 card p-4 rounded-2xl border border-line/70 bg-surface">
                <h2 class="text-base font-semibold text-ink mb-3">Logistik</h2>
                <ul class="divide-y divide-line/60">
                    @foreach($engagement->logisticsRequirements as $requirement)
                        <li class="px-3 py-2">
                            <p class="text-sm font-medium text-ink">{{ $requirement->type->label() }}: {{ $requirement->description }}</p>
                            <p class="text-[10px] text-muted">
                                Status: <span class="badge {{ $requirement->status->badge() }} text-[9px]">{{ $requirement->status->label() }}</span>
                            </p>
                            <form method="POST" action="{{ route('vendor-portal.logistics.confirm', $requirement) }}" class="mt-2">
                                @csrf
                                <button type="submit" class="btn btn-primary btn-sm w-full">
                                    <x-ui.icon name="check" class="h-3.5 w-3.5" /> Konfirmasi
                                </button>
                            </form>
                        </li>
                    @endforeach
                </ul>
            </div>
        @endif

        <!-- Communications timeline -->
        @if(isset($communications) && $communications->isNotEmpty())
            <div class="mt-6 card p-4 rounded-2xl border border-line/70 bg-surface">
                <h2 class="text-base font-semibold text-ink mb-3">Riwayat Komunikasi</h2>
                <ul class="divide-y divide-line/60">
                    @foreach($communications as $communication)
                        <li class="px-3 py-2">
                            <p class="text-sm text-ink">{{ $communication->body }}</p>
                            <p class="text-[10px] text-muted mt-1">
                                {{ $communication->type->label() }} · {{ $communication->created_at->translatedFormat('d M Y, H:i') }}
                            </p>
                        </li>
                    @endforeach
                </ul>
            </div>
        @endif

        <!-- Communications form -->
        <div class="mt-6 card p-4 rounded-2xl border border-line/70 bg-surface">
            <h2 class="text-base font-semibold text-ink mb-3">Komunikasi</h2>
            <form method="POST" action="{{ route('vendor-portal.communications.store', $engagement) }}" class="mt-3">
                @csrf
                <div class="grid gap-3 sm:grid-cols-2 mb-3">
                    <select name="type" class="input input-sm rounded-xl border border-line/50 px-3 py-2 text-sm text-ink" required>
                        <option value="">Pilih jenis komunikasi</option>
                        <option value="clarification">Klarifikasi</option>
                        <option value="service_confirmation">Konfirmasi Layanan</option>
                        <option value="schedule_confirmation">Konfirmasi Jadwal</option>
                        <option value="deliverable_update">Pembaruan Deliverable</option>
                        <option value="logistics_update">Pembaruan Logistik</option>
                        <option value="change_discussion">Diskusi Perubahan</option>
                    </select>

                    <textarea name="body" rows="3" class="input input-sm w-full rounded-xl border border-line/50 px-3 py-2 text-sm text-ink resize-y" placeholder="Isi pesan" required></textarea>
                </div>
                <button type="submit" class="btn btn-primary btn-sm w-full">
                    <x-ui.icon name="send" class="h-3.5 w-3.5" /> Kirim
                </button>
            </form>
        </div>

        <!-- Change request form -->
        <div class="mt-6 card p-4 rounded-2xl border border-line/70 bg-surface">
            <h2 class="text-base font-semibold text-ink mb-3">Permintaan Perubahan</h2>
            <form method="POST" action="{{ route('vendor-portal.change-requests.store', $engagement) }}" class="mt-3">
                @csrf
                <div class="grid gap-3 sm:grid-cols-2 mb-3">
                    <input type="text" name="title" class="input input-sm rounded-xl border border-line/50 px-3 py-2 text-sm text-ink" placeholder="Judul" required>
                    <textarea name="description" rows="3" class="input input-sm w-full rounded-xl border border-line/50 px-3 py-2 text-sm text-ink resize-y" placeholder="Deskripsi" required></textarea>
                </div>
                <div class="grid gap-3 sm:grid-cols-2 mb-3">
                    <textarea name="impact" rows="2" class="input input-sm w-full rounded-xl border border-line/50 px-3 py-2 text-sm text-ink resize-y" placeholder="Dampak (opsional)"></textarea>
                    <input type="number" name="proposed_changes[amount]" step="0.01" class="input input-sm rounded-xl border border-line/50 px-3 py-2 text-sm text-ink" placeholder="Nilai yang diusulkan (opsional)">
                </div>
                <div class="grid gap-3 sm:grid-cols-2 mb-3">
                    <input type="text" name="proposed_changes[scope]" class="input input-sm rounded-xl border border-line/50 px-3 py-2 text-sm text-ink" placeholder="Lingkup yang diusulkan (opsional)">
                    <input type="text" name="proposed_changes[schedule]" class="input input-sm rounded-xl border border-line/50 px-3 py-2 text-sm text-ink" placeholder="Jadwal yang diusulkan (opsional)">
                </div>
                <button type="submit" class="btn btn-primary btn-sm w-full">
                    <x-ui.icon name="send" class="h-3.5 w-3.5" /> Ajukan
                </button>
            </form>
        </div>
    @endif
</div>
@endsection