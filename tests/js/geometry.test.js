import { describe, expect, it } from 'vitest'
import {
  boundingBox,
  fitViewport,
  objectCenter,
  rotatePoint,
  rotateRect,
  screenToWorld,
  snapToGrid,
  worldToScreen,
} from '../../resources/js/editor/geometry.js'

describe('grid snapping', () => {
  it('snaps to the nearest multiple', () => {
    expect(snapToGrid(12, 10)).toBe(10)
    expect(snapToGrid(16, 10)).toBe(20)
    expect(snapToGrid(-12, 10)).toBe(-10)
    expect(snapToGrid(12.345, 5)).toBe(10)
  })

  it('leaves values untouched when the grid is disabled', () => {
    expect(snapToGrid(12.345, 0)).toBe(12.345)
    expect(snapToGrid(12.345, null)).toBe(12.345)
  })
})

describe('rotation and bounding boxes', () => {
  it('rotates a point 90 degrees clockwise around the origin', () => {
    const rotated = rotatePoint(10, 0, 90)
    expect(rotated.x).toBeCloseTo(0, 6)
    expect(rotated.y).toBeCloseTo(10, 6)
  })

  it('rotates around a pivot', () => {
    const rotated = rotatePoint(20, 10, 180, 10, 10)
    expect(rotated.x).toBeCloseTo(0, 6)
    expect(rotated.y).toBeCloseTo(10, 6)
  })

  it('keeps the bounding box unchanged for unrotated rectangles', () => {
    expect(rotateRect(10, 20, 100, 50, 0)).toEqual({ x: 10, y: 20, width: 100, height: 50 })
  })

  it('swaps width and height for a 90 degree rotation', () => {
    const box = boundingBox(0, 0, 100, 50, 90)
    expect(box.width).toBeCloseTo(50, 6)
    expect(box.height).toBeCloseTo(100, 6)
  })

  it('computes the object centre', () => {
    expect(objectCenter({ x: 10, y: 20, width: 100, height: 50, rotation: 0 })).toEqual({ x: 60, y: 45 })
  })
})

describe('viewport transforms', () => {
  const viewport = { zoom: 2, pan: { x: 30, y: 40 } }

  it('round-trips world to screen and back', () => {
    const screen = worldToScreen({ x: 100, y: 50 }, viewport)
    expect(screen).toEqual({ x: 230, y: 140 })
    expect(screenToWorld(screen, viewport)).toEqual({ x: 100, y: 50 })
  })

  it('fits an empty document to zoom 1 with no pan', () => {
    const fitted = fitViewport([], { ...viewport })
    expect(fitted.zoom).toBe(1)
    expect(fitted.pan).toEqual({ x: 0, y: 0 })
  })

  it('fits objects into the viewport and centres them', () => {
    const fitted = fitViewport(
      [
        { x: 0, y: 0, width: 100, height: 100, rotation: 0 },
        { x: 900, y: 900, width: 100, height: 100, rotation: 0 },
      ],
      { width: 1000, height: 1000, zoom: 1, pan: { x: 0, y: 0 } },
      50,
    )

    expect(fitted.zoom).toBeGreaterThan(0)
    expect(fitted.zoom).toBeLessThanOrEqual(4)
    expect(fitted.pan.x).toBeCloseTo(500 - 500 * fitted.zoom, 6)
  })
})
