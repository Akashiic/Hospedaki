import type { PageKey } from '~/utils/sitePages'

/**
 * Textos de uma página do site (os do dono ou os padrão) e as tags de SEO dela.
 * `image` é a foto da página, usada ao compartilhar quando a pousada não escolheu uma imagem própria.
 * Página tirada do Google pelo dono (ou a prévia do editor) recebe noindex e sai do mapa do site.
 */
export async function useSitePage(key: PageKey, options: { image?: () => string | null | undefined, noindex?: () => boolean } = {}) {
  // Depois do await o Nuxt perde o contexto da página: as tags são registradas dentro dele.
  const nuxtApp = useNuxtApp()
  const { property } = await useProperty()
  const content = computed(() => pageContent(key, property.value, property.value.pages?.[key]))

  nuxtApp.runWithContext(() => {
    // A origem sai agora: os getters abaixo rodam depois, fora do contexto do Nuxt.
    const origin = schemaOrigin()
    useSeoMeta({
      title: () => content.value.seoTitle,
      description: () => content.value.seoDescription ?? undefined,
      ogTitle: () => content.value.seoTitle,
      ogDescription: () => content.value.seoDescription ?? undefined,
      ogImage: () => absoluteSiteUrl(property.value.share_image_url ?? options.image?.(), origin),
      ogImageAlt: () => content.value.seoTitle,
    })
  })
  // Palavras-chave da página ou, sem elas, as do site (pageContent já faz a troca).
  nuxtApp.runWithContext(() => useHead({
    meta: () => content.value.keywords ? [{ name: 'keywords', content: content.value.keywords }] : [],
  }))
  // A página no grafo do Schema.org: tipo, assunto, foto principal e trilha.
  nuxtApp.runWithContext(() => usePageSchema(key, property.value, () => options.image?.()))
  if (property.value.pages?.[key]?.index === false || options.noindex?.()) {
    nuxtApp.runWithContext(() => useRobotsRule('noindex, follow'))
  }

  return content
}
