/**
 * Konva stage/layers, rendering, transformer, selection, marquee, zoom/pan.
 *
 * Every object renders as a Konva.Group positioned at (obj.x, obj.y) with the
 * object's rotation. The group contains the visual shape (drawn at local 0,0)
 * and a centred label. Dragging / transforming the group writes back to the
 * document model.
 */

import Konva from 'konva'
import { fitViewport, screenToWorld } from './geometry.js'

const CATEGORY_FILL = {
  seating: '#f5f5f5',
  venue: '#e8e2d6',
  operational: '#dfe8e4',
  general: '#eceae4',
}

const CATEGORY_STROKE = {
  seating: '#c9c3b8',
  venue: '#b8a994',
  operational: '#b9c9c2',
  general: '#d8d4c8',
}

const ACCENT = '#725A40'

const TABLE_TYPES = ['round_table', 'rectangle_table', 'square_table', 'vip_table', 'family_table', 'custom_table']

function categoryOf(type) {
  if (['chair', ...TABLE_TYPES].includes(type)) return 'seating'
  if (['stage', 'dance_floor', 'entrance', 'exit', 'door', 'wall', 'pillar', 'partition', 'staircase', 'aisle'].includes(type)) return 'venue'
  if (['buffet_station', 'catering_station', 'photo_booth', 'guest_reception', 'registration_desk', 'vendor_station', 'staff_station', 'sound_area', 'lighting_area', 'restricted_area'].includes(type)) return 'operational'
  return 'general'
}

export function isTableType(type) {
  return TABLE_TYPES.includes(type)
}

/** Build the visual child shape for an object (local coords 0,0). */
function buildVisual(obj) {
  const w = Math.max(1, obj.width)
  const h = Math.max(1, obj.height)
  const cat = categoryOf(obj.type)
  const fill = obj.properties?.color || CATEGORY_FILL[cat]
  const stroke = CATEGORY_STROKE[cat]

  switch (obj.type) {
    case 'round_table':
    case 'circle':
    case 'ellipse':
      return new Konva.Ellipse({
        x: w / 2,
        y: h / 2,
        radiusX: w / 2,
        radiusY: h / 2,
        fill,
        stroke,
        strokeWidth: 2,
        listening: true,
      })
    case 'line':
      return new Konva.Line({
        points: [0, 0, w, h],
        stroke: obj.properties?.color || '#8B7B63',
        strokeWidth: 4,
        hitStrokeWidth: 24,
        listening: true,
      })
    case 'text_label':
      return new Konva.Rect({
        width: w,
        height: h,
        fill: 'rgba(255,255,255,0.01)',
        listening: true,
      })
    case 'polygon': {
      const pts = obj.properties?.points || []
      return new Konva.Line({
        points: pts.length ? pts.flatMap((p) => [p.x, p.y]) : [0, 0, w, 0, w, h],
        fill,
        stroke,
        strokeWidth: 2,
        closed: true,
        listening: true,
      })
    }
    default:
      return new Konva.Rect({
        width: w,
        height: h,
        fill,
        stroke,
        strokeWidth: obj.type === 'wall' ? 1 : 2,
        cornerRadius: TABLE_TYPES.includes(obj.type) ? 8 : 2,
        listening: true,
      })
  }
}

