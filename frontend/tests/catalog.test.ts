import assert from 'node:assert/strict'
import { test } from 'node:test'
import { listPrice, nextFreeWeekend, nightsFree, sortCatalog, validRange } from '../app/utils/catalog.ts'

test('nightsFree olha as noites, não o dia da saída', () => {
  assert.equal(nightsFree(['2026-11-12'], '2026-11-10', '2026-11-12'), true)
  assert.equal(nightsFree(['2026-11-11'], '2026-11-10', '2026-11-12'), false)
})

test('nextFreeWeekend pula o fim de semana ocupado', () => {
  // 2026-10-20 é terça; a sexta seguinte é 23.
  assert.deepEqual(nextFreeWeekend([], '2026-10-20', '2026-12-31'), { checkIn: '2026-10-23', checkOut: '2026-10-25' })
  assert.deepEqual(nextFreeWeekend(['2026-10-24'], '2026-10-20', '2026-12-31'), { checkIn: '2026-10-30', checkOut: '2026-11-01' })
  // Numa sexta, vale a própria.
  assert.deepEqual(nextFreeWeekend([], '2026-10-23', '2026-12-31'), { checkIn: '2026-10-23', checkOut: '2026-10-25' })
  assert.equal(nextFreeWeekend([], '2026-10-20', '2026-12-31', 3), null)
  assert.equal(nextFreeWeekend([], '2026-10-20', '2026-10-24'), null)
})

test('validRange', () => {
  assert.equal(validRange('2026-11-10', '2026-11-12', '2026-10-20'), true)
  assert.equal(validRange('2026-11-10', '2026-11-10', '2026-10-20'), false)
  assert.equal(validRange('2026-10-19', '2026-10-22', '2026-10-20'), false)
  assert.equal(validRange('', '2026-10-22', '2026-10-20'), false)
})

const acc = (id: number, base: number, max: number, over: object = {}) => ({ id, base_price: base, weekend_price: null, hourly_price: null, max_guests: max, booking_mode: 'nightly' as const, ...over })

test('listPrice usa a menor diária ou a hora', () => {
  assert.equal(listPrice(acc(1, 500, 2, { weekend_price: 900 })), 500)
  assert.equal(listPrice(acc(1, 500, 2, { weekend_price: 400 })), 400)
  assert.equal(listPrice(acc(1, 0, 40, { booking_mode: 'hourly', hourly_price: 15000 })), 15000)
})

test('sortCatalog: as que servem primeiro, depois a ordem pedida, depois a do dono', () => {
  const items = [acc(1, 500, 4), acc(2, 300, 6), acc(3, 300, 2)]
  assert.deepEqual(sortCatalog(items, 'recomendadas').map(a => a.id), [1, 2, 3])
  assert.deepEqual(sortCatalog(items, 'menor-preco').map(a => a.id), [2, 3, 1])
  assert.deepEqual(sortCatalog(items, 'mais-espaco').map(a => a.id), [2, 1, 3])
  assert.deepEqual(sortCatalog(items, 'menor-preco', a => a.id !== 2).map(a => a.id), [3, 1, 2])
})

test('sortCatalog: por preço, quem é por hora vai depois das estadias', () => {
  const items = [acc(1, 50000, 4), acc(9, 0, 40, { booking_mode: 'hourly', hourly_price: 15000 }), acc(2, 30000, 6)]
  assert.deepEqual(sortCatalog(items, 'menor-preco').map(a => a.id), [2, 1, 9])
  assert.deepEqual(sortCatalog(items, 'recomendadas').map(a => a.id), [1, 9, 2])
})
