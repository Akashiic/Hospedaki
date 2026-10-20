// Lista das páginas do site para o Google: as fixas e uma por acomodação.
export default defineEventHandler(async (event) => {
  const property = await siteProperty(event)
  const origin = siteOrigin(event)
  const paths = property?.seo?.indexable === false
    ? []
    : ['/', '/o-lugar', '/acomodacoes', ...(property?.accommodations ?? []).map(a => `/acomodacoes/${a.slug}`), '/reservar', '/contato']

  setHeader(event, 'Content-Type', 'application/xml; charset=utf-8')
  setHeader(event, 'Cache-Control', 'public, max-age=600')
  const urls = paths.map(path => `  <url><loc>${origin}${path}</loc></url>`).join('\n')
  return `<?xml version="1.0" encoding="UTF-8"?>\n<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">\n${urls}\n</urlset>\n`
})
