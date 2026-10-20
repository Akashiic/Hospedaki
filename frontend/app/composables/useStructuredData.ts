import type { Property } from '~/composables/useApi'
import type { PageKey } from '~/utils/sitePages'

// Peças do grafo Schema.org de cada página (o desenho completo está em utils/lodgingSchema.ts).
// Tudo some quando o dono desliga os dados estruturados em "Seu site → SEO".

/** Endereço base dos @id: o do site config (domínio próprio ou NUXT_SITE_URL) ou o da requisição. */
export function schemaOrigin() {
  return (useSiteConfig().url || useRequestURL().origin).replace(/\/$/, '')
}

/** Endereço completo de uma foto do próprio site ("/fotos/x.jpg"): o WhatsApp e as redes não leem caminhos. */
export function absoluteSiteUrl(url: string | null | undefined, origin: string) {
  if (!url || url.startsWith('data:')) return undefined
  return url.startsWith('/') ? `${origin}${url}` : url
}

export const structuredDataOn = (property: Property) => property.seo?.structured_data !== false

/** Tipo de cada página no Schema.org (o nuxt-schema-org só reconhece os caminhos em inglês). */
const PAGE_TYPES: Record<PageKey, string> = {
  inicio: 'WebPage',
  lugar: 'AboutPage',
  acomodacoes: 'CollectionPage',
  reservar: 'WebPage',
  contato: 'ContactPage',
}

/**
 * A página no grafo: tipo, idioma, sobre o quê (a pousada), o assunto principal, a foto principal
 * e a trilha "Início › Página" (menos no Início).
 */
export function usePageSchema(key: PageKey, property: Property, image: () => string | null | undefined) {
  if (!structuredDataOn(property)) return
  const origin = schemaOrigin()
  const absolute = (url: string) => url.startsWith('/') ? `${origin}${url}` : url
  const info = SITE_PAGES.find(p => p.key === key)!

  const url = property.share_image_url ?? image()
  const photo = url && !url.startsWith('data:') ? absolute(url) : null

  useSchemaOrg([
    defineWebPage({
      '@type': PAGE_TYPES[key],
      'inLanguage': 'pt-BR',
      'about': { '@id': identityId(origin) },
      'mainEntity': { '@id': key === 'acomodacoes' ? catalogId(origin) : identityId(origin) },
      'primaryImageOfPage': photo ? { '@type': 'ImageObject', 'url': photo, 'contentUrl': photo } : undefined,
    } as Parameters<typeof defineWebPage>[0]),
    key !== 'inicio' && defineBreadcrumb({ itemListElement: [{ name: 'Início', item: '/' }, { name: info.label }] }),
  ])
}

/** Acomodações na versão curta (catálogo e início) e, no catálogo, a lista ordenada delas. */
export function useCatalogSchema(property: Property, { list = false } = {}) {
  if (!structuredDataOn(property) || !property.accommodations.length) return
  const origin = schemaOrigin()
  useSchemaOrg([
    ...property.accommodations.map(a => accommodationSchema(a, origin, { full: false })),
    list && catalogSchema(property.accommodations, origin),
  ])
}
