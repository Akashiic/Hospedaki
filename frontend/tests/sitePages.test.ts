import assert from 'node:assert/strict'
import { describe, it } from 'node:test'
import { menuPages, pageContent, pageDefaults } from '../app/utils/sitePages.ts'

const pousada = {
  name: 'Pousada Sol',
  tagline: 'Na beira do rio',
  description: 'Uma pousada tranquila.',
  city: 'Paraty',
  state: 'RJ',
  whatsapp: '5524999990000',
  phone: null,
  email: 'oi@sol.com',
  address: null,
  accommodations: [{ name: 'Chalé' }, { name: 'Suíte' }, { name: 'Casa' }],
}

describe('pageDefaults', () => {
  it('builds the texts from the property data', () => {
    assert.equal(pageDefaults('inicio', pousada).seoTitle, 'Pousada Sol · Na beira do rio')
    assert.equal(pageDefaults('lugar', pousada).lead, 'Paraty, RJ')
    assert.equal(pageDefaults('acomodacoes', pousada).seoDescription, 'Chalé, Suíte e Casa: fotos, comodidades e diárias de cada acomodação.')
    assert.equal(pageDefaults('contato', pousada).seoDescription, 'Pousada Sol: WhatsApp, e-mail e endereço. Fale com a gente e reserve direto.')
  })

  it('prefers the SEO description over the long presentation', () => {
    assert.equal(pageDefaults('inicio', { ...pousada, seo: { description: 'Curta.' } }).seoDescription, 'Curta.')
  })
})

describe('pageContent', () => {
  it('uses what the owner wrote and falls back to the default when empty', () => {
    const content = pageContent('contato', pousada, { title: 'Vamos conversar', lead: null, seo_title: '' })
    assert.equal(content.title, 'Vamos conversar')
    assert.equal(content.eyebrow, 'Contato')
    assert.equal(content.seoTitle, 'Contato · Pousada Sol')
  })
})

describe('menuPages', () => {
  it('drops only the optional pages turned off', () => {
    const keys = menuPages({ lugar: { menu: false }, inicio: { menu: false } as never, contato: { menu: true } }).map(p => p.key)
    assert.deepEqual(keys, ['inicio', 'acomodacoes', 'reservar', 'contato'])
    assert.equal(menuPages(null).length, 5)
  })
})
