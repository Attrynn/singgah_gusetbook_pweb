@props(['size' => 'md'])

<div x-data="{
    current: window.Theme ? window.Theme.get() : (localStorage.getItem('vowspace_theme') || 'system'),
    open: false,
    setTheme(val) {
        this.current = val;
        if (window.Theme) {
            window.Theme.set(val);
        } else {
            localStorage.setItem('vowspace_theme', val);
            if (val === 'dark' || (val === 'system' && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
                document.documentElement.classList.add('dark');
            } else {
                document.documentElement.classList.remove('dark');
            }
        }
        this.open = false;
    }
}" class="relative inline-block text-left">
    <button type="button"
            @click="open = !open"
            class="icon-btn {{ $size === 'sm' ? 'h-8 w-8 !rounded-xl' : 'h-9 w-9' }}"
            title="Ganti Tema (Gelap / Terang / Sistem)"
            aria-label="Pilih tema tampilan">
        <template x-if="current === 'dark'">
            <x-ui.icon name="sparkles" class="h-4 w-4 text-accent" />
        </template>
        <template x-if="current === 'light'">
            <x-ui.icon name="sparkles" class="h-4 w-4 text-accent" />
        </template>
        <template x-if="current === 'system'">
            <x-ui.icon name="sparkles" class="h-4 w-4 text-muted" />
        </template>
    </button>

    <div x-show="open"
         x-cloak
         @click.outside="open = false"
         class="absolute right-0 z-50 mt-2 w-36 rounded-2xl border border-line bg-surface p-1.5 shadow-soft">
        <button type="button"
                @click="setTheme('light')"
                class="flex w-full items-center gap-2.5 rounded-xl px-2.5 py-1.5 text-xs font-medium transition"
                :class="current === 'light' ? 'bg-accent/15 text-ink font-semibold' : 'text-muted hover:bg-surface-2/60 hover:text-ink'">
            <x-ui.icon name="sparkles" class="h-3.5 w-3.5 text-accent" />
            <span>Terang</span>
        </button>
        <button type="button"
                @click="setTheme('dark')"
                class="flex w-full items-center gap-2.5 rounded-xl px-2.5 py-1.5 text-xs font-medium transition"
                :class="current === 'dark' ? 'bg-accent/15 text-ink font-semibold' : 'text-muted hover:bg-surface-2/60 hover:text-ink'">
            <x-ui.icon name="sparkles" class="h-3.5 w-3.5 text-accent" />
            <span>Gelap</span>
        </button>
        <button type="button"
                @click="setTheme('system')"
                class="flex w-full items-center gap-2.5 rounded-xl px-2.5 py-1.5 text-xs font-medium transition"
                :class="current === 'system' ? 'bg-accent/15 text-ink font-semibold' : 'text-muted hover:bg-surface-2/60 hover:text-ink'">
            <x-ui.icon name="settings" class="h-3.5 w-3.5 text-muted" />
            <span>Sistem</span>
        </button>
    </div>
</div>
