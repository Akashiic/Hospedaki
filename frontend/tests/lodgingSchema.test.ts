import assert from 'node:assert/strict'
import { describe, it } from 'node:test'
import { accommodationSchema, catalogSchema, e164, lodgingSchema } from '../app/utils/lodgingSchema.ts'
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
  photos: [{ url: '/fotos/a.jpg', caption: 'Rio' }],
  accommodations: [
    { slug: 'chale', base_price: 49700, photos: [{ url: 'https://cdn.x/b.jpg' }], payment: { methods: ['pix', 'card'] } },
    { slug: 'suite', base_price: 29700, photos: [{ url: '/fotos/a.jpg' }], payment: { methods: [] } },
    { slug: 'salao', base_price: 0, booking_mode: 'hourly' as const, photos: [] },
  ],
}

describe('lodgingSchema', () => {
  it('links the social profiles and the map position', () => {
    const s = lodgingSchema({ ...pousada, social: { instagram: 'https://instagram.com/sol', google: 'https://maps.app.goo.gl/x', tripadvisor: 'https://tripadvisor.com.br/x' }, seo: { latitude: -23.5, longitude: -46.6 } }, 'Hotel')
    assert.deepEqual(s.sameAs, ['https://instagram.com/sol', 'https://tripadvisor.com.br/x'])
    // O link do Google Maps é o mapa do lugar, não um perfil.
    assert.equal(s.hasMap, 'https://maps.app.goo.gl/x')
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
    // Sem fotos repetidas, com a legenda de cada uma.
    assert.deepEqual(s.image?.map(i => i.url), ['https://sol.com/fotos/a.jpg', 'https://cdn.x/b.jpg'])
    assert.equal(s.image?.[0]?.caption, 'Rio')
    assert.equal(s.address?.addressCountry, 'BR')
  })

  it('nests highlights, extras, the reservations contact, the reserve action and the accommodations', () => {
    const s = lodgingSchema({ ...pousada, highlights: [{ title: 'Piscina', text: 'Aquecida' }], extras: [{ title: 'Café da manhã', text: 'R$ 40' }] }, 'Hotel', 'https://sol.com')
    assert.deepEqual(s.amenityFeature, [{ '@type': 'LocationFeatureSpecification', 'name': 'Piscina', 'description': 'Aquecida', 'value': true }])
    assert.deepEqual(s.makesOffer, [{ '@type': 'Offer', 'itemOffered': { '@type': 'Service', 'name': 'Café da manhã', 'description': 'R$ 40' } }])
    assert.equal(s.contactPoint?.[0]?.telephone, '+5511950211636')
    assert.equal(s.potentialAction['@type'], 'ReserveAction')
    assert.equal(s.potentialAction.target.urlTemplate, 'https://sol.com/reservar')
    assert.deepEqual(s.containsPlace, ['chale', 'suite', 'salao'].map(slug => ({ '@id': `https://sol.com/acomodacoes/${slug}#acomodacao-${slug}` })))
    // O salão por hora não conta como quarto nem entra na faixa de preço das diárias.
    assert.equal(s.numberOfRooms, 2)
    assert.equal(s.paymentAccepted, 'Pix, Cartão de crédito')
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

const chale = {
  name: 'Chalé',
  slug: 'chale',
  description: 'Com hidromassagem.',
  max_guests: 4,
  base_price: 49700,
  weekend_price: 99700,
  min_nights: 2,
  amenities: ['Wi-Fi'],
  photos: [{ url: '/fotos/c1.jpg', caption: 'Varanda' }, { url: '/fotos/c2.jpg', caption: null }],
  details: { kind: 'chalet' as const, bedrooms: 2, bathrooms: 1, area: 98, beds: [{ type: 'king' as const, count: 1 }], pets: false },
}

describe('accommodationSchema', () => {
  it('describes the stay with its sheet and a nightly offer', () => {
    const s = accommodationSchema(chale, 'https://sol.com') as Record<string, any>
    assert.deepEqual(s['@type'], ['House', 'Product'])
    assert.equal(s['@id'], 'https://sol.com/acomodacoes/chale#acomodacao-chale')
    assert.equal(s.additionalType, 'EntirePlace')
    assert.deepEqual(s.occupancy, { '@type': 'QuantitativeValue', 'minValue': 1, 'maxValue': 4, 'unitCode': 'C62' })
    assert.deepEqual(s.bed, [{ '@type': 'BedDetails', 'numberOfBeds': 1, 'typeOfBed': 'King' }])
    assert.equal(s.floorSize.value, 98)
    assert.equal(s.petsAllowed, false)
    assert.equal(s.image.length, 2)
    assert.deepEqual(s.containedInPlace, { '@id': 'https://sol.com/#identity' })
    assert.equal(s.offers.price, '497.00')
    assert.deepEqual(s.offers.priceSpecification.map((p: any) => [p.name, p.price, p.unitCode]), [['Diária (domingo a quinta)', '497.00', 'DAY'], ['Diária (sexta e sábado)', '997.00', 'DAY']])
    assert.deepEqual(s.offers.eligibleQuantity, { '@type': 'QuantitativeValue', 'minValue': 2, 'unitCode': 'DAY' })
  })

  it('is a Product only when it has a price', () => {
    const s = accommodationSchema({ ...chale, base_price: 0, weekend_price: null }) as Record<string, any>
    assert.equal(s['@type'], 'House')
    assert.equal(s.offers, undefined)
  })

  it('keeps the catalog version short', () => {
    const s = accommodationSchema(chale, 'https://sol.com', { full: false }) as Record<string, any>
    assert.equal(s.description, undefined)
    assert.equal(s.amenityFeature, undefined)
    assert.equal(s.image.length, 1)
  })

  it('treats an hourly space as an event venue priced by the hour', () => {
    const s = accommodationSchema({ ...chale, details: null, booking_mode: 'hourly', hourly_price: 15000, min_hours: 4, max_guests: 40 }) as Record<string, any>
    assert.deepEqual(s['@type'], ['EventVenue', 'Product'])
    assert.equal(s.occupancy, undefined)
    assert.equal(s.maximumAttendeeCapacity, 40)
    assert.deepEqual(s.offers.priceSpecification.map((p: any) => [p.price, p.unitCode]), [['150.00', 'HUR']])
    assert.equal(s.offers.eligibleQuantity.minValue, 4)
  })

  it('lists the catalog in order', () => {
    const s = catalogSchema([chale, { ...chale, slug: 'suite' }], 'https://sol.com') as Record<string, any>
    assert.equal(s.numberOfItems, 2)
    assert.deepEqual(s.itemListElement[1], { '@type': 'ListItem', 'position': 2, 'url': 'https://sol.com/acomodacoes/suite', 'item': { '@id': 'https://sol.com/acomodacoes/suite#acomodacao-suite' } })
  })
})
