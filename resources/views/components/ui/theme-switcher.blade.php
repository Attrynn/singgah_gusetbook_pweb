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
            this.close(true);
        },
        openMenu() {
            this.open = true;
            this.$nextTick(() => {
                const items = this.menuItems();
                if (items.length) { items[0].focus(); }
            });
        },
        close(returnFocus) {
            this.open = false;
            if (returnFocus && this.$refs.trigger) { this.$refs.trigger.focus(); }
        },
        toggle() {
            this.open ? this.close(false) : this.openMenu();
        },
        menuItems() {
            return Array.from(this.$refs.menu.querySelectorAll('[role=menuitemradio]'));
        },
        focusAt(index) {
            const items = this.menuItems();
            if (!items.length) { return; }
            items[(index + items.length) % items.length].focus();
        },
        onKeydown(event) {
            if (! this.open) { return; }
            const items = this.menuItems();
            const index = items.indexOf(document.activeElement);
            if (event.key === 'ArrowDown') { event.preventDefault(); this.focusAt(index + 1); }
            else if (event.key === 'ArrowUp') { event.preventDefault(); this.focusAt(index - 1); }
            else if (event.key === 'Home') { event.preventDefault(); this.focusAt(0); }
            else if (event.key === 'End') { event.preventDefault(); this.focusAt(items.length - 1); }
        }
    }" class="relative inline-block text-left" x-on:keydown="onKeydown($event)">
    <button type="button"
            x-ref="trigger"
            @click="toggle()"
            @keydown.escape.stop="open && close(true)"
            class="icon-btn {{ $size === 'sm' ? 'h-8 w-8 !rounded-xl' : 'h-9 w-9' }}"
            title="Ganti tema (terang / gelap / sistem)"
            aria-label="Pilih tema tampilan"
            aria-haspopup="menu"
            :aria-expanded="open ? 'true' : 'false'">
        <template x-if="current === 'dark'">
            <x-ui.icon name="moon" class="h-4 w-4 text-ink" />
        </template>
        <template x-if="current === 'light'">
            <x-ui.icon name="sun" class="h-4 w-4 text-ink" />
        </template>
        <template x-if="current === 'system'">
            <x-ui.icon name="monitor" class="h-4 w-4 text-muted" />
        </template>
    </button>

    <div x-ref="menu"
         x-show="open"
         x-cloak
         x-transition.duration.120ms
         @click.outside="close(false)"
         @keydown.escape.stop="close(true)"
         role="menu"
         aria-label="Pilih tema tampilan"
         class="absolute right-0 z-50 mt-2 w-40 rounded-2xl border border-line bg-surface p-1.5 shadow-soft">
        <button type="button"
                @click="setTheme('light')"
                role="menuitemradio"
                :aria-checked="current === 'light' ? 'true' : 'false'"
                class="flex w-full items-center gap-2.5 rounded-xl px-2.5 py-1.5 text-xs font-medium transition focus:outline-none focus-visible:ring-2 focus-visible:ring-accent/60"
                :class="current === 'light' ? 'bg-accent/15 text-ink font-semibold' : 'text-muted hover:bg-surface-2/60 hover:text-ink'">
            <x-ui.icon name="sun" class="h-3.5 w-3.5 text-accent" />
            <span class="flex-1 text-left">Terang</span>
            <template x-if="current === 'light'">
                <x-ui.icon name="check" class="h-3.5 w-3.5 text-accent" />
            </template>
        </button>
        <button type="button"
                @click="setTheme('dark')"
                role="menuitemradio"
                :aria-checked="current === 'dark' ? 'true' : 'false'"
                class="flex w-full items-center gap-2.5 rounded-xl px-2.5 py-1.5 text-xs font-medium transition focus:outline-none focus-visible:ring-2 focus-visible:ring-accent/60"
                :class="current === 'dark' ? 'bg-accent/15 text-ink font-semibold' : 'text-muted hover:bg-surface-2/60 hover:text-ink'">
            <x-ui.icon name="moon" class="h-3.5 w-3.5 text-accent" />
            <span class="flex-1 text-left">Gelap</span>
            <template x-if="current === 'dark'">
                <x-ui.icon name="check" class="h-3.5 w-3.5 text-accent" />
            </template>
        </button>
        <button type="button"
                @click="setTheme('system')"
                role="menuitemradio"
                :aria-checked="current === 'system' ? 'true' : 'false'"
                class="flex w-full items-center gap-2.5 rounded-xl px-2.5 py-1.5 text-xs font-medium transition focus:outline-none focus-visible:ring-2 focus-visible:ring-accent/60"
                :class="current === 'system' ? 'bg-accent/15 text-ink font-semibold' : 'text-muted hover:bg-surface-2/60 hover:text-ink'">
            <x-ui.icon name="monitor" class="h-3.5 w-3.5 text-muted" />
            <span class="flex-1 text-left">Sistem</span>
            <template x-if="current === 'system'">
                <x-ui.icon name="check" class="h-3.5 w-3.5 text-accent" />
            </template>
        </button>
    </div>
</div>
