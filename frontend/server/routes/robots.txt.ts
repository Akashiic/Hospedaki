// Regras para buscadores. Com "Aparecer no Google" desligado no painel, nada é indexado.
// Se a API não responder, libera o site (é o padrão) para não sumir do Google por uma falha.
export default defineEventHandler(async (event) => {
  const property = await siteProperty(event)
  setHeader(event, 'Content-Type', 'text/plain; charset=utf-8')
  setHeader(event, 'Cache-Control', 'public, max-age=600')

  if (property?.seo?.indexable === false) return 'User-agent: *\nDisallow: /\n'

  return `User-agent: *\nDisallow: /painel\n\nSitemap: ${siteOrigin(event)}/sitemap.xml\n`
})
