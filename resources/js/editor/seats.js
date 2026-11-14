/**
 * Chair generation helpers for tables.
 * Produces chair positions around round and rectangular tables; the editor
 * turns these into real chair objects with `properties.table_id` and
 * `properties.seat_index`.
 */

import { boundingBox, objectCenter } from './geometry.js'

const ROUND_TYPES = ['round_table', 'circle', 'ellipse']

export function isRoundTable(table) {
  return ROUND_TYPES.includes(table.type)
}

/** Chair positions around a round table (centimetres). */
export function generateRoundTableChairs(table, capacity, seatSpacing = 30, startAngle = -90) {
  const positions = []
  const radius = Math.max(45, Math.min(table.width, table.height) / 2 + 30 + seatSpacing / 2)
  const count = Math.max(1, Math.min(capacity, 32))
  const angleStep = 360 / count

  for (let i = 0; i < count; i++) {
    const angleDeg = startAngle + i * angleStep + angleStep / 2
    const angle = (angleDeg * Math.PI) / 180
    positions.push({
      x: Math.round((table.x + table.width / 2 + radius * Math.cos(angle)) * 100) / 100,
      y: Math.round((table.y + table.height / 2 + radius * Math.sin(angle)) * 100) / 100,
      angle: Math.round(angleDeg * 10) / 10,
      seat_index: i,
    })
  }

  return positions
}

/** Chair positions distributed along the four sides of a rectangular table. */
export function generateRectTableChairs(table, capacity, seatSpacing = 30) {
  const positions = []
  const count = Math.max(1, Math.min(capacity, 64))
  const perLong = Math.ceil(count / 2)
  const perShort = Math.max(1, Math.floor((count - perLong * 2) / 2))
  const chair = 45
  let seatIndex = 0

  const addRow = (y, xs) => {
    for (const x of xs) {
      positions.push({
        x: Math.round(x * 100) / 100,
        y: Math.round(y * 100) / 100,
        angle: 0,
        seat_index: seatIndex++,
      })
    }
  }

  // Top and bottom rows (long sides)
  const topXs = []
  const bottomXs = []
  for (let i = 0; i < perLong; i++) {
    const offset = (i + 0.5) * (table.width / perLong)
    topXs.push(table.x + offset)
    bottomXs.push(table.x + offset)
  }
  addRow(table.y - chair / 2, topXs)
  addRow(table.y + table.height + chair / 2, bottomXs)

  // Left and right columns (short sides), only if capacity remains
  const remaining = count - perLong * 2
  if (remaining > 0) {
    const sideCount = Math.ceil(remaining / 2)
    for (let i = 0; i < sideCount && seatIndex < count; i++) {
      const offset = (i + 0.5) * (table.height / sideCount)
      positions.push({
        x: Math.round((table.x - chair / 2) * 100) / 100,
        y: Math.round((table.y + offset) * 100) / 100,
        angle: 90,
        seat_index: seatIndex++,
      })
    }
    for (let i = 0; i < sideCount && seatIndex < count; i++) {
      const offset = (i + 0.5) * (table.height / sideCount)
      positions.push({
        x: Math.round((table.x + table.width + chair / 2) * 100) / 100,
        y: Math.round((table.y + offset) * 100) / 100,
        angle: 90,
        seat_index: seatIndex++,
      })
    }
  }

  return positions
}

/** Generate chair positions for any table type. */
export function generateTableChairs(table, capacity, options = {}) {
  const { seatSpacing = 30, startAngle = -90 } = options || {}
  if (isRoundTable(table)) {
    return generateRoundTableChairs(table, capacity, seatSpacing, startAngle)
  }
  return generateRectTableChairs(table, capacity, seatSpacing)
}

export function getTableCenter(table) {
  return objectCenter(table)
}

export function getTableBoundingBox(table) {
  return boundingBox(table.x, table.y, table.width, table.height, table.rotation)
}

export default {
  isRoundTable,
  generateRoundTableChairs,
  generateRectTableChairs,
  generateTableChairs,
  getTableCenter,
  getTableBoundingBox,
}