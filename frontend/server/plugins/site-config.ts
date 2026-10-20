// Nome, descrição e "aparecer no Google" de cada requisição vêm da pousada (painel → SEO).
// Valem para robots.txt, sitemap.xml, tags padrão e dados estruturados.
export default defineNitroPlugin((nitroApp) => {
  nitroApp.hooks.hook('site-config:init', async ({ siteConfig }) => {
    const property = await siteProperty()
    if (!property) return

    siteConfig.push({
      _context: 'hospedaki-property',
      name: property.name,
      // Domínio próprio no ar (painel → Domínio): vira o endereço oficial (canonical, sitemap, og:url).
      ...(property.domain ? { url: `https://${property.domain}` } : {}),
      description: property.seo?.description || property.tagline || property.description || undefined,
      // Desligado no painel: Disallow: / no robots.txt e noindex em tudo. Ligado, segue o
      // ambiente (produção indexa; prévias e desenvolvimento não).
      ...(property.seo?.indexable === false ? { indexable: false } : {}),
    })
  })
})