/** Build a full Konva.Group for an object. */
export function createShape(obj, readOnly = false) {
  const w = Math.max(1, obj.width)
  const h = Math.max(1, obj.height)
  const locked = obj.locked === true

  // Group is positioned at the object's centre with an offset of half its size,
  // so rotation pivots around the centre while local (0,0) stays the top-left.
  const group = new Konva.Group({
    id: `shape_${obj.id}`,
    name: 'object',
    objectId: obj.id,
    x: obj.x + w / 2,
    y: obj.y + h / 2,
    offsetX: w / 2,
    offsetY: h / 2,
    rotation: Number(obj.rotation) || 0,
    draggable: !locked && !readOnly,
    listening: true,
  })

  const visual = buildVisual(obj)
  visual.setAttr('name', 'object-visual')
  group.add(visual)

  // Label
  const isText = obj.type === 'text_label'
  const labelText = obj.label || ''
  if (labelText && obj.type !== 'line') {
    const fontSize = isText ? 16 : Math.max(10, Math.min(14, Math.round(w / 12)))
    const text = new Konva.Text({
      text: labelText,
      width: w,
      height: isText ? h : undefined,
      align: isText ? 'left' : 'center',
      verticalAlign: isText ? 'top' : 'middle',
      fontSize,
      fontFamily: 'Inter, sans-serif',
      fontStyle: isText ? 'normal' : '600',
      fill: isText ? '#283640' : '#3a4650',
      listening: false,
      name: 'object-label',
      wrap: 'word',
    })
    if (isText) {
      text.x(0)
      text.y(0)
    } else {
      text.x(0)
      text.y(0)
      text.height(h)
    }
    group.add(text)
  }

  return group
}

/** Build a Konva shape for a zone. */
export function createZoneShape(zone) {
  const fill = zone.color || '#ffcc00'
  const geo = zone.geometry || {}

  if (geo.kind === 'polygon') {
    return new Konva.Line({
      id: `zone_${zone.id}`,
      name: 'zone',
      zoneId: zone.id,
      points: (geo.points || []).flatMap((p) => [p.x, p.y]),
      fill,
      opacity: 0.2,
      stroke: fill,
      strokeWidth: 2,
      closed: true,
      listening: true,
    })
  }

  return new Konva.Rect({
    id: `zone_${zone.id}`,
    name: 'zone',
    zoneId: zone.id,
    x: geo.x || 0,
    y: geo.y || 0,
    width: geo.width || 100,
    height: geo.height || 100,
    fill,
    opacity: 0.2,
    stroke: fill,
    strokeWidth: 2,
    dash: [8, 5],
    listening: true,
  })
}

export class Stage {
  constructor(container, options = {}) {
    this.container = container
    this.options = {
      width: options.width || 800,
      height: options.height || 600,
      zoom: 1,
      pan: { x: 0, y: 0 },
      gridSize: options.gridSize || 50,
      gridVisible: options.gridVisible !== false,
      snapEnabled: options.snapEnabled !== false,
      canvasWidth: options.canvasWidth || 2000,
      canvasHeight: options.canvasHeight || 1500,
      readOnly: options.readOnly === true,
      ...options,
    }

    this._doc = null
    this._selectedIds = new Set()
    this._transformer = null
    this._marquee = null
    this._marqueeStart = null
    this._dragMode = null
    this._spaceDown = false
    this._panStart = null
    this._groupDragStart = null

    this._drawMode = null
    this._polyPoints = []
    this._tempShape = null
    this._rectStart = null
    this._drawCursor = null

    this._onSelectionChange = null
    this._onDocumentChange = null
    this._onZoneClick = null
    this._onZoomChange = null
    this._onZoneDrawComplete = null

    this._build()
  }

  _build() {
    this._stage = new Konva.Stage({
      container: this.container,
      width: this.options.width,
      height: this.options.height,
    })

    this._bgLayer = new Konva.Layer({ listening: false, name: 'bg' })
    this._gridLayer = new Konva.Layer({ listening: false, name: 'grid' })
    this._zoneLayer = new Konva.Layer({ name: 'zones' })
    this._objectLayer = new Konva.Layer({ name: 'objects' })
    this._overlayLayer = new Konva.Layer({ name: 'overlay' })

    this._stage.add(this._bgLayer)
    this._stage.add(this._gridLayer)
    this._stage.add(this._zoneLayer)
    this._stage.add(this._objectLayer)
    this._stage.add(this._overlayLayer)

    this._applyTransform()
    this._bindEvents()
  }

  setDocument(doc) {
    this._doc = doc
    this._selectedIds.clear()
    this._renderAll()
    this._onSelectionChange?.([])
  }

  setReadOnly(readOnly) {
    this.options.readOnly = readOnly
    this._renderAll()
  }

