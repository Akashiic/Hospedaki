import assert from 'node:assert/strict'
import { describe, it } from 'node:test'
import { savings } from '../app/utils/savings.ts'

describe('savings', () => {
  it('commission saved on the share of bookings that becomes direct', () => {
    // R$ 10.000 por mês a 15%: R$ 1.500 de comissão; metade direta economiza R$ 750 por mês.
    assert.deepEqual(savings(10000, 15, 50), { commissionMonth: 150000, savedMonth: 75000, savedYear: 900000 })
  })

  it('decimal fees round to the cent', () => {
    assert.deepEqual(savings(3333.33, 15.5, 100), { commissionMonth: 51667, savedMonth: 51667, savedYear: 620004 })
  })

  it('empty or out-of-range inputs never produce negative or NaN values', () => {
    assert.deepEqual(savings(Number.NaN, 15, 50), { commissionMonth: 0, savedMonth: 0, savedYear: 0 })
    assert.deepEqual(savings(-500, 15, 50), { commissionMonth: 0, savedMonth: 0, savedYear: 0 })
    assert.deepEqual(savings(1000, 150, 200), { commissionMonth: 100000, savedMonth: 100000, savedYear: 1200000 })
  })
})
