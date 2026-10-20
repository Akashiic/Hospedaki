import type { SchemaType } from '~/utils/sitePages'

// Dados estruturados (Schema.org) do site, em um grafo só por página (nuxt-schema-org):
//
//   WebSite ── publisher ──▶ #identity (a pousada: LodgingBusiness + o tipo escolhido no painel)
//   WebPage ── about ──────▶ #identity            (AboutPage, CollectionPage, ItemPage, ContactPage...)
//      └─ mainEntity ──────▶ a pousada, a lista de acomodações ou a acomodação da página
//   #identity
//      ├─ address (PostalAddress), geo (GeoCoordinates), hasMap, sameAs (redes)
//      ├─ image (ImageObject com legenda), logo
//      ├─ amenityFeature (diferenciais), makesOffer (serviços extras)
//      ├─ contactPoint (reservas), potentialAction (ReserveAction → /reservar)
//      └─ containsPlace ──▶ cada acomodação (#acomodacao-{slug}, na página dela)
//   Acomodação (HotelRoom, Suite, House, Apartment... + Product)
//      ├─ occupancy, bed (BedDetails), floorSize, quartos, banheiros, pets, fumantes
//      ├─ amenityFeature (comodidades)
//      ├─ offers (Offer com preço da diária ou da hora em UnitPriceSpecification)
//      └─ containedInPlace ──▶ #identity
//
// Só entra o que está cadastrado: nada de nota, avaliação ou número inventado.

/** Dados da pousada usados nos dados estruturados. */
export interface LodgingSource {
  name: string
  description: string | null
  tagline: string | null
  phone: string | null
  whatsapp: string | null
  email: string | null
  address: string | null
  city: string | null
  state: string | null
  logo_url: string | null
  share_image_url?: string | null
  check_in_time: string
  check_out_time: string
  photos: { url: string, caption?: string | null }[]
  accommodations: { slug?: string, base_price: number, booking_mode?: 'nightly' | 'hourly', photos: { url: string }[], payment?: { methods: string[] } | null }[]
  /** Diferenciais do lugar ("O lugar"): viram amenityFeature. */
  highlights?: { title: string, text?: string }[]
  /** Serviços opcionais (café da manhã, decoração): viram makesOffer. */
  extras?: { title: string, text?: string }[]
  /** Perfis nas redes (Instagram, Tripadvisor...): viram o sameAs; o Google Maps vira hasMap. */
  social?: Partial<Record<string, string>> | null
  /** Posição no mapa, escolhida em "Seu site → SEO". */
  seo?: { latitude?: number | null, longitude?: number | null } | null
}

/** Uma acomodação, como a página dela e o catálogo descrevem. */
export interface AccommodationSource {
  name: string
  slug: string
  description: string | null
  max_guests: number
  base_price: number
  weekend_price: number | null
  min_nights: number
  amenities: string[]
  booking_mode?: 'nightly' | 'hourly'
  hourly_price?: number | null
  min_hours?: number
  updated_at?: string | null
  photos: { url: string, caption?: string | null }[]
  details?: AccommodationDetails | null
}

export type AccommodationKind = 'room' | 'suite' | 'chalet' | 'house' | 'apartment' | 'camping' | 'venue'
export type BedType = 'king' | 'queen' | 'double' | 'single' | 'sofa_bed' | 'bunk' | 'crib'

/** Ficha para o Google, preenchida em Acomodações no painel (tudo opcional). */
export interface AccommodationDetails {
  kind?: AccommodationKind
  bedrooms?: number
  bathrooms?: number
  /** Área em m². */
  area?: number
  beds?: { type: BedType, count: number }[]
  pets?: boolean
  smoking?: boolean
}

type Node = Record<string, unknown>

const money = (cents: number) => `R$ ${Math.round(cents / 100).toLocaleString('pt-BR')}`
/** Preço em reais para o Schema.org ("497.00"). */
const price = (cents: number) => (cents / 100).toFixed(2)

