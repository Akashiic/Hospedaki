import assert from 'node:assert/strict'
import { describe, it } from 'node:test'
import { bookingPhase, inScope, matchesSearch } from '../app/utils/bookings.ts'

const today = '2026-10-20'
const b = (status: 'pending' | 'confirmed' | 'declined' | 'cancelled', check_in: string, check_out: string) => ({ status, check_in, check_out })

describe('bookingPhase', () => {
  it('crosses the status with the dates', () => {
    assert.equal(bookingPhase(b('confirmed', '2026-10-25', '2026-10-27'), today), 'upcoming')
    assert.equal(bookingPhase(b('confirmed', '2026-10-20', '2026-10-22'), today), 'ongoing')
    assert.equal(bookingPhase(b('confirmed', '2026-10-18', '2026-10-20'), today), 'completed')
    assert.equal(bookingPhase(b('pending', '2026-10-20', '2026-10-21'), today), 'request')
    assert.equal(bookingPhase(b('pending', '2026-10-19', '2026-10-21'), today), 'expired')
    assert.equal(bookingPhase(b('declined', '2026-10-25', '2026-10-27'), today), 'declined')
  })
})

describe('inScope', () => {
  it('past holds what ended and requests that expired', () => {
    assert.ok(inScope(b('cancelled', '2026-10-01', '2026-10-03'), 'past', today))
    assert.ok(inScope(b('pending', '2026-10-19', '2026-10-25'), 'past', today))
    assert.ok(!inScope(b('confirmed', '2026-10-19', '2026-10-25'), 'past', today))
  })
})

describe('matchesSearch', () => {
  const booking = { guest_name: 'Família Oliveira', code: 'ZX8D8JWW', guest_phone: '(11) 99999-0000', accommodations: [{ name: 'Chalé Master' }] }
  it('finds by name without accents, code, accommodation and phone digits', () => {
    assert.ok(matchesSearch(booking, 'familia'))
    assert.ok(matchesSearch(booking, 'zx8d'))
    assert.ok(matchesSearch(booking, 'chale'))
    assert.ok(matchesSearch(booking, '99999'))
    assert.ok(!matchesSearch(booking, 'souza'))
  })
})
