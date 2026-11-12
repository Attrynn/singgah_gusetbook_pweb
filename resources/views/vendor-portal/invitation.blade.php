@extends('layouts.vendor-portal')

@section('content')
<div class="container mx-auto px-4 py-8">
    <h1 class="text-2xl font-semibold text-ink mb-6">Undangan Portal Vendor</h1>

    @if(isset($membership))
        <div class="mb-6 card p-4 rounded-2xl border border-line/70 bg-surface">
            <p class="text-sm text-muted">Vendor: {{ $membership->vendor->name }}</p>
            @if($membership->vendorContact)
                <p class="text-[10px] text-muted mt-1">Kontak: {{ $membership->vendorContact->email }}</p>
            @endif
            <p class="text-[10px] text-muted mt-1">Email: {{ $membership->invited_email }}</p>
            @if($membership->invitation_expires_at)
                <p class="text-[10px] text-muted mt-1">Berakhir: {{ $membership->invitation_expires_at->translatedFormat('d M Y, H:i') }}</p>
            @endif
        </div>
    @endif

    @if(isset($expired) && $expired)
        <div class="mb-6 card p-4 rounded-2xl border border-line/70 bg-surface">
            <p class="text-sm text-muted">Undangan ini sudah kedaluwarsa.</p>
        </div>
    @elseif(isset($emailMatches) && $emailMatches)
        <div class="mb-6 card p-4 rounded-2xl border border-line/70 bg-surface">
            <p class="text-sm text-muted">Undangan ini ditujukan untuk alamat email Anda.</p>
        </div>
    @else
        <div class="mb-6 card p-4 rounded-2xl border border-line/70 bg-surface">
            <p class="text-sm text-muted">Undangan ini ditujukan untuk alamat email lain.</p>
        </div>
    @endif

    @if(isset($token) && !$expired && $emailMatches)
        <form method="POST" action="{{ route('vendor-portal.invitations.accept', $token) }}" class="mt-6">
            @csrf
            <button type="submit" class="btn btn-primary btn-sm w-full">
                <x-ui.icon name="check" class="h-3.5 w-3.5" /> Terima Undangan
            </button>
        </form>
    @endif
</div>
@endsection