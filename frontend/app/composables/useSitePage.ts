import type { PageKey } from '~/utils/sitePages'

/**
 * Textos de uma página do site (os do dono ou os padrão) e as tags de SEO dela.
 * `image` é a foto da página, usada ao compartilhar quando a pousada não escolheu uma imagem própria.
 */
export async function useSitePage(key: PageKey, options: { image?: () => string | null | undefined, noindex?: () => boolean } = {}) {
  // Depois do await o Nuxt perde o contexto da página: as tags são registradas dentro dele.
  const nuxtApp = useNuxtApp()
  const { property } = await useProperty()
  const content = computed(() => pageContent(key, property.value, property.value.pages?.[key]))

  nuxtApp.runWithContext(() => useSeoMeta({
    title: () => content.value.seoTitle,
    description: () => content.value.seoDescription ?? undefined,
    ogTitle: () => content.value.seoTitle,
    ogDescription: () => content.value.seoDescription ?? undefined,
    ogImage: () => property.value.share_image_url ?? options.image?.() ?? undefined,
    robots: () => options.noindex?.() ? 'noindex' : undefined,
  }))

  return content
}
