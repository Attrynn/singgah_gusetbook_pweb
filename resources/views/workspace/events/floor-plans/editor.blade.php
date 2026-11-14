@extends('layouts.owner')

@section('title', 'Editor Denah · '.$floorPlan->name)

@push('scripts')
    @php
        $editorConfig = [
            'document' => $document,
            'lockVersion' => (int) $version->lock_version,
            'readOnly' => (bool) $isReadOnly,
            'canPublish' => (bool) $canPublish,
            'canAssignSeating' => (bool) $canAssignSeating,
            'canManage' => (bool) $canManage,
            'saveUrl' => $saveUrl,
            'publishUrl' => $publishUrl,
            'validateUrl' => $validateUrl,
            'seatingUrl' => $seatingUrl,
            'backUrl' => $backUrl,
            'floorPlan' => [
                'name' => $floorPlan->name,
                'venue' => $floorPlan->venue?->name,
                'space' => $floorPlan->venueSpace?->name,
            ],
            'event' => [
                'name' => $event->name,
                'couple' => $event->coupleNames(),
            ],
            'version' => [
                'id' => $version->id,
                'number' => $version->version_number,
                'status' => $version->status->value,
            ],
            'tableSummary' => $tableSummary,
            'objectTypes' => collect($objectTypes)->map(fn ($type) => ['name' => $type->name, 'value' => $type->value])->values(),
            'zoneTypes' => collect($zoneTypes)->map(fn ($type) => ['name' => $type->name, 'value' => $type->value])->values(),
            'guests' => $guests->map(fn ($guest) => [
                'id' => $guest->id,
                'name' => $guest->name,
                'max_attendees' => (int) $guest->max_attendees,
                'active_members' => $guest->activeMembers->map(fn ($member) => [
                    'id' => $member->id,
                    'name' => $member->name,
                ])->values(),
            ])->values(),
        ];
    @endphp
    <script nonce="{{ request()->attributes->get('csp_nonce') }}">
        window.__floorPlanEditor = @json($editorConfig);
    </script>
    @vite('resources/js/editor.js')
@endpush

