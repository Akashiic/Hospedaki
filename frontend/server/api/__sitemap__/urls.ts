import { defineSitemapEventHandler } from '#sitemap/server'

// Páginas do site que o dono deixou no Google (painel → Páginas) e uma por acomodação, com as
// fotos (Google Imagens) quando a opção está ligada em SEO. O painel e o login nunca entram.
const PAGES: [key: string, path: string][] = [
  ['inicio', '/'], ['lugar', '/o-lugar'], ['acomodacoes', '/acomodacoes'], ['reservar', '/reservar'], ['contato', '/contato'],
]

export default defineSitemapEventHandler(async () => {
  const property = await siteProperty()
  if (!property) return PAGES.map(([, loc]) => ({ loc }))
  // Site fora do Google (painel → SEO): mapa vazio, coerente com o Disallow: / do robots.txt.
  if (property.seo?.indexable === false) return []

  const images = property.seo?.sitemap_images !== false
  const photo = (p: { url: string, caption: string | null }) => ({ loc: p.url, ...(p.caption ? { title: p.caption } : {}) })
  const listed = (key: string) => property.pages?.[key]?.index !== false

  const pages = PAGES.filter(([key]) => listed(key)).map(([key, loc]) => ({
    loc,
    ...(key === 'inicio' && images && property.photos?.length ? { images: property.photos.slice(0, 20).map(photo) } : {}),
  }))
  // As acomodações seguem a página "Acomodações": fora do Google lá, fora aqui também.
  const stays = listed('acomodacoes')
    ? (property.accommodations ?? []).map(a => ({
        loc: `/acomodacoes/${a.slug}`,
        ...(a.updated_at ? { lastmod: a.updated_at } : {}),
        ...(images && a.photos.length ? { images: a.photos.map(photo) } : {}),
      }))
    : []
  // A página de vendas do Hospedaki só existe na demonstração.
  const sales = property.demo ? [{ loc: '/hospedaki' }] : []

  return [...pages, ...stays, ...sales]
})
