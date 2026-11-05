<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#1F2A34">
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
                    {{ $event->groom_name }} & {{ $event->bride_name }}
                </h1>
                <p class="mt-1 text-xs text-muted">
                    {{ $event->event_date?->translatedFormat('l, d F Y') }} · {{ $event->venue?->name ?? 'Wedding Celebration' }}
                </p>
            </div>
            <div class="flex items-center gap-3">
                <span class="inline-flex items-center gap-2 rounded-full border border-line bg-surface px-4 py-2 text-xs font-semibold text-ink shadow-sm">
                    <span class="inline-block h-2.5 w-2.5 animate-pulse rounded-full bg-emerald-500"></span>
                    <span>Live Updates</span>
                </span>
                <button type="button" @click="fetchNewMessages()" class="btn btn-secondary btn-sm" :disabled="loading">
                    <x-ui.icon name="refresh" class="h-4 w-4" />
                    <span x-text="loading ? 'Memuat...' : 'Segarkan'"></span>
                </button>
            </div>
        </header>

        <!-- Messages Grid -->
        <div class="grid grid-cols-1 gap-5 md:grid-cols-2 lg:grid-cols-3">
            <template x-for="msg in messages" :key="msg.id">
                <div class="card p-5 transition duration-300 hover:shadow-soft animate-float-in">
                    <div class="flex items-center justify-between gap-3 mb-3">
                        <div class="flex items-center gap-2.5 min-w-0">
                            <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-2xl bg-accent/15 text-accent text-sm font-bold"
                                  x-text="(msg.sender || '?').slice(0, 1).toUpperCase()">
                            </span>
                            <span class="truncate font-semibold text-ink text-sm" x-text="msg.sender"></span>
                        </div>
                        <span class="shrink-0 text-[10px] text-faint" x-text="msg.time"></span>
                    </div>
                    <p class="text-sm leading-relaxed text-muted break-words" x-text="msg.message"></p>
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
                messages: initialMessages || [],
                endpoint: endpoint,
                loading: false,
                sinceId: 0,
                seenIds: new Set(),
                pollInterval: null,
                initWall() {
                    this.messages.forEach(m => this.seenIds.add(m.id));
                    this.sinceId = this.messages.reduce((max, m) => Math.max(max, m.id), 0);
                    this.pollInterval = setInterval(() => this.fetchNewMessages(), 10000);
                },
                fetchNewMessages() {
                    if (this.loading) return;
                    this.loading = true;
                    fetch(`${this.endpoint}?since_id=${this.sinceId}`)
                        .then(r => r.json())
                        .then(data => {
                            if (data.messages && data.messages.length > 0) {
                                const newOnes = data.messages.filter(m => !this.seenIds.has(m.id));
                                newOnes.forEach(m => {
                                    this.seenIds.add(m.id);
                                    this.messages.unshift({
                                        id: m.id,
                                        sender: m.sender,
                                        message: m.message,
                                        emoji: m.emoji || null,
                                        time: 'Baru saja'
                                    });
                                    this.sinceId = Math.max(this.sinceId, m.id);
                                });
                            }
                        })
                        .catch(() => {})
                        .finally(() => {
                            this.loading = false;
                        });
                }
            };
        }
    </script>
</body>
</html>
