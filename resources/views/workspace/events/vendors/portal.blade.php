@extends('layouts.owner')

@section('content')
<div class="container mx-auto px-4 py-8">
    <x-ui.page-header
        title="Portal Vendor"
        eyebrow="Vendor"
        :subtitle="'Kelola akses portal vendor untuk engagement '.$engagement->title.'.'"
    >
        <x-ui.button variant="secondary" :href="route('workspace.events.vendors.engagements.show', [$organization, $event, $engagement])" icon="arrow-left">Kembali</x-ui.button>
    </x-ui.page-header>

    <div class="mt-5 grid gap-5 xl:grid-cols-3">
        <!-- Invitation form -->
        <div class="xl:col-span-2">
            <div class="card p-4 rounded-2xl border border-line/70 bg-surface">
                <h2 class="text-base font-semibold text-ink mb-3">Undangan</h2>
                <form method="POST" action="{{ route('workspace.events.vendors.portal.invite', [$organization, $event, $engagement]) }}" class="mt-3">
                    <div class="grid gap-3 sm:grid-cols-2">
                        <div>
                            <label class="block text-sm font-semibold text-ink">Email</label>
                            <input type="email" name="email" class="input input-sm rounded-xl border border-line/50 px-3 py-2 text-sm text-ink" placeholder="email@domain.com" required>
                        </div>
                        <div>
                            <label class="block text-sm font-semibold text-ink">Kontak Vendor</label>
                            <select name="vendor_contact_id" class="input input-sm rounded-xl border border-line/50 px-3 py-2 text-sm text-ink">
                                <option value="">Pilih kontak</option>
                                @foreach ($engagement->vendor->contacts()->where('is_active', true)->get() as $contact)
                                    <option value="{{ $contact->id }}">{{ $contact->name }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <button type="submit" class="btn btn-primary btn-sm mt-3 w-full">
                        <x-ui.icon name="send" class="h-3.5 w-3.5" /> Buat Undangan
                    </button>
                </form>
            </div>

            <!-- Invitation link -->
            @if ($invitationUrl)
                <div class="mt-5 card p-4 rounded-2xl border border-line/70 bg-surface">
                    <h2 class="text-base font-semibold text-ink mb-3">Tautan Undangan</h2>
                    <div class="flex items-center gap-2">
                        <input type="text" value="{{ $invitationUrl }}" class="input input-sm rounded-xl border border-line/50 px-3 py-2 text-sm text-ink" readonly>
                        <button type="button" class="icon-btn h-9 w-9 !rounded-lg" onclick="navigator.clipboard.writeText('{{ $invitationUrl }}')">
                            <x-ui.icon name="copy" class="h-4 w-4" />
                        </button>
                    </div>
                    <p class="text-[10px] text-muted mt-2">Tautan ini berlaku satu kali dan kedaluwarsa dalam 7 hari.</p>
                </div>
            @endif

            <!-- Memberships -->
            <div class="mt-5 card p-4 rounded-2xl border border-line/70 bg-surface">
                <h2 class="text-base font-semibold text-ink mb-3">Keanggotaan</h2>
                <ul class="divide-y divide-line/60">
                    @foreach ($memberships as $membership)
                        <li class="px-3 py-2">
                            <p class="text-sm font-medium text-ink">{{ $membership->user?->email ?? $membership->invited_email }}</p>
                            <p class="text-[10px] text-muted">
                                Status: <span class="badge {{ $membership->status->badge() }} text-[9px]">{{ $membership->status->label() }}</span>
                            </p>
                            <div class="mt-2 flex items-center gap-2">
                                <form method="POST" action="{{ route('workspace.events.vendors.portal.grant', [$organization, $event, $membership]) }}" class="inline">
                                    <input type="hidden" name="event_vendor_engagement_id" value="{{ $engagement->id }}">
                                    <button type="submit" class="btn btn-primary btn-sm w-full">
                                        <x-ui.icon name="check" class="h-3.5 w-3.5" /> Beri Akses
                                    </button>
                                </form>
                                <form method="POST" action="{{ route('workspace.events.vendors.portal.revoke', [$organization, $event, $membership]) }}" class="inline">
                                    <button type="submit" class="btn btn-danger btn-sm w-full">
                                        <x-ui.icon name="x" class="h-3.5 w-3.5" /> Cabut
                                    </button>
                                </form>
                            </div>
                        </li>
                    @endforeach
                </ul>
            </div>
        </div>
    </div>
</div>