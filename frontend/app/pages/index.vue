<script setup lang="ts">
import { accommodationFor } from '~/composables/usePersona'

const { property, heroSlides, gallery, place, whatsappLink } = await useProperty()
const persona = usePersona()
const scrollTo = useScrollTo()
const route = useRoute()
const chosen = computed(() => accommodationFor(property.value, persona.value))

// Seções na ordem que o dono escolheu no painel. A prévia do editor manda um layout ainda
// não salvo em ?layout=hero,stays,story (só muda o que esta visita vê).
const preview = computed(() => fromLayoutParam(route.query.layout))
const sections = computed(() => preview.value ?? normalizeLayout(property.value.home_sections))
const shown = computed(() => shownSections(sections.value, availableSections({
  story: property.value.story.length,
  accommodations: property.value.accommodations.length,
  photos: gallery.value.length,
  extras: property.value.extras?.length ?? 0,
})))
// Logo depois de "Um dia aqui", as acomodações entram no fim dela, sob o mesmo céu.
const staysInStory = computed(() => shown.value.includes('story') && shown.value[shown.value.indexOf('story') + 1] === 'stays')
// "Conhecer" e a escolha da capa levam ao dia na pousada ou, sem ele, às acomodações.
const exploreTarget = computed(() => shown.value.includes('story') ? 'um-dia' : 'acomodacoes-home')

useSeoMeta({
  title: property.value.tagline ? `${property.value.name} · ${property.value.tagline}` : property.value.name,
  description: property.value.description ?? undefined,
  ogTitle: property.value.name,
  ogDescription: property.value.description ?? undefined,
  ogImage: heroSlides.value[0]?.url,
  robots: preview.value ? 'noindex' : undefined,
})
useHead({
  link: heroSlides.value[0] && !heroSlides.value[0].url.startsWith('data:')
    ? [{ rel: 'preload', as: 'image', href: heroSlides.value[0].url, fetchpriority: 'high' }]
    : [],
})
</script>

<template>
  <div>
    <template v-for="key in shown" :key="key">
      <SiteHero
        v-if="key === 'hero'"
        :slides="heroSlides"
        :title="property.tagline ?? property.name"
        :subtitle="property.tagline ? null : property.description"
        :place="[property.name, place].filter(Boolean).join(' · ')"
        @book="navigateTo('/reservar')"
        @explore="scrollTo(exploreTarget)"
      >
        <template #actions>
          <SiteDoors :property="property" @chose="scrollTo(exploreTarget)" />
        </template>
      </SiteHero>

      <SiteDayStory v-else-if="key === 'story'" :moments="property.story" :fallback-photos="gallery" :title="property.story_title">
        <template v-if="staysInStory" #default>
          <div id="acomodacoes-home" class="where">
            <p v-reveal class="eyebrow">Onde esse dia acontece</p>
            <h2 v-reveal style="--i: 1">{{ chosen ? `${chosen.name} espera por vocês` : 'Escolha o seu canto' }}</h2>
          </div>
          <LazySiteStayCards hydrate-on-visible :accommodations="property.accommodations" :highlight="chosen?.id" />
        </template>
      </SiteDayStory>

      <section v-else-if="key === 'stays' && !staysInStory" id="acomodacoes-home" class="stays container">
        <p v-reveal class="eyebrow">Onde ficar</p>
        <h2 v-reveal class="title" style="--i: 1">{{ chosen ? `${chosen.name} espera por vocês` : 'Escolha o seu canto' }}</h2>
        <LazySiteStayCards hydrate-on-visible :accommodations="property.accommodations" :highlight="chosen?.id" />
      </section>

      <SiteIntro v-else-if="key === 'intro'" :property="property" />

      <LazySiteGallery v-else-if="key === 'gallery'" hydrate-on-visible :photos="gallery" />

      <SiteExtras v-else-if="key === 'extras'" :extras="property.extras ?? []" :whatsapp="whatsappLink('Olá! Quero saber dos serviços opcionais.')" />
    </template>
  </div>
</template>

<style scoped lang="scss">
// Dentro de "Um dia aqui": a cor segue o céu da seção e o texto deixa livre a coluna do relógio.
.where { margin-bottom: clamp(2rem, 5vw, 3rem); margin-right: var(--dial-room, 0px); }
.where .eyebrow { color: var(--ink-accent, var(--amber)); }
.where h2 { @include display(clamp(2.4rem, 6vw, 4.6rem)); line-height: 1; margin: .5rem 0 0; max-width: 16ch; }
.stays { padding-block: clamp(4rem, 9vw, 7rem); }
.stays .eyebrow { @include eyebrow; }
.title { @include display(clamp(2.4rem, 6vw, 4.6rem)); line-height: 1; margin: .5rem 0 clamp(2rem, 5vw, 3rem); max-width: 16ch; }
</style>
