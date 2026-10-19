<script setup lang="ts">
import type { Accommodation } from '~/composables/useApi'

// Cartões das acomodações: foto grande, nome e preço; levam para a página de cada uma.
// Com `highlight`, essa acomodação vem primeiro e ganha o selo "Para vocês".
const props = defineProps<{ accommodations: Accommodation[], highlight?: number | null }>()
const ordered = computed(() => props.highlight
  ? [...props.accommodations].sort((a, b) => Number(b.id === props.highlight) - Number(a.id === props.highlight))
  : props.accommodations)
</script>

<template>
  <div class="cards" :class="`n${Math.min(ordered.length, 4)}`">
    <NuxtLink
      v-for="(acc, i) in ordered"
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
        <span v-if="acc.id === highlight" class="badge">Para vocês</span>
        <h3>{{ acc.name }}</h3>
        <p class="meta">{{ guestCopy.card(acc) }}</p>
        <span class="more">Conhecer <span class="arrow">→</span></span>
      </div>
    </NuxtLink>
  </div>
</template>

<style scoped lang="scss">
// Colunas pelo número de acomodações, sem deixar um cartão sozinho na última linha:
// 2 por linha no tablet; 3 ou 4 lado a lado nas telas largas.
.cards {
  display: grid; gap: clamp(1rem, 2.5vw, 2rem); grid-template-columns: minmax(0, 1fr);
  @include above(md) { grid-template-columns: repeat(2, minmax(0, 1fr)); }
  &.n1 { grid-template-columns: minmax(0, 1fr); }
  &.n3 { @include above(lg) { grid-template-columns: repeat(3, minmax(0, 1fr)); } }
  &.n4 { @include above(xl) { grid-template-columns: repeat(4, minmax(0, 1fr)); } }
}
// Quatro lado a lado: cartões em pé e título menor, para o texto ficar na parte escura da foto.
.n4 .card { @include above(xl) { aspect-ratio: 3 / 4.2; } }
.n4 h3 { @include above(xl) { font-size: 2.1rem; } }
.card {
  position: relative; display: block; border-radius: 6px; overflow: hidden; color: #fff; text-decoration: none; background: var(--night);
  aspect-ratio: 4 / 5; max-width: 100%;
  @include above(md) { aspect-ratio: 4 / 4.4; }
}
.media, .media img { position: absolute; inset: 0; width: 100%; height: 100%; }
.media img { object-fit: cover; transition: transform $dur-2xl $ease-enter; }
.media::after { content: ""; position: absolute; inset: 0; background: linear-gradient(180deg, rgb(8 12 10 / 0) 30%, rgb(8 12 10 / .55) 60%, rgb(8 12 10 / .9)); }
.card:hover .media img { transform: scale($zoom-sm); }
.body { position: absolute; inset: auto 0 0; padding: clamp(1.2rem, 3vw, 2rem); display: grid; gap: .3rem; }
.badge { @include eyebrow(var(--night), sm); justify-self: start; margin-bottom: .6rem; padding: .3rem .65rem; border-radius: 999px; background: var(--amber); }
h3 { @include display(clamp(2rem, 3.6vw, 3rem)); margin: 0; }
.meta { margin: 0; color: rgb(255 255 255 / .82); }
.more { margin-top: .6rem; font-weight: 700; display: inline-flex; gap: .4rem; }
.arrow { transition: transform $dur-md $ease-enter; }
.card:hover .arrow { transform: translateX(4px); }
</style>
