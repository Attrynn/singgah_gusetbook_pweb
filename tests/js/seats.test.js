import { describe, expect, it } from 'vitest'
import {
  generateRectTableChairs,
  generateRoundTableChairs,
  generateTableChairs,
  getTableCenter,
  isRoundTable,
} from '../../resources/js/editor/seats.js'

const roundTable = { id: 'table_round', type: 'round_table', x: 100, y: 100, width: 180, height: 180, rotation: 0 }
const rectTable = { id: 'table_rect', type: 'rectangle_table', x: 100, y: 100, width: 240, height: 120, rotation: 0 }

describe('table classification', () => {
  it('treats round tables and circles as round', () => {
    expect(isRoundTable({ type: 'round_table' })).toBe(true)
    expect(isRoundTable({ type: 'circle' })).toBe(true)
    expect(isRoundTable({ type: 'ellipse' })).toBe(true)
    expect(isRoundTable({ type: 'rectangle_table' })).toBe(false)
  })
})

describe('round table chair generation', () => {
  it('generates exactly the requested capacity, capped at 32', () => {
    expect(generateRoundTableChairs(roundTable, 8)).toHaveLength(8)
    expect(generateRoundTableChairs(roundTable, 0)).toHaveLength(1)
    expect(generateRoundTableChairs(roundTable, 100)).toHaveLength(32)
  })

  it('assigns sequential seat indices and unique positions', () => {
    const chairs = generateRoundTableChairs(roundTable, 8)
    const indices = chairs.map((chair) => chair.seat_index)
    expect(indices).toEqual([0, 1, 2, 3, 4, 5, 6, 7])

    const positions = new Set(chairs.map((chair) => `${chair.x}:${chair.y}`))
    expect(positions.size).toBe(8)
  })

  it('places chairs outside the table footprint', () => {
    const centerX = roundTable.x + roundTable.width / 2
    const centerY = roundTable.y + roundTable.height / 2
    const radius = roundTable.width / 2

    for (const chair of generateRoundTableChairs(roundTable, 8)) {
      const distance = Math.hypot(chair.x - centerX, chair.y - centerY)
      expect(distance).toBeGreaterThan(radius)
    }
  })
})

describe('rectangular table chair generation', () => {
  it('generates the requested capacity', () => {
    expect(generateRectTableChairs(rectTable, 6)).toHaveLength(6)
    expect(generateRectTableChairs(rectTable, 10)).toHaveLength(10)
  })

  it('assigns unique sequential seat indices', () => {
    const chairs = generateRectTableChairs(rectTable, 8)
    expect(chairs.map((chair) => chair.seat_index)).toEqual([0, 1, 2, 3, 4, 5, 6, 7])
  })
})

describe('table chair dispatcher', () => {
  it('routes round tables to the round generator', () => {
    const chairs = generateTableChairs(roundTable, 6)
    expect(chairs).toHaveLength(6)
  })

  it('routes rectangular tables to the rect generator', () => {
    const chairs = generateTableChairs(rectTable, 6)
    expect(chairs).toHaveLength(6)
  })

  it('reports the table centre', () => {
    expect(getTableCenter(roundTable)).toEqual({ x: 190, y: 190 })
  })
})
