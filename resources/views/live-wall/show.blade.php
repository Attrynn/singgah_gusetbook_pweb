<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $event->coupleNames() }} · Live Wall · VOWSPACE</title>
    <script>
        (function() {
            const theme = localStorage.getItem('vowspace_theme') || 'system';
            const isDark = theme === 'dark' || (theme === 'system' && window.matchMedia('(prefers-color-scheme: dark)').matches);
            if (isDark) { document.documentElement.classList.add('dark'); }
        })();
    </script>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-dvh bg-canvas text-ink antialiased">
    <div class="mx-auto min-h-screen max-w-7xl px-4 py-8 md:px-8 md:py-12"
         x-data="liveWall({{ Js::from($messages->map(fn ($m) => [
             'id' => $m->id,
             'sender' => $m->sender_name,
             'message' => $m->message,
             'emoji' => $m->emoji,
             'time' => $m->created_at?->diffForHumans() ?? '',
         ])) }}, '{{ route('live-wall.messages', $event->slug) }}')"
         x-init="initWall()">

        <!-- Header -->
        <header class="mb-10 flex flex-wrap items-center justify-between gap-4 border-b border-line/60 pb-6">
            <div>
                <p class="section-kicker">Tembok Ucapan Langsung</p>
                <h1 class="font-serif text-3xl font-bold tracking-tight text-ink md:text-4xl">
                    {{ $event->groom_name }} &amp; {{ $event->bride_name }}
                </h1>
                <p class="mt-1 text-xs text-muted">
                    {{ $event->event_date?->translatedFormat('l, d F Y') }} · {{ $event->venue?->name ?? 'Perayaan pernikahan' }}
                </p>
            </div>
            <div class="flex items-center gap-3">
                <span class="inline-flex items-center gap-2 rounded-full border border-line bg-surface px-4 py-2 text-xs font-semibold text-ink shadow-sm"
                      role="status" aria-live="polite">
                    <span class="inline-block h-2.5 w-2.5 rounded-full"
                          :class="status === 'offline' ? 'bg-danger animate-pulse' : (status === 'live' ? 'bg-success' : 'bg-warning animate-pulse')"></span>
                    <span x-text="status === 'offline' ? 'Terputus · mencoba lagi' : (status === 'live' ? 'Terhubung' : 'Menghubungkan...')">Menghubungkan...</span>
                </span>
                <button type="button" @click="fetchNewMessages()" class="btn btn-secondary btn-sm" :disabled="loading" aria-label="Muat ulang ucapan">
                    <x-ui.icon name="refresh" class="h-4 w-4" />
                    <span x-text="loading ? 'Memuat...' : 'Segarkan'">Segarkan</span>
                </button>
            </div>
        </header>

        <!-- Messages Grid -->
        <div class="grid grid-cols-1 gap-5 md:grid-cols-2 lg:grid-cols-3">
            <template x-for="msg in messages" :key="msg.id">
                <div class="card animate-float-in p-5 transition duration-300 hover:shadow-soft">
                    <div class="mb-3 flex items-center justify-between gap-3">
                        <div class="flex min-w-0 items-center gap-2.5">
                            <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-2xl bg-accent/15 text-sm font-bold text-accent"
                                  x-text="(msg.sender || '?').slice(0, 1).toUpperCase()"></span>
                            <span class="truncate text-sm font-semibold text-ink" x-text="msg.sender"></span>
                        </div>
                        <span class="shrink-0 text-[10px] text-faint" x-text="msg.time"></span>
                    </div>
                    <p class="break-words text-sm leading-relaxed text-muted" x-text="msg.message"></p>
                    <template x-if="msg.emoji">
                        <div class="mt-3 text-2xl" x-text="msg.emoji"></div>
                    </template>
                </div>
            </template>
        </div>

        <!-- Empty State -->
        <div x-show="messages.length === 0" x-cloak class="flex flex-col items-center justify-center py-20 text-center">
            <span class="flex h-16 w-16 items-center justify-center rounded-3xl border border-line bg-surface text-accent">
                <x-ui.icon name="sparkles" class="h-8 w-8" />
            </span>
            <h2 class="mt-6 font-serif text-2xl font-semibold text-ink">Belum Ada Ucapan Langsung</h2>
            <p class="mt-2 max-w-sm text-sm text-muted">
                Ketika para tamu mengirimkan ucapan melalui undangan digital mereka, ucapan akan tampil secara langsung di sini.
            </p>
        </div>

        <!-- Footer hint -->
        <footer class="mt-16 border-t border-line/60 pt-6 text-center text-xs text-faint">
            <p>Kunjungi undangan digital Anda untuk mengirim ucapan langsung ke layar ini.</p>
            <p class="mt-1">Dibuat dengan tenang oleh <span class="font-semibold text-ink">VOWSPACE</span></p>
        </footer>
    </div>

    <script>
        function liveWall(initialMessages, endpoint) {
            return {
                messages: (initialMessages || []).slice().sort((a, b) => a.id - b.id),
                endpoint: endpoint,
                loading: false,
                sinceId: 0,
                seenIds: new Set(),
                status: 'connecting',
                pollInterval: null,
                retryTimeout: null,
                retryDelay: 10000,
                initWall() {
                    this.messages.forEach((m) => this.seenIds.add(m.id));
                    // Cursor starts at the newest id we already have.
                    this.sinceId = this.messages.reduce((max, m) => Math.max(max, m.id), 0);
                    this.status = 'connecting';
                    this.fetchNewMessages();
                    this.pollInterval = setInterval(() => this.fetchNewMessages(), 10000);
                },
                destroy() {
                    if (this.pollInterval) { clearInterval(this.pollInterval); }
                    if (this.retryTimeout) { clearTimeout(this.retryTimeout); }
                },
                fetchNewMessages() {
                    if (this.loading) { return; }
                    this.loading = true;

                    fetch(`${this.endpoint}?since_id=${this.sinceId}`, { headers: { Accept: 'application/json' } })
                        .then((response) => {
                            if (! response.ok) { throw new Error('HTTP ' + response.status); }
                            return response.json();
                        })
                        .then((data) => {
                            const batch = Array.isArray(data.messages) ? data.messages : [];
                            // Work in ascending id order so we never skip messages,
                            // even if the endpoint ever returns a descending batch.
                            const ascending = batch.slice().sort((a, b) => a.id - b.id);

                            ascending.forEach((m) => {
                                if (this.seenIds.has(m.id)) { return; }
                                this.seenIds.add(m.id);
                                this.messages.push({
                                    id: m.id,
                                    sender: m.sender,
                                    message: m.message,
                                    emoji: m.emoji || null,
                                    time: 'Baru saja',
                                });
                            });

                            // Advance the cursor only to the last processed id.
                            if (ascending.length) {
                                this.sinceId = Math.max(this.sinceId, ascending[ascending.length - 1].id);
                            }

                            this.status = 'live';
                            this.retryDelay = 10000;
                            if (this.retryTimeout) { clearTimeout(this.retryTimeout); this.retryTimeout = null; }
                        })
                        .catch(() => {
                            // Keep the cursor untouched so nothing is skipped, then retry with backoff.
                            this.status = 'offline';
                            if (this.retryTimeout) { clearTimeout(this.retryTimeout); }
                            this.retryTimeout = setTimeout(() => this.fetchNewMessages(), this.retryDelay);
                            this.retryDelay = Math.min(this.retryDelay * 2, 60000);
                        })
                        .finally(() => {
                            this.loading = false;
                        });
                }
            };
        }
    </script>
</body>
</html>
