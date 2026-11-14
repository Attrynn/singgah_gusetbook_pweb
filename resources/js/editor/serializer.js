/**
 * Document serialization / deserialization + schema guards.
 * Converts between the internal document model and the backend JSON contract.
 */

import Document from './document.js'
import { OBJECT_TYPES, ZONE_TYPES } from './types.js'

/** Normalise a rotation into [0, 360). */
function normalizeRotation(value) {
  const n = Number(value) || 0
  const wrapped = ((n % 360) + 360) % 360
  return Math.round(wrapped * 100) / 100
}

/** Serialize a document to the backend JSON shape. */
export function serializeDocument(document) {
  const json = document.toJSON()
  return {
    schema_version: 1,
    canvas: {
      width: Number(document.canvas.width) || 2000,
      height: Number(document.canvas.height) || 1500,
      unit: document.canvas.unit || 'cm',
      grid_size: Number(document.canvas.grid_size) || 50,
      grid_visible: document.canvas.grid_visible !== false,
      snap_enabled: document.canvas.snap_enabled !== false,
      background_color: document.canvas.background_color || '#ffffff',
    },
    objects: (document.objects || []).map((obj) => ({
      id: obj.id,
      type: obj.type,
      x: Number(obj.x) || 0,
      y: Number(obj.y) || 0,
      width: Number(obj.width) || 1,
      height: Number(obj.height) || 1,
      rotation: normalizeRotation(obj.rotation),
      z_index: Math.round(Number(obj.z_index) || 0),
      visible: obj.visible !== false,
      locked: obj.locked === true,
      label: obj.label || obj.type,
      group_id: obj.group_id ?? null,
      parent_id: obj.parent_id ?? null,
      properties: {
        ...(obj.properties || {}),
        capacity: obj.properties?.capacity ?? 0,
        table_number: obj.properties?.table_number ?? null,
        color: obj.properties?.color ?? '#f5f5f5',
        table_id: obj.properties?.table_id ?? null,
        seat_index: obj.properties?.seat_index ?? 0,
        seat_spacing: obj.properties?.seat_spacing ?? 30,
        generated: obj.properties?.generated ?? true,
      },
    })),
    zones: (document.zones || []).map((zone) => ({
      id: zone.id,
      type: zone.type,
      name: zone.name,
      color: zone.color,
      description: zone.description ?? null,
      visible: zone.visible !== false,
      capacity: Number(zone.capacity) || 0,
      geometry: zone.geometry?.kind === 'polygon'
        ? { kind: 'polygon', points: (zone.geometry.points || []).map((p) => (Array.isArray(p) ? [Number(p[0]) || 0, Number(p[1]) || 0] : [Number(p.x) || 0, Number(p.y) || 0])) }
        : {
            kind: 'rect',
            x: Number(zone.geometry?.x) || 0,
            y: Number(zone.geometry?.y) || 0,
            width: Number(zone.geometry?.width) || 100,
            height: Number(zone.geometry?.height) || 100,
          },
    })),
  }
}

/** Deserialize JSON into a validated Document instance. */
export function deserializeDocument(json) {
  const doc = new Document()
  return doc.fromJSON(json)
}

/** Validate a raw object payload against the schema. Returns { valid, errors }. */
export function validateObjectPayload(obj) {
  const doc = new Document()
  const validated = doc.validateObject(obj)
  const errors = validated._validationErrors || validated.errors || []
  return { valid: errors.length === 0, errors }
}

/** Validate a raw zone payload against the schema. Returns { valid, errors }. */
export function validateZonePayload(zone) {
  const doc = new Document()
  const validated = doc.validateZone(zone)
  const errors = validated._validationErrors || validated.errors || []
  return { valid: errors.length === 0, errors }
}

/** Check if a type is a known object type. */
export function isKnownObjectType(type) {
  return Boolean(OBJECT_TYPES[type])
}

/** Check if a type is a known zone type. */
export function isKnownZoneType(type) {
  return Boolean(ZONE_TYPES[type])
}

/** Check payload size against the 2MB backend limit. */
export function isPayloadWithinLimit(json) {
  const size = JSON.stringify(json).length
  return size <= 2 * 1024 * 1024
}

/** Check object count against the 2000 backend limit. */
export function isObjectCountWithinLimit(objects) {
  return Array.isArray(objects) && objects.length <= 2000
}

/** Check zone count against the 200 backend limit. */
export function isZoneCountWithinLimit(zones) {
  return Array.isArray(zones) && zones.length <= 200
}

export default {
  serializeDocument,
  deserializeDocument,
  validateObjectPayload,
  validateZonePayload,
  isKnownObjectType,
  isKnownZoneType,
  isPayloadWithinLimit,
  isObjectCountWithinLimit,
  isZoneCountWithinLimit,
}
