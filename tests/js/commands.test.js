import { describe, expect, it } from 'vitest'
import Document from '../../resources/js/editor/document.js'
import { History } from '../../resources/js/editor/commands.js'

function makeDocument() {
  const doc = new Document()
  doc.addObject({ id: 'object_0001', type: 'round_table', x: 100, y: 100 })
  return doc
}

describe('History undo/redo', () => {
  it('undoes and redoes a mutation', () => {
    const doc = makeDocument()
    const history = new History(doc)

    history.run('move', () => doc.updateObject('object_0001', { x: 300 }))
    expect(doc.getObject('object_0001').x).toBe(300)
    expect(history.canUndo).toBe(true)

    expect(history.undo()).toBe('move')
    expect(doc.getObject('object_0001').x).toBe(100)
    expect(history.canRedo).toBe(true)

    expect(history.redo()).toBe('move')
    expect(doc.getObject('object_0001').x).toBe(300)
  })

  it('does not record a no-op mutation', () => {
    const doc = makeDocument()
    const history = new History(doc)

    const changed = history.run('noop', () => {})
    expect(changed).toBe(false)
    expect(history.canUndo).toBe(false)
  })

  it('clears the redo stack after a new mutation', () => {
    const doc = makeDocument()
    const history = new History(doc)

    history.run('first', () => doc.updateObject('object_0001', { x: 300 }))
    history.undo()
    expect(history.canRedo).toBe(true)

    history.run('second', () => doc.updateObject('object_0001', { y: 400 }))
    expect(history.canRedo).toBe(false)
  })

  it('caps history at the configured depth', () => {
    const doc = makeDocument()
    const history = new History(doc, 3)

    for (let i = 0; i < 10; i++) {
      history.run(`move-${i}`, () => doc.updateObject('object_0001', { x: i }))
    }

    expect(history.undoLabel).toBe('move-9')
    history.undo()
    history.undo()
    history.undo()
    expect(history.canUndo).toBe(false)
  })

  it('returns null when there is nothing to undo or redo', () => {
    const history = new History(makeDocument())

    expect(history.undo()).toBeNull()
    expect(history.redo()).toBeNull()
  })

  it('clear removes both stacks', () => {
    const doc = makeDocument()
    const history = new History(doc)

    history.run('move', () => doc.updateObject('object_0001', { x: 300 }))
    history.clear()

    expect(history.canUndo).toBe(false)
    expect(history.canRedo).toBe(false)
  })
})
