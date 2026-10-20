// Textos de preço por pessoa usados em cartões, vitrine, ficha, orçamento e painel.
import assert from 'node:assert/strict'
import { describe, it } from 'node:test'
import { centsFromInput, guestCopy, guestPricing, inputFromCents, plural, stayCharges, totalGuests } from '../app/utils/pricing.ts'

const espaco = { max_guests: 6, base_price: 49700, included_guests: 2, extra_guest_price: null }
// Os valores em BRL usam espaço não separável depois do "R$".
const money = (s: string) => s.replace(/ /g, ' ')

describe('plural', () => {
  it('1 no singular, o resto no plural', () => {
    assert.equal(plural(1, 'diária', 'diárias'), '1 diária')
    assert.equal(plural(2, 'diária', 'diárias'), '2 diárias')
  })
})

describe('guestPricing', () => {
  it('só existe quando a diária vale para menos gente que a capacidade', () => {
    assert.deepEqual(guestPricing(espaco), { included: 2, perPerson: null })
    assert.equal(guestPricing({ ...espaco, included_guests: null }), null)
    assert.equal(guestPricing({ ...espaco, included_guests: 6 }), null)
  })
})

describe('guestCopy', () => {
  it('cartão diz para quantas pessoas é o preço', () => {
    assert.equal(money(guestCopy.card(espaco)), 'a partir de R$ 497,00/diária para 2 pessoas · até 6 com acréscimo')
    assert.equal(money(guestCopy.card({ ...espaco, included_guests: null })), 'até 6 pessoas · a partir de R$ 497,00/diária')
  })

  it('acréscimo sem valor informado é combinado com a pousada', () => {
    assert.equal(guestCopy.extraRule({ included: 2, perPerson: null }), 'Pessoas a mais têm acréscimo, combinado com a pousada.')
    assert.equal(money(guestCopy.extraRule({ included: 2, perPerson: 5000 })), '+ R$ 50,00 por pessoa a mais, por diária.')
  })

  it('orçamento e painel', () => {
    assert.equal(guestCopy.quoteTotal(2), 'Total para 2 pessoas')
    assert.equal(guestCopy.quoteNote(4), 'Não inclui o acréscimo de 4 pessoas a mais, combinado com a pousada na confirmação.')
    assert.equal(guestCopy.quoteExtraLine(1, 2), '1 pessoa a mais · 2 diárias')
    assert.equal(guestCopy.panelUnpriced(4), '+ acréscimo de 4 pessoas a combinar')
  })
})

describe('totalGuests', () => {
  it('counts accommodations that share a unit only once, by the larger one', () => {
    const accs = [
      { max_guests: 4, shared_unit: null },
      { max_guests: 6, shared_unit: 'apto' },
      { max_guests: 5, shared_unit: 'apto' },
      { max_guests: 3 },
    ]
    assert.equal(totalGuests(accs), 13)
  })
})

describe('stayCharges', () => {
  const acc = { cleaning_fee: 5000, weekly_discount: 10, monthly_discount: 25 }
  it('matches the backend: cleaning always, weekly from 7 nights, monthly from 28', () => {
    assert.deepEqual(stayCharges(30000, 3, acc), { discount: 0, discount_percent: 0, cleaning_fee: 5000, total: 35000 })
    assert.deepEqual(stayCharges(70000, 7, acc), { discount: 7000, discount_percent: 10, cleaning_fee: 5000, total: 68000 })
    assert.deepEqual(stayCharges(280000, 28, acc), { discount: 70000, discount_percent: 25, cleaning_fee: 5000, total: 215000 })
    assert.deepEqual(stayCharges(10000, 1, {}), { discount: 0, discount_percent: 0, cleaning_fee: 0, total: 10000 })
  })
})

describe('money inputs', () => {
  it('reads what owners type', () => {
    assert.equal(centsFromInput('450'), 45000)
    assert.equal(centsFromInput('450,5'), 45050)
    assert.equal(centsFromInput('1.250,00'), 125000)
    assert.equal(centsFromInput('R$ 1.250'), 125000)
    assert.equal(centsFromInput('450.5'), 45050)
    assert.equal(centsFromInput(''), null)
    assert.equal(centsFromInput('abc'), null)
  })
  it('writes cents back for the field', () => {
    assert.equal(inputFromCents(125000), '1.250,00')
    assert.equal(inputFromCents(null), '')
  })
})

describe('hourly spaces', () => {
  it('are not lodging and have their own copy', async () => {
    const { totalGuests, guestCopy } = await import('../app/utils/pricing.ts')
    assert.equal(totalGuests([
      { max_guests: 4, shared_unit: null, booking_mode: 'nightly' },
      { max_guests: 40, shared_unit: 'espaco', booking_mode: 'hourly' },
      { max_guests: 6, shared_unit: 'espaco', booking_mode: 'nightly' },
    ]), 10)
    assert.equal(guestCopy.card({ max_guests: 40, base_price: 0, included_guests: null, extra_guest_price: null, booking_mode: 'hourly', hourly_price: 15000, min_hours: 4 }), 'R$ 150,00/hora · mínimo 4 horas · até 40 pessoas')
  })
})