@section('content')
<div class="fp-editor-shell"
     x-data="floorPlanEditor(window.__floorPlanEditor || {})">
    <div class="fp-editor">

        {{-- ============================= TOOLBAR ============================= --}}
        <header class="fp-toolbar">
            <div class="fp-toolbar-start">
                <a href="{{ $backUrl }}" class="icon-btn h-9 w-9" title="Kembali ke denah" aria-label="Kembali">
                    <x-ui.icon name="arrow-left" class="h-4 w-4" />
                </a>

                <div class="fp-title">
                    <p class="fp-title-name">{{ $floorPlan->name }}</p>
                    <p class="fp-title-sub">
                        <span>{{ $event->coupleNames() }}</span>
                        <span class="fp-dot" aria-hidden="true">·</span>
                        <span>Versi {{ $version->version_number }}</span>
                        @if ($version->isDraft())
                            <span class="badge-neutral ml-1">Draf</span>
                        @else
                            <span class="badge-info ml-1">{{ ucfirst($version->status->value) }}</span>
                        @endif
                    </p>
                </div>
            </div>

            <div class="fp-toolbar-center">
                <div class="fp-toolgroup" role="group" aria-label="Riwayat">
                    <button type="button" class="fp-tool" @click="undo()" :disabled="!canUndo" title="Undo (Ctrl+Z)">
                        <x-ui.icon name="refresh" class="h-4 w-4 -scale-x-100" />
                    </button>
                    <button type="button" class="fp-tool" @click="redo()" :disabled="!canRedo" title="Redo (Ctrl+Shift+Z)">
                        <x-ui.icon name="refresh" class="h-4 w-4" />
                    </button>
                </div>

                <div class="fp-toolgroup" role="group" aria-label="Zoom">
                    <button type="button" class="fp-tool" @click="zoomOut()" title="Perkecil">
                        <span class="text-base leading-none">−</span>
                    </button>
                    <button type="button" class="fp-zoom-label" @click="resetZoom()" title="Reset zoom" x-text="formatZoom()">100%</button>
                    <button type="button" class="fp-tool" @click="zoomIn()" title="Perbesar">
                        <span class="text-base leading-none">+</span>
                    </button>
                    <button type="button" class="fp-tool" @click="fitToScreen()" title="Paskan ke layar">
                        <x-ui.icon name="grid" class="h-4 w-4" />
                    </button>
                    <button type="button" class="fp-tool" @click="zoomToSelection()" :disabled="!hasSelection" title="Zoom ke pilihan">
                        <x-ui.icon name="search" class="h-4 w-4" />
                    </button>
                </div>

                <div class="fp-toolgroup" role="group" aria-label="Bantuan tampilan">
                    <button type="button" class="fp-tool" :class="gridVisible ? 'is-on' : ''" @click="toggleGrid()" :disabled="readOnly" title="Tampilkan grid">
                        <x-ui.icon name="grid" class="h-4 w-4" />
                    </button>
                    <button type="button" class="fp-tool" :class="snapEnabled ? 'is-on' : ''" @click="toggleSnap()" :disabled="readOnly" title="Snap ke grid">
                        <x-ui.icon name="pin" class="h-4 w-4" />
                    </button>
                </div>
            </div>

            <div class="fp-toolbar-end">
                <span class="badge" :class="saveLabelClass" :title="savedAtLabel ? ('Tersimpan '+savedAtLabel) : ''">
                    <span x-show="saveState==='saving'" class="fp-spinner" aria-hidden="true"></span>
                    <span x-text="saveLabel"></span>
                </span>

                <button type="button" class="btn-secondary btn-sm hidden lg:inline-flex" @click="runValidation()" :disabled="validating">
                    <x-ui.icon name="shield" class="h-3.5 w-3.5" />
                    <span>Validasi</span>
                </button>

                @if ($canPublish)
                    <button type="button" class="btn-primary btn-sm" @click="openPublish()" :disabled="readOnly">
                        <x-ui.icon name="send" class="h-3.5 w-3.5" />
                        <span class="hidden sm:inline">Terbitkan</span>
                    </button>
                @endif

                <button type="button" class="fp-tool lg:hidden" @click="leftOpen = !leftOpen" title="Perpustakaan">
                    <x-ui.icon name="list" class="h-4 w-4" />
                </button>
                <button type="button" class="fp-tool lg:hidden" @click="rightOpen = !rightOpen" title="Properti">
                    <x-ui.icon name="sliders" class="h-4 w-4" />
                </button>
            </div>
        </header>

        {{-- ============================= BODY ============================= --}}
        <div class="fp-body">

            {{-- ---------------------------- LEFT RAIL ---------------------------- --}}
            <aside class="fp-rail fp-rail-left" :class="leftOpen ? 'is-open' : ''">
                <div class="fp-rail-head">
                    <div class="fp-tabs">
                        <button type="button" class="fp-tab" :class="leftTab==='library' ? 'is-active' : ''" @click="leftTab='library'">Perpustakaan</button>
                        <button type="button" class="fp-tab" :class="leftTab==='layers' ? 'is-active' : ''" @click="leftTab='layers'">Lapisan</button>
                    </div>
                    <button type="button" class="fp-tool lg:hidden" @click="leftOpen=false" aria-label="Tutup">
                        <x-ui.icon name="close" class="h-4 w-4" />
                    </button>
                </div>

                {{-- Library --}}
                <div class="fp-rail-scroll" x-show="leftTab==='library'">
                    <div class="px-3 pt-3">
                        <div class="relative">
                            <x-ui.icon name="search" class="pointer-events-none absolute left-3 top-1/2 h-3.5 w-3.5 -translate-y-1/2 text-faint" />
                            <input type="search" class="input input-sm pl-8" placeholder="Cari objek…" x-model="librarySearch" :disabled="readOnly">
                        </div>
                    </div>

                    <template x-for="group in libraryGroups" :key="group.key">
                        <div class="fp-library-group">
                            <button type="button" class="fp-library-head" @click="toggleLibraryGroup(group.key)">
                                <span class="inline-flex" :class="libraryExpanded[group.key] ? '' : '-rotate-90'">
                                    <x-ui.icon name="chevron-down" class="h-3.5 w-3.5 transition-transform" />
                                </span>
                                <span x-text="group.label"></span>
                                <span class="fp-library-count" x-text="group.items.length"></span>
                            </button>
                            <div class="fp-library-items" x-show="libraryExpanded[group.key]">
                                <template x-for="item in group.items" :key="item.type">
                                    <button type="button"
                                            class="fp-library-item"
                                            draggable="true"
                                            @dragstart="onLibraryDragStart($event, item.type)"
                                            @click="addObject(item.type)"
                                            :disabled="readOnly"
                                            :title="'Tambah ' + item.label">
                                        <span class="fp-library-glyph" x-text="item.label.slice(0,1)"></span>
                                        <span class="fp-library-label" x-text="item.label"></span>
                                        <x-ui.icon name="plus" class="h-3.5 w-3.5 text-faint" />
                                    </button>
                                </template>
                            </div>
                        </div>
                    </template>

                    <p class="px-3 py-4 text-xs leading-relaxed text-faint">
                        Klik untuk menambah di tengah layar, atau seret ke kanvas.
                    </p>
                </div>

                {{-- Layers --}}
                <div class="fp-rail-scroll" x-show="leftTab==='layers'">
                    <template x-if="layers.length === 0 && zoneLayers.length === 0">
                        <div class="fp-empty">
                            <x-ui.icon name="list" class="h-6 w-6 text-faint" />
                            <p>Belum ada objek.</p>
                        </div>
                    </template>

                    <div class="fp-layer-list">
                        <template x-for="obj in layers" :key="obj.id">
                            <div class="fp-layer-row" :class="selection.includes(obj.id) ? 'is-selected' : ''" @click="selectObject(obj.id)">
                                <button type="button" class="fp-layer-eye" @click.stop="toggleObjectVisibility(obj.id)" :title="obj.visible ? 'Sembunyikan' : 'Tampilkan'">
                                    <span class="inline-flex" :class="obj.visible ? '' : 'opacity-30'">
                                        <x-ui.icon name="eye" class="h-3.5 w-3.5" />
                                    </span>
                                </button>
                                <span class="fp-layer-name" :class="obj.visible ? '' : 'line-through opacity-60'" x-text="obj.label"></span>
                                <span class="fp-layer-type" x-text="objectLabel(obj.type)"></span>
                                <button type="button" class="fp-layer-lock" @click.stop="toggleObjectLock(obj.id)" :title="obj.locked ? 'Buka kunci' : 'Kunci'">
                                    <span class="inline-flex" :class="obj.locked ? 'text-accent' : 'opacity-30'">
                                        <x-ui.icon name="lock" class="h-3.5 w-3.5" />
                                    </span>
                                </button>
                            </div>
                        </template>
                    </div>

                    <template x-if="zoneLayers.length > 0">
                        <div class="fp-layer-zone-head">Zona</div>
                    </template>
                    <div class="fp-layer-list">
                        <template x-for="zone in zoneLayers" :key="zone.id">
                            <div class="fp-layer-row" :class="activeZoneId===zone.id ? 'is-selected' : ''" @click="selectZone(zone.id)">
                                <button type="button" class="fp-layer-eye" @click.stop="toggleZoneVisibility(zone.id)">
                                    <span class="inline-flex" :class="zone.visible ? '' : 'opacity-30'">
                                        <x-ui.icon name="eye" class="h-3.5 w-3.5" />
                                    </span>
                                </button>
                                <span class="fp-layer-swatch" :style="'background:'+zone.color"></span>
                                <span class="fp-layer-name" x-text="zone.name"></span>
                                <span class="fp-layer-type" x-text="zoneLabel(zone.type)"></span>
                            </div>
                        </template>
                    </div>
                </div>
            </aside>

            {{-- ---------------------------- CANVAS ---------------------------- --}}
            <div class="fp-canvas-wrap"
                 @dragover.prevent
                 @drop.prevent="onCanvasDrop($event)">
                <div x-ref="canvasHost" class="fp-canvas-host" id="floorplan-canvas"></div>

                {{-- Empty state --}}
                <div class="fp-canvas-empty" x-show="objectCount === 0 && zoneCount === 0 && !zoneDrawMode">
                    <div class="fp-empty-card">
                        <span class="fp-empty-icon"><x-ui.icon name="grid" class="h-7 w-7" /></span>
                        <h2>Kanvas kosong</h2>
                        <p>Mulai dari nol. Tambahkan meja, kursi, panggung, atau zona dari perpustakaan di kiri.</p>
                        <div class="fp-empty-actions">
                            <button type="button" class="btn-primary btn-sm" @click="addObject('round_table')" :disabled="readOnly">
                                <x-ui.icon name="plus" class="h-3.5 w-3.5" /> Meja Bulat
                            </button>
                            <button type="button" class="btn-secondary btn-sm" @click="addObject('stage')" :disabled="readOnly">
                                <x-ui.icon name="plus" class="h-3.5 w-3.5" /> Panggung
                            </button>
                            <button type="button" class="btn-ghost btn-sm" @click="leftTab='library'; leftOpen=true">
                                Lihat semua
                            </button>
                        </div>
                    </div>
                </div>

                {{-- Zone drawing hint --}}
                <div class="fp-draw-hint" x-show="zoneDrawMode" x-cloak>
                    <template x-if="zoneDrawMode==='rect'">
                        <span>Seret di kanvas untuk menggambar zona persegi.</span>
                    </template>
                    <template x-if="zoneDrawMode==='polygon'">
                        <span>Klik untuk menambah titik. Klik dua kali atau tekan Enter untuk selesai.</span>
                    </template>
                    <button type="button" class="btn-ghost btn-sm" @click="cancelZoneDraw()">Batal (Esc)</button>
                </div>

                {{-- Floating zone tools --}}
                <div class="fp-float-tools" x-show="!readOnly && !zoneDrawMode">
                    <button type="button" class="fp-tool" @click="startZoneDraw('rect')" title="Gambar zona persegi">
                        <x-ui.icon name="grid" class="h-4 w-4" />
                    </button>
                    <button type="button" class="fp-tool" @click="startZoneDraw('polygon')" title="Gambar zona poligon">
                        <x-ui.icon name="star" class="h-4 w-4" />
                    </button>
                </div>

                {{-- Conflict banner --}}
                <div class="fp-conflict" x-show="saveState==='conflict'" x-cloak>
                    <x-ui.icon name="alert" class="h-4 w-4" />
                    <div class="fp-conflict-text">
                        <strong>Konflik versi.</strong>
                        <span>Draf ini telah diubah di tempat lain. Muat ulang untuk melanjutkan dengan versi terbaru.</span>
                    </div>
                    <button type="button" class="btn-secondary btn-sm" @click="reloadForConflict()">Muat ulang</button>
                </div>

                {{-- Save error banner --}}
                <div class="fp-save-error" x-show="saveState==='error'" x-cloak>
                    <x-ui.icon name="alert" class="h-4 w-4" />
                    <span x-text="saveError || 'Gagal menyimpan.'"></span>
                    <button type="button" class="btn-secondary btn-sm" @click="retrySave()">Coba lagi</button>
                </div>
            </div>

            {{-- ---------------------------- RIGHT RAIL ---------------------------- --}}
            <aside class="fp-rail fp-rail-right" :class="rightOpen ? 'is-open' : ''">
                <div class="fp-rail-head">
                    <div class="fp-tabs">
                        <button type="button" class="fp-tab" :class="rightTab==='properties' ? 'is-active' : ''" @click="rightTab='properties'">Properti</button>
                        <button type="button" class="fp-tab" :class="rightTab==='seating' ? 'is-active' : ''" @click="rightTab='seating'">Seating</button>
                        <button type="button" class="fp-tab" :class="rightTab==='validation' ? 'is-active' : ''" @click="rightTab='validation'">
                            Validasi
                            <span class="fp-tab-count" x-show="validationCounts.error > 0" x-text="validationCounts.error"></span>
                        </button>
                    </div>
                    <button type="button" class="fp-tool lg:hidden" @click="rightOpen=false" aria-label="Tutup">
                        <x-ui.icon name="close" class="h-4 w-4" />
                    </button>
                </div>

                <div class="fp-rail-scroll">

                    {{-- ===== PROPERTIES ===== --}}
                    <div x-show="rightTab==='properties'">

                        {{-- Zone properties --}}
                        <template x-if="selectedZone">
                            <div class="fp-props">
                                <div class="fp-props-head">
                                    <span class="fp-props-swatch" :style="'background:'+selectedZone.color"></span>
                                    <div>
                                        <p class="fp-props-title" x-text="selectedZone.name"></p>
                                        <p class="fp-props-type">Zona · <span x-text="zoneLabel(selectedZone.type)"></span></p>
                                    </div>
                                    <button type="button" class="fp-tool ml-auto text-danger" @click="deleteZone()" :disabled="readOnly" title="Hapus zona">
                                        <x-ui.icon name="trash" class="h-4 w-4" />
                                    </button>
                                </div>

                                <label class="fp-field">
                                    <span class="label">Nama zona</span>
                                    <input type="text" class="input input-sm" :value="selectedZone.name" @change="updateZoneField('name', $event.target.value)" :disabled="readOnly">
                                </label>

                                <label class="fp-field">
                                    <span class="label">Tipe zona</span>
                                    <select class="input input-sm" :value="selectedZone.type" @change="updateZoneField('type', $event.target.value)" :disabled="readOnly">
                                        <template x-for="zt in zoneTypesList()" :key="zt">
                                            <option :value="zt" x-text="zoneLabel(zt)"></option>
                                        </template>
                                    </select>
                                </label>

                                <div class="fp-field-row">
                                    <label class="fp-field">
                                        <span class="label">Warna</span>
                                        <input type="color" class="fp-color" :value="selectedZone.color" @input="updateZoneField('color', $event.target.value)" :disabled="readOnly">
                                    </label>
                                    <label class="fp-field">
                                        <span class="label">Kapasitas</span>
                                        <input type="number" class="input input-sm" min="0" :value="selectedZone.capacity" @change="updateZoneField('capacity', $event.target.value)" :disabled="readOnly">
                                    </label>
                                </div>

                                <label class="fp-check">
                                    <input type="checkbox" :checked="selectedZone.visible" @change="updateZoneField('visible', $event.target.checked)" :disabled="readOnly">
                                    <span>Tampilkan zona</span>
                                </label>

                                <p class="hint">Geometri: <span x-text="selectedZone.geometry.kind === 'polygon' ? (selectedZone.geometry.points.length + ' titik') : 'persegi'"></span></p>
                            </div>
                        </template>

                        {{-- Single object --}}
                        <template x-if="!selectedZone && selectedObject">
                            <div class="fp-props">
                                <div class="fp-props-head">
                                    <span class="fp-props-glyph" x-text="objectLabel(selectedObject.type).slice(0,1)"></span>
                                    <div class="min-w-0">
                                        <p class="fp-props-title" x-text="selectedObject.label"></p>
                                        <p class="fp-props-type" x-text="objectLabel(selectedObject.type)"></p>
                                    </div>
                                </div>

                                {{-- Capacity mismatch warning --}}
                                <template x-if="capacityMismatch">
                                    <div class="fp-warn">
                                        <x-ui.icon name="alert" class="h-4 w-4 shrink-0" />
                                        <span>Kapasitas (<span x-text="selectedObject.properties.capacity"></span>) tidak sama dengan jumlah kursi (<span x-text="selectedTableChairs.length"></span>).</span>
                                    </div>
                                </template>

                                <label class="fp-field">
                                    <span class="label">Label</span>
                                    <input type="text" class="input input-sm" maxlength="120" :value="selectedObject.label" @change="updateSelected('label', $event.target.value)" :disabled="readOnly">
                                </label>

                                {{-- Table-specific --}}
                                <template x-if="isTableType(selectedObject.type)">
                                    <div class="space-y-3">
                                        <div class="fp-field-row">
                                            <label class="fp-field">
                                                <span class="label">Nomor meja</span>
                                                <input type="text" class="input input-sm" :value="selectedObject.properties.table_number || ''" @change="updateSelectedProperty('table_number', $event.target.value)" :disabled="readOnly">
                                            </label>
                                            <label class="fp-field">
                                                <span class="label">Bentuk</span>
                                                <input type="text" class="input input-sm" :value="objectLabel(selectedObject.type)" disabled>
                                            </label>
                                        </div>

                                        <div class="fp-field-row">
                                            <label class="fp-field">
                                                <span class="label">Kapasitas</span>
                                                <input type="number" class="input input-sm" min="0" max="200" :value="selectedObject.properties.capacity" @change="updateSelectedProperty('capacity', $event.target.value)" :disabled="readOnly">
                                            </label>
                                            <label class="fp-field">
                                                <span class="label">Warna</span>
                                                <input type="color" class="fp-color" :value="selectedObject.properties.color || '#f5f5f5'" @input="updateSelectedProperty('color', $event.target.value)" :disabled="readOnly">
                                            </label>
                                        </div>

                                        <button type="button" class="btn-secondary btn-sm w-full" @click="promptGenerateChairs()" :disabled="readOnly">
                                            <x-ui.icon name="plus" class="h-3.5 w-3.5" />
                                            <span x-text="selectedTableChairs.length ? 'Atur ulang kursi' : 'Buat kursi otomatis'"></span>
                                        </button>

                                        <p class="hint" x-show="selectedTableChairs.length">
                                            <span x-text="selectedTableChairs.length"></span> kursi terpasang pada meja ini.
                                        </p>
                                    </div>
                                </template>

                                {{-- Chair-specific --}}
                                <template x-if="isChairType(selectedObject.type)">
                                    <div class="fp-field-row">
                                        <label class="fp-field">
                                            <span class="label">Nomor kursi</span>
                                            <input type="number" class="input input-sm" min="0" :value="selectedObject.properties.seat_index ?? 0" @change="updateSelectedProperty('seat_index', $event.target.value)" :disabled="readOnly">
                                        </label>
                                        <label class="fp-field">
                                            <span class="label">Meja</span>
                                            <input type="text" class="input input-sm" :value="selectedObject.properties.table_id ? 'Terhubung' : '—'" disabled>
                                        </label>
                                    </div>
                                </template>

                                {{-- Geometry --}}
                                <div class="fp-field-row">
                                    <label class="fp-field">
                                        <span class="label">Posisi X</span>
                                        <input type="number" class="input input-sm" :value="Math.round(selectedObject.x)" @change="updateSelected('x', $event.target.value)" :disabled="readOnly">
                                    </label>
                                    <label class="fp-field">
                                        <span class="label">Posisi Y</span>
                                        <input type="number" class="input input-sm" :value="Math.round(selectedObject.y)" @change="updateSelected('y', $event.target.value)" :disabled="readOnly">
                                    </label>
                                </div>

                                <div class="fp-field-row">
                                    <label class="fp-field">
                                        <span class="label">Lebar</span>
                                        <input type="number" class="input input-sm" min="1" :value="Math.round(selectedObject.width)" @change="updateSelected('width', $event.target.value)" :disabled="readOnly">
                                    </label>
                                    <label class="fp-field">
                                        <span class="label">Tinggi</span>
                                        <input type="number" class="input input-sm" min="1" :value="Math.round(selectedObject.height)" @change="updateSelected('height', $event.target.value)" :disabled="readOnly">
                                    </label>
                                </div>

                                <div class="fp-field-row">
                                    <label class="fp-field">
                                        <span class="label">Rotasi (°)</span>
                                        <input type="number" class="input input-sm" step="1" :value="Math.round(selectedObject.rotation)" @change="updateSelected('rotation', $event.target.value)" :disabled="readOnly">
                                    </label>
                                    <label class="fp-field">
                                        <span class="label">Lapisan (z)</span>
                                        <input type="number" class="input input-sm" :value="selectedObject.z_index" @change="updateSelected('z_index', $event.target.value)" :disabled="readOnly">
                                    </label>
                                </div>

                                {{-- Generic color --}}
                                <template x-if="!isTableType(selectedObject.type)">
                                    <label class="fp-field">
                                        <span class="label">Warna</span>
                                        <input type="color" class="fp-color" :value="selectedObject.properties.color || '#eceae4'" @input="updateSelectedProperty('color', $event.target.value)" :disabled="readOnly">
                                    </label>
                                </template>

                                <div class="fp-field-row">
                                    <label class="fp-check">
                                        <input type="checkbox" :checked="selectedObject.visible" @change="updateSelected('visible', $event.target.checked)" :disabled="readOnly">
                                        <span>Tampil</span>
                                    </label>
                                    <label class="fp-check">
                                        <input type="checkbox" :checked="selectedObject.locked" @change="updateSelected('locked', $event.target.checked)" :disabled="readOnly">
                                        <span>Kunci</span>
                                    </label>
                                </div>

                                <div class="fp-btn-grid">
                                    <button type="button" class="btn-ghost btn-sm" @click="bringToFront()" :disabled="readOnly">Ke depan</button>
                                    <button type="button" class="btn-ghost btn-sm" @click="bringForward()" :disabled="readOnly">Maju</button>
                                    <button type="button" class="btn-ghost btn-sm" @click="sendBackward()" :disabled="readOnly">Mundur</button>
                                    <button type="button" class="btn-ghost btn-sm" @click="sendToBack()" :disabled="readOnly">Ke belakang</button>
                                </div>

                                <div class="fp-btn-grid">
                                    <button type="button" class="btn-secondary btn-sm" @click="duplicateSelected()" :disabled="readOnly">
                                        <x-ui.icon name="copy" class="h-3.5 w-3.5" /> Duplikat
                                    </button>
                                    <button type="button" class="btn-danger btn-sm" @click="deleteSelected()" :disabled="readOnly">
                                        <x-ui.icon name="trash" class="h-3.5 w-3.5" /> Hapus
                                    </button>
                                </div>
                            </div>
                        </template>

                        {{-- Multi selection --}}
                        <template x-if="!selectedZone && selectionCount > 1">
                            <div class="fp-props">
                                <div class="fp-props-head">
                                    <span class="fp-props-glyph" x-text="selectionCount"></span>
                                    <div>
                                        <p class="fp-props-title" x-text="selectionCount + ' objek dipilih'"></p>
                                        <p class="fp-props-type">Pilih tindakan massal</p>
                                    </div>
                                </div>

                                <div class="fp-btn-grid">
                                    <button type="button" class="btn-ghost btn-sm" @click="bringToFront()" :disabled="readOnly">Ke depan</button>
                                    <button type="button" class="btn-ghost btn-sm" @click="sendToBack()" :disabled="readOnly">Ke belakang</button>
                                    <button type="button" class="btn-ghost btn-sm" @click="groupSelected()" :disabled="readOnly">Grup</button>
                                    <button type="button" class="btn-ghost btn-sm" @click="ungroupSelected()" :disabled="readOnly">Ungroup</button>
                                    <button type="button" class="btn-ghost btn-sm" @click="toggleLock()" :disabled="readOnly">Kunci</button>
                                    <button type="button" class="btn-ghost btn-sm" @click="toggleVisibility()" :disabled="readOnly">Sembunyikan</button>
                                </div>

                                <div class="fp-btn-grid">
                                    <button type="button" class="btn-secondary btn-sm" @click="duplicateSelected()" :disabled="readOnly">Duplikat</button>
                                    <button type="button" class="btn-danger btn-sm" @click="deleteSelected()" :disabled="readOnly">Hapus</button>
                                </div>
                            </div>
                        </template>

                        {{-- Nothing selected --}}
                        <template x-if="!selectedZone && selectionCount === 0">
                            <div class="fp-empty">
                                <x-ui.icon name="sliders" class="h-6 w-6 text-faint" />
                                <p>Pilih objek atau zona untuk mengubah propertinya.</p>
                                <p class="fp-empty-sub">Tip: Shift+klik untuk memilih beberapa objek, atau seret kotak di kanvas.</p>
                            </div>
                        </template>
                    </div>

                    {{-- ===== SEATING ===== --}}
                    <div x-show="rightTab==='seating'" class="fp-seating">
                        <div class="fp-seat-table-head">
                            <p class="section-kicker">Penempatan Tamu</p>
                            <p class="hint mt-1">Pilih meja, lalu tempatkan tamu atau anggota party.</p>
                        </div>

                        <template x-if="tableList.length === 0">
                            <div class="fp-empty">
                                <x-ui.icon name="users" class="h-6 w-6 text-faint" />
                                <p>Belum ada meja.</p>
                                <p class="fp-empty-sub">Tambahkan meja dari perpustakaan untuk mulai mengatur seating.</p>
                            </div>
                        </template>

                        <template x-if="tableList.length > 0">
                            <div class="fp-table-picker">
                                <template x-for="t in tableList" :key="t.id">
                                    <button type="button" class="fp-table-chip" :class="selectedTableId===t.id ? 'is-active' : ''" @click="selectTableForSeating(t.id)">
                                        <span class="fp-table-chip-name" x-text="t.label"></span>
                                        <span class="fp-table-chip-cap">
                                            <span x-text="t.assigned"></span>/<span x-text="t.capacity"></span>
                                        </span>
                                    </button>
                                </template>
                            </div>
                        </template>

                        <template x-if="selectedTableId && activeTableSummary">
                            <div class="fp-seat-body">
                                <div class="fp-seat-meter">
                                    <div class="fp-seat-meter-bar">
                                        <div class="fp-seat-meter-fill" :style="'width:'+Math.min(100, (activeTableSummary.assigned/Math.max(1,activeTableSummary.capacity))*100)+'%'"></div>
                                    </div>
                                    <div class="fp-seat-meter-legend">
                                        <span>Terisi <strong x-text="activeTableSummary.assigned"></strong></span>
                                        <span>Sisa <strong x-text="activeTableSummary.remaining"></strong></span>
                                        <span>Kursi <strong x-text="activeTableSummary.chairs"></strong></span>
                                    </div>
                                </div>

                                {{-- Existing assignments --}}
                                <template x-if="tableAssignments.length > 0">
                                    <div class="fp-assign-list">
                                        <p class="label">Sudah ditempatkan</p>
                                        <template x-for="a in tableAssignments" :key="a.id">
                                            <div class="fp-assign-row">
                                                <div class="min-w-0">
                                                    <p class="fp-assign-name" x-text="a.member_name || a.guest_name"></p>
                                                    <p class="fp-assign-sub" x-text="a.member_name ? (a.guest_name + ' · anggota') : (a.headcount + ' orang')"></p>
                                                </div>
                                                <button type="button" class="fp-tool text-danger" @click="releaseAssignment(a)" :disabled="readOnly || !canAssignSeating" title="Lepas">
                                                    <x-ui.icon name="x" class="h-3.5 w-3.5" />
                                                </button>
                                            </div>
                                        </template>
                                    </div>
                                </template>

                                {{-- Guest picker --}}
                                <div class="fp-seat-guest-head">
                                    <p class="label mb-0">Tambah tamu</p>
                                    <div class="fp-headcount">
                                        <span>Kursi</span>
                                        <input type="number" min="1" max="100" class="input input-sm w-16" x-model="assignHeadcount" :disabled="readOnly || !canAssignSeating">
                                    </div>
                                </div>

                                <div class="relative mb-2">
                                    <x-ui.icon name="search" class="pointer-events-none absolute left-3 top-1/2 h-3.5 w-3.5 -translate-y-1/2 text-faint" />
                                    <input type="search" class="input input-sm pl-8" placeholder="Cari tamu…" x-model="guestSearch">
                                </div>

                                <template x-if="filteredGuests.length === 0">
                                    <p class="fp-empty-sub py-4 text-center">Tidak ada tamu yang cocok.</p>
                                </template>

                                <div class="fp-guest-list">
                                    <template x-for="g in filteredGuests" :key="g.id">
                                        <div class="fp-guest-row">
                                            <button type="button" class="fp-guest-main" @click="g.members.length ? toggleGuestExpand(g.id) : assignGuest(g)">
                                                <span class="fp-guest-name" x-text="g.name"></span>
                                                <span class="fp-guest-meta">
                                                    <span x-show="g.members.length" x-text="g.members.length + ' anggota'"></span>
                                                    <span x-show="!g.members.length" x-text="'hingga ' + g.maxAttendees + ' orang'"></span>
                                                </span>
                                            </button>
                                            <button type="button" class="fp-tool" @click="assignGuest(g)" :disabled="readOnly || !canAssignSeating || activeTableSummary.remaining < 1" title="Tempatkan party">
                                                <x-ui.icon name="plus" class="h-3.5 w-3.5" />
                                            </button>
                                        </div>

                                        <template x-if="expandedGuestId===g.id">
                                            <div class="fp-member-list">
                                                <template x-for="m in g.members" :key="m.id">
                                                    <div class="fp-member-row">
                                                        <span x-text="m.name"></span>
                                                        <button type="button" class="btn-ghost btn-sm" @click="assignGuest(g, m)" :disabled="readOnly || !canAssignSeating || activeTableSummary.remaining < 1">
                                                            Tempatkan
                                                        </button>
                                                    </div>
                                                </template>
                                                <button type="button" class="fp-member-party" @click="assignGuest(g)" :disabled="readOnly || !canAssignSeating || activeTableSummary.remaining < 1">
                                                    Tempatkan party (placeholder)
                                                </button>
                                            </div>
                                        </template>
                                    </template>
                                </div>
                            </div>
                        </template>

                        <template x-if="!selectedTableId">
                            <p class="fp-empty-sub px-3 py-3">Pilih salah satu meja di atas.</p>
                        </template>
                    </div>

                    {{-- ===== VALIDATION ===== --}}
                    <div x-show="rightTab==='validation'" class="fp-validation">
                        <div class="fp-validation-head">
                            <button type="button" class="btn-secondary btn-sm" @click="runValidation()" :disabled="validating">
                                <span class="inline-flex" :class="validating ? 'animate-spin' : ''">
                                    <x-ui.icon name="refresh" class="h-3.5 w-3.5" />
                                </span>
                                <span x-text="validating ? 'Memeriksa…' : 'Periksa ulang'"></span>
                            </button>
                        </div>

                        <div class="fp-val-counts">
                            <span class="badge-danger" x-text="validationCounts.error + ' error'"></span>
                            <span class="badge-warning" x-text="validationCounts.warning + ' peringatan'"></span>
                            <span class="badge-neutral" x-text="validationCounts.info + ' info'"></span>
                        </div>

                        <template x-if="issues.length === 0">
                            <div class="fp-empty">
                                <x-ui.icon name="check" class="h-6 w-6 text-success" />
                                <p>Tidak ada masalah ditemukan.</p>
                            </div>
                        </template>

                        <template x-for="group in [['error','Error'],['warning','Peringatan'],['info','Info']]" :key="group[0]">
                            <div x-show="groupedIssues[group[0]].length > 0" class="fp-issue-group">
                                <p class="fp-issue-group-title" x-text="group[1]"></p>
                                <template x-for="issue in groupedIssues[group[0]]" :key="issue.code + (issue.object_id || '') + issue.message">
                                    <button type="button" class="fp-issue" :class="'is-'+issue.severity" @click="focusIssue(issue)">
                                        <span class="fp-issue-dot"></span>
                                        <span class="fp-issue-msg" x-text="issue.message"></span>
                                        <span class="inline-flex shrink-0 text-faint" x-show="issue.object_id">
                                            <x-ui.icon name="chevron-right" class="h-3.5 w-3.5" />
                                        </span>
                                    </button>
                                </template>
                            </div>
                        </template>
                    </div>
                </div>
            </aside>

            {{-- Mobile rail backdrop --}}
            <div class="fp-rail-backdrop" x-show="leftOpen || rightOpen" x-cloak @click="leftOpen=false; rightOpen=false"></div>
        </div>

        {{-- ============================= STATUS BAR ============================= --}}
        <footer class="fp-status">
            <div class="fp-status-left">
                <span><strong x-text="objectCount"></strong> objek</span>
                <span class="fp-dot" aria-hidden="true">·</span>
                <span><strong x-text="tableCount"></strong> meja</span>
                <span class="fp-dot" aria-hidden="true">·</span>
                <span><strong x-text="chairCount"></strong> kursi</span>
                <span class="fp-dot" aria-hidden="true">·</span>
                <span><strong x-text="zoneCount"></strong> zona</span>
                <span class="fp-dot" aria-hidden="true">·</span>
                <span>kapasitas <strong x-text="capacityTotal"></strong></span>
            </div>
            <div class="fp-status-right">
                <span x-show="hasSelection" x-text="selectionCount + ' dipilih'"></span>
                <span x-show="!hasSelection" class="text-faint">Spasi + seret untuk geser kanvas</span>
                <span class="fp-status-zoom" x-text="formatZoom()"></span>
            </div>
        </footer>
    </div>

    {{-- ============================= CHAIR MODAL ============================= --}}
    <div class="fp-modal" x-show="chairPrompt.open" x-cloak @click.self="chairPrompt.open=false">
        <div class="fp-modal-card">
            <div class="fp-modal-head">
                <h3>Atur kursi otomatis</h3>
                <button type="button" class="fp-tool" @click="chairPrompt.open=false"><x-ui.icon name="close" class="h-4 w-4" /></button>
            </div>

            <template x-if="chairPrompt.hasManual">
                <div class="fp-warn">
                    <x-ui.icon name="alert" class="h-4 w-4 shrink-0" />
                    <span>Beberapa kursi telah dipindah manual. Meregenerasi semua kursi akan menimpa perubahan tersebut.</span>
                </div>
            </template>

            <div class="fp-field-row">
                <label class="fp-field">
                    <span class="label">Jumlah kursi</span>
                    <input type="number" class="input input-sm" min="0" max="200" x-model.number="chairPrompt.capacity">
                </label>
                <label class="fp-field">
                    <span class="label">Jarak (cm)</span>
                    <input type="number" class="input input-sm" min="0" x-model.number="chairPrompt.spacing">
                </label>
            </div>

            <label class="fp-field">
                <span class="label">Sudut awal (°)</span>
                <input type="number" class="input input-sm" step="15" x-model.number="chairPrompt.startAngle">
            </label>

            <div class="fp-modal-actions">
                <button type="button" class="btn-ghost btn-sm" @click="chairPrompt.open=false">Batal</button>
                <template x-if="chairPrompt.hasManual">
                    <button type="button" class="btn-secondary btn-sm" @click="applyChairs('keep')">Pertahankan kursi manual</button>
                </template>
                <button type="button" class="btn-primary btn-sm" @click="applyChairs('regenerate')">Regenerasi semua</button>
            </div>
        </div>
    </div>

    {{-- ============================= PUBLISH MODAL ============================= --}}
    <div class="fp-modal" x-show="publishOpen" x-cloak @click.self="closePublish()">
        <div class="fp-modal-card">
            <div class="fp-modal-head">
                <h3>Terbitkan denah</h3>
                <button type="button" class="fp-tool" @click="closePublish()"><x-ui.icon name="close" class="h-4 w-4" /></button>
            </div>

            <template x-if="!publishDone">
                <div class="space-y-3">
                    <p class="text-sm text-muted">Versi {{ $version->version_number }} akan diterbitkan sebagai denah resmi acara. Denah yang sudah terbit tidak dapat diubah lagi.</p>

                    <template x-if="publishErrors.length > 0">
                        <div class="fp-publish-errors">
                            <p class="font-semibold text-danger">Perbaiki masalah berikut sebelum menerbitkan:</p>
                            <ul>
                                <template x-for="err in publishErrors" :key="err">
                                    <li x-text="err"></li>
                                </template>
                            </ul>
                        </div>
                    </template>

                    <div class="fp-modal-actions">
                        <button type="button" class="btn-ghost btn-sm" @click="closePublish()">Batal</button>
                        <button type="button" class="btn-primary btn-sm" @click="confirmPublish()" :disabled="publishBusy">
                            <span x-text="publishBusy ? 'Menerbitkan…' : 'Ya, terbitkan'"></span>
                        </button>
                    </div>
                </div>
            </template>

            <template x-if="publishDone">
                <div class="space-y-3 text-center py-2">
                    <span class="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-success/15 text-success">
                        <x-ui.icon name="check" class="h-6 w-6" />
                    </span>
                    <p class="text-sm font-semibold text-ink">Denah berhasil diterbitkan.</p>
                    <div class="fp-modal-actions justify-center">
                        <a href="{{ $backUrl }}" class="btn-primary btn-sm">Lihat denah</a>
                    </div>
                </div>
            </template>
        </div>
    </div>

    {{-- ============================= CONFIRM MODAL ============================= --}}
    <div class="fp-modal" x-show="confirmState.open" x-cloak @click.self="cancelConfirm()">
        <div class="fp-modal-card">
            <div class="fp-modal-head">
                <h3 x-text="confirmState.title"></h3>
                <button type="button" class="fp-tool" @click="cancelConfirm()"><x-ui.icon name="close" class="h-4 w-4" /></button>
            </div>
            <p class="text-sm text-muted" x-text="confirmState.message"></p>
            <div class="fp-modal-actions">
                <button type="button" class="btn-ghost btn-sm" @click="cancelConfirm()">Batal</button>
                <button type="button" class="btn-primary btn-sm" @click="runConfirm()" x-text="confirmState.confirmLabel"></button>
            </div>
        </div>
    </div>

    {{-- ============================= TOAST ============================= --}}
    <div class="fp-toast" x-show="toast" x-cloak :class="toast && toast.type==='error' ? 'is-error' : ''">
        <span x-text="toast?.message"></span>
    </div>
</div>
@endsection