/**
 * Undo/redo history with bounded depth.
 *
 * Uses document snapshots (the document is at most 2 MB and history is capped),
 * which keeps every mutating action reversible without bespoke inverse logic
 * for each operation type.
 */

export class History {
  constructor(document, maxDepth = 100) {
    this.doc = document
    this.maxDepth = maxDepth
    this._undo = []
    this._redo = []
  }

  /** Snapshot the document before a mutation. */
  begin() {
    return this.doc.toJSON()
  }

  /**
   * Commit a mutation. Returns true when the document actually changed.
   * @param {string} label human readable action label
   * @param {object} before snapshot from begin()
   */
  commit(label, before) {
    const after = this.doc.toJSON()
    if (JSON.stringify(before) === JSON.stringify(after)) return false

    this._undo.push({ label, before, after })
    if (this._undo.length > this.maxDepth) this._undo.shift()
    this._redo = []
    return true
  }

  /** Run a mutation and record it in one call. */
  run(label, fn) {
    const before = this.begin()
    fn()
    return this.commit(label, before)
  }

  undo() {
    const entry = this._undo.pop()
    if (!entry) return null
    this.doc.fromJSON(entry.before)
    this.doc._markDirty()
    this._redo.push(entry)
    return entry.label
  }

  redo() {
    const entry = this._redo.pop()
    if (!entry) return null
    this.doc.fromJSON(entry.after)
    this.doc._markDirty()
    this._undo.push(entry)
    return entry.label
  }

  clear() {
    this._undo = []
    this._redo = []
  }

  get canUndo() {
    return this._undo.length > 0
  }

  get canRedo() {
    return this._redo.length > 0
  }

  get undoLabel() {
    return this._undo.length ? this._undo[this._undo.length - 1].label : null
  }

  get redoLabel() {
    return this._redo.length ? this._redo[this._redo.length - 1].label : null
  }
}

export default History