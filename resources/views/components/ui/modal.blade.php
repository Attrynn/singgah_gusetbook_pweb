@props(['name', 'title' => null, 'description' => null, 'maxWidth' => 'max-w-lg'])

@php
    $slug = \Illuminate\Support\Str::slug($name) ?: 'modal';
    $titleId = 'modal-'.$slug.'-title';
    $descriptionId = 'modal-'.$slug.'-description';
@endphp

<div
    x-data="{
        open: false,
        active: false,
        opener: null,
        inertedItems: [],
        init() {
            this.$watch('open', (value) => {
                if (value) {
                    this.activate();
                    this.$nextTick(() => this.focusFirst());
                } else {
                    this.deactivate();
                    this.restoreFocus();
                }
            });
        },
        destroy() {
            this.deactivate();
        },
        activate() {
            if (this.active) { return; }
            this.active = true;
            this.opener = document.activeElement instanceof HTMLElement ? document.activeElement : null;

            const items = [];
            let node = this.$el;

            while (node && node.parentElement && node !== document.body) {
                const parent = node.parentElement;

                Array.from(parent.children).forEach((sibling) => {
                    if (sibling === node) { return; }
                    const tag = sibling.tagName;
                    if (tag === 'SCRIPT' || tag === 'STYLE' || tag === 'LINK' || tag === 'TEMPLATE') { return; }
                    if (sibling.matches('.flatpickr-calendar, .swal2-container, [data-modal-root]')) { return; }

                    items.push({
                        el: sibling,
                        inert: sibling.inert,
                        ariaHidden: sibling.getAttribute('aria-hidden'),
                    });

                    sibling.inert = true;
                    sibling.setAttribute('aria-hidden', 'true');
                });

                node = parent;
            }

            this.inertedItems = items;
        },
        deactivate() {
            this.active = false;
            (this.inertedItems || []).forEach((item) => {
                item.el.inert = item.inert;
                if (item.ariaHidden === null) {
                    item.el.removeAttribute('aria-hidden');
                } else {
                    item.el.setAttribute('aria-hidden', item.ariaHidden);
                }
            });
            this.inertedItems = [];
        },
        restoreFocus() {
            if (this.opener && document.body.contains(this.opener)) {
                this.opener.focus();
            }
            this.opener = null;
        },
        focusables() {
            const panel = this.$refs.panel;
            if (!panel) { return []; }
            return Array.from(panel.querySelectorAll(
                'a[href], button:not([disabled]), input:not([disabled]):not([type=hidden]), select:not([disabled]), textarea:not([disabled])'
            )).filter((el) => el.getAttribute('tabindex') !== '-1' && el.getClientRects().length > 0);
        },
        focusFirst() {
            const panel = this.$refs.panel;
            if (!panel) { return; }
            const items = this.focusables();
            if (items.length) { items[0].focus(); } else { panel.focus(); }
        },
        trapTab(event) {
            const items = this.focusables();
            const panel = this.$refs.panel;
            if (!items.length) {
                event.preventDefault();
                if (panel) { panel.focus(); }
                return;
            }
            const first = items[0];
            const last = items[items.length - 1];
            if (event.shiftKey && document.activeElement === first) {
                event.preventDefault();
                last.focus();
            } else if (!event.shiftKey && document.activeElement === last) {
                event.preventDefault();
                first.focus();
            }
        },
        close() {
            this.open = false;
        }
    }"
    x-on:open-modal-{{ $name }}.window="open = true"
    x-on:close-modal-{{ $name }}.window="open = false"
    @keydown.escape.window="open = false"
    data-modal-root
    x-cloak
>
    <div
        x-show="open"
        class="fixed inset-0 z-50 flex items-end justify-center p-0 sm:items-center sm:p-6"
        role="dialog"
        aria-modal="true"
        @if ($title) aria-labelledby="{{ $titleId }}" @endif
        @if ($description) aria-describedby="{{ $descriptionId }}" @endif
    >
        <div class="absolute inset-0 bg-ink/30 backdrop-blur-sm" x-on:click="close()" aria-hidden="true"></div>

        <div
            x-ref="panel"
            x-show="open"
            x-transition:enter="transition ease-out duration-200"
            x-transition:enter-start="translate-y-6 opacity-0 sm:translate-y-0 sm:scale-95"
            x-transition:enter-end="translate-y-0 opacity-100 sm:scale-100"
            x-transition:leave="transition ease-in duration-150"
            x-transition:leave-start="translate-y-0 opacity-100 sm:scale-100"
            x-transition:leave-end="translate-y-6 opacity-0 sm:translate-y-0 sm:scale-95"
            class="relative w-full {{ $maxWidth }} rounded-t-3xl border border-line bg-surface p-6 shadow-soft sm:rounded-3xl"
            role="document"
            tabindex="-1"
            @keydown.tab="trapTab($event)"
        >
            <div class="flex items-start justify-between gap-4">
                <div>
                    @if ($title)
                        <h2 id="{{ $titleId }}" class="text-lg font-semibold text-ink">{{ $title }}</h2>
                    @endif
                    @if ($description)
                        <p id="{{ $descriptionId }}" class="mt-1 text-sm text-muted">{{ $description }}</p>
                    @endif
                </div>
                <button type="button" class="icon-btn h-9 w-9" x-on:click="close()" aria-label="Tutup">
                    <x-ui.icon name="close" class="h-4 w-4" />
                </button>
            </div>

            <div class="mt-5">
                {{ $slot }}
            </div>

            @isset($footer)
                <div class="mt-6 flex flex-wrap items-center justify-end gap-2">
                    {{ $footer }}
                </div>
            @endisset
        </div>
    </div>
</div>