  _applyTransform() {
    const { zoom, pan } = this.options
    this._stage.scale({ x: zoom, y: zoom })
    this._stage.position({ x: pan.x, y: pan.y })
    this._stage.batchDraw()
  }

  _renderAll() {
    this._renderBackground()
    this._renderGrid()
    this._renderZones()
    this._renderObjects()
    this._renderSelection()
    this._stage.batchDraw()
  }

  _renderBackground() {
    this._bgLayer.destroyChildren()
    this._bgLayer.add(
      new Konva.Rect({
        x: 0,
        y: 0,
        width: this.options.canvasWidth,
        height: this.options.canvasHeight,
        fill: this._doc?.canvas?.background_color || '#ffffff',
        shadowColor: '#283640',
        shadowBlur: 20,
        shadowOpacity: 0.1,
        shadowOffset: { x: 0, y: 6 },
        listening: false,
      }),
    )
  }

  _renderGrid() {
    this._gridLayer.destroyChildren()
    if (!this.options.gridVisible) return

    const w = this.options.canvasWidth
    const h = this.options.canvasHeight
    const g = this.options.gridSize
    if (!g || g <= 0) return

    const group = new Konva.Group({ listening: false })
    for (let x = g; x < w; x += g) {
      group.add(new Konva.Line({ points: [x, 0, x, h], stroke: '#e4ded2', strokeWidth: 1, dash: [3, 5] }))
    }
    for (let y = g; y < h; y += g) {
      group.add(new Konva.Line({ points: [0, y, w, y], stroke: '#e4ded2', strokeWidth: 1, dash: [3, 5] }))
    }
    this._gridLayer.add(group)
  }

  _renderZones() {
    this._zoneLayer.destroyChildren()
    if (!this._doc) return
    for (const zone of this._doc.zones) {
      if (!zone.visible) continue
      this._zoneLayer.add(createZoneShape(zone))
    }
  }

  _renderObjects() {
    this._objectLayer.destroyChildren()
    if (!this._doc) return
    const sorted = [...this._doc.objects].sort((a, b) => a.z_index - b.z_index)
    for (const obj of sorted) {
      if (!obj.visible) continue
      const node = createShape(obj, this.options.readOnly)
      if (this.options.snapEnabled && !this.options.readOnly) {
        node.dragBoundFunc((pos) => this._snapDragBound(node, pos))
      }
      this._objectLayer.add(node)
    }
  }

  _snapDragBound(node, pos) {
    if (!this.options.snapEnabled) return pos
    const g = this.options.gridSize
    const zoom = this.options.zoom
    const offsetX = node.offsetX() || 0
    const offsetY = node.offsetY() || 0
    // pos is the node origin (centre) in stage coords; snap the top-left corner.
    const worldX = pos.x / zoom
    const worldY = pos.y / zoom
    const topLeftX = Math.round((worldX - offsetX) / g) * g
    const topLeftY = Math.round((worldY - offsetY) / g) * g
    return { x: (topLeftX + offsetX) * zoom, y: (topLeftY + offsetY) * zoom }
  }

  _renderSelection() {
    this._overlayLayer.destroyChildren()
    this._transformer = null
    if (this.options.readOnly) return
    if (this._selectedIds.size !== 1) return

    const [id] = this._selectedIds
    const node = this._findNodeById(id)
    if (!node) return

    this._transformer = new Konva.Transformer({
      nodes: [node],
      rotateEnabled: !node.attrs.locked,
      flipEnabled: false,
      padding: 2,
      anchorSize: 9,
      anchorStroke: ACCENT,
      anchorFill: '#ffffff',
      anchorCornerRadius: 2,
      borderStroke: ACCENT,
      borderStrokeWidth: 1,
      borderDash: [4, 4],
      rotateAnchorOffset: 26,
      boundBoxFunc: (oldBox, newBox) => {
        if (newBox.width < 10 || newBox.height < 10) return oldBox
        return newBox
      },
    })
    this._overlayLayer.add(this._transformer)
    this._bindTransformer(this._transformer)
  }