/** "+55 11 4604-3867" a partir de 1146043867 ou 5511950211636. */
export function e164(value: string | null) {
  const digits = (value ?? '').replace(/\D/g, '').replace(/^55(?=\d{10,11}$)/, '')
  return digits.length === 10 || digits.length === 11 ? `+55${digits}` : null
}

/** O Google pede endereços completos; as fotos do próprio site vêm como "/fotos/x.jpg". */
const absoluteUrl = (origin: string) => (url: string) => url.startsWith('/') ? `${origin}${url}` : url

/** @id da pousada (o nó de identidade do nuxt-schema-org). */
export const identityId = (origin: string) => `${origin}/#identity`
/**
 * @id de cada acomodação, na página dela. O fragmento leva o slug: o Unhead junta os nós pelo que
 * vem depois do "#", e "#accommodation" em todas viraria uma acomodação só no catálogo.
 */
export const accommodationId = (origin: string, slug: string) => `${origin}/acomodacoes/${slug}#acomodacao-${slug}`
/** @id da lista do catálogo. */
export const catalogId = (origin: string) => `${origin}/acomodacoes#itemlist`

/** Tipo do Schema.org para cada tipo da ficha; sem ficha, "Accommodation" (ou EventVenue, por hora). */
const KIND_TYPES: Record<AccommodationKind, string> = {
  room: 'HotelRoom',
  suite: 'Suite',
  chalet: 'House',
  house: 'House',
  apartment: 'Apartment',
  camping: 'CampingPitch',
  venue: 'EventVenue',
}

const BED_TYPES: Record<BedType, string> = {
  king: 'King',
  queen: 'Queen',
  double: 'Double',
  single: 'Single',
  sofa_bed: 'Sofa bed',
  bunk: 'Bunk bed',
  crib: 'Crib',
}

/** Formas de pagamento online aceitas em alguma acomodação ("Pix, cartão de crédito"). */
function paymentAccepted(p: LodgingSource) {
  const methods = new Set(p.accommodations.flatMap(a => a.payment?.methods ?? []))
  const names = [methods.has('pix') && 'Pix', methods.has('card') && 'Cartão de crédito'].filter(Boolean)
  return names.length ? names.join(', ') : undefined
}

/**
 * A pousada como o Google entende (Schema.org): tipo de hospedagem, endereço, telefone, faixa de
 * preço das diárias, horários de check-in e check-out, fotos com legenda, posição no mapa, perfis
 * nas redes, diferenciais, serviços extras, contato de reservas, a ação de reservar e as
 * acomodações que ela tem. Só entra o que está cadastrado.
 */
