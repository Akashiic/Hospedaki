<script setup lang="ts">
import type { Swiper as SwiperInstance } from 'swiper'
import type { Photo } from '~/composables/useApi'
import { A11y, Keyboard, Mousewheel, Scrollbar } from 'swiper/modules'
import { Swiper, SwiperSlide } from 'swiper/vue'
import 'swiper/css'
import 'swiper/css/a11y'
import 'swiper/css/scrollbar'

// Faixa escura de cenas (Swiper): arrastar com o dedo ou o mouse, rolar de lado no trackpad,
// setas do teclado e, no computador, os botões. A barra embaixo mostra onde você está e também arrasta.
defineProps<{ photos: Photo[] }>()

let swiper: SwiperInstance | null = null
const atStart = ref(true)
const atEnd = ref(false)

function onSwiper(instance: SwiperInstance) {
  swiper = instance
  if (prefersReducedMotion()) instance.params.speed = 0
  onMove(instance)
}
function onMove(instance: SwiperInstance) {
  atStart.value = instance.isBeginning
  atEnd.value = instance.isEnd
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
        <button type="button" aria-label="Fotos anteriores" :disabled="atStart" @click="swiper?.slidePrev()">←</button>
        <button type="button" aria-label="Próximas fotos" :disabled="atEnd" @click="swiper?.slideNext()">→</button>
      </div>
    </div>
    <div class="container">
      <Swiper
        class="track"
        :modules="[A11y, Keyboard, Mousewheel, Scrollbar]"
        slides-per-view="auto"
        :space-between="16"
        :speed="510"
        :grab-cursor="true"
        :watch-slides-progress="true"
        :keyboard="{ enabled: true, onlyInViewport: true }"
        :mousewheel="{ forceToAxis: true }"
        :scrollbar="{ draggable: true, hide: false }"
        :a11y="carouselA11y('Cenas do lugar')"
        @swiper="onSwiper"
        @slide-change="onMove"
        @reach-beginning="onMove"
        @reach-end="onMove"
        @from-edge="onMove"
      >
        <SwiperSlide v-for="(photo, i) in photos" :key="photo.url" class="shot" :class="{ wide: i % 3 === 0 }">
          <figure v-reveal :style="{ '--i': Math.min(i, 3) }">
            <img :src="photo.url" :alt="photo.caption ?? ''" loading="lazy" decoding="async" draggable="false">
            <figcaption v-if="photo.caption">{{ photo.caption }}</figcaption>
          </figure>
        </SwiperSlide>
      </Swiper>
    </div>
  </section>
</template>

<style scoped lang="scss">
.gallery { --focus: var(--focus-dark); background: var(--night); color: var(--on-night); padding-block: clamp(5rem, 10vw, 8rem); overflow: hidden; }
.eyebrow { @include eyebrow(var(--amber)); }
.head { display: flex; justify-content: space-between; align-items: end; gap: 1rem; margin-bottom: 2.5rem; }
h2 { font-size: clamp(2.8rem, 7vw, 5.5rem); margin: .4rem 0 0; }
.arrows { display: flex; gap: .5rem; }
.arrows button { width: 52px; height: 52px; border-radius: 50%; border: 1px solid rgb(255 255 255 / .25); background: none; color: #fff; font-size: 1.1rem; cursor: pointer; transition: opacity $dur-sm $ease-move; }
// Hover troca fundo e borda na hora (cor não anima).
.arrows button:hover:not(:disabled) { background: rgb(255 255 255 / .08); border-color: rgb(255 255 255 / .5); }
.arrows button:disabled { opacity: .35; cursor: default; }
// A faixa começa alinhada ao texto e as fotos seguintes vazam para a direita, até a borda da tela;
// as que já passaram somem na margem da esquerda (recorte só desse lado).
.track {
  overflow: visible; clip-path: inset(-1rem -100vw -1rem 0); padding-bottom: 2.4rem;
  --swiper-scrollbar-bg-color: rgb(255 255 255 / .14);
  --swiper-scrollbar-drag-bg-color: rgb(255 255 255 / .7);
  --swiper-scrollbar-size: 3px;
  --swiper-scrollbar-sides-offset: 0px;
  --swiper-scrollbar-bottom: 0px;
}
.shot { width: min(78vw, 360px); height: auto; }
.shot.wide { width: min(88vw, 620px); }
figure { margin: 0; }
.shot img { width: 100%; height: clamp(300px, 46vh, 520px); object-fit: cover; border-radius: 4px; display: block; user-select: none; }
figcaption { margin-top: .8rem; color: var(--on-night-muted); font-size: .9rem; }
// A barra tem 3px, mas a área de arrastar é maior.
.track :deep(.swiper-scrollbar) { cursor: grab; }
.track :deep(.swiper-scrollbar::before) { content: ""; position: absolute; inset: -10px 0; }
@include below(md) { .arrows { display: none; } }
</style>
