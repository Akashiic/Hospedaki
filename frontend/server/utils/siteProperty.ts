/** O que o servidor do site precisa da pousada para SEO (robots, mapa do site, nome). */
export interface SiteProperty {
  name: string
  description: string | null
  tagline: string | null
  demo?: boolean
  pages?: Record<string, { index?: boolean }>
  seo?: { indexable?: boolean, ai_bots?: 'allow' | 'training' | 'all', sitemap_images?: boolean, description?: string | null }
  photos?: { url: string, caption: string | null }[]
  accommodations?: { slug: string, name: string, updated_at?: string | null, photos: { url: string, caption: string | null }[] }[]
}

/**
 * Pousada do site, guardada por um minuto: robots.txt, sitemap.xml e cada página leem daqui sem
 * chamar a API toda vez. Null se a API não responder (o site segue com os padrões).
 */
const cachedProperty = defineCachedFunction(async (apiBase: string, slug: string) => {
  return await $fetch<{ data: SiteProperty }>(`${apiBase}/properties/${slug}`, { timeout: 25000 })
    .then(res => res.data)
    .catch(() => null)
}, { name: 'site-property', maxAge: 60, swr: false, getKey: (apiBase: string, slug: string) => `${apiBase}|${slug}` })

export async function siteProperty(): Promise<SiteProperty | null> {
  const { apiBase, propertySlug } = useRuntimeConfig().public
  return await cachedProperty(apiBase, propertySlug)
}
