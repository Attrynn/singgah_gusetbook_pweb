import { describe, expect, it } from 'vitest'
import Document from '../../resources/js/editor/document.js'
import {
  deserializeDocument,
  isKnownObjectType,
  isKnownZoneType,
  isObjectCountWithinLimit,
  isPayloadWithinLimit,
  isZoneCountWithinLimit,
  serializeDocument,
} from '../../resources/js/editor/serializer.js'

function sampleDocument() {
  const doc = new Document()
  doc.addObject({
    id: 'table_000001',
    type: 'round_table',
    x: 100,
    y: 200,
    width: 180,
    height: 180,
    label: 'Meja 1',
    properties: { capacity: 8, table_number: '1' },
  })
  doc.addObject({
    id: 'chair_000001',
    type: 'chair',
    x: 190,
    y: 400,
    width: 45,
    height: 45,
    properties: { table_id: 'table_000001', seat_index: 0 },
  })
  doc.addZone({
    id: 'zone_0000001',
    type: 'vip',
    name: 'Area VIP',
    geometry: { kind: 'rect', x: 0, y: 0, width: 500, height: 400 },
  })

  return doc
}

describe('serializeDocument', () => {
  it('produces the backend document shape', () => {
    const json = serializeDocument(sampleDocument())

    expect(json.schema_version).toBe(1)
    expect(json.canvas.unit).toBe('cm')
    expect(json.objects).toHaveLength(2)
    expect(json.zones).toHaveLength(1)
    expect(json.objects[0].properties.capacity).toBe(8)
    expect(json.objects[1].properties.table_id).toBe('table_000001')
    expect(json.zones[0].geometry).toEqual({ kind: 'rect', x: 0, y: 0, width: 500, height: 400 })
  })

  it('fills required properties for every object', () => {
    const json = serializeDocument(sampleDocument())
    const chair = json.objects[1]

    expect(chair).toHaveProperty('z_index')
    expect(chair).toHaveProperty('visible')
    expect(chair).toHaveProperty('locked')
    expect(chair).toHaveProperty('group_id')
    expect(chair).toHaveProperty('parent_id')
    expect(chair.properties).toHaveProperty('seat_spacing')
    expect(chair.properties).toHaveProperty('generated')
  })

  it('serializes polygon zones', () => {
    const doc = new Document()
    doc.addZone({
      id: 'zone_poly001',
      type: 'vip',
      name: 'Poly',
      geometry: { kind: 'polygon', points: [{ x: 0, y: 0 }, { x: 100, y: 0 }, { x: 100, y: 100 }] },
    })

    const json = serializeDocument(doc)
    expect(json.zones[0].geometry.kind).toBe('polygon')
    expect(json.zones[0].geometry.points).toHaveLength(3)
  })
})

describe('deserializeDocument', () => {
  it('round-trips a serialized document', () => {
    const json = serializeDocument(sampleDocument())
    const doc = deserializeDocument(json)

    expect(doc.objects).toHaveLength(2)
    expect(doc.zones).toHaveLength(1)
    expect(doc.getObject('table_000001').properties.capacity).toBe(8)
  })

  it('rejects an unsupported schema version', () => {
    expect(() => deserializeDocument({ schema_version: 2, objects: [], zones: [] })).toThrow()
  })
})

describe('schema guards', () => {
  it('recognizes known object and zone types', () => {
    expect(isKnownObjectType('round_table')).toBe(true)
    expect(isKnownObjectType('chair')).toBe(true)
    expect(isKnownObjectType('portal')).toBe(false)
    expect(isKnownZoneType('vip')).toBe(true)
    expect(isKnownZoneType('nowhere')).toBe(false)
  })

  it('enforces the 2 MB payload limit', () => {
    expect(isPayloadWithinLimit({ objects: [] })).toBe(true)
    expect(isPayloadWithinLimit({ padding: 'x'.repeat(2 * 1024 * 1024 + 1) })).toBe(false)
  })

  it('enforces object and zone count limits', () => {
    expect(isObjectCountWithinLimit(new Array(2000).fill({}))).toBe(true)
    expect(isObjectCountWithinLimit(new Array(2001).fill({}))).toBe(false)
    expect(isZoneCountWithinLimit(new Array(200).fill({}))).toBe(true)
    expect(isZoneCountWithinLimit(new Array(201).fill({}))).toBe(false)
  })
})
