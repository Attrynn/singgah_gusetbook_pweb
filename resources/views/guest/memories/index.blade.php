@extends('layouts.guest')

@section('content')
<div class="px-4 pt-6 pb-12 sm:px-6 lg:px-8">
    <div class="mx-auto max-w-3xl">
        <x-ui.page-header title="Kenangan" subtitle="Kirim foto dan ucapan untuk melengkapi kenangan acara ini." />

        {{-- Quota reassurance: one quiet line, not a dashboard widget --}}
        <p class="mt-4 inline-flex items-center gap-2 rounded-full border border-line bg-surface px-3.5 py-1.5 text-xs font-medium text-muted shadow-raised">
            <x-ui.icon name="heart" class="h-3.5 w-3.5 shrink-0 text-accent" />
            Sisa kuota: {{ $quota }} foto
        </p>

        {{-- Kirim Foto: the primary path --}}
        <section class="mt-5 card p-5 sm:p-7" aria-labelledby="memory-photo-heading">
            <div class="mb-5 flex items-center gap-3">
                <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-2xl bg-accent-soft text-accent">
                    <x-ui.icon name="camera" class="h-5 w-5" />
                </span>
                <div>
                    <h2 id="memory-photo-heading" class="section-kicker">Kirim Foto</h2>
                    <p class="mt-0.5 text-xs text-faint">Bagikan momen terbaik dari acara ini.</p>
                </div>
            </div>

            @if ($quota > 0)
                <form method="POST" action="{{ route('guest.memories.store', $token) }}" enctype="multipart/form-data"
                      class="space-y-5"
                      x-data="{
                          sending: false,
                          selected: [],
                          limits: {
                              maxFiles: {{ (int) config('memory.guest_max_files', 5) }},
                              maxFileBytes: {{ (int) config('media.max_kb', 5120) * 1024 }},
                              maxTotalBytes: {{ (int) config('media.max_total_kb', 51200) * 1024 }},
                          },
                          init() { this.sync(); },
                          sync() {
                              const files = this.$refs.files.files || [];
                              this.selected = window.memorySelection
                                  ? window.memorySelection.describeSelection(files, this.limits).items
                                  : Array.from(files).map((file) => ({ name: file.name, size: file.size, sizeLabel: '', oversized: false }));
                          },
                          drop(event) {
                              this.$refs.files.files = event.dataTransfer.files;
                              this.sync();
                          },
                          remove(index) {
                              const transfer = new DataTransfer();
                              Array.from(this.$refs.files.files).forEach((file, i) => { if (i !== index) { transfer.items.add(file); } });
                              this.$refs.files.files = transfer.files;
                              this.sync();
                          }
                      }"
                      @submit="sending = true">
                    @csrf

                    {{-- Drop / select target (the real input stays underneath) --}}
                    <div>
                        <label for="memory-files" class="label">
                            Pilih foto <span class="text-danger" aria-hidden="true">*</span>
                            <span class="sr-only">(wajib)</span>
                        </label>
                        <label for="memory-files"
                               class="group flex cursor-pointer flex-col items-center justify-center gap-1.5 rounded-2xl border-2 border-dashed border-line bg-surface-2/40 px-4 py-7 text-center transition hover:border-accent/50 hover:bg-surface-2/60 focus-within:border-accent focus-within:bg-surface focus-within:ring-2 focus-within:ring-accent/25"
                               @dragover.prevent @drop.prevent="drop($event)">
                            <span class="flex h-11 w-11 items-center justify-center rounded-2xl bg-surface text-accent shadow-pressed transition group-hover:bg-accent-soft">
                                <x-ui.icon name="camera" class="h-5 w-5" />
                            </span>
                            <span class="mt-1 text-sm font-semibold text-ink">Ketuk untuk memilih foto</span>
                            <span class="text-xs text-faint">atau seret & letakkan berkas di sini</span>
                            <span class="mt-1 rounded-full border border-line bg-surface px-2.5 py-0.5 text-[10px] font-medium text-muted">
                                JPG, PNG, WEBP · maks. {{ (int) config('media.max_kb', 5120) / 1024 }} MB per foto
                            </span>
                        </label>
                        <input id="memory-files" type="file" name="files[]" accept="image/jpeg,image/png,image/webp"
                               class="sr-only" multiple required
                               aria-describedby="memory-files-hint"
                               x-ref="files"
                               @change="sync()"
                               @error('files') aria-invalid="true" @enderror>
                        <p id="memory-files-hint" class="hint">
                            Format JPG, PNG, atau WEBP. Maksimal {{ (int) config('memory.guest_max_files', 5) }} foto per kiriman.
                        </p>
                        @error('files')
                            <p class="error-text">{{ $message }}</p>
                        @enderror
                        @error('files.*')
                            <p class="error-text">{{ $message }}</p>
                        @enderror

                        {{-- Live selected-file list --}}
                        <ul x-show="selected.length > 0" x-cloak class="mt-3 space-y-1.5" aria-live="polite">
                            <template x-for="(file, index) in selected" :key="index">
                                <li class="flex items-center gap-2 rounded-xl border border-line/70 bg-surface px-3 py-2">
                                    <x-ui.icon name="image" class="h-4 w-4 shrink-0 text-faint" />
                                    <span class="min-w-0 flex-1 truncate text-xs font-medium text-ink" x-text="file.name"></span>
                                    <span class="shrink-0 text-[11px] tabular-nums text-faint" :class="file.oversized && 'text-danger'" x-text="file.sizeLabel"></span>
                                    <button type="button" class="icon-btn h-7 w-7" @click="remove(index)" :aria-label="'Hapus ' + file.name">
                                        <x-ui.icon name="close" class="h-3.5 w-3.5" />
                                    </button>
                                </li>
                            </template>
                        </ul>
                    </div>

                    <div>
                        <label for="memory-caption" class="label">Keterangan <span class="text-faint">(opsional)</span></label>
                        <input id="memory-caption" type="text" name="caption" class="input"
                               placeholder="Contoh: Resepsi, momen bersama keluarga" value="{{ old('caption') }}" maxlength="255">
                        @error('caption')
                            <p class="error-text">{{ $message }}</p>
                        @enderror
                    </div>

                    {{-- Consent: a quiet, deliberate agreement step --}}
                    <div class="space-y-3 rounded-2xl border border-line/70 bg-surface-2/40 p-4">
                        <p class="text-xs leading-relaxed text-muted">
                            Foto yang Anda kirim dapat dilihat oleh mempelai. Foto hanya ditampilkan secara publik jika Anda mengizinkannya di bawah.
                        </p>
                        <label for="memory-accept-terms" class="flex cursor-pointer items-start gap-3 text-sm text-ink">
                            <input id="memory-accept-terms" type="checkbox" name="accept_terms" value="1"
                                   class="mt-0.5 h-4 w-4 shrink-0 rounded border-line bg-surface-2 text-accent focus:ring-accent/30"
                                   required @checked(old('accept_terms'))>
                            <span>Saya setuju mengirim foto ini ke mempelai untuk diperiksa.</span>
                        </label>
                        @error('accept_terms')
                            <p class="error-text">{{ $message }}</p>
                        @enderror

                        <label for="memory-public-display" class="flex cursor-pointer items-start gap-3 text-sm text-ink">
                            <input id="memory-public-display" type="checkbox" name="allow_public_display" value="1"
                                   class="mt-0.5 h-4 w-4 shrink-0 rounded border-line bg-surface-2 text-accent focus:ring-accent/30"
                                   @checked(old('allow_public_display'))>
                            <span>Izinkan foto ini tampil di halaman berbagi publik, bila mempelai mengaktifkannya.</span>
                        </label>
                    </div>

                    {{-- Reserved status region so success never jumps the layout --}}
                    <div class="min-h-5" aria-live="polite">
                        @if (session('status'))
                            <p class="flex items-center gap-1.5 text-xs font-medium text-success">
                                <x-ui.icon name="check" class="h-3.5 w-3.5 shrink-0" />
                                {{ session('status') }}
                            </p>
                        @endif
                    </div>

                    <button type="submit" class="btn btn-primary w-full" :disabled="sending">
                        <span x-show="!sending" class="inline-flex items-center justify-center gap-2">
                            <x-ui.icon name="send" class="h-4 w-4" />
                            Kirim foto
                        </span>
                        <span x-show="sending" x-cloak class="inline-flex items-center justify-center gap-2">
                            <span class="h-4 w-4 animate-spin rounded-full border-2 border-current border-t-transparent" aria-hidden="true"></span>
                            Mengirim...
                        </span>
                    </button>
                </form>
            @else
                <p class="max-w-prose text-sm leading-relaxed text-muted">
                    Kuota foto Anda untuk acara ini sudah habis. Terima kasih sudah berbagi kenangan.
                </p>
            @endif
        </section>

        {{-- Tulis Ucapan: clearly the secondary, lighter path --}}
        <section class="mt-6 card-muted p-5 sm:p-6" aria-labelledby="memory-message-heading">
            <div class="mb-4 flex items-center gap-3">
                <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-surface text-muted shadow-pressed">
                    <x-ui.icon name="message" class="h-4 w-4" />
                </span>
                <div>
                    <h2 id="memory-message-heading" class="section-kicker">Tulis Ucapan</h2>
                    <p class="mt-0.5 text-xs text-faint">Tanpa foto? Sebuah ucapan juga berarti.</p>
                </div>
            </div>

            <form method="POST" action="{{ route('guest.memories.message', $token) }}"
                  class="space-y-4" x-data="{ sending: false }" @submit="sending = true">
                @csrf

                <div>
                    <label for="memory-display-name" class="label">Nama yang tampil <span class="text-faint">(opsional)</span></label>
                    <input id="memory-display-name" type="text" name="display_name" class="input"
                           placeholder="Nama yang tampil" value="{{ old('display_name') }}" maxlength="120">
                    @error('display_name')
                        <p class="error-text">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="memory-message" class="label">
                        Ucapan <span class="text-danger" aria-hidden="true">*</span>
                        <span class="sr-only">(wajib)</span>
                    </label>
                    <textarea id="memory-message" name="message" rows="4" class="input" required
                              minlength="3" maxlength="1000"
                              placeholder="Tuliskan ucapan atau doa untuk kedua mempelai"
                              @error('message') aria-invalid="true" @enderror>{{ old('message') }}</textarea>
                    <p class="hint">3–1000 karakter. Tautan dan tag HTML tidak diperbolehkan.</p>
                    @error('message')
                        <p class="error-text">{{ $message }}</p>
                    @enderror
                </div>

                <div class="space-y-3 rounded-2xl border border-line/70 bg-surface p-4">
                    <p class="text-xs leading-relaxed text-muted">
                        Ucapan Anda dapat dibaca oleh mempelai. Ucapan hanya ditampilkan secara publik jika Anda mengizinkannya di bawah.
                    </p>
                    <label for="memory-message-accept-terms" class="flex cursor-pointer items-start gap-3 text-sm text-ink">
                        <input id="memory-message-accept-terms" type="checkbox" name="accept_terms" value="1"
                               class="mt-0.5 h-4 w-4 shrink-0 rounded border-line bg-surface-2 text-accent focus:ring-accent/30"
                               required @checked(old('accept_terms'))>
                        <span>Saya setuju mengirim ucapan ini ke mempelai untuk diperiksa.</span>
                    </label>
                    @error('accept_terms')
                        <p class="error-text">{{ $message }}</p>
                    @enderror

                    <label for="memory-message-public-display" class="flex cursor-pointer items-start gap-3 text-sm text-ink">
                        <input id="memory-message-public-display" type="checkbox" name="allow_public_display" value="1"
                               class="mt-0.5 h-4 w-4 shrink-0 rounded border-line bg-surface-2 text-accent focus:ring-accent/30"
                               @checked(old('allow_public_display'))>
                        <span>Izinkan ucapan ini tampil di halaman berbagi publik, bila mempelai mengaktifkannya.</span>
                    </label>
                </div>

                <div class="min-h-5" aria-live="polite">
                    @if (session('status'))
                        <p class="flex items-center gap-1.5 text-xs font-medium text-success">
                            <x-ui.icon name="check" class="h-3.5 w-3.5 shrink-0" />
                            {{ session('status') }}
                        </p>
                    @endif
                </div>

                <button type="submit" class="btn btn-secondary w-full" :disabled="sending">
                    <span x-show="!sending" class="inline-flex items-center justify-center gap-2">
                        <x-ui.icon name="send" class="h-4 w-4" />
                        Kirim ucapan
                    </span>
                    <span x-show="sending" x-cloak class="inline-flex items-center justify-center gap-2">
                        <span class="h-4 w-4 animate-spin rounded-full border-2 border-current border-t-transparent" aria-hidden="true"></span>
                        Mengirim...
                    </span>
                </button>
            </form>
        </section>

        {{-- Riwayat kiriman: compact reassurance list --}}
        <section class="mt-8" aria-labelledby="memory-recent-heading">
            <h2 id="memory-recent-heading" class="section-kicker mb-3">Kiriman Anda</h2>

            @if ($recent->isEmpty() && $recentMessages->isEmpty())
                <x-ui.empty-state icon="image" title="Belum ada kiriman" description="Foto dan ucapan yang Anda kirim akan tampil di sini." />
            @else
                <div class="card divide-y divide-line/60 overflow-hidden">
                    @foreach ($recent as $asset)
                        @php
                            $badge = match (true) {
                                $asset->status === \App\Enums\MediaStatus::Pending => ['badge-warning', 'Menunggu pemeriksaan'],
                                $asset->status === \App\Enums\MediaStatus::Approved && $asset->hidden_at === null => ['badge-success', 'Sudah ditampilkan'],
                                $asset->status === \App\Enums\MediaStatus::Approved && $asset->hidden_at !== null => ['badge-neutral', 'Disembunyikan'],
                                default => ['badge-danger', 'Tidak ditampilkan'],
                            };
                        @endphp
                        <div class="flex items-center gap-3 px-4 py-3">
                            @if ($asset->isApproved())
                                <img src="{{ $asset->thumbnailUrl(true) }}" alt="" class="h-10 w-10 shrink-0 rounded-lg border border-line object-cover" loading="lazy" />
                            @else
                                <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-surface-2 text-faint">
                                    <x-ui.icon name="image" class="h-4 w-4" />
                                </span>
                            @endif
                            <div class="min-w-0 flex-1">
                                <p class="truncate text-sm font-medium text-ink">{{ $asset->caption ?: 'Foto' }}</p>
                                <p class="mt-0.5 text-[11px] text-faint">{{ $asset->created_at?->translatedFormat('d M Y H:i') }}</p>
                            </div>
                            <x-ui.badge :variant="$badge[0]">{{ $badge[1] }}</x-ui.badge>
                        </div>
                    @endforeach

                    @foreach ($recentMessages as $message)
                        @php
                            $badge = match ($message->status) {
                                \App\Enums\MessageStatus::Pending => ['badge-warning', 'Menunggu pemeriksaan'],
                                \App\Enums\MessageStatus::Approved => ['badge-success', 'Sudah ditampilkan'],
                                \App\Enums\MessageStatus::Hidden => ['badge-neutral', 'Disembunyikan'],
                                default => ['badge-danger', 'Tidak ditampilkan'],
                            };
                        @endphp
                        <div class="flex items-center gap-3 px-4 py-3">
                            <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-teal-soft text-ink">
                                <x-ui.icon name="message" class="h-4 w-4" />
                            </span>
                            <div class="min-w-0 flex-1">
                                <p class="truncate text-sm text-ink">{{ $message->message }}</p>
                                <p class="mt-0.5 text-[11px] text-faint">{{ $message->created_at?->translatedFormat('d M Y H:i') }}</p>
                            </div>
                            <x-ui.badge :variant="$badge[0]">{{ $badge[1] }}</x-ui.badge>
                        </div>
                    @endforeach
                </div>
            @endif
        </section>

        <p class="mt-10 text-center text-[11px] text-faint">
            Dibuat dengan tenang oleh <span class="font-semibold text-ink">VOWSPACE</span>
        </p>
    </div>
</div>
@endsection