  _bindTransformer(transformer) {
    const commit = () => {
      const node = transformer.getNodes()[0]
      if (!node || !this._doc) return
      const id = node.attrs.objectId
      const obj = this._doc.getObject(id)
      if (!obj) return

      const scaleX = Math.abs(node.scaleX())
      const scaleY = Math.abs(node.scaleY())

      let newW = obj.width * scaleX
      let newH = obj.height * scaleY

      if (this.options.snapEnabled) {
        newW = Math.max(10, Math.round(newW / this.options.gridSize) * this.options.gridSize)
        newH = Math.max(10, Math.round(newH / this.options.gridSize) * this.options.gridSize)
      }

      obj.width = Math.max(10, Math.round(newW))
      obj.height = Math.max(10, Math.round(newH))
      // node.x/y is the centre; derive the unrotated top-left.
      obj.x = Math.round(node.x() - obj.width / 2)
      obj.y = Math.round(node.y() - obj.height / 2)
      obj.rotation = Math.round(node.rotation())

      node.scaleX(1)
      node.scaleY(1)

      this._doc._markDirty()
      this._renderObjects()
      this._renderSelection()
      this._onDocumentChange?.('transform', [id])
    }

    transformer.on('transformend', commit)
    transformer.on('dragend', commit)
  }

