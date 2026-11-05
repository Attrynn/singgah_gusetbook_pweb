@extends('layouts.guest')
@section('content')
<div class="px-5 pt-5 pb-12">
    <x-ui.page-header title="Galeri Foto" subtitle="{{ $assets->total() }} foto disetujui" />

    @if ($event->gallery_enabled && $event->allowsGuestActions())
        <div class="card p-6 mb-10" x-data="{ uploading: false, progress: 0 }">
            <p class="section-kicker mb-4">Unggah Foto</p>
            <form method="POST" action="{{ route('guest.gallery.store', $token) }}" enctype="multipart/form-data" class="space-y-4" @submit.prevent="uploadPhoto">
                @csrf
                <div class="flex items-start gap-3">
                    <input type="file" name="photo" accept="image/*" capture class="input" required x-ref="fileInput" @change="previewFile">
                </div>
                <div class="flex items-start gap-3">
                    <input type="text" name="caption" placeholder="Keterangan (opsional)" class="input" x-model="caption">
                </div>
                <div class="flex items-start gap-3" x-show="uploading">
                    <div class="flex-1">
                        <div class="h-2 bg-surface-2/60 rounded-full overflow-hidden">
                            <div class="h-full bg-accent transition-all duration-300" :style="'width: ' + progress + '%'"></div>
                        </div>
                        <p class="text-[10px] text-faint mt-1" x-text="'Mengunggah... ' + progress + '%'"></p>
                    </div>
                </div>
                <button type="submit" class="btn-primary" :disabled="uploading">
                    <span x-show="!uploading">Unggah Foto</span>
                    <span x-show="uploading">Mengunggah...</span>
                </button>
            </form>
            <p class="mt-3 text-xs text-faint">Foto akan ditampilkan setelah disetujui admin.</p>
        </div>
    @else
        <div class="card p-6 mb-10">
            <p class="section-kicker mb-4">Unggah Foto</p>
            <p class="prose-wedding">Galeri foto sedang tidak menerima unggahan saat ini.</p>
        </div>
    @endif

    <p class="section-kicker mb-4">Foto Disetujui</p>
    @if ($assets->count())
        <div class="grid grid-cols-2 gap-3 mb-6" x-data="{ lightboxOpen: false, selectedImage: '', selectedCaption: '' }">
            @foreach ($assets as $asset)
                <button type="button" class="group relative aspect-square rounded-2xl overflow-hidden" @click="lightboxOpen = true; selectedImage = '{{ $asset->url() }}'; selectedCaption = '{{ $asset->caption ?? '' }}'">
                    <img src="{{ $asset->thumbnailUrl() }}" alt="" class="w-full h-full object-cover transition-transform duration-300 group-hover:scale-105">
                    <div class="absolute inset-0 bg-ink/0 group-hover:bg-ink/20 transition-colors"></div>
                </button>
            @endforeach
        </div>

        <!-- Lightbox -->
        <div x-show="lightboxOpen" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100" x-transition:leave="transition ease-in duration-150" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0" class="fixed inset-0 z-50 flex items-center justify-center bg-ink/90 p-4" @click="lightboxOpen = false" @keydown.escape.window="lightboxOpen = false">
            <div class="relative max-w-[90vw] max-h-[90vh]" @click.outside="lightboxOpen = false">
                <img :src="selectedImage" alt="" class="max-w-full max-h-[80vh] rounded-2xl shadow-soft">
                <div x-show="selectedCaption" class="mt-3 text-center text-white/80 text-sm" x-text="selectedCaption"></div>
                <button type="button" class="absolute top-4 right-4 icon-btn" @click="lightboxOpen = false" aria-label="Tutup">
                    <x-ui.icon name="close" class="h-5 w-5" />
                </button>
            </div>
        </div>

        <!-- Pagination -->
        @if ($assets->hasPages())
            <div class="flex items-center justify-center gap-2">
                @if ($assets->onFirstPage())
                    <button type="button" class="btn-ghost" disabled><x-ui.icon name="chevron-left" class="h-5 w-5" /></button>
                @else
                    <a href="{{ $assets->previousPageUrl() }}" class="btn-ghost"><x-ui.icon name="chevron-left" class="h-5 w-5" /></a>
                @endif
                <span class="text-sm text-muted">{{ $assets->currentPage() }} / {{ $assets->lastPage() }}</span>
                @if ($assets->hasMorePages())
                    <a href="{{ $assets->nextPageUrl() }}" class="btn-ghost"><x-ui.icon name="chevron-right" class="h-5 w-5" /></a>
                @else
                    <button type="button" class="btn-ghost" disabled><x-ui.icon name="chevron-right" class="h-5 w-5" /></button>
                @endif
            </div>
        @endif
    @else
        <x-ui.empty-state icon="image" title="Belum ada foto" description="Tunggu foto resmi dipublikasikan atau unggah foto Anda." />
    @endif

    <p class="text-center text-[11px] text-faint mt-10">Dibuat dengan tenang oleh <span class="font-semibold text-ink">VOWSPACE</span></p>
</div>
</section>
</body>
</html>