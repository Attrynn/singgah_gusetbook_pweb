import { describe, expect, it } from 'vitest'
import { describeSelection, formatFileSize } from '../../resources/js/memory/selection.js'

describe('file size formatting', () => {
  it('uses Indonesian decimal separators', () => {
    expect(formatFileSize(512)).toBe('512 B')
    expect(formatFileSize(1536)).toBe('1,5 KB')
    expect(formatFileSize(5 * 1024 * 1024)).toBe('5 MB')
  })

  it('treats missing or negative sizes as empty', () => {
    expect(formatFileSize(0)).toBe('0 KB')
    expect(formatFileSize(-10)).toBe('0 KB')
    expect(formatFileSize(Number.NaN)).toBe('0 KB')
  })
})

describe('selection summary', () => {
  const file = (name, size) => ({ name, size })

  it('accepts a selection inside every limit', () => {
    const summary = describeSelection([file('a.jpg', 1024), file('b.jpg', 2048)], {
      maxFiles: 5,
      maxFileBytes: 4096,
      maxTotalBytes: 10240,
    })

    expect(summary.count).toBe(2)
    expect(summary.totalLabel).toBe('3 KB')
    expect(summary.items.map((item) => item.sizeLabel)).toEqual(['1 KB', '2 KB'])
    expect(summary.hasRejection).toBe(false)
  })

  it('flags an oversized file without rejecting the rest', () => {
    const summary = describeSelection([file('big.jpg', 9000), file('ok.jpg', 100)], {
      maxFiles: 5,
      maxFileBytes: 5000,
      maxTotalBytes: 20000,
    })

    expect(summary.items[0].oversized).toBe(true)
    expect(summary.items[1].oversized).toBe(false)
    expect(summary.hasRejection).toBe(true)
  })

  it('flags too many files and an over-budget total', () => {
    const tooMany = describeSelection([file('a.jpg', 10), file('b.jpg', 10), file('c.jpg', 10)], {
      maxFiles: 2,
      maxTotalBytes: 1000,
    })

    expect(tooMany.exceedsCount).toBe(true)
    expect(tooMany.hasRejection).toBe(true)

    const tooLarge = describeSelection([file('a.jpg', 800), file('b.jpg', 800)], {
      maxFiles: 5,
      maxTotalBytes: 1000,
    })

    expect(tooLarge.exceedsTotal).toBe(true)
    expect(tooLarge.hasRejection).toBe(true)
  })

  it('handles an empty selection', () => {
    const summary = describeSelection([], { maxFiles: 5 })

    expect(summary.count).toBe(0)
    expect(summary.totalLabel).toBe('0 KB')
    expect(summary.hasRejection).toBe(false)
  })
})
