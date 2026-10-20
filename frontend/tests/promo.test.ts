import assert from 'node:assert/strict'
import { test } from 'node:test'
import { pixPrice, promoTarget, splitRemaining } from '../app/utils/promo.ts'

const H = 3_600_000

test('promoTarget: antes do fim, o próprio fim', () => {
  assert.equal(promoTarget(10 * H, null, 4 * H), 10 * H)
  assert.equal(promoTarget(10 * H, 24, 4 * H), 10 * H)
})

test('promoTarget: sem loop, acabou; com loop, o fim do ciclo atual', () => {
  assert.equal(promoTarget(10 * H, null, 11 * H), null)
  assert.equal(promoTarget(10 * H, 24, 10 * H), 34 * H)
  assert.equal(promoTarget(10 * H, 24, 11 * H), 34 * H)
  assert.equal(promoTarget(10 * H, 6, 23 * H), 28 * H)
})

test('splitRemaining', () => {
  assert.deepEqual(splitRemaining(((26 * 60 + 3) * 60 + 9) * 1000), { days: 1, hours: 2, minutes: 3, seconds: 9 })
  assert.deepEqual(splitRemaining(-5), { days: 0, hours: 0, minutes: 0, seconds: 0 })
})

test('pixPrice arredonda o desconto para baixo', () => {
  assert.equal(pixPrice(60000, 10), 54000)
  assert.equal(pixPrice(49700, 5), 47215)
  assert.equal(pixPrice(999, 7), 930)
})
