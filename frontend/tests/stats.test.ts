import assert from 'node:assert/strict'
import { describe, it } from 'node:test'
import { compactMoney, deltaPercent, monthLabel } from '../app/utils/stats.ts'

describe('stats formatting', () => {
  it('compares with the previous period', () => {
    assert.equal(deltaPercent(150, 100), 50)
    assert.equal(deltaPercent(90, 120), -25)
    assert.equal(deltaPercent(100, 0), null)
    assert.equal(deltaPercent(null, 10), null)
  })

  it('writes money and months short', () => {
    assert.equal(compactMoney(95000), 'R$ 950')
    assert.equal(compactMoney(1290000), 'R$ 12,9 mil')
    assert.equal(compactMoney(120000000), 'R$ 1,2 mi')
    assert.equal(monthLabel('2026-10', '2026'), 'out')
    assert.equal(monthLabel('2025-11', '2026'), 'nov/25')
  })
})
