<script setup lang="ts">
import { accommodationFor } from '~/composables/usePersona'

const { property, heroSlides, gallery, place } = await useProperty()
const persona = usePersona()
const scrollTo = useScrollTo()
const chosen = computed(() => accommodationFor(property.value, persona.value))

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
</script>

<template>
  <div>
    <SiteHero
      :slides="heroSlides"
      :title="property.tagline ?? property.name"
      :subtitle="property.tagline ? null : property.description"
      :place="[property.name, place].filter(Boolean).join(' · ')"
      @book="navigateTo('/reservar')"
      @explore="scrollTo('um-dia')"
    >
      <template #actions>
        <SiteDoors :property="property" @chose="scrollTo('um-dia')" />
      </template>
    </SiteHero>

    <SiteDayStory v-if="property.story.length" :moments="property.story" :fallback-photos="gallery" :title="property.story_title">
      <div class="where">
        <p v-reveal class="eyebrow">Onde esse dia acontece</p>
        <h2 v-reveal style="--i: 1">{{ chosen ? `${chosen.name} espera por vocês` : 'Escolha o seu canto' }}</h2>
      </div>
      <LazySiteStayCards hydrate-on-visible :accommodations="property.accommodations" :highlight="chosen?.id" />
    </SiteDayStory>

    <section v-else class="stays container">
      <h2 class="title">Acomodações</h2>
      <LazySiteStayCards hydrate-on-visible :accommodations="property.accommodations" />
    </section>
  </div>
</template>

<style scoped lang="scss">
// Dentro de "Um dia aqui": a cor segue o céu da seção e o texto deixa livre a coluna do relógio.
.where { margin-bottom: clamp(2rem, 5vw, 3rem); margin-right: var(--dial-room, 0px); }
.where .eyebrow { color: var(--ink-accent, var(--amber)); }
.where h2 { @include display(clamp(2.4rem, 6vw, 4.6rem)); line-height: 1; margin: .5rem 0 0; max-width: 16ch; }
.stays { padding-block: clamp(4rem, 9vw, 7rem); }
.title { @include display(clamp(2.8rem, 7vw, 5.5rem)); margin: 0 0 2rem; }
</style>
