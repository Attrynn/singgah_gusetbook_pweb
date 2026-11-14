/**
 * World-coordinate math for the floor plan editor.
 * Coordinates are in centimeters, origin top-left, +x right, +y down,
 * rotation degrees clockwise.
 */

/** Rotate a point around a pivot (cx, cy) by `degrees` clockwise. */
export function rotatePoint(x, y, degrees, cx = 0, cy = 0) {
  const rad = (Number(degrees) * Math.PI) / 180
  const cos = Math.cos(rad)
  const sin = Math.sin(rad)
  const dx = x - cx
  const dy = y - cy
  const rx = cx + dx * cos - dy * sin
  const ry = cy + dx * sin + dy * cos
  return { x: rx, y: ry }
}

/** Rotate a rect (x, y, width, height) around its center by degrees clockwise. */
export function rotateRect(x, y, width, height, degrees) {
  const cx = x + width / 2
  const cy = y + height / 2
  const corners = [
    { x, y },
    { x: x + width, y },
    { x: x + width, y: y + height },
    { x, y: y + height },
  ]
  const rotated = corners.map((c) => rotatePoint(c.x, c.y, degrees, cx, cy))
  const xs = rotated.map((c) => c.x)
  const ys = rotated.map((c) => c.y)
  const minX = Math.min(...xs)
  const maxX = Math.max(...xs)
  const minY = Math.min(...ys)
  const maxY = Math.max(...ys)
  return {
    x: minX,
    y: minY,
    width: maxX - minX,
    height: maxY - minY,
  }
}

/** Axis-aligned bounding box of a rotated rect. */
export function boundingBox(x, y, width, height, rotation) {
  const r = rotateRect(x, y, width, height, rotation)
  return { x: r.x, y: r.y, width: r.width, height: r.height }
}


/** Snap a value to the nearest grid multiple. */
export function snapToGrid(value, gridSize) {
  if (!gridSize || gridSize <= 0) return value
  const snapped = Math.round(value / gridSize) * gridSize
  return Math.round(snapped * 100) / 100
}

/** Compute the world-space center of an object. */
export function objectCenter(obj) {
  const cx = obj.x + obj.width / 2
  const cy = obj.y + obj.height / 2
  const rotated = rotatePoint(cx, cy, obj.rotation, cx, cy)
  return { x: rotated.x, y: rotated.y }
}

/** Convert screen (viewport) coordinates to world coordinates. */
export function screenToWorld(screenPoint, viewport) {
  return {
    x: (screenPoint.x - viewport.pan.x) / viewport.zoom,
    y: (screenPoint.y - viewport.pan.y) / viewport.zoom,
  }
}

/** Convert world coordinates to screen coordinates. */
export function worldToScreen(worldPoint, viewport) {
  return {
    x: worldPoint.x * viewport.zoom + viewport.pan.x,
    y: worldPoint.y * viewport.zoom + viewport.pan.y,
  }
}

/** Fit the viewport to a set of objects. */
export function fitViewport(objects, viewport, padding = 60) {
  if (objects.length === 0) {
    viewport.zoom = 1
    viewport.pan = { x: 0, y: 0 }
    return viewport
  }

  let minX = Infinity
  let minY = Infinity
  let maxX = -Infinity
  let maxY = -Infinity

  for (const obj of objects) {
    const box = boundingBox(obj.x, obj.y, obj.width, obj.height, obj.rotation)
    minX = Math.min(minX, box.x)
    minY = Math.min(minY, box.y)
    maxX = Math.max(maxX, box.x + box.width)
    maxY = Math.max(maxY, box.y + box.height)
  }

  const w = maxX - minX || 1
  const h = maxY - minY || 1
  const zoomX = (viewport.width - padding * 2) / w
  const zoomY = (viewport.height - padding * 2) / h
  const zoom = Math.min(zoomX, zoomY, viewport.maxZoom ?? 4)
  const cx = (minX + maxX) / 2
  const cy = (minY + maxY) / 2

  viewport.zoom = Math.max(0.05, Math.min(zoom, viewport.maxZoom ?? 4))
  viewport.pan = {
    x: viewport.width / 2 - cx * viewport.zoom,
    y: viewport.height / 2 - cy * viewport.zoom,
  }

  return viewport
}

export default {
  rotatePoint,
  rotateRect,
  boundingBox,
  snapToGrid,
  objectCenter,
  screenToWorld,
  worldToScreen,
  fitViewport,
}
