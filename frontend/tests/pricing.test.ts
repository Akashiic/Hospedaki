// Textos de preço por pessoa usados em cartões, vitrine, ficha, orçamento e painel.
import assert from 'node:assert/strict'
import { describe, it } from 'node:test'
import { guestCopy, guestPricing, plural, totalGuests } from '../app/utils/pricing.ts'

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