export function lodgingSchema(p: LodgingSource, type: SchemaType, origin = '') {
  const absolute = absoluteUrl(origin)
  const nightly = p.accommodations.filter(a => a.booking_mode !== 'hourly')
  const prices = nightly.map(a => a.base_price).filter(n => n > 0)
  const min = prices.length ? Math.min(...prices) : null
  const max = prices.length ? Math.max(...prices) : null
  const captions = new Map(p.photos.map(ph => [ph.url, ph.caption ?? null]))
  const images = [p.share_image_url, ...p.photos.map(ph => ph.url), ...p.accommodations.flatMap(a => a.photos.slice(0, 1).map(ph => ph.url))]
    .filter((url, i, all): url is string => !!url && all.indexOf(url) === i)
    .slice(0, 10)
    .map(url => ({ '@type': 'ImageObject', 'url': absolute(url), 'contentUrl': absolute(url), 'caption': captions.get(url) || undefined }))
  const hasAddress = p.address || p.city
  const { google: map, ...profiles } = p.social ?? {}
  const sameAs = Object.values(profiles).filter((url): url is string => !!url)
  const lat = p.seo?.latitude
  const lng = p.seo?.longitude
  const phone = e164(p.phone) ?? e164(p.whatsapp)
  const reservationsPhone = e164(p.whatsapp) ?? e164(p.phone)
  const highlights = (p.highlights ?? []).filter(h => h.title)
  const extras = (p.extras ?? []).filter(e => e.title)
  const rooms = p.accommodations.filter(a => a.slug)

  return {
    '@type': type === 'LodgingBusiness' ? 'LodgingBusiness' : ['LodgingBusiness', type],
    'name': p.name,
    'description': p.description ?? p.tagline ?? undefined,
    'slogan': p.tagline ?? undefined,
    'url': '/',
    'telephone': phone ?? undefined,
    'email': p.email ?? undefined,
    'logo': p.logo_url ? absolute(p.logo_url) : undefined,
    'image': images.length ? images : undefined,
    'priceRange': min !== null && max !== null ? (min === max ? money(min) : `${money(min)} – ${money(max)}`) : undefined,
    'currenciesAccepted': 'BRL',
    'paymentAccepted': paymentAccepted(p),
    'knowsLanguage': 'pt-BR',
    'checkinTime': p.check_in_time || undefined,
    'checkoutTime': p.check_out_time || undefined,
    'numberOfRooms': nightly.length || undefined,
    'address': hasAddress
      ? {
          '@type': 'PostalAddress',
          'streetAddress': p.address ?? undefined,
          'addressLocality': p.city ?? undefined,
          'addressRegion': p.state ?? undefined,
          'addressCountry': 'BR',
        }
      : undefined,
    'geo': typeof lat === 'number' && typeof lng === 'number' ? { '@type': 'GeoCoordinates', 'latitude': lat, 'longitude': lng } : undefined,
    'hasMap': map || undefined,
    'sameAs': sameAs.length ? sameAs : undefined,
    'amenityFeature': highlights.length
      ? highlights.map(h => ({ '@type': 'LocationFeatureSpecification', 'name': h.title, 'description': h.text || undefined, 'value': true }))
      : undefined,
    'makesOffer': extras.length
      ? extras.map(e => ({ '@type': 'Offer', 'itemOffered': { '@type': 'Service', 'name': e.title, 'description': e.text || undefined } }))
      : undefined,
    'contactPoint': reservationsPhone || p.email
      ? [{
          '@type': 'ContactPoint',
          'contactType': 'reservations',
          'telephone': reservationsPhone ?? undefined,
          'email': p.email ?? undefined,
          'availableLanguage': ['Portuguese'],
          'areaServed': 'BR',
        }]
      : undefined,
    'potentialAction': {
      '@type': 'ReserveAction',
      'target': {
        '@type': 'EntryPoint',
        'urlTemplate': absolute('/reservar'),
        'inLanguage': 'pt-BR',
        'actionPlatform': ['https://schema.org/DesktopWebPlatform', 'https://schema.org/MobileWebPlatform'],
      },
      'result': { '@type': 'LodgingReservation', 'name': `Reserva em ${p.name}` },
    },
    'containsPlace': rooms.length ? rooms.map(a => ({ '@id': accommodationId(origin, a.slug!) })) : undefined,
  }
}

/**
 * Uma acomodação: o tipo (da ficha do painel), quem cabe, camas, área, quartos, banheiros,
 * comodidades, fotos e a oferta com o preço da diária (ou da hora). Com preço, vai também como
 * Product, que é o que leva `offers` no Schema.org e o que o Google lê para mostrar o preço.
 * `full: false` é a versão curta do catálogo (sem descrição, comodidades nem todas as fotos).
 */
