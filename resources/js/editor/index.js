/**
 * Alpine component for the freeform floor plan editor.
 * Orchestrates document state, history, Konva stage, panels, autosave,
 * seating, validation and publishing.
 */

import Document from './document.js'
import History from './commands.js'
import Stage from './stage.js'
import { serializeDocument, isPayloadWithinLimit } from './serializer.js'
import {
  OBJECT_TYPES,
  ZONE_TYPES,
  CATEGORY_LABELS,
  objectLabel,
  zoneLabel,
  isTableType,
  isChairType,
  defaultSize,
  defaultColor,
} from './types.js'
import { snapToGrid } from './geometry.js'
import {
  generateTableChairs,
  isRoundTable,
} from './seats.js'

const CATEGORY_ORDER = ['seating', 'venue', 'operational', 'general']

function csrfToken() {
  return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
}

function uid() {
  return 'obj_' + Math.random().toString(36).slice(2, 10)
}

export default function floorPlanEditor(config = {}) {
  // Non-reactive handles kept in closure so Alpine never proxies Konva,
  // the history stack, observers or timers.
  let stageInstance = null
  let historyInstance = null
  let resizeObserver = null
  let autosaveTimer = null
  let toastTimer = null
  let clipboard = null
  let keyHandler = null
  let spaceHandler = null
  let spaceUpHandler = null

  return {
    // ---------------------------------------------------------------
    // State
    // ---------------------------------------------------------------
    config,
    doc: null,
    selection: [],
    activeZoneId: null,

    readOnly: false,
    canPublish: false,
    canAssignSeating: false,

    lockVersion: 0,
    saveState: 'idle', // idle | unsaved | saving | saved | error | conflict
    lastSavedAt: null,
    saveError: '',
    conflict: false,

    zoom: 1,
    gridVisible: true,
    snapEnabled: true,

    leftTab: 'library', // library | layers
    rightTab: 'properties', // properties | seating | validation
    leftOpen: false,
    rightOpen: false,
    librarySearch: '',
    libraryExpanded: { seating: true, venue: true, operational: true, general: true },

    zoneDrawMode: null, // null | 'rect' | 'polygon'
    polygonPointCount: 0,

    toast: null,

    issues: [],
    validationCounts: { error: 0, warning: 0, info: 0 },
    validating: false,

    seating: { tables: {}, assignments: [] },
    seatingLoading: false,
    selectedTableId: null,
    guestSearch: '',
    expandedGuestId: null,
    assignHeadcount: 1,

    publishOpen: false,
    publishBusy: false,
    publishErrors: [],
    publishDone: false,

    chairPrompt: { open: false, tableId: null, capacity: 10, spacing: 30, startAngle: -90, hasManual: false },

    confirmState: { open: false, title: '', message: '', confirmLabel: 'Lanjutkan', onConfirm: null },

    objectTypes: [],
    zoneTypes: [],
    guests: [],

    _changeCounter: 0,
    _savedCounter: 0,
    _saving: false,
    _pendingSave: false,

    // ---------------------------------------------------------------
    // Init
    // ---------------------------------------------------------------
    init() {
      this.readOnly = config.readOnly === true
      this.canPublish = config.canPublish === true
      this.canAssignSeating = config.canAssignSeating === true
      this.lockVersion = Number(config.lockVersion || 0)
      this.objectTypes = config.objectTypes || []
      this.zoneTypes = config.zoneTypes || []
      this.guests = (config.guests || []).map((g) => ({
        id: g.id,
        name: g.name,
        maxAttendees: Number(g.max_attendees ?? g.maxAttendees ?? 1) || 1,
        members: (g.active_members || g.activeMembers || g.members || []).map((m) => ({
          id: m.id,
          name: m.name,
        })),
      }))

      // Build document
      const rawDoc = config.document || null
      this.doc = new Document(rawDoc || {})
      if (rawDoc) {
        try {
          this.doc.fromJSON(rawDoc)
        } catch (e) {
          // fall back to blank document
          this.doc = new Document({})
        }
      }

      this.gridVisible = this.doc.canvas.grid_visible !== false
      this.snapEnabled = this.doc.canvas.snap_enabled !== false

      historyInstance = new History(this.doc, 100)

      // Build stage after DOM ready
      this.$nextTick(() => this._buildStage())

      // Keyboard shortcuts
      this._installKeyboard()

      // Load seating snapshot (non-blocking)
      this.loadSeating()

      // Initial validation run (non-blocking)
      this.runValidation()
    },

    _buildStage() {
      const host = this.$refs.canvasHost
      if (!host) return

      const rect = host.getBoundingClientRect()
      const stage = new Stage(host, {
        width: Math.max(320, rect.width),
        height: Math.max(320, rect.height),
        zoom: 1,
        gridSize: this.doc.canvas.grid_size || 50,
        gridVisible: this.gridVisible,
        snapEnabled: this.snapEnabled,
        canvasWidth: this.doc.canvas.width || 2000,
        canvasHeight: this.doc.canvas.height || 1500,
        readOnly: this.readOnly,
      })

      stage.setDocument(this.doc)
      stage.onSelectionChange((ids) => this._handleSelection(ids))
      stage.onDocumentChange((kind, ids) => this._handleStageChange(kind, ids))
      stage.onZoneClick((id) => this._selectZone(id))
      stage.onZoomChange((z) => { this.zoom = Math.round(z * 100) / 100 })
      stage.onZoneDrawComplete((geometry) => this._completeZoneDraw(geometry))

      stageInstance = stage
      this.zoom = 1

      // Initial fit after layout settles
      setTimeout(() => {
        stage.resize(Math.max(320, host.clientWidth), Math.max(320, host.clientHeight))
        stage.fitToCanvas()
      }, 40)

      // Responsive resize
      resizeObserver = new ResizeObserver(() => {
        if (!stageInstance || !host) return
        const r = host.getBoundingClientRect()
        if (r.width > 0 && r.height > 0) {
          stageInstance.resize(r.width, r.height)
        }
      })
      resizeObserver.observe(host)
    },

    // ---------------------------------------------------------------
    // Selection
    // ---------------------------------------------------------------
    _handleSelection(ids) {
      this.selection = ids
      this.activeZoneId = null
      if (ids.length === 1) {
        const obj = this.doc.getObject(ids[0])
        if (obj && isTableType(obj.type)) this.selectedTableId = obj.id
        if (obj) this.rightTab = 'properties'
      }
    },

    _selectZone(id) {
      this.activeZoneId = id
      this.selection = []
      if (stageInstance) stageInstance.clearSelection()
      this.rightTab = 'properties'
    },

    get selectedObjects() {
      return this.selection.map((id) => this.doc?.getObject(id)).filter(Boolean)
    },

    get selectedObject() {
      return this.selectedObjects.length === 1 ? this.selectedObjects[0] : null
    },

    get selectedZone() {
      return this.activeZoneId ? this.doc?.getZone(this.activeZoneId) : null
    },

    get hasSelection() {
      return this.selection.length > 0
    },

    get selectionCount() {
      return this.selection.length
    },

    selectObject(id) {
      if (stageInstance) stageInstance.select([id])
      this._handleSelection([id])
    },

    selectZone(id) {
      this._selectZone(id)
    },

    clearSelection() {
      if (stageInstance) stageInstance.clearSelection()
      this.selection = []
      this.activeZoneId = null
    },

    // ---------------------------------------------------------------
    // History helpers
    // ---------------------------------------------------------------
    _mutate(label, fn) {
      if (this.readOnly) return
      const changed = historyInstance.run(label, fn)
      if (changed) {
        stageInstance?.refresh()
        this._afterChange()
      }
      return changed
    },

    _afterChange() {
      this._changeCounter++
      this.saveState = 'unsaved'
      this.conflict = false
      this.scheduleAutosave()
    },

    undo() {
      if (this.readOnly) return
      const label = historyInstance.undo()
      if (label === null) return
      this._syncCanvasSettings()
      stageInstance?.refresh()
      this.clearSelection()
      this._changeCounter++
      this.saveState = 'unsaved'
      this.scheduleAutosave()
      this.showToast(`Undo: ${label}`)
    },

    redo() {
      if (this.readOnly) return
      const label = historyInstance.redo()
      if (label === null) return
      this._syncCanvasSettings()
      stageInstance?.refresh()
      this.clearSelection()
      this._changeCounter++
      this.saveState = 'unsaved'
      this.scheduleAutosave()
      this.showToast(`Redo: ${label}`)
    },

    get canUndo() {
      return historyInstance?.canUndo ?? false
    },

    get canRedo() {
      return historyInstance?.canRedo ?? false
    },

    _syncCanvasSettings() {
      this.gridVisible = this.doc.canvas.grid_visible !== false
      this.snapEnabled = this.doc.canvas.snap_enabled !== false
      if (stageInstance) {
        stageInstance.setGridVisible(this.gridVisible)
        stageInstance.setSnapEnabled(this.snapEnabled)
        stageInstance.setGridSize(this.doc.canvas.grid_size || 50)
      }
    },

    // ---------------------------------------------------------------
    // Stage change (drag / transform)
    // ---------------------------------------------------------------
    _handleStageChange(kind, ids) {
      this._changeCounter++
      this.saveState = 'unsaved'
      this.scheduleAutosave()
    },

    // ---------------------------------------------------------------
    // Autosave
    // ---------------------------------------------------------------
    scheduleAutosave() {
      if (this.readOnly) return
      clearTimeout(autosaveTimer)
      autosaveTimer = setTimeout(() => this.save(), 1200)
    },

    async save() {
      if (this.readOnly) return
      if (this._saving) {
        this._pendingSave = true
        return
      }

      const payload = serializeDocument(this.doc)
      if (!isPayloadWithinLimit(payload)) {
        this.saveState = 'error'
        this.saveError = 'Dokumen melebihi batas 2 MB. Hapus beberapa objek.'
        return
      }

      this._saving = true
      this.saveState = 'saving'
      this.saveError = ''
      const startCounter = this._changeCounter

      try {
        const res = await fetch(this.config.saveUrl, {
          method: 'PUT',
          headers: {
            Accept: 'application/json',
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': csrfToken(),
          },
          body: JSON.stringify({ document: payload, lock_version: this.lockVersion }),
        })

        if (res.status === 409) {
          this.conflict = true
          this.saveState = 'conflict'
          this._saving = false
          return
        }

        if (!res.ok) {
          const body = await res.json().catch(() => ({}))
          this.saveState = 'error'
          this.saveError = body.message || `Gagal menyimpan (${res.status}).`
          this._saving = false
          return
        }

        const data = await res.json()
        this.lockVersion = Number(data.lock_version ?? this.lockVersion)
        this.lastSavedAt = data.saved_at || new Date().toISOString()
        this._savedCounter = startCounter
        this._saving = false

        if (this._changeCounter !== startCounter) {
          // Changes happened while saving — stay unsaved and schedule again
          this.saveState = 'unsaved'
          this.scheduleAutosave()
        } else {
          this.saveState = 'saved'
          this.doc.markClean()
        }
      } catch (e) {
        this._saving = false
        this.saveState = 'error'
        this.saveError = 'Koneksi gagal. Perubahan Anda tersimpan di perangkat ini.'
      }

      if (this._pendingSave) {
        this._pendingSave = false
        this.scheduleAutosave()
      }
    },

    retrySave() {
      this.save()
    },

    reloadForConflict() {
      window.location.reload()
    },

    // ---------------------------------------------------------------
    // Object library
    // ---------------------------------------------------------------
    get libraryGroups() {
      const groups = {}
      for (const type of Object.keys(OBJECT_TYPES)) {
        const def = OBJECT_TYPES[type]
        if (!groups[def.category]) groups[def.category] = []
        groups[def.category].push({ type, label: def.label, category: def.category })
      }
      return CATEGORY_ORDER.filter((c) => groups[c]).map((c) => ({
        key: c,
        label: CATEGORY_LABELS[c] || c,
        items: groups[c].filter((item) =>
          !this.librarySearch || item.label.toLowerCase().includes(this.librarySearch.toLowerCase()),
        ),
      }))
    },

    toggleLibraryGroup(key) {
      this.libraryExpanded[key] = !this.libraryExpanded[key]
    },

    addObject(type) {
      if (this.readOnly) return
      if (this.doc.objects.length >= 2000) {
        this.showToast('Batas 2000 objek tercapai.', 'error')
        return
      }
      const size = defaultSize(type)
      // Place at viewport center in world coords
      let cx = this.doc.canvas.width / 2 - size.width / 2
      let cy = this.doc.canvas.height / 2 - size.height / 2
      if (stageInstance) {
        const host = this.$refs.canvasHost
        const rect = host.getBoundingClientRect()
        const world = stageInstance.clientToWorld(rect.left + rect.width / 2, rect.top + rect.height / 2)
        cx = snapToGrid(world.x - size.width / 2, this.doc.canvas.grid_size || 50)
        cy = snapToGrid(world.y - size.height / 2, this.doc.canvas.grid_size || 50)
      }

      const maxZ = this.doc.objects.reduce((m, o) => Math.max(m, o.z_index || 0), 0)
      const obj = {
        id: uid(),
        type,
        x: cx,
        y: cy,
        width: size.width,
        height: size.height,
        rotation: 0,
        z_index: maxZ + 1,
        visible: true,
        locked: false,
        label: this._nextLabel(type),
        group_id: null,
        parent_id: null,
        properties: {
          color: defaultColor(type),
          capacity: isTableType(type) ? 8 : 0,
          table_number: isTableType(type) ? String(this.doc.objects.filter((o) => isTableType(o.type)).length + 1) : null,
          table_id: null,
          seat_index: 0,
          seat_spacing: 30,
          generated: true,
        },
      }

      this._mutate(`Tambah ${objectLabel(type)}`, () => {
        this.doc.addObject(obj)
      })
      this.selectObject(obj.id)
    },

    _nextLabel(type) {
      if (isTableType(type)) {
        const n = this.doc.objects.filter((o) => isTableType(o.type)).length + 1
        return `Meja ${n}`
      }
      if (isChairType(type)) {
        const n = this.doc.objects.filter((o) => isChairType(o.type)).length + 1
        return `Kursi ${n}`
      }
      return objectLabel(type)
    },

    onLibraryDragStart(event, type) {
      if (this.readOnly) {
        event.preventDefault()
        return
      }
      event.dataTransfer.setData('application/x-floorplan-type', type)
      event.dataTransfer.effectAllowed = 'copy'
    },

    onCanvasDrop(event) {
      if (this.readOnly) return
      const type = event.dataTransfer.getData('application/x-floorplan-type')
      if (!type || !OBJECT_TYPES[type]) return
      event.preventDefault()

      const host = this.$refs.canvasHost
      const rect = host.getBoundingClientRect()
      const world = stageInstance
        ? stageInstance.clientToWorld(event.clientX, event.clientY)
        : { x: event.clientX - rect.left, y: event.clientY - rect.top }
      const size = defaultSize(type)
      const maxZ = this.doc.objects.reduce((m, o) => Math.max(m, o.z_index || 0), 0)

      const obj = {
        id: uid(),
        type,
        x: snapToGrid(world.x - size.width / 2, this.doc.canvas.grid_size || 50),
        y: snapToGrid(world.y - size.height / 2, this.doc.canvas.grid_size || 50),
        width: size.width,
        height: size.height,
        rotation: 0,
        z_index: maxZ + 1,
        visible: true,
        locked: false,
        label: this._nextLabel(type),
        group_id: null,
        parent_id: null,
        properties: {
          color: defaultColor(type),
          capacity: isTableType(type) ? 8 : 0,
          table_number: isTableType(type) ? String(this.doc.objects.filter((o) => isTableType(o.type)).length + 1) : null,
          table_id: null,
          seat_index: 0,
          seat_spacing: 30,
          generated: true,
        },
      }

      this._mutate(`Tambah ${objectLabel(type)}`, () => this.doc.addObject(obj))
      this.selectObject(obj.id)
    },

    // ---------------------------------------------------------------
    // Object actions
    // ---------------------------------------------------------------
    deleteSelected() {
      if (this.readOnly || this.selection.length === 0) return
      const ids = [...this.selection]
      this._mutate('Hapus objek', () => {
        for (const id of ids) {
          // Also remove chairs belonging to a deleted table
          const obj = this.doc.getObject(id)
          if (obj && isTableType(obj.type)) {
            const chairs = this.doc.objects.filter((o) => o.properties?.table_id === id)
            for (const chair of chairs) this.doc.removeObject(chair.id)
          }
          this.doc.removeObject(id)
        }
      })
      this.clearSelection()
    },

    duplicateSelected() {
      if (this.readOnly || this.selection.length === 0) return
      const newIds = []
      this._mutate('Duplikat objek', () => {
        for (const id of this.selection) {
          const obj = this.doc.getObject(id)
          if (!obj) continue
          const copy = JSON.parse(JSON.stringify(obj))
          copy.id = uid()
          copy.x = obj.x + 40
          copy.y = obj.y + 40
          copy.z_index = Math.max(0, ...this.doc.objects.map((o) => o.z_index || 0)) + 1
          copy.group_id = null
          this.doc.addObject(copy)
          newIds.push(copy.id)
        }
      })
      if (newIds.length && stageInstance) stageInstance.select(newIds)
      this._handleSelection(newIds)
    },

    copySelected() {
      if (this.selection.length === 0) return
      clipboard = this.selection.map((id) => {
        const o = this.doc.getObject(id)
        return o ? JSON.parse(JSON.stringify(o)) : null
      }).filter(Boolean)
      this.showToast(`${clipboard.length} objek disalin.`)
    },

    pasteClipboard() {
      if (this.readOnly || !clipboard || clipboard.length === 0) return
      const newIds = []
      this._mutate('Tempel objek', () => {
        for (const src of clipboard) {
          const copy = JSON.parse(JSON.stringify(src))
          copy.id = uid()
          copy.x += 30
          copy.y += 30
          copy.z_index = Math.max(0, ...this.doc.objects.map((o) => o.z_index || 0)) + 1
          copy.group_id = null
          this.doc.addObject(copy)
          newIds.push(copy.id)
        }
      })
      if (newIds.length && stageInstance) stageInstance.select(newIds)
      this._handleSelection(newIds)
    },

    moveSelected(dx, dy) {
      if (this.readOnly || this.selection.length === 0) return
      this._mutate('Geser objek', () => {
        for (const obj of this.selectedObjects) {
          if (obj.locked) continue
          obj.x = snapToGrid(obj.x + dx, this.snapEnabled ? this.doc.canvas.grid_size : 0)
          obj.y = snapToGrid(obj.y + dy, this.snapEnabled ? this.doc.canvas.grid_size : 0)
        }
      })
    },

    bringForward() {
      this._reorder('forward')
    },
    sendBackward() {
      this._reorder('back')
    },
    bringToFront() {
      this._reorder('bringFront')
    },
    sendToBack() {
      this._reorder('sendBack')
    },

    _reorder(direction) {
      if (this.readOnly || this.selection.length === 0) return
      this._mutate('Ubah urutan', () => {
        const sorted = [...this.doc.objects].sort((a, b) => a.z_index - b.z_index)
        for (const id of this.selection) {
          const obj = this.doc.getObject(id)
          if (!obj) continue
          const idx = sorted.findIndex((o) => o.id === id)
          let target = idx
          if (direction === 'forward') target = Math.min(sorted.length - 1, idx + 1)
          else if (direction === 'back') target = Math.max(0, idx - 1)
          else if (direction === 'bringFront') target = sorted.length - 1
          else if (direction === 'sendBack') target = 0
          if (target === idx) continue
          const targetObj = sorted[target]
          const tmp = obj.z_index
          obj.z_index = targetObj.z_index
          targetObj.z_index = tmp
          sorted.sort((a, b) => a.z_index - b.z_index)
        }
        // Reassign contiguous z-indexes
        this.doc.objects.sort((a, b) => a.z_index - b.z_index)
        this.doc.objects.forEach((o, i) => { o.z_index = i })
      })
    },

    toggleLock() {
      if (this.readOnly || this.selection.length === 0) return
      this._mutate('Ubah kunci', () => {
        for (const obj of this.selectedObjects) obj.locked = !obj.locked
      })
    },

    toggleVisibility() {
      if (this.readOnly || this.selection.length === 0) return
      this._mutate('Ubah visibilitas', () => {
        for (const obj of this.selectedObjects) obj.visible = !obj.visible
      })
    },

    toggleObjectVisibility(id) {
      if (this.readOnly) return
      this._mutate('Ubah visibilitas', () => {
        const o = this.doc.getObject(id)
        if (o) o.visible = !o.visible
      })
    },

    toggleObjectLock(id) {
      if (this.readOnly) return
      this._mutate('Ubah kunci', () => {
        const o = this.doc.getObject(id)
        if (o) o.locked = !o.locked
      })
    },

    groupSelected() {
      if (this.readOnly || this.selection.length < 2) return
      this._mutate('Grup objek', () => {
        const gid = 'grp_' + Math.random().toString(36).slice(2, 10)
        for (const obj of this.selectedObjects) obj.group_id = gid
      })
    },

    ungroupSelected() {
      if (this.readOnly || this.selection.length === 0) return
      this._mutate('Ungroup objek', () => {
        const ids = new Set(this.selection)
        for (const obj of this.doc.objects) {
          if (obj.group_id && (ids.has(obj.id) || this.selection.some((id) => this.doc.getObject(id)?.group_id === obj.group_id))) {
            obj.group_id = null
          }
        }
      })
    },

    selectAll() {
      if (!stageInstance) return
      const ids = this.doc.objects.filter((o) => o.visible).map((o) => o.id)
      stageInstance.select(ids)
      this._handleSelection(ids)
    },

    // ---------------------------------------------------------------
    // Property editing
    // ---------------------------------------------------------------
    updateSelected(field, value) {
      if (this.readOnly) return
      const obj = this.selectedObject
      if (!obj) return
      const before = historyInstance.begin()
      const parsed = this._parseField(field, value)
      if (parsed === undefined) return
      obj[field] = parsed
      this.doc._markDirty()
      if (historyInstance.commit('Ubah properti', before)) {
        stageInstance?.refresh()
        this._afterChange()
      }
    },

    updateSelectedProperty(key, value) {
      if (this.readOnly) return
      const obj = this.selectedObject
      if (!obj) return
      const before = historyInstance.begin()
      let parsed = value
      if (key === 'capacity' || key === 'seat_index' || key === 'seat_spacing') {
        parsed = parseInt(value, 10)
        if (Number.isNaN(parsed)) return
        if (key === 'capacity') parsed = Math.max(0, Math.min(200, parsed))
      }
      if (key === 'color') {
        parsed = String(value)
      }
      obj.properties[key] = parsed
      this.doc._markDirty()
      if (historyInstance.commit('Ubah properti', before)) {
        stageInstance?.refresh()
        this._afterChange()
      }
    },

    _parseField(field, value) {
      if (['x', 'y', 'width', 'height', 'rotation', 'z_index'].includes(field)) {
        const n = parseFloat(value)
        if (Number.isNaN(n)) return undefined
        if ((field === 'width' || field === 'height') && n <= 0) return undefined
        if (field === 'z_index') return Math.round(n)
        if (field === 'rotation') return ((n % 360) + 360) % 360
        return n
      }
      if (field === 'label') return String(value).slice(0, 120)
      if (field === 'visible' || field === 'locked') return value === true || value === 'true'
      return value
    },

    updateZoneField(field, value) {
      if (this.readOnly || !this.selectedZone) return
      const zone = this.selectedZone
      const before = historyInstance.begin()
      if (field === 'capacity') {
        zone.capacity = Math.max(0, parseInt(value, 10) || 0)
      } else {
        zone[field] = value
      }
      this.doc._markDirty()
      if (historyInstance.commit('Ubah zona', before)) {
        stageInstance?.refresh()
        this._afterChange()
      }
    },

    updateCanvasField(field, value) {
      if (this.readOnly) return
      const before = historyInstance.begin()
      if (field === 'grid_size') {
        this.doc.canvas.grid_size = Math.max(1, parseInt(value, 10) || 50)
        stageInstance?.setGridSize(this.doc.canvas.grid_size)
      } else if (field === 'grid_visible') {
        this.doc.canvas.grid_visible = value
        this.gridVisible = value
        stageInstance?.setGridVisible(value)
      } else if (field === 'snap_enabled') {
        this.doc.canvas.snap_enabled = value
        this.snapEnabled = value
        stageInstance?.setSnapEnabled(value)
      } else if (field === 'background_color') {
        this.doc.canvas.background_color = value
        stageInstance?.refresh()
      } else {
        this.doc.canvas[field] = value
      }
      this.doc._markDirty()
      if (historyInstance.commit('Ubah canvas', before)) this._afterChange()
    },

    // ---------------------------------------------------------------
    // Table chair generation
    // ---------------------------------------------------------------
    promptGenerateChairs() {
      const obj = this.selectedObject
      if (!obj || !isTableType(obj.type) || this.readOnly) return
      const chairs = this.doc.objects.filter((o) => o.properties?.table_id === obj.id)
      const hasManual = chairs.some((c) => c.properties?.generated === false)
      this.chairPrompt = {
        open: true,
        tableId: obj.id,
        capacity: obj.properties?.capacity ?? 8,
        spacing: obj.properties?.seat_spacing ?? 30,
        startAngle: obj.properties?.start_angle ?? -90,
        hasManual,
      }
    },

    applyChairs(mode = 'regenerate') {
      const p = this.chairPrompt
      if (!p.tableId || this.readOnly) return
      const table = this.doc.getObject(p.tableId)
      if (!table) return

      const capacity = Math.max(0, Math.min(200, parseInt(p.capacity, 10) || 0))
      const spacing = Math.max(0, parseInt(p.spacing, 10) || 30)
      const startAngle = parseInt(p.startAngle, 10) || -90

      this._mutate('Buat kursi', () => {
        table.properties.capacity = capacity
        table.properties.seat_spacing = spacing
        table.properties.start_angle = startAngle

        const existing = this.doc.objects.filter((o) => o.properties?.table_id === table.id)
        const manual = existing.filter((c) => c.properties?.generated === false)

        if (mode === 'regenerate') {
          for (const c of existing) this.doc.removeObject(c.id)
        } else {
          // keep manual: remove only generated chairs
          for (const c of existing) {
            if (c.properties?.generated !== false) this.doc.removeObject(c.id)
          }
        }

        const keepCount = mode === 'keep' ? manual.length : 0
        const positions = generateTableChairs(
          { ...table, type: table.type },
          Math.max(capacity, keepCount),
          { seatSpacing: spacing, startAngle },
        )

        // Build a set of manual seat indices to skip
        const manualIndices = new Set(manual.map((c) => c.properties?.seat_index ?? -1))
        const maxZ = Math.max(0, ...this.doc.objects.map((o) => o.z_index || 0))
        let added = 0
        positions.forEach((pos, i) => {
          if (mode === 'keep' && manualIndices.has(i)) return
          const chair = {
            id: uid(),
            type: 'chair',
            x: Math.round(pos.x),
            y: Math.round(pos.y),
            width: 45,
            height: 45,
            rotation: Math.round(pos.angle || 0),
            z_index: maxZ + 1 + added,
            visible: true,
            locked: false,
            label: `Kursi ${table.label || ''} ${i + 1}`.trim(),
            group_id: null,
            parent_id: table.id,
            properties: {
              table_id: table.id,
              seat_index: i,
              generated: true,
              color: '#f0ece2',
              capacity: 0,
              seat_spacing: spacing,
            },
          }
          this.doc.addObject(chair)
          added++
        })
      })

      this.chairPrompt.open = false
      this.showToast('Kursi diperbarui.')
    },

    get selectedTableChairs() {
      const obj = this.selectedObject
      if (!obj || !isTableType(obj.type)) return []
      return this.doc.objects.filter((o) => o.properties?.table_id === obj.id)
    },

    get capacityMismatch() {
      const obj = this.selectedObject
      if (!obj || !isTableType(obj.type)) return false
      const cap = obj.properties?.capacity ?? 0
      const chairCount = this.selectedTableChairs.length
      return chairCount > 0 && cap !== chairCount
    },

    // ---------------------------------------------------------------
    // Zones
    // ---------------------------------------------------------------
    startZoneDraw(kind) {
      if (this.readOnly) return
      this.zoneDrawMode = kind
      this.polygonPointCount = 0
      if (stageInstance) {
        stageInstance.beginZoneDraw(kind)
        stageInstance.clearSelection()
      }
      this.selection = []
      this.rightTab = 'properties'
    },

    cancelZoneDraw() {
      this.zoneDrawMode = null
      this.polygonPointCount = 0
      if (stageInstance) stageInstance.cancelZoneDraw()
    },

    finishPolygonDraw() {
      if (stageInstance) {
        this.polygonPointCount = stageInstance._polyPoints?.length || 0
        stageInstance.finishPolygon()
      }
      this.zoneDrawMode = null
    },

    _completeZoneDraw(geometry) {
      if (this.readOnly) return
      if (this.doc.zones.length >= 200) {
        this.showToast('Batas 200 zona tercapai.', 'error')
        this.zoneDrawMode = null
        return
      }
      const zone = {
        id: 'zone_' + Math.random().toString(36).slice(2, 10),
        type: 'other',
        name: 'Zona Baru',
        color: '#c3a477',
        description: null,
        visible: true,
        capacity: 0,
        geometry,
      }
      this._mutate('Tambah zona', () => this.doc.addZone(zone))
      this.zoneDrawMode = null
      this._selectZone(zone.id)
      this.showToast('Zona ditambahkan.')
    },

    deleteZone() {
      if (this.readOnly || !this.activeZoneId) return
      const id = this.activeZoneId
      this._mutate('Hapus zona', () => this.doc.removeZone(id))
      this.activeZoneId = null
    },

    toggleZoneVisibility(id) {
      if (this.readOnly) return
      this._mutate('Ubah visibilitas zona', () => {
        const z = this.doc.getZone(id)
        if (z) z.visible = !z.visible
      })
    },

    // ---------------------------------------------------------------
    // Layers panel
    // ---------------------------------------------------------------
    get layers() {
      return [...this.doc.objects].sort((a, b) => (b.z_index || 0) - (a.z_index || 0))
    },

    get zoneLayers() {
      return [...this.doc.zones]
    },

    // ---------------------------------------------------------------
    // Zoom controls
    // ---------------------------------------------------------------
    zoomIn() { stageInstance?.zoomBy(1.2); this.zoom = stageInstance?.options.zoom ?? this.zoom },
    zoomOut() { stageInstance?.zoomBy(1 / 1.2); this.zoom = stageInstance?.options.zoom ?? this.zoom },
    resetZoom() { stageInstance?.resetView(); this.zoom = 1 },
    fitToScreen() { stageInstance?.fitToCanvas(); this.zoom = stageInstance?.options.zoom ?? this.zoom },
    zoomToSelection() { stageInstance?.zoomToSelection(); this.zoom = stageInstance?.options.zoom ?? this.zoom },

    toggleGrid() {
      if (this.readOnly) return
      this.updateCanvasField('grid_visible', !this.gridVisible)
    },

    toggleSnap() {
      if (this.readOnly) return
      this.updateCanvasField('snap_enabled', !this.snapEnabled)
    },

    // ---------------------------------------------------------------
    // Validation
    // ---------------------------------------------------------------
    async runValidation() {
      this.validating = true
      try {
        const res = await fetch(this.config.validateUrl, {
          headers: { Accept: 'application/json' },
        })
        if (!res.ok) throw new Error('validate failed')
        const data = await res.json()
        this.issues = data.issues || []
        this.validationCounts = data.counts || { error: 0, warning: 0, info: 0 }
      } catch (e) {
        // Silent — validation is advisory
      } finally {
        this.validating = false
      }
    },

    get groupedIssues() {
      return {
        error: this.issues.filter((i) => i.severity === 'error'),
        warning: this.issues.filter((i) => i.severity === 'warning'),
        info: this.issues.filter((i) => i.severity === 'info'),
      }
    },

    focusIssue(issue) {
      if (issue.object_id && this.doc.getObject(issue.object_id)) {
        this.selectObject(issue.object_id)
        this.rightTab = 'properties'
      }
    },

    // ---------------------------------------------------------------
    // Seating
    // ---------------------------------------------------------------
    async loadSeating() {
      if (!this.config.seatingUrl) return
      this.seatingLoading = true
      try {
        const res = await fetch(this.config.seatingUrl, { headers: { Accept: 'application/json' } })
        if (!res.ok) throw new Error('seating failed')
        const data = await res.json()
        this.seating = {
          tables: data.tables || {},
          assignments: data.assignments || [],
        }
      } catch (e) {
        // keep previous
      } finally {
        this.seatingLoading = false
      }
    },

    get tableList() {
      return this.doc.objects
        .filter((o) => isTableType(o.type))
        .map((o) => {
          const summary = this.seating.tables?.[o.id] || { capacity: o.properties?.capacity || 0, assigned: 0, remaining: o.properties?.capacity || 0, chairs: 0 }
          return {
            id: o.id,
            label: o.label,
            capacity: summary.capacity,
            assigned: summary.assigned,
            remaining: summary.remaining,
            chairs: summary.chairs,
            type: o.type,
          }
        })
    },

    get activeTableSummary() {
      if (!this.selectedTableId) return null
      const t = this.tableList.find((x) => x.id === this.selectedTableId)
      return t || null
    },

    get tableAssignments() {
      if (!this.selectedTableId) return []
      return this.seating.assignments.filter((a) => a.table_object_id === this.selectedTableId)
    },

    selectTableForSeating(id) {
      this.selectedTableId = id
      this.selectObject(id)
    },

    get filteredGuests() {
      const q = this.guestSearch.trim().toLowerCase()
      if (!q) return this.guests
      return this.guests.filter((g) => g.name.toLowerCase().includes(q))
    },

    toggleGuestExpand(id) {
      this.expandedGuestId = this.expandedGuestId === id ? null : id
    },

    async assignGuest(guest, member = null) {
      if (!this.canAssignSeating || this.readOnly || !this.selectedTableId) return
      const table = this.activeTableSummary
      if (!table) return

      const headcount = member ? 1 : Math.max(1, Math.min(100, parseInt(this.assignHeadcount, 10) || 1))
      if (headcount > table.remaining) {
        this.showToast('Kapasitas meja tidak mencukupi.', 'error')
        return
      }

      const body = {
        table_object_id: this.selectedTableId,
        guest_id: guest.id,
        headcount,
      }
      if (member) body.guest_party_membership_id = member.id

      try {
        const res = await fetch(this.config.seatingUrl, {
          method: 'POST',
          headers: {
            Accept: 'application/json',
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': csrfToken(),
          },
          body: JSON.stringify(body),
        })
        if (!res.ok) {
          const data = await res.json().catch(() => ({}))
          const first = data.errors ? Object.values(data.errors)[0]?.[0] : data.message
          this.showToast(first || 'Gagal menempatkan tamu.', 'error')
          return
        }
        await this.loadSeating()
        this.showToast(`${member ? member.name : guest.name} ditempatkan.`)
      } catch (e) {
        this.showToast('Koneksi gagal saat menempatkan tamu.', 'error')
      }
    },

    async releaseAssignment(assignment) {
      if (!this.canAssignSeating || this.readOnly) return
      try {
        const res = await fetch(`${this.config.seatingUrl}/${assignment.id}`, {
          method: 'DELETE',
          headers: {
            Accept: 'application/json',
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': csrfToken(),
          },
          body: JSON.stringify({}),
        })
        if (!res.ok) {
          this.showToast('Gagal melepas assignment.', 'error')
          return
        }
        await this.loadSeating()
        this.showToast('Assignment dilepas.')
      } catch (e) {
        this.showToast('Koneksi gagal saat melepas assignment.', 'error')
      }
    },

    // ---------------------------------------------------------------
    // Publish
    // ---------------------------------------------------------------
    openPublish() {
      if (!this.canPublish || this.readOnly) return
      this.publishErrors = []
      this.publishDone = false
      this.publishOpen = true
    },

    closePublish() {
      this.publishOpen = false
      this.publishBusy = false
    },

    async confirmPublish() {
      if (this.publishBusy) return
      this.publishBusy = true
      this.publishErrors = []
      try {
        const res = await fetch(this.config.publishUrl, {
          method: 'POST',
          headers: {
            Accept: 'application/json',
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': csrfToken(),
          },
          body: JSON.stringify({}),
        })

        if (res.status === 422) {
          const data = await res.json().catch(() => ({}))
          const docErrors = data.errors?.document || []
          this.publishErrors = Array.isArray(docErrors) ? docErrors : [docErrors].filter(Boolean)
          this.publishBusy = false
          return
        }

        if (!res.ok) {
          const data = await res.json().catch(() => ({}))
          this.publishErrors = [data.message || 'Penerbitan gagal.']
          this.publishBusy = false
          return
        }

        const data = await res.json()
        this.publishDone = true
        this.publishBusy = false
        this.readOnly = true
        stageInstance?.setReadOnly(true)
        this.showToast(`Versi ${data.version?.version_number ?? ''} diterbitkan.`)
        await this.runValidation()
      } catch (e) {
        this.publishBusy = false
        this.publishErrors = ['Koneksi gagal saat menerbitkan.']
      }
    },

    // ---------------------------------------------------------------
    // Keyboard
    // ---------------------------------------------------------------
    _isEditableTarget(el) {
      if (!el) return false
      const tag = el.tagName
      return (
        tag === 'INPUT' ||
        tag === 'TEXTAREA' ||
        tag === 'SELECT' ||
        el.isContentEditable === true
      )
    },

    _installKeyboard() {
      keyHandler = (e) => {
        if (this._isEditableTarget(e.target)) return
        const mod = e.metaKey || e.ctrlKey

        if (e.code === 'Space') {
          e.preventDefault()
          stageInstance?.setSpaceDown(true)
          return
        }

        if (this.zoneDrawMode === 'polygon' && e.key === 'Enter') {
          e.preventDefault()
          this.finishPolygonDraw()
          return
        }

        if (e.key === 'Escape') {
          if (this.zoneDrawMode) {
            this.cancelZoneDraw()
          } else if (this.publishOpen) {
            this.closePublish()
          } else if (this.chairPrompt.open) {
            this.chairPrompt.open = false
          } else {
            this.clearSelection()
          }
          return
        }

        if (mod && e.key.toLowerCase() === 'z') {
          e.preventDefault()
          if (e.shiftKey) this.redo()
          else this.undo()
          return
        }

        if (mod && e.key.toLowerCase() === 'y') {
          e.preventDefault()
          this.redo()
          return
        }

        if (mod && e.key.toLowerCase() === 'c') {
          e.preventDefault()
          this.copySelected()
          return
        }

        if (mod && e.key.toLowerCase() === 'v') {
          e.preventDefault()
          this.pasteClipboard()
          return
        }

        if (mod && e.key.toLowerCase() === 'd') {
          e.preventDefault()
          this.duplicateSelected()
          return
        }

        if (mod && e.key.toLowerCase() === 'a') {
          e.preventDefault()
          this.selectAll()
          return
        }

        if (e.key === 'Delete' || e.key === 'Backspace') {
          if (this.selection.length) {
            e.preventDefault()
            this.deleteSelected()
          }
          return
        }

        if (e.key === 'ArrowLeft' || e.key === 'ArrowRight' || e.key === 'ArrowUp' || e.key === 'ArrowDown') {
          if (this.selection.length) {
            e.preventDefault()
            const step = e.shiftKey ? 10 : 1
            if (e.key === 'ArrowLeft') this.moveSelected(-step, 0)
            if (e.key === 'ArrowRight') this.moveSelected(step, 0)
            if (e.key === 'ArrowUp') this.moveSelected(0, -step)
            if (e.key === 'ArrowDown') this.moveSelected(0, step)
          }
        }
      }

      spaceHandler = (e) => {
        if (this._isEditableTarget(e.target)) return
        if (e.code === 'Space') {
          e.preventDefault()
          stageInstance?.setSpaceDown(true)
        }
      }
      spaceUpHandler = (e) => {
        if (e.code === 'Space') stageInstance?.setSpaceDown(false)
      }

      window.addEventListener('keydown', keyHandler)
      window.addEventListener('keydown', spaceHandler)
      window.addEventListener('keyup', spaceUpHandler)
    },

    _uninstallKeyboard() {
      if (keyHandler) window.removeEventListener('keydown', keyHandler)
      if (spaceHandler) window.removeEventListener('keydown', spaceHandler)
      if (spaceUpHandler) window.removeEventListener('keyup', spaceUpHandler)
    },

    // ---------------------------------------------------------------
    // Utilities
    // ---------------------------------------------------------------
    showToast(message, type = 'success') {
      this.toast = { message, type }
      clearTimeout(toastTimer)
      toastTimer = setTimeout(() => { this.toast = null }, 2800)
    },

    confirm(title, message, onConfirm, confirmLabel = 'Lanjutkan') {
      this.confirmState = { open: true, title, message, confirmLabel, onConfirm }
    },

    runConfirm() {
      const fn = this.confirmState.onConfirm
      this.confirmState.open = false
      if (typeof fn === 'function') fn()
    },

    cancelConfirm() {
      this.confirmState.open = false
    },

    objectLabel(type) { return objectLabel(type) },
    zoneLabel(type) { return zoneLabel(type) },
    isTableType(type) { return isTableType(type) },
    isChairType(type) { return isChairType(type) },
    objectTypesList() { return Object.keys(OBJECT_TYPES) },
    zoneTypesList() { return Object.keys(ZONE_TYPES) },

    get objectCount() { return this.doc?.objects.length ?? 0 },
    get zoneCount() { return this.doc?.zones.length ?? 0 },
    get tableCount() { return this.doc?.objects.filter((o) => isTableType(o.type)).length ?? 0 },
    get chairCount() { return this.doc?.objects.filter((o) => isChairType(o.type)).length ?? 0 },
    get capacityTotal() {
      return this.doc?.objects.filter((o) => isTableType(o.type)).reduce((s, o) => s + (o.properties?.capacity || 0), 0) ?? 0
    },

    get saveLabel() {
      switch (this.saveState) {
        case 'saving': return 'Menyimpan…'
        case 'saved': return 'Tersimpan'
        case 'unsaved': return 'Perubahan belum disimpan'
        case 'error': return 'Gagal menyimpan'
        case 'conflict': return 'Konflik versi'
        default: return 'Siap'
      }
    },

    get saveLabelClass() {
      switch (this.saveState) {
        case 'saving': return 'badge-info'
        case 'saved': return 'badge-success'
        case 'unsaved': return 'badge-warning'
        case 'error': return 'badge-danger'
        case 'conflict': return 'badge-danger'
        default: return 'badge-neutral'
      }
    },

    get savedAtLabel() {
      if (!this.lastSavedAt) return ''
      try {
        return new Date(this.lastSavedAt).toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' })
      } catch (e) {
        return ''
      }
    },

    formatZoom() {
      return `${Math.round(this.zoom * 100)}%`
    },

    destroy() {
      clearTimeout(autosaveTimer)
      this._uninstallKeyboard()
      if (resizeObserver) resizeObserver.disconnect()
      if (stageInstance) stageInstance.destroy()
    },
  }
}
