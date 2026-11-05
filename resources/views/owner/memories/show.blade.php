@extends('layouts.owner')

@section('title', 'Kenangan: {{ $asset->original_name }}')

@section('content')
    <x-ui.page-header title="Kenangan" subtitle="Foto besar dan detail kenangan.">
        <a href="{{ route('owner.memories.index') }}" class="link text-sm flex items-center gap-1">
            <x-ui.icon name="chevron-left" class="h-4 w-4" />
            Kembali ke kenangan
        </a>
    </x-ui.page-header>

    <div class="card p-5 mb-5">
        <img src="{{ $asset->url() }}" alt="{{ $asset->original_name }}" class="w-full h-64 object-cover rounded-3xl shadow-raised" loading="lazy" />
        <div class="mt-4">
            <p class="text-sm text-faint">{{ $asset->caption ?? $asset->original_name }}</p>
            <p class="mt-1 text-[10px] text-faint">
                {{ $asset->guest?->name ?? 'Tamu' }} · {{ $asset->created_at?->translatedFormat('d M Y') }}
            </p>
        </div>
    </div>

    <div class="card p-5">
        <div class="flex items-center justify-between gap-3">
            <div>
                <p class="section-kicker">Uploader</p>
                <div class="flex items-center gap-2">
                    <x-ui.avatar :name="$asset->uploader?->name" :src="$asset->uploader?->avatar_path ? Illuminate\Support\Facades\Storage::disk('public')->url($asset->uploader?->avatar_path) : null" size="sm" />
                    <p class="font-semibold text-sm text-ink">{{ $asset->uploader?->name ?? '-' }}</p>
                </div>
            </div>
            <div>
                <p class="section-kicker">Album</p>
                <a href="#" class="link text-sm">{{ $asset->album?->name ?? 'Tanpa album' }}</a>
            </div>
        </div>

        <div class="mt-4">
            <a href="{{ route('owner.memories.download', $asset) }}" class="btn btn-primary">
                <x-ui.icon name="download" class="h-4 w-4" />
                Unduh foto
            </a>
        </div>
    </div>

    <a href="{{ route('owner.memories.index') }}" class="link text-sm mt-5">
        <x-ui.icon name="chevron-left" class="h-4 w-4" />
        Kembali ke kenangan
    </a>
@endsection