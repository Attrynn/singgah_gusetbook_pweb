/**
 * Document model for the floor plan editor.
 * Manages the JSON document structure, object/zone CRUD, and stable ID generation.
 *
 * Object IDs: 8–64 chars [A-Za-z0-9_-]
 * Property keys: snake_case, max 40 chars
 * Capacity: 0–200 per table
 */

import {
  OBJECT_TYPES,
  ZONE_TYPES,
  isTableType,
  isChairType,
  objectLabel,
  zoneLabel,
  defaultSize,
  defaultColor,
} from './types.js'

const ID_CHARS = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789_-'

/** Generate a stable 12-char ID matching [A-Za-z0-9_-]{8,64}. */
export function generateObjectId(length = 12) {
  let id = ''
  for (let i = 0; i < length; i++) {
    id += ID_CHARS[Math.floor(Math.random() * ID_CHARS.length)]
  }
  return id
}

/** Validate an object ID format: 8–64 chars [A-Za-z0-9_-]. */
export function isValidObjectId(id) {
  return typeof id === 'string' && /^[A-Za-z0-9_-]{8,64}$/.test(id)
}

/** Validate a label: ≤120 chars. */
export function isValidLabel(label) {
  return typeof label === 'string' && label.length <= 120
}

/** Validate a property key: snake_case, ≤40 chars. */
export function isValidPropertyKey(key) {
  return typeof key === 'string' && /^[a-z][a-z0-9_]{0,39}$/.test(key)
}

/** Validate capacity: 0–200 integer. */
export function isValidCapacity(cap) {
  return typeof cap === 'number' && Number.isInteger(cap) && cap >= 0 && cap <= 200
}

/** Clamp a coordinate to the accepted ±100000 range. */
export function clampCoord(value) {
  const n = Number(value)
  if (!Number.isFinite(n)) return 0
  return Math.max(-100000, Math.min(100000, n))
}

/**
 * Document class — the single source of truth for the floor plan state.
 * Objects and zones are stored as plain serializable records.
 */
class Document {
  constructor(raw = {}) {
    this.canvas = {
      width: raw.canvas?.width ?? 2000,
      height: raw.canvas?.height ?? 1500,
      unit: raw.canvas?.unit ?? 'cm',
      grid_size: raw.canvas?.grid_size ?? 50,
      grid_visible: raw.canvas?.grid_visible ?? true,
      snap_enabled: raw.canvas?.snap_enabled ?? true,
      background_color: raw.canvas?.background_color ?? '#ffffff',
    }

    this.objects = []
    this.zones = []
    this._objectMap = new Map()
    this._zoneMap = new Map()

    if (raw.objects) {
      for (const obj of raw.objects) this.addObject(obj)
    }
    if (raw.zones) {
      for (const zone of raw.zones) this.addZone(zone)
    }

    this._isDirty = false
    this._lastChange = 0
  }

  /** Serialize to the exact backend-accepted JSON shape. */
  toJSON() {
    return {
      schema_version: 1,
      canvas: { ...this.canvas },
      objects: this.objects.map((o) => ({ ...o, properties: { ...o.properties } })),
      zones: this.zones.map((z) => ({ ...z, geometry: { ...z.geometry } })),
    }
  }

  /** Load from serialized JSON, running schema guards. */
  fromJSON(json) {
    this.objects = []
    this.zones = []
    this._objectMap.clear()
    this._zoneMap.clear()

    if (json.schema_version !== 1) {
      throw new Error('Unsupported schema version')
    }

    if (json.canvas) {
      this.canvas = {
        width: json.canvas.width ?? 2000,
        height: json.canvas.height ?? 1500,
        unit: json.canvas.unit ?? 'cm',
        grid_size: json.canvas.grid_size ?? 50,
        grid_visible: json.canvas.grid_visible ?? true,
        snap_enabled: json.canvas.snap_enabled ?? true,
        background_color: json.canvas.background_color ?? '#ffffff',
      }
    }

    if (json.objects) {
      for (const obj of json.objects) this.addObject(obj)
    }
    if (json.zones) {
      for (const zone of json.zones) this.addZone(zone)
    }

    this._isDirty = false
    return this
  }

