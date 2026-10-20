import type { SchemaType } from '~/utils/sitePages'

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
  photos: { url: string }[]
  accommodations: { base_price: number, photos: { url: string }[] }[]
  /** Perfis nas redes (Instagram, Tripadvisor...): viram o sameAs. */
  social?: Partial<Record<string, string>> | null
  /** Posição no mapa, escolhida em "Seu site → SEO". */
  seo?: { latitude?: number | null, longitude?: number | null } | null
}

const money = (cents: number) => `R$ ${Math.round(cents / 100).toLocaleString('pt-BR')}`

/** "+55 11 4604-3867" a partir de 1146043867 ou 5511950211636. */
export function e164(value: string | null) {
  const digits = (value ?? '').replace(/\D/g, '').replace(/^55(?=\d{10,11}$)/, '')
  return digits.length === 10 || digits.length === 11 ? `+55${digits}` : null
}

/**
 * A pousada como o Google entende (Schema.org): tipo de hospedagem, endereço, telefone, faixa de
 * preço das diárias, horários de check-in e check-out, fotos, posição no mapa e perfis nas redes.
 * Só entra o que está cadastrado.
 */
export function lodgingSchema(p: LodgingSource, type: SchemaType, origin = '') {
  // O Google pede endereços completos; as fotos do próprio site vêm como "/fotos/x.jpg".
  const absolute = (url: string) => url.startsWith('/') ? `${origin}${url}` : url
  const prices = p.accommodations.map(a => a.base_price).filter(n => n > 0)
  const min = prices.length ? Math.min(...prices) : null
  const max = prices.length ? Math.max(...prices) : null
  const images = [p.share_image_url, ...p.photos.map(ph => ph.url), ...p.accommodations.flatMap(a => a.photos.slice(0, 1).map(ph => ph.url))]
    .filter((url, i, all): url is string => !!url && all.indexOf(url) === i)
    .slice(0, 10)
    .map(absolute)
  const hasAddress = p.address || p.city
  const sameAs = Object.values(p.social ?? {}).filter((url): url is string => !!url)
  const lat = p.seo?.latitude
  const lng = p.seo?.longitude

  return {
    '@type': type === 'LodgingBusiness' ? 'LodgingBusiness' : ['LodgingBusiness', type],
    'name': p.name,
    'description': p.description ?? p.tagline ?? undefined,
    'url': '/',
    'telephone': e164(p.phone) ?? e164(p.whatsapp) ?? undefined,
    'email': p.email ?? undefined,
    'logo': p.logo_url ? absolute(p.logo_url) : undefined,
    'image': images.length ? images : undefined,
    'priceRange': min !== null && max !== null ? (min === max ? money(min) : `${money(min)} – ${money(max)}`) : undefined,
    'currenciesAccepted': 'BRL',
    'checkinTime': p.check_in_time || undefined,
    'checkoutTime': p.check_out_time || undefined,
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
    'sameAs': sameAs.length ? sameAs : undefined,
  }
}
