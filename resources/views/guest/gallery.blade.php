@extends('layouts.guest')
@section('content')
@php
    $canUpload = $event->gallery_enabled && $event->allowsGuestActions();
@endphp
<div class="px-4 pt-6 pb-12 sm:px-6 lg:px-8">
    <div class="mx-auto max-w-3xl">
        <x-ui.page-header title="Galeri Foto" subtitle="{{ $assets->total() }} foto disetujui" />

        <div class="mt-6 card p-6 sm:p-8">
            <h2 class="section-kicker mb-4">Unggah Foto</h2>
            @if ($canUpload)
                <form method="POST" action="{{ route('guest.gallery.store', $token) }}" enctype="multipart/form-data"
                      class="space-y-5" x-data="{ uploading: false }" @submit="uploading = true">
                    @csrf
                    <div>
                        <label for="gallery-photo" class="label">
                            Pilih foto <span class="text-danger" aria-hidden="true">*</span>
                            <span class="sr-only">(wajib)</span>
                        </label>
                        <input id="gallery-photo" type="file" name="photo" accept="image/*" capture class="input" required
                               aria-describedby="gallery-photo-hint"
                               @error('photo') aria-invalid="true" @enderror>
                        <p id="gallery-photo-hint" class="hint">
                            Format JPG, PNG, atau HEIC. Foto akan ditampilkan setelah disetujui admin.
                        </p>
                        @error('photo')
                            <p class="error-text">{{ $message }}</p>
                        @enderror
                    </div>
                    <div>
                        <label for="gallery-caption" class="label">Keterangan <span class="text-faint">(opsional)</span></label>
                        <input id="gallery-caption" type="text" name="caption" class="input"
                               placeholder="Contoh: Resepsi, Momen bersama keluarga">
                    </div>
                    <button type="submit" class="btn btn-primary" :disabled="uploading">
                        <span x-show="!uploading">Unggah foto</span>
                        <span x-show="uploading" x-cloak>Mengunggah...</span>
                    </button>
                </form>
            @else
                <p class="max-w-prose text-sm leading-relaxed text-muted">
                    Galeri foto sedang tidak menerima unggahan saat ini. Anda tetap dapat melihat foto yang telah dibagikan.
                </p>
            @endif
        </div>

        <h2 class="section-kicker mt-10 mb-4">Foto Disetujui</h2>
        @if ($assets->count())
            <div class="grid grid-cols-2 gap-3 sm:grid-cols-3" x-data="{
                    lightboxOpen: false,
                    selectedImage: '',
                    selectedCaption: '',
                    openLightbox(el) {
                        this.selectedImage = el.dataset.image;
                        this.selectedCaption = el.dataset.caption || '';
                        this.lightboxOpen = true;
                    }
                }">
                @foreach ($assets as $asset)
                    <button type="button" class="group relative aspect-square overflow-hidden rounded-2xl bg-surface-2"
                            data-image="{{ $asset->url() }}" data-caption="{{ $asset->caption }}"
                            @click="openLightbox($event.currentTarget)"
                            aria-label="Perbesar foto{{ $asset->caption ? ': '.$asset->caption : '' }}">
                        <img src="{{ $asset->thumbnailUrl() }}" alt="{{ $asset->caption ?: 'Foto tamu' }}"
                             class="h-full w-full object-cover transition-transform duration-300 group-hover:scale-105">
                        <span class="absolute inset-0 bg-scrim/0 transition-colors group-hover:bg-scrim/20" aria-hidden="true"></span>
                    </button>
                @endforeach

                <!-- Lightbox -->
                <div x-show="lightboxOpen" x-cloak
                     x-transition:enter="transition ease-out duration-200"
                     x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
                     x-transition:leave="transition ease-in duration-150"
                     x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"
                     class="fixed inset-0 z-50 flex items-center justify-center bg-scrim/90 p-4"
                     role="dialog" aria-modal="true" aria-label="Pratinjau foto"
                     @click="lightboxOpen = false" @keydown.escape.window="lightboxOpen = false">
                    <div class="relative max-h-[90vh] max-w-[90vw]" @click.stop>
                        <img :src="selectedImage" :alt="selectedCaption || 'Foto tamu'" class="max-h-[80vh] max-w-full rounded-2xl shadow-soft">
                        <p x-show="selectedCaption" x-cloak class="mt-3 text-center text-sm text-on-scrim/80" x-text="selectedCaption"></p>
                        <button type="button" class="icon-btn absolute right-2 top-2" @click="lightboxOpen = false" aria-label="Tutup pratinjau">
                            <x-ui.icon name="close" class="h-5 w-5" />
                        </button>
                    </div>
                </div>
            </div>

            @if ($assets->hasPages())
                <nav class="mt-8 flex items-center justify-center gap-3" aria-label="Navigasi halaman galeri">
                    @if ($assets->onFirstPage())
                        <button type="button" class="btn-ghost" disabled aria-label="Halaman sebelumnya">
                            <x-ui.icon name="chevron-left" class="h-5 w-5" />
                        </button>
                    @else
                        <a href="{{ $assets->previousPageUrl() }}" class="btn-ghost" aria-label="Halaman sebelumnya">
                            <x-ui.icon name="chevron-left" class="h-5 w-5" />
                        </a>
                    @endif
                    <span class="text-sm text-muted">Halaman {{ $assets->currentPage() }} dari {{ $assets->lastPage() }}</span>
                    @if ($assets->hasMorePages())
                        <a href="{{ $assets->nextPageUrl() }}" class="btn-ghost" aria-label="Halaman berikutnya">
                            <x-ui.icon name="chevron-right" class="h-5 w-5" />
                        </a>
                    @else
                        <button type="button" class="btn-ghost" disabled aria-label="Halaman berikutnya">
                            <x-ui.icon name="chevron-right" class="h-5 w-5" />
                        </button>
                    @endif
                </nav>
            @endif
        @else
            <x-ui.empty-state icon="image" title="Belum ada foto" description="Tunggu foto resmi dipublikasikan atau unggah foto Anda." />
        @endif

        <p class="mt-10 text-center text-[11px] text-faint">
            Dibuat dengan tenang oleh <span class="font-semibold text-ink">VOWSPACE</span>
        </p>
    </div>
</div>
@endsection