  /** Add an object, validating and assigning an ID when needed. */
  addObject(raw) {
    let id = raw.id
    if (!isValidObjectId(id)) id = generateObjectId()

    const type = OBJECT_TYPES[raw.type] ? raw.type : 'rectangle_table'
    const props = { ...(raw.properties || {}) }

    if (isTableType(type) && props.capacity === undefined) props.capacity = 4
    if (props.capacity !== undefined && !isValidCapacity(props.capacity)) {
      props.capacity = Math.max(0, Math.min(200, Math.round(Number(props.capacity) || 0)))
    }

    const size = defaultSize(type)
    const label = isValidLabel(raw.label) ? raw.label : objectLabel(type)

    const obj = {
      id,
      type,
      x: clampCoord(raw.x ?? 0),
      y: clampCoord(raw.y ?? 0),
      width: raw.width > 0 ? raw.width : size.width,
      height: raw.height > 0 ? raw.height : size.height,
      rotation: Number(raw.rotation) || 0,
      z_index: Number.isFinite(raw.z_index) ? raw.z_index : 0,
      visible: raw.visible !== false,
      locked: raw.locked === true,
      label,
      group_id: raw.group_id ?? null,
      parent_id: raw.parent_id ?? null,
      properties: props,
    }

    this.objects.push(obj)
    this._objectMap.set(id, obj)
    this._markDirty()
    return obj
  }

  getObject(id) {
    return this._objectMap.get(id) ?? null
  }

  removeObject(id) {
    if (!this._objectMap.has(id)) return false
    this.objects = this.objects.filter((o) => o.id !== id)
    this._objectMap.delete(id)
    this._markDirty()
    return true
  }

  /** Update whitelisted object fields. */
  updateObject(id, updates) {
    const obj = this.getObject(id)
    if (!obj) return false

    const allowed = ['label', 'x', 'y', 'width', 'height', 'rotation', 'z_index', 'visible', 'locked']
    for (const key of allowed) {
      if (updates[key] === undefined) continue
      if (key === 'label' && !isValidLabel(updates.label)) continue
      if ((key === 'width' || key === 'height') && !(updates[key] > 0)) continue
      if (key === 'x' || key === 'y') {
        obj[key] = clampCoord(updates[key])
        continue
      }
      obj[key] = updates[key]
    }

    this._markDirty()
    return true
  }

  /** Update an object's properties map (validated keys only). */
  updateObjectProperties(id, props) {
    const obj = this.getObject(id)
    if (!obj) return false

    for (const [key, value] of Object.entries(props)) {
      if (!isValidPropertyKey(key)) continue
      if (key === 'capacity' && !isValidCapacity(value)) continue
      obj.properties[key] = value
    }

    this._markDirty()
    return true
  }

  addZone(raw) {
    let id = raw.id
    if (!isValidObjectId(id)) id = generateObjectId()

    const type = ZONE_TYPES[raw.type] ? raw.type : 'other'
    const geometry = raw.geometry?.kind === 'polygon'
      ? {
          kind: 'polygon',
          points: (raw.geometry.points || []).map((p) =>
            Array.isArray(p)
              ? { x: Number(p[0]) || 0, y: Number(p[1]) || 0 }
              : { x: Number(p.x) || 0, y: Number(p.y) || 0 },
          ),
        }
      : {
          kind: 'rect',
          x: clampCoord(raw.geometry?.x ?? 0),
          y: clampCoord(raw.geometry?.y ?? 0),
          width: Math.max(1, Number(raw.geometry?.width) || 100),
          height: Math.max(1, Number(raw.geometry?.height) || 100),
        }

    const zone = {
      id,
      type,
      name: isValidLabel(raw.name) ? raw.name : zoneLabel(type),
      color: /^#[0-9a-fA-F]{6}$/.test(raw.color || '') ? raw.color : '#ffcc00',
      description: raw.description ?? null,
      visible: raw.visible !== false,
      capacity: Number.isInteger(raw.capacity) ? Math.max(0, Math.min(100000, raw.capacity)) : 0,
      geometry,
    }

    this.zones.push(zone)
    this._zoneMap.set(id, zone)
    this._markDirty()
    return zone
  }

  getZone(id) {
    return this._zoneMap.get(id) ?? null
  }

  removeZone(id) {
    if (!this._zoneMap.has(id)) return false
    this.zones = this.zones.filter((z) => z.id !== id)
    this._zoneMap.delete(id)
    this._markDirty()
    return true
  }

