@extends('layouts.memory-public')

@section('title', 'Kenangan '.$event->coupleNames())

@section('content')
@php
    $eventDate = $event->localStartsAt()?->translatedFormat('l, d F Y')
        ?? $event->event_date?->translatedFormat('l, d F Y');
@endphp
<div class="px-4 pt-6 pb-12 sm:px-6 lg:px-8">
    <div class="mx-auto max-w-3xl">
        <header class="text-center">
            <p class="guest-eyebrow">Kenangan</p>
            <h1 class="mt-2 font-serif text-2xl font-bold tracking-tight text-ink sm:text-3xl">
                {{ $event->coupleNames() }}
            </h1>
            <p class="mt-1.5 text-sm text-muted">{{ $eventDate }}</p>
            <div class="mx-auto mt-4 h-px w-16 bg-accent/40" aria-hidden="true"></div>
            <div class="mx-auto mt-4 flex max-w-prose items-start justify-center gap-1.5 text-left">
                <x-ui.icon name="shield" class="mt-0.5 h-3.5 w-3.5 shrink-0 text-faint" />
                <p class="text-xs leading-relaxed text-faint">
                    Hanya konten yang diizinkan kontributor untuk dibagikan secara publik yang ditampilkan di sini.
                </p>
            </div>
        </header>

        @if ($photos->isEmpty() && $messages->isEmpty())
            <div class="mt-8">
                <x-ui.empty-state icon="sparkles" title="Belum ada kenangan" description="Pasangan belum membagikan kenangan apa pun secara publik." />
            </div>
        @else
            {{-- Photo grid --}}
            @if ($photos->isNotEmpty())
                <section class="mt-10" aria-labelledby="share-photos-heading">
                    <h2 id="share-photos-heading" class="section-kicker mb-4">Foto ({{ $photoCount }})</h2>

                    <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4" x-data="{
                            lightboxOpen: false,
                            lightboxIndex: 0,
                            items: [],
                            lastFocus: null,
                            init() {
                                this.items = Array.from(this.$el.querySelectorAll('[data-image]')).map((el) => ({
                                    image: el.dataset.image,
                                    caption: el.dataset.caption || '',
                                    contributor: el.dataset.contributor || '',
                                }));
                            },
                            open(index) {
                                this.lightboxIndex = index;
                                this.lightboxOpen = true;
                                this.lastFocus = document.activeElement;
                                document.body.style.overflow = 'hidden';
                                this.$nextTick(() => { this.$refs.close?.focus(); });
                            },
                            close() {
                                this.lightboxOpen = false;
                                document.body.style.overflow = '';
                                if (this.lastFocus) {
                                    this.lastFocus.focus();
                                    this.lastFocus = null;
                                }
                            },
                            prev() {
                                this.lightboxIndex = (this.lightboxIndex - 1 + this.items.length) % this.items.length;
                            },
                            next() {
                                this.lightboxIndex = (this.lightboxIndex + 1) % this.items.length;
                            },
                            get current() {
                                return this.items[this.lightboxIndex] || {};
                            },
                            trapTab(event) {
                                if (!this.lightboxOpen) { return; }
                                const focusables = [this.$refs.close, this.$refs.prev, this.$refs.next].filter(Boolean);
                                if (!focusables.length) { return; }
                                const first = focusables[0];
                                const last = focusables[focusables.length - 1];
                                if (event.shiftKey && document.activeElement === first) {
                                    event.preventDefault();
                                    last.focus();
                                } else if (!event.shiftKey && document.activeElement === last) {
                                    event.preventDefault();
                                    first.focus();
                                }
                            }
                        }"
                        @keydown.escape.window="close()"
                        @keydown.arrow-left.window="lightboxOpen && prev()"
                        @keydown.arrow-right.window="lightboxOpen && next()"
                        @keydown.tab.window="trapTab($event)">
                        @foreach ($photos as $photo)
                            <button type="button" class="group relative aspect-square overflow-hidden rounded-2xl bg-surface-2"
                                    data-image="{{ $photo->url() }}"
                                    data-caption="{{ $photo->caption }}"
                                    data-contributor="{{ $photo->consent?->contributor_name }}"
                                    @click="open({{ $loop->index }})"
                                    aria-label="Perbesar foto{{ $photo->caption ? ': '.$photo->caption : '' }}">
                                <img src="{{ $photo->thumbnailUrl() }}" alt="{{ $photo->caption ?: 'Foto kenangan' }}"
                                     class="h-full w-full object-cover transition-transform duration-300 group-hover:scale-105" loading="lazy">
                                <span class="absolute inset-0 bg-scrim/0 transition-colors group-hover:bg-scrim/20" aria-hidden="true"></span>
                                <span class="absolute inset-0 flex items-center justify-center opacity-0 transition-opacity group-hover:opacity-100" aria-hidden="true">
                                    <x-ui.icon name="eye" class="h-6 w-6 text-on-scrim drop-shadow" />
                                </span>
                            </button>
                        @endforeach

                        {{-- Lightbox --}}
                        <div x-show="lightboxOpen" x-cloak
                             x-transition:enter="transition ease-out duration-200"
                             x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
                             x-transition:leave="transition ease-in duration-150"
                             x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"
                             class="fixed inset-0 z-50 flex items-center justify-center bg-scrim/90 p-4"
                             role="dialog" aria-modal="true" aria-label="Pratinjau foto"
                             @click.self="close()">
                            <button type="button" x-ref="close" class="icon-btn absolute right-4 top-4" @click="close()" aria-label="Tutup pratinjau">
                                <x-ui.icon name="close" class="h-5 w-5" />
                            </button>
                            <button type="button" x-ref="prev" class="icon-btn absolute left-4 top-1/2 -translate-y-1/2" @click="prev()" aria-label="Foto sebelumnya">
                                <x-ui.icon name="chevron-left" class="h-5 w-5" />
                            </button>
                            <button type="button" x-ref="next" class="icon-btn absolute right-4 top-1/2 -translate-y-1/2" @click="next()" aria-label="Foto berikutnya">
                                <x-ui.icon name="chevron-right" class="h-5 w-5" />
                            </button>

                            <div class="relative max-w-4xl" @click.stop>
                                <img :src="current.image" :alt="current.caption || 'Foto kenangan'"
                                     class="mx-auto block max-h-[78vh] w-auto max-w-full rounded-2xl shadow-soft">
                                <div class="mt-3 text-center">
                                    <p x-show="current.caption" x-cloak class="text-sm text-on-scrim" x-text="current.caption"></p>
                                    <p x-show="current.contributor" x-cloak class="mt-1 text-xs text-on-scrim/70" x-text="current.contributor"></p>
                                    <p class="mt-2 text-xs font-medium tabular-nums text-on-scrim/70">
                                        <span x-text="lightboxIndex + 1"></span> / <span x-text="items.length"></span>
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="mt-6">{{ $photos->links() }}</div>
                </section>
            @endif

            {{-- Messages --}}
            @if ($messages->isNotEmpty())
                <section class="mt-10" aria-labelledby="share-messages-heading">
                    <h2 id="share-messages-heading" class="section-kicker mb-4">Ucapan ({{ $messageCount }})</h2>

                    <div class="space-y-3">
                        @foreach ($messages as $message)
                            <article class="card border-l-2 border-l-accent/40 p-4 sm:p-5">
                                @if ($message->sender_name)
                                    <p class="font-serif text-base font-semibold text-ink">{{ $message->sender_name }}</p>
                                @endif
                                <p class="mt-1.5 text-sm leading-relaxed text-ink">{{ $message->message }}</p>
                                <p class="mt-2 text-[11px] text-faint">{{ $message->created_at?->toImmutable()->setTimezone($event->timezoneName())->translatedFormat('d M Y H:i') }}</p>
                            </article>
                        @endforeach
                    </div>

                    <div class="mt-6">{{ $messages->links() }}</div>
                </section>
            @endif

            {{-- Collections --}}
            @if ($collections->isNotEmpty())
                <section class="mt-10" aria-labelledby="share-collections-heading">
                    <h2 id="share-collections-heading" class="section-kicker mb-4">Koleksi</h2>

                    <ul class="grid gap-3 sm:grid-cols-2">
                        @foreach ($collections as $collection)
                            <li class="card flex items-center justify-between gap-3 p-4">
                                <span class="min-w-0 truncate text-sm font-medium text-ink">{{ $collection->name }}</span>
                                <span class="shrink-0 rounded-full border border-line bg-surface-2/60 px-2.5 py-0.5 text-[11px] font-medium text-muted">
                                    {{ $collection->items->count() }} item
                                </span>
                            </li>
                        @endforeach
                    </ul>
                </section>
            @endif
        @endif
    </div>
</div>
@endsection