  _bindEvents() {
    const stage = this._stage

    stage.on('dragstart', (e) => {
      if (this.options.readOnly) return
      const node = e.target
      const id = node.attrs.objectId
      if (!id) return
      if (!this._selectedIds.has(id)) this._setSelection(this._groupSelectionFor(id))

      this._groupDragStart = {
        primaryId: id,
        primaryStart: { x: node.x(), y: node.y() },
        others: this.getSelectedObjects()
          .filter((o) => o.id !== id)
          .map((o) => {
            const n = this._findNodeById(o.id)
            return { id: o.id, start: n ? { x: n.x(), y: n.y() } : { x: o.x + o.width / 2, y: o.y + o.height / 2 } }
          }),
      }
    })

    stage.on('dragmove', (e) => {
      if (this.options.readOnly) return
      const node = e.target
      if (!node.attrs.objectId || !this._groupDragStart) return
      if (this._selectedIds.size <= 1) return

      const dx = node.x() - this._groupDragStart.primaryStart.x
      const dy = node.y() - this._groupDragStart.primaryStart.y

      for (const p of this._groupDragStart.others) {
        const otherNode = this._findNodeById(p.id)
        if (otherNode) otherNode.position({ x: p.start.x + dx, y: p.start.y + dy })
      }
      this._overlayLayer.batchDraw()
    })

    stage.on('dragend', (e) => {
      if (this.options.readOnly) return
      const node = e.target
      const id = node.attrs.objectId
      if (!id) return
      const obj = this._doc?.getObject(id)
      if (!obj) return

      if (this._selectedIds.size > 1 && this._groupDragStart) {
        const dx = node.x() - this._groupDragStart.primaryStart.x
        const dy = node.y() - this._groupDragStart.primaryStart.y
        for (const p of this._groupDragStart.others) {
          const other = this._doc.getObject(p.id)
          if (!other || other.locked) continue
          other.x = Math.round(p.start.x + dx - other.width / 2)
          other.y = Math.round(p.start.y + dy - other.height / 2)
        }
      }

      obj.x = Math.round(node.x() - obj.width / 2)
      obj.y = Math.round(node.y() - obj.height / 2)

      this._doc._markDirty()
      this._groupDragStart = null
      this._renderObjects()
      this._renderSelection()
      this._onDocumentChange?.('move', [...this._selectedIds])
    })

    stage.on('mousedown touchstart', (e) => {
      const evt = e.evt
      const pos = stage.getPointerPosition()
      if (!pos) return

      // Zone drawing intercepts all pointer interaction
      if (this._drawMode === 'rect') {
        evt.preventDefault()
        this._rectStart = stage.getRelativePointerPosition()
        this._tempShape = new Konva.Rect({
          x: this._rectStart.x,
          y: this._rectStart.y,
          width: 0,
          height: 0,
          fill: 'rgba(114,90,64,0.14)',
          stroke: ACCENT,
          strokeWidth: 1.5 / this.options.zoom,
          dash: [6, 4],
          listening: false,
        })
        this._overlayLayer.add(this._tempShape)
        this._dragMode = 'zoneRect'
        this._overlayLayer.batchDraw()
        return
      }

      if (this._drawMode === 'polygon') {
        evt.preventDefault()
        const p = stage.getRelativePointerPosition()
        this._polyPoints.push({ x: p.x, y: p.y })
        this._redrawTempPolygon()
        return
      }

      const isObject = e.target.attrs?.name === 'object'
      const isZone = e.target.attrs?.name === 'zone'

      if (evt.button === 1 || this._spaceDown) {
        evt.preventDefault()
        this._dragMode = 'pan'
        this._panStart = { pos, pan: { ...this.options.pan } }
        return
      }

      if (isZone && !isObject) {
        this._onZoneClick?.(e.target.attrs.zoneId)
        this._setSelection([])
        return
      }

      if (isObject) {
        const id = e.target.attrs.objectId
        if (evt.shiftKey) {
          this._toggleSelection(id)
        } else if (!this._selectedIds.has(id)) {
          this._setSelection(this._groupSelectionFor(id))
        }
        return
      }

      if (evt.shiftKey) {
        this._marqueeStart = pos
        this._dragMode = 'marquee'
        this._marquee = new Konva.Rect({
          x: pos.x,
          y: pos.y,
          width: 0,
          height: 0,
          fill: 'rgba(114,90,64,0.12)',
          stroke: ACCENT,
          strokeWidth: 1,
          dash: [4, 4],
          listening: false,
          name: 'marquee',
        })
        this._overlayLayer.add(this._marquee)
        this._overlayLayer.batchDraw()
      } else {
        this._setSelection([])
      }
    })

    stage.on('mousemove touchmove', () => {
      const pos = stage.getPointerPosition()
      if (!pos) return

      if (this._dragMode === 'zoneRect' && this._tempShape && this._rectStart) {
        const p = stage.getRelativePointerPosition()
        const x = Math.min(this._rectStart.x, p.x)
        const y = Math.min(this._rectStart.y, p.y)
        const w = Math.abs(p.x - this._rectStart.x)
        const h = Math.abs(p.y - this._rectStart.y)
        this._tempShape.setAttrs({ x, y, width: w, height: h })
        this._overlayLayer.batchDraw()
      } else if (this._dragMode === 'pan' && this._panStart) {
        this.options.pan = {
          x: this._panStart.pan.x + (pos.x - this._panStart.pos.x),
          y: this._panStart.pan.y + (pos.y - this._panStart.pos.y),
        }
        this._applyTransform()
      } else if (this._dragMode === 'marquee' && this._marquee && this._marqueeStart) {
        const x = Math.min(this._marqueeStart.x, pos.x)
        const y = Math.min(this._marqueeStart.y, pos.y)
        const w = Math.abs(pos.x - this._marqueeStart.x)
        const h = Math.abs(pos.y - this._marqueeStart.y)
        this._marquee.setAttrs({ x, y, width: w, height: h })
        this._overlayLayer.batchDraw()
      }
    })

    stage.on('mouseup touchend', () => {
      if (this._dragMode === 'zoneRect' && this._tempShape) {
        const x = Math.round(this._tempShape.x())
        const y = Math.round(this._tempShape.y())
        const width = Math.round(this._tempShape.width())
        const height = Math.round(this._tempShape.height())
        this._tempShape.destroy()
        this._tempShape = null
        this._rectStart = null
        this._dragMode = null
        if (width > 10 && height > 10) {
          this._onZoneDrawComplete?.({ kind: 'rect', x, y, width, height })
        }
        this.cancelZoneDraw()
        this._overlayLayer.batchDraw()
        return
      }

      if (this._dragMode === 'marquee' && this._marquee) {
        const rect = this._marquee.getClientRect()
        const ids = []
        for (const node of this._objectLayer.getChildren()) {
          const box = node.getClientRect()
          if (
            box.x >= rect.x &&
            box.y >= rect.y &&
            box.x + box.width <= rect.x + rect.width &&
            box.y + box.height <= rect.y + rect.height &&
            node.attrs.objectId
          ) {
            ids.push(node.attrs.objectId)
          }
        }
        this._setSelection(ids)
        this._marquee.destroy()
        this._marquee = null
        this._marqueeStart = null
        this._overlayLayer.batchDraw()
      }

      this._dragMode = null
      this._panStart = null
    })

    stage.on('wheel', (e) => {
      e.evt.preventDefault()
      const pointer = stage.getPointerPosition()
      if (!pointer) return

      const oldZoom = this.options.zoom
      const factor = e.evt.deltaY > 0 ? 1 / 1.08 : 1.08
      const newZoom = Math.max(0.1, Math.min(4, oldZoom * factor))
      if (newZoom === oldZoom) return

      const worldPoint = {
        x: (pointer.x - this.options.pan.x) / oldZoom,
        y: (pointer.y - this.options.pan.y) / oldZoom,
      }

      this.options.zoom = newZoom
      this.options.pan = {
        x: pointer.x - worldPoint.x * newZoom,
        y: pointer.y - worldPoint.y * newZoom,
      }

      this._applyTransform()
      this._renderSelection()
      this._onZoomChange?.(newZoom)
    })

    stage.on('mouseenter', () => {
      stage.container().style.cursor = this._spaceDown ? 'grab' : 'default'
    })

    stage.on('dblclick dbltap', () => {
      if (this._drawMode === 'polygon') this.finishPolygon()
    })
  }

