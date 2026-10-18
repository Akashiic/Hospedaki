<script setup lang="ts">
import type { Photo } from '~/composables/useApi'

// Faixa escura de cenas que desliza na horizontal (arrastar no celular, setas no computador).
defineProps<{ photos: Photo[] }>()
const track = ref<HTMLElement | null>(null)

function slide(direction: 1 | -1) {
  const el = track.value
  if (!el) return
  el.scrollBy({ left: direction * el.clientWidth * 0.8, behavior: 'smooth' })
}
</script>

<template>
  <section class="gallery">
    <div class="container head">
      <div>
        <p v-reveal class="eyebrow">Galeria</p>
        <h2 v-reveal style="--i: 1">Cenas do lugar</h2>
      </div>
      <div class="arrows">
        <button type="button" aria-label="Anterior" @click="slide(-1)">←</button>
        <button type="button" aria-label="Próxima" @click="slide(1)">→</button>
      </div>
    </div>
    <div ref="track" class="track" tabindex="0" aria-label="Fotos da pousada">
      <figure v-for="(photo, i) in photos" :key="photo.url" v-reveal class="shot" :class="{ wide: i % 3 === 0 }" :style="{ '--i': Math.min(i, 3) }">
        <img :src="photo.url" :alt="photo.caption ?? ''" loading="lazy" decoding="async">
        <figcaption v-if="photo.caption">{{ photo.caption }}</figcaption>
      </figure>
    </div>
  </section>
</template>

<style scoped lang="scss">
.gallery { --focus: var(--focus-dark); background: var(--night); color: var(--on-night); padding-block: clamp(5rem, 10vw, 8rem); overflow: hidden; }
.eyebrow { @include eyebrow(var(--amber)); }
.head { display: flex; justify-content: space-between; align-items: end; gap: 1rem; margin-bottom: 2.5rem; }
h2 { font-size: clamp(2.8rem, 7vw, 5.5rem); margin: .4rem 0 0; }
.arrows { display: flex; gap: .5rem; }
.arrows button { width: 52px; height: 52px; border-radius: 50%; border: 1px solid rgb(255 255 255 / .25); background: none; color: #fff; font-size: 1.1rem; cursor: pointer; transition: background-color $dur-sm $ease-move, border-color $dur-sm $ease-move; }
.arrows button:hover { background: rgb(255 255 255 / .08); border-color: rgb(255 255 255 / .5); }
.track { display: flex; gap: 1rem; overflow-x: auto; scroll-snap-type: x mandatory; scrollbar-width: none; padding-inline: max(var(--gutter), calc((100vw - 1200px) / 2 + 16px)); scroll-padding-inline: max(var(--gutter), calc((100vw - 1200px) / 2 + 16px)); }
.track::-webkit-scrollbar { display: none; }
.shot { flex: 0 0 auto; margin: 0; scroll-snap-align: start; width: min(78vw, 360px); position: relative; }
.shot.wide { width: min(88vw, 620px); }
.shot img { width: 100%; height: clamp(300px, 46vh, 520px); object-fit: cover; border-radius: 4px; display: block; }
figcaption { margin-top: .8rem; color: var(--on-night-muted); font-size: .9rem; }
@include below(md) { .arrows { display: none; } }
</style>
