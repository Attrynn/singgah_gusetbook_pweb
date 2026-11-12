@extends('layouts.vendor-portal')

@section('title', 'Portal Vendor')

@section('content')
<div class="space-y-9">
    <x-ui.page-header
        title="Portal Vendor"
        eyebrow="Ruang kerja vendor"
        subtitle="Lihat acara yang Anda tangani, konfirmasi jadwal, dan kirim pembaruan dari satu tempat."
    />

    {{-- Engagement list --}}
    <section>
        <div class="flex flex-wrap items-end justify-between gap-2">
            <div>
                <h2 class="section-title">Engagement Anda</h2>
                <p class="mt-0.5 text-xs text-faint">Hanya acara yang telah membagikan akses kepada Anda yang tampil di sini.</p>
            </div>
            @if ($engagements->total() > 0)
                <span class="text-[11px] font-semibold text-muted">{{ $engagements->total() }} engagement</span>
            @endif
        </div>

        @if ($engagements->isEmpty())
            <div class="mt-4">
                <x-ui.empty-state
                    icon="calendar-check"
                    title="Belum ada engagement"
                    description="Engagement akan muncul di sini setelah penyelenggara membagikan akses untuk acara tertentu."
                />
            </div>
        @else
            <div class="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($engagements as $engagement)
                    <a href="{{ route('vendor-portal.engagements.show', $engagement) }}"
                       class="card group flex flex-col p-5 transition duration-200 hover:-translate-y-0.5 hover:border-accent/40 hover:shadow-raised focus:outline-none focus-visible:ring-2 focus-visible:ring-accent/40">
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <p class="text-[10px] font-semibold uppercase tracking-[0.14em] text-faint">
                                    {{ $engagement->event?->name ?? 'Acara' }}
                                </p>
                                <p class="mt-1 truncate text-sm font-semibold text-ink transition group-hover:text-accent">
                                    {{ $engagement->title }}
                                </p>
                            </div>
                            <span class="badge {{ $engagement->status->badge() }} shrink-0 text-[9px]">
                                {{ $engagement->status->label() }}
                            </span>
                        </div>

                        <p class="mt-3 text-[11px] text-muted">{{ $engagement->vendor?->name ?? 'Vendor' }}</p>

                        <span class="mt-4 inline-flex items-center gap-1 text-[11px] font-semibold text-accent">
                            Buka engagement
                            <x-ui.icon name="chevron-right" class="h-3.5 w-3.5 transition group-hover:translate-x-0.5" />
                        </span>
                    </a>
                @endforeach
            </div>

            @include('owner.partials.pagination', ['paginator' => $engagements])
        @endif
    </section>

    {{-- Vendor access --}}
    @if ($memberships->isNotEmpty())
        <section>
            <h2 class="section-title">Akses vendor</h2>
            <p class="mt-0.5 text-xs text-faint">Akun vendor yang terhubung dengan portal ini.</p>

            <div class="mt-3 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($memberships as $membership)
                    <div class="card flex items-center gap-3 p-4">
                        <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-accent/12 text-accent">
                            <x-ui.icon name="archive" class="h-4 w-4" />
                        </span>
                        <div class="min-w-0 flex-1">
                            <p class="truncate text-sm font-semibold text-ink">{{ $membership->vendor->name }}</p>
                            <p class="truncate text-[10px] text-muted">
                                {{ $membership->vendorContact?->email ?? $membership->invited_email }}
                            </p>
                        </div>
                        <span class="badge {{ $membership->status->badge() }} shrink-0 text-[9px]">
                            {{ $membership->status->label() }}
                        </span>
                    </div>
                @endforeach
            </div>
        </section>
    @endif
</div>
@endsection