  updateZone(id, updates) {
    const zone = this.getZone(id)
    if (!zone) return false

    const allowed = ['name', 'color', 'visible', 'capacity', 'description']
    for (const key of allowed) {
      if (updates[key] === undefined) continue
      if (key === 'name' && !isValidLabel(updates.name)) continue
      if (key === 'color' && !/^#[0-9a-fA-F]{6}$/.test(updates.color)) continue
      zone[key] = updates[key]
    }

    this._markDirty()
    return true
  }

  _markDirty() {
    this._isDirty = true
    this._lastChange = Date.now()
  }

  isDirty() {
    return this._isDirty
  }

  markClean() {
    this._isDirty = false
  }

  /** Validate an object record against schema guards. Returns list of issues. */
  validateObject(obj) {
    const errors = []
    if (obj.id && !isValidObjectId(obj.id)) {
      errors.push({ code: 'invalid_object_id', message: 'ID objek tidak valid' })
    }
    if (obj.type && !OBJECT_TYPES[obj.type]) {
      errors.push({ code: 'invalid_type', message: `Tipe objek tidak dikenal: ${obj.type}` })
    }
    if (obj.label && !isValidLabel(obj.label)) {
      errors.push({ code: 'invalid_label', message: 'Label maksimal 120 karakter' })
    }
    if (obj.properties) {
      for (const key of Object.keys(obj.properties)) {
        if (!isValidPropertyKey(key)) {
          errors.push({ code: 'invalid_property_key', message: `Kunci properti tidak valid: ${key}` })
        }
      }
    }
    if (isTableType(obj.type) && obj.properties?.capacity !== undefined && !isValidCapacity(obj.properties.capacity)) {
      errors.push({ code: 'invalid_capacity', message: 'Kapasitas harus 0–200' })
    }
    if (obj.x !== undefined && (obj.x < -100000 || obj.x > 100000)) {
      errors.push({ code: 'out_of_bounds', message: 'Koordinat X di luar rentang' })
    }
    if (obj.y !== undefined && (obj.y < -100000 || obj.y > 100000)) {
      errors.push({ code: 'out_of_bounds', message: 'Koordinat Y di luar rentang' })
    }
    if (obj.width !== undefined && obj.width <= 0) {
      errors.push({ code: 'invalid_dimension', message: 'Lebar harus > 0' })
    }
    if (obj.height !== undefined && obj.height <= 0) {
      errors.push({ code: 'invalid_dimension', message: 'Tinggi harus > 0' })
    }
    return errors
  }

  /** Validate a zone record against schema guards. Returns list of issues. */
  validateZone(zone) {
    const errors = []
    if (zone.id && !isValidObjectId(zone.id)) {
      errors.push({ code: 'invalid_object_id', message: 'ID zona tidak valid' })
    }
    if (zone.type && !ZONE_TYPES[zone.type]) {
      errors.push({ code: 'invalid_type', message: `Tipe zona tidak dikenal: ${zone.type}` })
    }
    if (zone.name && zone.name.length > 120) {
      errors.push({ code: 'invalid_name', message: 'Nama zona maksimal 120 karakter' })
    }
    if (zone.color && !/^#[0-9a-fA-F]{6}$/.test(zone.color)) {
      errors.push({ code: 'invalid_color', message: 'Warna harus format #rrggbb' })
    }
    if (zone.geometry?.kind === 'rect') {
      if (!(zone.geometry.width > 0) || !(zone.geometry.height > 0)) {
        errors.push({ code: 'invalid_geometry', message: 'Zona persegi harus memiliki lebar/tinggi positif' })
      }
    } else if (zone.geometry?.kind === 'polygon') {
      const pts = zone.geometry.points || []
      if (pts.length < 3 || pts.length > 200) {
        errors.push({ code: 'invalid_geometry', message: 'Poligon harus memiliki 3–200 titik' })
      }
    }
    return errors
  }

  /** Summary stats for the status bar and validation panel. */
  getStats() {
    const objectCounts = {}
    const zoneCounts = {}
    for (const obj of this.objects) objectCounts[obj.type] = (objectCounts[obj.type] || 0) + 1
    for (const zone of this.zones) zoneCounts[zone.type] = (zoneCounts[zone.type] || 0) + 1

    const tables = this.objects.filter((o) => isTableType(o.type))
    const chairs = this.objects.filter((o) => isChairType(o.type))
    const totalCapacity = tables.reduce((sum, t) => sum + (t.properties?.capacity || 0), 0)

    return {
      objectCount: this.objects.length,
      zoneCount: this.zones.length,
      tableCount: tables.length,
      chairCount: chairs.length,
      totalCapacity,
      objectCounts,
      zoneCounts,
      withinLimits: this.objects.length <= 2000 && this.zones.length <= 200,
      payloadSize: JSON.stringify(this.toJSON()).length,
    }
  }
}

export default Document