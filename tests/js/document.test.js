import { describe, expect, it } from 'vitest'
import Document, {
  clampCoord,
  generateObjectId,
  isValidCapacity,
  isValidLabel,
  isValidObjectId,
  isValidPropertyKey,
} from '../../resources/js/editor/document.js'

describe('document id and field guards', () => {
  it('generates ids that match the backend pattern', () => {
    for (let i = 0; i < 50; i++) {
      const id = generateObjectId()
      expect(id).toMatch(/^[A-Za-z0-9_-]{8,64}$/)
      expect(isValidObjectId(id)).toBe(true)
    }
  })

  it('rejects ids that are too short, too long or contain invalid characters', () => {
    expect(isValidObjectId('short')).toBe(false)
    expect(isValidObjectId('a'.repeat(65))).toBe(false)
    expect(isValidObjectId('bad id!!')).toBe(false)
    expect(isValidObjectId('a'.repeat(64))).toBe(true)
  })

  it('validates labels, property keys and capacity', () => {
    expect(isValidLabel('Meja 1')).toBe(true)
    expect(isValidLabel('x'.repeat(121))).toBe(false)
    expect(isValidPropertyKey('table_number')).toBe(true)
    expect(isValidPropertyKey('Table Number')).toBe(false)
    expect(isValidPropertyKey('9lives')).toBe(false)
    expect(isValidCapacity(0)).toBe(true)
    expect(isValidCapacity(200)).toBe(true)
    expect(isValidCapacity(201)).toBe(false)
    expect(isValidCapacity(8.5)).toBe(false)
  })

  it('clamps coordinates to the accepted range', () => {
    expect(clampCoord(200000)).toBe(100000)
    expect(clampCoord(-200000)).toBe(-100000)
    expect(clampCoord('not-a-number')).toBe(0)
  })
})

describe('Document object model', () => {
  it('adds an object with defaults and an id', () => {
    const doc = new Document()
    const table = doc.addObject({ type: 'round_table', x: 100, y: 100 })

    expect(table.id).toMatch(/^[A-Za-z0-9_-]{8,64}$/)
    expect(table.type).toBe('round_table')
    expect(table.properties.capacity).toBe(4)
    expect(doc.objects).toHaveLength(1)
    expect(doc.getObject(table.id)).toBe(table)
  })

  it('falls back to a safe type and clamps coordinates and capacity', () => {
    const doc = new Document()
    const obj = doc.addObject({
      type: 'not_a_real_type',
      x: 999999,
      y: -999999,
      properties: { capacity: 999 },
    })

    expect(obj.type).toBe('rectangle_table')
    expect(obj.x).toBe(100000)
    expect(obj.y).toBe(-100000)
    expect(obj.properties.capacity).toBe(200)
  })

  it('removes objects and reports whether anything changed', () => {
    const doc = new Document()
    const obj = doc.addObject({ type: 'chair' })

    expect(doc.removeObject(obj.id)).toBe(true)
    expect(doc.removeObject(obj.id)).toBe(false)
    expect(doc.objects).toHaveLength(0)
  })

  it('only updates whitelisted object fields and keeps coordinates valid', () => {
    const doc = new Document()
    const obj = doc.addObject({ type: 'rectangle_table', x: 0, y: 0 })

    doc.updateObject(obj.id, {
      label: 'Meja Utama',
      x: 250,
      width: 300,
      type: 'stage',
      properties: { hacked: true },
    })

    const updated = doc.getObject(obj.id)
    expect(updated.label).toBe('Meja Utama')
    expect(updated.x).toBe(250)
    expect(updated.width).toBe(300)
    expect(updated.type).toBe('rectangle_table')
    expect(updated.properties.hacked).toBeUndefined()
  })

  it('rejects invalid property keys and capacities on property updates', () => {
    const doc = new Document()
    const obj = doc.addObject({ type: 'round_table' })

    doc.updateObjectProperties(obj.id, {
      table_number: '7',
      'Bad Key': 1,
      capacity: 999,
    })

    const updated = doc.getObject(obj.id)
    expect(updated.properties.table_number).toBe('7')
    expect(updated.properties['Bad Key']).toBeUndefined()
    expect(updated.properties.capacity).toBe(4)
  })

  it('reports accurate stats', () => {
    const doc = new Document()
    doc.addObject({ type: 'round_table', properties: { capacity: 8 } })
    doc.addObject({ type: 'round_table', properties: { capacity: 6 } })
    doc.addObject({ type: 'chair' })
    doc.addZone({ type: 'vip', name: 'VIP', geometry: { kind: 'rect', width: 100, height: 100 } })

    const stats = doc.getStats()
    expect(stats.objectCount).toBe(3)
    expect(stats.tableCount).toBe(2)
    expect(stats.chairCount).toBe(1)
    expect(stats.zoneCount).toBe(1)
    expect(stats.totalCapacity).toBe(14)
    expect(stats.withinLimits).toBe(true)
  })
})

