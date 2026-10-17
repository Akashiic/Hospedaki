<script setup lang="ts">
const { property, heroSlides, place } = await useProperty()

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
      @explore="navigateTo('/o-lugar')"
    />

    <section class="welcome container">
      <p v-reveal class="eyebrow">Bem-vindo</p>
      <p v-reveal class="statement" style="--i: 1">{{ property.description }}</p>
      <ul class="highlights">
        <li v-for="(item, i) in property.highlights.slice(0, 3)" :key="item.title" v-reveal :style="{ '--i': 2 + i }">
          <h2>{{ item.title }}</h2>
          <p>{{ item.text }}</p>
        </li>
      </ul>
      <NuxtLink v-reveal to="/o-lugar" class="text-link">Conhecer o lugar <span class="arrow">→</span></NuxtLink>
    </section>

    <section class="stays container">
      <div class="head">
        <div>
          <p v-reveal class="eyebrow">Onde você vai ficar</p>
          <h2 v-reveal class="title" style="--i: 1">Acomodações</h2>
        </div>
        <NuxtLink v-reveal to="/acomodacoes" class="text-link">Ver todas <span class="arrow">→</span></NuxtLink>
      </div>
      <LazySiteStayCards hydrate-on-visible :accommodations="property.accommodations" />
    </section>
  </div>
</template>

<style scoped lang="scss">
.welcome { @include section-pad(clamp(5rem, 11vw, 8.5rem), clamp(3rem, 7vw, 5rem)); }
.statement { @include display(clamp(2rem, 4.4vw, 3.6rem)); line-height: 1.12; margin: 1.2rem 0 0; max-width: 24ch; text-wrap: pretty; }
.highlights {
  list-style: none; padding: 0; margin: clamp(2.5rem, 6vw, 4rem) 0 2.5rem;
  display: grid; gap: 1.5rem 2.5rem; grid-template-columns: repeat(auto-fit, minmax(min(100%, 240px), 1fr));
  li { border-top: 1px solid var(--line); padding-top: 1.1rem; }
  h2 { @include display(1.6rem); margin: 0 0 .3rem; }
  p { margin: 0; color: var(--muted); }
}
.stays { padding-bottom: clamp(5rem, 10vw, 8rem); }
.head { display: flex; justify-content: space-between; align-items: end; flex-wrap: wrap; gap: 1rem; margin-bottom: clamp(2rem, 5vw, 3rem); }
.title { @include display(clamp(2.8rem, 7vw, 5.5rem)); margin: .4rem 0 0; }
</style>