  // ------------------------------------------------------------------
  // Zone drawing
  // ------------------------------------------------------------------

  beginZoneDraw(kind) {
    this._drawMode = kind
    this._polyPoints = []
    this._tempShape = null
    this._rectStart = null
    this._objectLayer.listening(false)
    this._zoneLayer.listening(false)
    if (this._stage) this._stage.container().style.cursor = 'crosshair'
    if (kind === 'polygon') this._redrawTempPolygon()
  }

  cancelZoneDraw() {
    this._drawMode = null
    this._polyPoints = []
    this._rectStart = null
    if (this._tempShape) {
      this._tempShape.destroy()
      this._tempShape = null
    }
    this._objectLayer.listening(true)
    this._zoneLayer.listening(true)
    if (this._stage) this._stage.container().style.cursor = this._spaceDown ? 'grab' : 'default'
    this._overlayLayer.batchDraw()
  }

  _redrawTempPolygon() {
    if (this._tempShape) {
      this._tempShape.destroy()
      this._tempShape = null
    }
    if (this._polyPoints.length === 0) {
      this._overlayLayer.batchDraw()
      return
    }
    this._tempShape = new Konva.Line({
      points: this._polyPoints.flatMap((p) => [p.x, p.y]),
      stroke: ACCENT,
      strokeWidth: 1.5 / this.options.zoom,
      dash: [6, 4],
      fill: 'rgba(114,90,64,0.12)',
      closed: false,
      listening: false,
    })
    this._overlayLayer.add(this._tempShape)
    this._overlayLayer.batchDraw()
  }

  finishPolygon() {
    if (this._drawMode !== 'polygon') return
    const points = this._polyPoints.slice()
    if (points.length >= 3) {
      this._onZoneDrawComplete?.({ kind: 'polygon', points })
    }
    this.cancelZoneDraw()
  }

  onZoneDrawComplete(cb) {
    this._onZoneDrawComplete = cb
  }

  _findNodeById(id) {
    return this._objectLayer.getChildren().find((n) => n.attrs.objectId === id) || null
  }