describe('Document zone model', () => {
  it('normalizes rectangle zone geometry', () => {
    const doc = new Document()
    const zone = doc.addZone({
      type: 'restricted',
      name: 'Area Terbatas',
      geometry: { kind: 'rect', x: 10, y: 20, width: 300, height: 200 },
    })

    expect(zone.type).toBe('restricted')
    expect(zone.geometry).toEqual({ kind: 'rect', x: 10, y: 20, width: 300, height: 200 })
  })

  it('accepts polygon points from the backend array shape', () => {
    const doc = new Document()
    const zone = doc.addZone({
      type: 'vip',
      name: 'VIP',
      geometry: {
        kind: 'polygon',
        points: [[0, 0], [500, 0], [500, 400]],
      },
    })

    expect(zone.geometry.points).toEqual([
      { x: 0, y: 0 },
      { x: 500, y: 0 },
      { x: 500, y: 400 },
    ])
  })

  it('accepts polygon points from the internal object shape', () => {
    const doc = new Document()
    const zone = doc.addZone({
      type: 'vip',
      name: 'VIP',
      geometry: {
        kind: 'polygon',
        points: [{ x: 10, y: 20 }, { x: 30, y: 20 }, { x: 30, y: 40 }],
      },
    })

    expect(zone.geometry.points[1]).toEqual({ x: 30, y: 20 })
  })

  it('falls back to a safe zone type and colour', () => {
    const doc = new Document()
    const zone = doc.addZone({ type: 'unknown', name: 'X', color: 'red' })

    expect(zone.type).toBe('other')
    expect(zone.color).toBe('#ffcc00')
  })

  it('removes zones', () => {
    const doc = new Document()
    const zone = doc.addZone({ type: 'vip', name: 'VIP' })

    expect(doc.removeZone(zone.id)).toBe(true)
    expect(doc.removeZone(zone.id)).toBe(false)
    expect(doc.zones).toHaveLength(0)
  })
})

describe('Document validation helpers', () => {
  it('flags invalid object payloads', () => {
    const doc = new Document()

    const errors = doc.validateObject({
      id: 'bad',
      type: 'portal',
      label: 'x'.repeat(200),
      x: 200000,
      width: 0,
      properties: { 'Bad Key': 1 },
    })

    const codes = errors.map((error) => error.code)
    expect(codes).toContain('invalid_object_id')
    expect(codes).toContain('invalid_type')
    expect(codes).toContain('invalid_label')
    expect(codes).toContain('out_of_bounds')
    expect(codes).toContain('invalid_dimension')
    expect(codes).toContain('invalid_property_key')
  })

  it('flags polygon zones outside the 3–200 point range', () => {
    const doc = new Document()

    expect(doc.validateZone({
      type: 'vip',
      name: 'VIP',
      geometry: { kind: 'polygon', points: [{ x: 0, y: 0 }] },
    }).map((error) => error.code)).toContain('invalid_geometry')
  })
})
