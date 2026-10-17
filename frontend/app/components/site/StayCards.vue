<script setup lang="ts">
import type { Accommodation } from '~/composables/useApi'

// Cartões das acomodações na home: foto grande, nome e preço; levam para a página de cada uma.
defineProps<{ accommodations: Accommodation[] }>()
</script>

<template>
  <div class="cards">
    <NuxtLink
      v-for="(acc, i) in accommodations"
      :key="acc.id"
      v-reveal
      :to="`/acomodacoes/${acc.slug}`"
      class="card"
      :style="{ '--i': i * 2 }"
    >
      <div class="media">
        <img v-if="acc.photos[0]" :src="acc.photos[0].url" :alt="acc.photos[0].caption ?? acc.name" loading="lazy" decoding="async">
      </div>
      <div class="body">
        <h3>{{ acc.name }}</h3>
        <p class="meta">até {{ acc.max_guests }} pessoas · a partir de {{ formatMoney(acc.base_price) }}/diária</p>
        <span class="more">Conhecer <span class="arrow">→</span></span>
      </div>
    </NuxtLink>
  </div>
</template>

<style scoped lang="scss">
.cards { display: grid; gap: clamp(1rem, 2.5vw, 2rem); grid-template-columns: repeat(auto-fit, minmax(min(100%, 340px), 1fr)); }
.card {
  position: relative; display: block; border-radius: 6px; overflow: hidden; color: #fff; text-decoration: none; background: var(--night);
  aspect-ratio: 4 / 5; max-width: 100%;
  @include above(md) { aspect-ratio: 4 / 4.4; }
}
.media, .media img { position: absolute; inset: 0; width: 100%; height: 100%; }
.media img { object-fit: cover; transition: transform $dur-2xl $ease-enter, filter $dur-lg $ease-move; }
.media::after { content: ""; position: absolute; inset: 0; background: linear-gradient(180deg, rgb(8 12 10 / 0) 40%, rgb(8 12 10 / .82)); }
.card:hover .media img { transform: scale($zoom-sm); }
.body { position: absolute; inset: auto 0 0; padding: clamp(1.2rem, 3vw, 2rem); display: grid; gap: .3rem; }
h3 { @include display(clamp(2.2rem, 4.5vw, 3.2rem)); margin: 0; }
.meta { margin: 0; color: rgb(255 255 255 / .82); }
.more { margin-top: .6rem; font-weight: 700; display: inline-flex; gap: .4rem; }
.arrow { transition: transform $dur-md $ease-enter; }
.card:hover .arrow { transform: translateX(4px); }
</style>