  _groupSelectionFor(id) {
    const obj = this._doc?.getObject(id)
    if (obj?.group_id) {
      const ids = this._doc.objects
        .filter((o) => o.group_id === obj.group_id && o.visible !== false)
        .map((o) => o.id)
      if (ids.length) return ids
    }
    return [id]
  }

  _setSelection(ids) {
    this._selectedIds = new Set(ids)
    this._renderSelection()
    this._stage.batchDraw()
    this._onSelectionChange?.([...this._selectedIds])
  }

  _toggleSelection(id) {
    if (this._selectedIds.has(id)) this._selectedIds.delete(id)
    else this._selectedIds.add(id)
    this._renderSelection()
    this._stage.batchDraw()
    this._onSelectionChange?.([...this._selectedIds])
  }

  clearSelection() {
    this._setSelection([])
  }

  select(ids) {
    this._setSelection(Array.isArray(ids) ? ids : [ids])
  }

  getSelectedIds() {
    return [...this._selectedIds]
  }

  getSelectedObjects() {
    return this.getSelectedIds().map((id) => this._doc?.getObject(id)).filter(Boolean)
  }

  setSpaceDown(down) {
    this._spaceDown = down
    if (this._stage) this._stage.container().style.cursor = down ? 'grab' : 'default'
  }

  setZoom(zoom) {
    this.options.zoom = Math.max(0.1, Math.min(4, zoom))
    this._applyTransform()
    this._renderSelection()
    this._onZoomChange?.(this.options.zoom)
  }

  zoomBy(factor) {
    this.setZoom(this.options.zoom * factor)
  }

  fitToObjects(objects) {
    const all = objects && objects.length ? objects : (this._doc?.objects || [])
    fitViewport(all, this.options)
    this._applyTransform()
    this._renderSelection()
    this._onZoomChange?.(this.options.zoom)
  }

  fitToCanvas() {
    const bounds = [{ x: 0, y: 0, width: this.options.canvasWidth, height: this.options.canvasHeight, rotation: 0 }]
    fitViewport(bounds, this.options, 90)
    this._applyTransform()
    this._renderSelection()
    this._onZoomChange?.(this.options.zoom)
  }

  resetView() {
    this.options.zoom = 1
    this.options.pan = { x: 0, y: 0 }
    this._applyTransform()
    this._renderSelection()
    this._onZoomChange?.(1)
  }

  zoomToSelection() {
    const objs = this.getSelectedObjects()
    if (!objs.length) return
    fitViewport(objs, this.options, 120)
    this._applyTransform()
    this._renderSelection()
    this._onZoomChange?.(this.options.zoom)
  }

  setGridVisible(visible) {
    this.options.gridVisible = visible
    this._renderGrid()
    this._stage.batchDraw()
  }

  setSnapEnabled(enabled) {
    this.options.snapEnabled = enabled
    this._renderObjects()
    this._stage.batchDraw()
  }

  setGridSize(size) {
    this.options.gridSize = Math.max(1, size)
    this._renderGrid()
    this._stage.batchDraw()
  }

  refresh() {
    this._renderBackground()
    this._renderGrid()
    this._renderZones()
    this._renderObjects()
    this._renderSelection()
    this._stage.batchDraw()
  }

  resize(width, height) {
    this.options.width = width
    this.options.height = height
    this._stage.size({ width, height })
    this._renderAll()
  }

  clientToWorld(clientX, clientY) {
    const rect = this._stage.container().getBoundingClientRect()
    return screenToWorld(
      { x: clientX - rect.left, y: clientY - rect.top },
      { zoom: this.options.zoom, pan: this.options.pan },
    )
  }

  onSelectionChange(cb) { this._onSelectionChange = cb }
  onDocumentChange(cb) { this._onDocumentChange = cb }
  onZoneClick(cb) { this._onZoneClick = cb }
  onZoomChange(cb) { this._onZoomChange = cb }

  destroy() {
    if (this._stage) {
      this._stage.destroy()
      this._stage = null
    }
  }
}

export default Stage