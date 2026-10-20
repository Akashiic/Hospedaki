import assert from 'node:assert/strict'
import { describe, it } from 'node:test'
import { e164, lodgingSchema } from '../app/utils/lodgingSchema.ts'
import { pageDefaults } from '../app/utils/sitePages.ts'

const pousada = {
  name: 'Pousada Sol',
  description: 'Na beira do rio.',
  tagline: null,
  phone: '1146043867',
  whatsapp: '5511950211636',
  email: null,
  address: 'Rua A, 1',
  city: 'Paraty',
  state: 'RJ',
  logo_url: '/logo.png',
  share_image_url: null,
  check_in_time: '14:00',
  check_out_time: '12:00',
  photos: [{ url: '/fotos/a.jpg' }],
  accommodations: [
    { base_price: 49700, photos: [{ url: 'https://cdn.x/b.jpg' }] },
    { base_price: 29700, photos: [{ url: '/fotos/a.jpg' }] },
  ],
}

describe('lodgingSchema', () => {
  it('links the social profiles and the map position', () => {
    const s = lodgingSchema({ ...pousada, social: { instagram: 'https://instagram.com/sol', tripadvisor: 'https://tripadvisor.com.br/x' }, seo: { latitude: -23.5, longitude: -46.6 } }, 'Hotel')
    assert.deepEqual(s.sameAs, ['https://instagram.com/sol', 'https://tripadvisor.com.br/x'])
    assert.deepEqual(s.geo, { '@type': 'GeoCoordinates', 'latitude': -23.5, 'longitude': -46.6 })
    const none = lodgingSchema({ ...pousada, social: {}, seo: { latitude: -23.5, longitude: null } }, 'Hotel')
    assert.equal(none.sameAs, undefined)
    assert.equal(none.geo, undefined)
  })

  it('describes the property as a lodging business with absolute URLs', () => {
    const s = lodgingSchema(pousada, 'BedAndBreakfast', 'https://sol.com')
    assert.deepEqual(s['@type'], ['LodgingBusiness', 'BedAndBreakfast'])
    assert.equal(s.priceRange, 'R$ 297 – R$ 497')
    assert.equal(s.telephone, '+551146043867')
    assert.equal(s.logo, 'https://sol.com/logo.png')
    // Sem fotos repetidas.
    assert.deepEqual(s.image, ['https://sol.com/fotos/a.jpg', 'https://cdn.x/b.jpg'])
    assert.equal(s.address?.addressCountry, 'BR')
  })

  it('leaves out what is not registered', () => {
    const s = lodgingSchema({ ...pousada, address: null, city: null, phone: null, whatsapp: null, accommodations: [] }, 'LodgingBusiness')
    assert.equal(s['@type'], 'LodgingBusiness')
    assert.equal(s.address, undefined)
    assert.equal(s.telephone, undefined)
    assert.equal(s.priceRange, undefined)
  })

  it('normalises Brazilian phone numbers', () => {
    assert.equal(e164('5511950211636'), '+5511950211636')
    assert.equal(e164('(11) 4604-3867'), '+551146043867')
    assert.equal(e164('123'), null)
  })
})

describe('separator', () => {
  it('is used in the default titles', () => {
    const source = { ...pousada, accommodations: [], seo: { separator: '|' } }
    assert.equal(pageDefaults('contato', source).seoTitle, 'Contato | Pousada Sol')
    assert.equal(pageDefaults('lugar', { ...source, seo: null }).seoTitle, 'O lugar · Pousada Sol')
  })
})
