import type { H3Event } from 'h3'

interface SiteProperty {
  seo?: { indexable?: boolean }
  accommodations?: { slug: string }[]
}

/** Pousada do site, para robots.txt e sitemap.xml. Null se a API não responder. */
export async function siteProperty(event: H3Event): Promise<SiteProperty | null> {
  const { apiBase, propertySlug } = useRuntimeConfig(event).public
  return await $fetch<{ data: SiteProperty }>(`${apiBase}/properties/${propertySlug}`, { timeout: 20000 })
    .then(res => res.data)
    .catch(() => null)
}

/** Endereço do site como o visitante usou (domínio próprio ou o da Vercel). */
export const siteOrigin = (event: H3Event) => getRequestURL(event, { xForwardedHost: true, xForwardedProto: true }).origin
