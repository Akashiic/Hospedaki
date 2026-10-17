<script setup lang="ts">
import type { Photo, Property } from '~/composables/useApi'

const api = useApi()
const scrollTo = useScrollTo()

const { data: property, error } = await useAsyncData('property', () =>
  api<{ data: Property }>('/').then(res => res.data),
)

if (error.value || !property.value) {
  throw createError({ statusCode: 404, statusMessage: 'Pousada não encontrada', fatal: true })
}

const accommodationPhotos = computed<Photo[]>(() => property.value!.accommodations.flatMap(a => a.photos))

// Capa: fotos marcadas como destaque; sem elas, as primeiras fotos das acomodações.
const heroSlides = computed<Photo[]>(() => {
  const featured = property.value!.photos.filter(p => p.featured)
  return (featured.length ? featured : property.value!.photos.length ? property.value!.photos : accommodationPhotos.value).slice(0, 5)
})
const galleryPhotos = computed<Photo[]>(() => {
  const all = [...property.value!.photos, ...accommodationPhotos.value]
  return all.filter((p, i) => all.findIndex(o => o.url === p.url) === i)
})

const place = computed(() => {
  const where = [property.value!.city, property.value!.state].filter(Boolean).join(', ')
  return [property.value!.name, where].filter(Boolean).join(' · ')
})

useSeoMeta({
  title: property.value.tagline ? `${property.value.name} · ${property.value.tagline}` : property.value.name,
  description: property.value.description ?? undefined,
  ogTitle: property.value.name,
  ogDescription: property.value.description ?? undefined,
  ogImage: heroSlides.value[0]?.url,
})
useHead({
  link: heroSlides.value[0] && !heroSlides.value[0].url.startsWith('data:')
    ? [{ rel: 'preload', as: 'image', href: heroSlides.value[0].url, fetchpriority: 'high' }]
    : [],
})

const selected = ref<number[]>(property.value.accommodations.slice(0, 1).map(a => a.id))

function choose(id: number) {
  if (!selected.value.includes(id)) selected.value = [...selected.value, id]
  scrollTo('reservar')
}
</script>

<template>
  <div v-if="property" class="site" :style="{ '--brand': property.primary_color }">
    <SiteNav :name="property.name" :logo-url="property.logo_url" />
    <main>
      <SiteHero
        :slides="heroSlides"
        :title="property.tagline ?? property.name"
        :subtitle="property.tagline ? null : property.description"
        :place="place"
        @book="scrollTo('reservar')"
        @explore="scrollTo('o-lugar')"
      />
      <!-- Abaixo da capa, cada parte só "acorda" (hidrata) quando chega perto da tela: menos trabalho no carregamento. -->
      <LazySiteIntro hydrate-on-visible :property="property" />
      <LazySiteShowcase hydrate-on-visible :accommodations="property.accommodations" :selected="selected" @choose="choose" />
      <LazySiteGallery v-if="galleryPhotos.length > 1" hydrate-on-visible :photos="galleryPhotos" />
      <LazySiteBooking v-model:selected="selected" hydrate-on-visible :property="property" />
    </main>
    <LazySiteFooter hydrate-on-visible :property="property" @book="scrollTo('reservar')" />
  </div>
</template>
