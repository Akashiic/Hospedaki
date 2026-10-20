import assert from 'node:assert/strict'
import { describe, it } from 'node:test'
import { arrivalOptions, durationOptions, endAt, formatHour, startOptions, type HoursDay } from '../app/utils/hours.ts'

const day = (over: Partial<HoursDay> = {}): HoursDay => ({ opens_at: '09:00', closes_at: '23:00', min_hours: 4, closed: false, taken: [], not_before: null, ...over })

describe('startOptions', () => {
  it('lists starts that still fit the minimum before closing', () => {
    const options = startOptions(day())
    assert.equal(options[0]?.time, '09:00')
    assert.equal(options.at(-1)?.time, '19:00') // 19h + 4h = 23h
    assert.ok(options.every(o => o.free))
  })

  it('blocks starts inside a booking or without room before the next one', () => {
    const options = Object.fromEntries(startOptions(day({ taken: [{ start: '14:00', end: '18:00' }] })).map(o => [o.time, o.free]))
    assert.equal(options['10:00'], true) // 10h–14h cabe
    assert.equal(options['11:00'], false) // só 3h até as 14h
    assert.equal(options['15:00'], false) // dentro da festa
    assert.equal(options['18:00'], true)
  })

  it('respects closed days and hours that already passed', () => {
    assert.ok(startOptions(day({ closed: true })).every(o => !o.free))
    const today = Object.fromEntries(startOptions(day({ not_before: '12:10' })).map(o => [o.time, o.free]))
    assert.equal(today['12:00'], false)
    assert.equal(today['13:00'], true)
  })
})

describe('durationOptions', () => {
  it('goes from the minimum until the next booking or closing', () => {
    assert.deepEqual(durationOptions('10:00', day({ taken: [{ start: '16:00', end: '18:00' }] })), [4, 5, 6])
    assert.deepEqual(durationOptions('18:00', day()), [4, 5])
    assert.equal(endAt('18:00', 5), '23:00')
  })
})

describe('formatting', () => {
  it('writes hours the Brazilian way', () => {
    assert.equal(formatHour('14:00'), '14h')
    assert.equal(formatHour('09:30'), '9h30')
    assert.deepEqual(arrivalOptions('20:00'), ['20:00', '21:00', '22:00', '23:00'])
    assert.equal(arrivalOptions('14:30')[0], '15:00')
  })
})
