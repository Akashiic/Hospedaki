import type { Photo, Property } from '~/composables/useApi'

/**
 * Dados da pousada, buscados uma vez e reaproveitados em todas as páginas
 * (a navegação entre páginas não refaz a busca).
 */
export async function useProperty() {
  const api = useApi()
  const nuxtApp = useNuxtApp()

  const { data, error } = await useAsyncData('property', () => api<{ data: Property }>('/').then(res => res.data), {
    getCachedData: key => nuxtApp.payload.data[key] ?? nuxtApp.static.data[key],
  })

  // 404 só quando a API diz que a pousada não existe; outras falhas (servidor fora do ar, 500)
  // viram 503, para buscadores tentarem de novo em vez de tirarem o site do índice.
  // O texto vai em `message`: statusMessage acentuado vira lixo no cabeçalho HTTP. A página de
  // erro (app/error.vue) escolhe a cópia pelo status.
  if (error.value || !data.value) {
    const missing = !error.value || error.value.statusCode === 404
    throw createError({
      status: missing ? 404 : 503,
      message: missing ? 'Pousada não encontrada' : 'Site temporariamente indisponível',
      fatal: true,
    })
  }

  const property = data as Ref<Property>
  const accommodationPhotos = computed<Photo[]>(() => property.value.accommodations.flatMap(a => a.photos))

  // Capa: fotos marcadas como destaque; sem elas, as primeiras fotos das acomodações.
  const heroSlides = computed<Photo[]>(() => {
    const featured = property.value.photos.filter(p => p.featured)
    const pool = featured.length ? featured : property.value.photos.length ? property.value.photos : accommodationPhotos.value
    return pool.slice(0, 5)
  })

  const gallery = computed<Photo[]>(() => {
    const all = [...property.value.photos, ...accommodationPhotos.value]
    return all.filter((p, i) => all.findIndex(o => o.url === p.url) === i)
  })

  const place = computed(() => [property.value.city, property.value.state].filter(Boolean).join(', '))

  const whatsappLink = (text = 'Olá! Gostaria de informações sobre hospedagem.') =>
    property.value.whatsapp ? `https://wa.me/${property.value.whatsapp}?text=${encodeURIComponent(text)}` : null

  return { property, heroSlides, gallery, place, whatsappLink }
}