export function accommodationSchema(a: AccommodationSource, origin = '', { full = true } = {}): Node {
  const absolute = absoluteUrl(origin)
  const d = a.details ?? {}
  const hourly = a.booking_mode === 'hourly'
  const type = d.kind ? KIND_TYPES[d.kind] : hourly ? 'EventVenue' : 'Accommodation'
  const venue = type === 'EventVenue'
  const url = absolute(`/acomodacoes/${a.slug}`)
  const photos = full ? a.photos : a.photos.slice(0, 1)
  const unit = hourly ? 'HUR' : 'DAY'
  const perUnit = (cents: number, name: string) => ({
    '@type': 'UnitPriceSpecification',
    'name': name,
    'price': price(cents),
    'priceCurrency': 'BRL',
    'unitCode': unit,
    'referenceQuantity': { '@type': 'QuantitativeValue', 'value': 1, 'unitCode': unit },
  })
  const unitPrice = hourly ? (a.hourly_price ?? 0) : a.base_price
  const minimum = hourly ? (a.min_hours ?? 1) : a.min_nights
  const entire = d.kind === 'chalet' || d.kind === 'house' || d.kind === 'apartment'
  const privateRoom = d.kind === 'room' || d.kind === 'suite'

  return {
    // Product só com preço: o Google exige oferta, avaliação ou nota num Product.
    '@type': unitPrice > 0 ? [type, 'Product'] : type,
    '@id': accommodationId(origin, a.slug),
    'name': a.name,
    'url': url,
    'identifier': a.slug,
    'sku': a.slug,
    'category': venue ? 'Espaço para eventos' : 'Hospedagem',
    'description': full ? (a.description ?? undefined) : undefined,
    'image': photos.length
      ? photos.map(ph => ({ '@type': 'ImageObject', 'url': absolute(ph.url), 'contentUrl': absolute(ph.url), 'caption': ph.caption || undefined }))
      : undefined,
    // Quantas pessoas: ocupação na hospedagem, público máximo num espaço de eventos.
    'occupancy': venue ? undefined : { '@type': 'QuantitativeValue', 'minValue': 1, 'maxValue': a.max_guests, 'unitCode': 'C62' },
    'maximumAttendeeCapacity': venue ? a.max_guests : undefined,
    'additionalType': entire ? 'EntirePlace' : privateRoom ? 'PrivateRoom' : undefined,
    'numberOfBedrooms': d.bedrooms ?? undefined,
    'numberOfRooms': d.bedrooms ?? undefined,
    'numberOfBathroomsTotal': d.bathrooms ?? undefined,
    'floorSize': d.area ? { '@type': 'QuantitativeValue', 'value': d.area, 'unitCode': 'MTK', 'unitText': 'm²' } : undefined,
    'bed': d.beds?.length ? d.beds.map(b => ({ '@type': 'BedDetails', 'numberOfBeds': b.count, 'typeOfBed': BED_TYPES[b.type] })) : undefined,
    'petsAllowed': d.pets ?? undefined,
    'smokingAllowed': d.smoking ?? undefined,
    'amenityFeature': full && a.amenities.length
      ? a.amenities.map(name => ({ '@type': 'LocationFeatureSpecification', 'name': name, 'value': true }))
      : undefined,
    'containedInPlace': { '@id': identityId(origin) },
    'brand': { '@id': identityId(origin) },
    'offers': unitPrice > 0
      ? {
          '@type': 'Offer',
          'url': url,
          'price': price(unitPrice),
          'priceCurrency': 'BRL',
          'availability': 'https://schema.org/InStock',
          'businessFunction': 'http://purl.org/goodrelations/v1#LeaseOut',
          'seller': { '@id': identityId(origin) },
          'priceSpecification': hourly
            ? [perUnit(unitPrice, 'Hora')]
            : [
                perUnit(a.base_price, a.weekend_price ? 'Diária (domingo a quinta)' : 'Diária'),
                ...(a.weekend_price ? [perUnit(a.weekend_price, 'Diária (sexta e sábado)')] : []),
              ],
          'eligibleQuantity': minimum > 1 ? { '@type': 'QuantitativeValue', 'minValue': minimum, 'unitCode': unit } : undefined,
        }
      : undefined,
  }
}

/** O catálogo como lista ordenada (ItemList), apontando para cada acomodação. */
export function catalogSchema(list: AccommodationSource[], origin = ''): Node {
  return {
    '@type': 'ItemList',
    '@id': catalogId(origin),
    'name': 'Acomodações',
    'itemListOrder': 'https://schema.org/ItemListOrderAscending',
    'numberOfItems': list.length,
    'itemListElement': list.map((a, i) => ({
      '@type': 'ListItem',
      'position': i + 1,
      'url': `${origin}/acomodacoes/${a.slug}`,
      'item': { '@id': accommodationId(origin, a.slug) },
    })),
  }
}
