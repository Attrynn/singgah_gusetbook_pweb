<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#F1F5F9">
    <title>{{ $event->coupleNames() }} · Tembok Ucapan · VOWSPACE</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>
        html, body { background: #F1F5F9; }
        .wall { min-height: 100vh; padding: 24px; max-width: 1200px; margin: 0 auto; }
        @media (min-width: 768px) { .wall { padding: 48px; } }
        .couple { font-family: 'Playfair Display', Georgia, serif; text-align: center; margin-bottom: 32px; }
        .message { background: rgba(255,255,255,0.92); border-radius: 16px; padding: 16px 20px; box-shadow: 0 8px 24px rgba(37,52,71,0.12); animation: float-in 0.5s ease-out both; }
        .message-name { font-weight: 600; color: #253447; }
        .message-text { color: #64748B; margin-top: 4px; }
        .live-dot { display: inline-block; width: 8px; height: 8px; border-radius: 50%; background: #5DAD91; margin-right: 6px; animation: pulse 1.6s infinite; }
        @keyframes pulse { 0%,100% { opacity: 1; } 50% { opacity: 0.3; } }
        @media (max-width: 480px) { .message { padding: 12px 16px; } .message-text { font-size: 13px; } }
    </style>
</head>
<body class="min-h-dvh bg-canvas">
    <div class="wall" x-data="{ messages: [], loading: false, sinceId: 0 }" x-init="init()">
        <!-- Header -->
        <header class="flex items-center justify-between mb-8">
            <div>
                <p class="section-kicker text-[11px] font-semibold uppercase tracking-[0.18em] text-faint">Tembak Ucapan Langsung</p>
                <h1 class="font-serif text-3xl font-bold text-ink">{{ $event->groom_name }} & {{ $event->bride_name }}</h1>
            </div>
            <div class="flex items-center gap-3">
                <span class="flex items-center gap-2 rounded-full bg-surface px-4 py-2 text-sm font-semibold text-ink">
                    <span class="live-dot"></span>
                    <span x-text="'Live'"></span>
                </span>
                <a href="{{ route('live-wall.show', $event->slug) }}" target="_blank" rel="noopener" class="btn-soft text-sm">
                    <x-ui.icon name="external-link" class="h-4 w-4 mr-1" /> Buka di browser
                </a>
            </div>
        </header>

        <!-- Messages -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
            @foreach ($messages as $message)
                <div class="message animate-float-in" :key="$message->id">
                    <div class="flex items-center gap-2">
                        <span class="flex h-8 w-8 items-center justify-center rounded-full bg-blue-soft text-[#4f7592] text-xs font-semibold">
                            {{ mb_strtoupper(mb_substr($message->sender_name, 0, 1)) }}
                        </span>
                        <span class="message-name text-sm">{{ $message->sender_name }}</span>
                    </div>
                    <p class="message-text mt-2" x-text="$message->message"></p>
                    @if ($message->emoji)
                        <span class="text-xl mt-2 block" x-text="$message->emoji"></span>
                    @endif
                </div>
            @endforeach
        </div>

        <!-- Empty state -->
        @if ($messages->isEmpty())
            <div class="flex flex-col items-center justify-center py-20 text-center">
                <span class="flex h-16 w-16 items-center justify-center rounded-3xl bg-blue-soft text-[#4f7592]">
                    <x-ui.icon name="sparkles" class="h-8 w-8" />
                </span>
                <h2 class="mt-6 font-serif text-2xl font-semibold text-ink">Tidak ada ucapan saat ini</h2>
                <p class="mt-3 text-sm text-muted">Ketika tamu meninggalkan ucapan, ucapan mereka akan muncul di sini.</p>
            </div>
        @endif

        <!-- Footer hint -->
        <footer class="mt-12 flex flex-col items-center gap-2 text-center text-xs text-faint">
            <p>Halaman utama event: <a href="{{ route('guest.landing', $event->slug) }}" class="font-semibold text-ink underline-offset-4 hover:underline">{{ route('guest.landing', $event->slug) }}</a></p>
            <p>Buka tautan undangan personal Anda untuk mengirim ucapan ke Live Wall.</p>
        </footer>
    </div>

    <script>
        function init() {
            const wall = document.querySelector('.wall');
            if (!wall) return;
            fetch('{{ route('live-wall.messages', $event->slug) }}?since_id=0')
                .then(r => r.json())
                .then(data => {
                    if (!data.messages || data.messages.length === 0) return;
                    data.messages.forEach(m => {
                        const div = document.createElement('div');
                        div.className = 'message animate-float-in';
                        div.innerHTML = `
                            <div class="flex items-center gap-2">
                                <span class="flex h-8 w-8 items-center justify-center rounded-full bg-blue-soft text-[#4f7592] text-xs font-semibold">${escapeHtml(m.sender)}</span>
                                <span class="message-name text-sm">${escapeHtml(m.sender)}</span>
                            </div>
                            <p class="message-text mt-2">${escapeHtml(m.message)}</p>
                            ${m.emoji ? `<span class="text-xl mt-2 block">${escapeHtml(m.emoji)}</span>` : ''}
                        `;
                        wall.appendChild(div);
                    });
                    window.Alpine.store('wallMaxId', Math.max(...data.messages.map(m => m.id)));
                })
                .catch(() => {});

            setInterval(() => {
                fetch(`{{ route('live-wall.messages', $event->slug) }}?since_id=${window.Alpine.store('wallMaxId') || 0}`)
                    .then(r => r.json())
                    .then(data => {
                        if (!data.messages || data.messages.length === 0) return;
                        data.messages.forEach(m => {
                            const existing = document.querySelectorAll('.message').find(el => el.dataset && el.dataset.id == m.id);
                            if (!existing) {
                                const div = document.createElement('div');
                                div.className = 'message animate-float-in';
                                div.dataset.id = m.id;
                                div.innerHTML = `
                                    <div class="flex items-center gap-2">
                                        <span class="flex h-8 w-8 items-center justify-center rounded-full bg-blue-soft text-[#4f7592] text-xs font-semibold">${escapeHtml(m.sender)}</span>
                                        <span class="message-name text-sm">${escapeHtml(m.sender)}</span>
                                    </div>
                                    <p class="message-text mt-2">${escapeHtml(m.message)}</p>
                                    ${m.emoji ? `<span class="text-xl mt-2 block">${escapeHtml(m.emoji)}</span>` : ''}
                                `;
                                wall.appendChild(div);
                            }
                        });
                        window.Alpine.store('wallMaxId', Math.max(...data.messages.map(m => m.id)));
                    })
                    .catch(() => {});
            }, 15000);
        }

        function escapeHtml(s) {
            return String(s).replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
        }
    </script>
</body>
</html>