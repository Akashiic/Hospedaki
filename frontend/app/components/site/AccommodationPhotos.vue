<script setup lang="ts">
import type { Swiper as SwiperInstance } from 'swiper'
import type { Photo } from '~/composables/useApi'
import { ChevronLeft, ChevronRight } from 'lucide-vue-next'
import { A11y, FreeMode, Keyboard, Thumbs } from 'swiper/modules'
import { Swiper, SwiperSlide } from 'swiper/vue'
import 'swiper/css'
import 'swiper/css/a11y'
import 'swiper/css/free-mode'
import 'swiper/css/thumbs'

// Fotos da página de uma acomodação, no layout escolhido em "Seu site → Páginas → Acomodações":
// grade (a primeira larga e as outras duas a duas) ou carrossel (Swiper) com miniaturas embaixo.
const props = defineProps<{ photos: Photo[], name: string, layout?: 'grid' | 'carousel' | null }>()

const carousel = computed(() => props.layout === 'carousel')
let main: SwiperInstance | null = null
const thumbs = shallowRef<SwiperInstance | null>(null)
const index = ref(0)
const pad = (n: number) => String(n).padStart(2, '0')

function onMain(instance: SwiperInstance) {
  main = instance
  if (prefersReducedMotion()) instance.params.speed = 0
}
</script>

<template>
  <section v-if="photos.length > 1" class="container photos" :aria-label="`Fotos de ${name}`">
    <template v-if="carousel">
      <div class="stage">
        <Swiper
          class="main"
          :modules="[A11y, Keyboard, Thumbs]"
          :speed="510"
          :space-between="12"
          :rewind="true"
          :grab-cursor="true"
          :keyboard="{ enabled: true, onlyInViewport: true }"
          :thumbs="{ swiper: thumbs, autoScrollOffset: 1 }"
          :a11y="carouselA11y(`Fotos de ${name}`)"
          @swiper="onMain"
          @slide-change="(s: SwiperInstance) => index = s.realIndex"
        >
          <SwiperSlide v-for="(photo, i) in photos" :key="photo.url">
            <figure>
              <img :src="photo.url" :alt="photo.caption ?? name" :loading="i === 0 ? 'eager' : 'lazy'" decoding="async" draggable="false">
            </figure>
          </SwiperSlide>
        </Swiper>
        <button type="button" class="nav prev" aria-label="Foto anterior" @click="main?.slidePrev()"><ChevronLeft :size="22" /></button>
        <button type="button" class="nav next" aria-label="Próxima foto" @click="main?.slideNext()"><ChevronRight :size="22" /></button>
        <p class="count" aria-hidden="true">{{ pad(index + 1) }} / {{ pad(photos.length) }}</p>
      </div>
      <p class="caption" aria-live="polite">{{ photos[index]?.caption ?? '' }}</p>
      <!-- Miniaturas: atalho para o mouse e o dedo (no teclado, as setas já passam as fotos acima). -->
      <Swiper
        class="thumbs"
        aria-hidden="true"
        :modules="[FreeMode, Thumbs]"
        slides-per-view="auto"
        :space-between="8"
        :free-mode="true"
        :watch-slides-progress="true"
        :slide-to-clicked-slide="true"
        @swiper="(s: SwiperInstance) => thumbs = s"
      >
        <SwiperSlide v-for="photo in photos" :key="photo.url" class="thumb">
          <img :src="photo.url"  alt="" loading="lazy" decoding="async" draggable="false">
        </SwiperSlide>
      </Swiper>
    </template>

    <div v-else class="grid">
      <figure v-for="(photo, i) in photos" :key="photo.url" v-reveal="'wipe'" :class="{ wide: i === 0 }" :style="{ '--i': i % 3 }">
        <img :src="photo.url" :alt="photo.caption ?? name" loading="lazy" decoding="async">
        <figcaption v-if="photo.caption">{{ photo.caption }}</figcaption>
      </figure>
    </div>
  </section>
</template>

<style scoped lang="scss">
.photos { padding-bottom: clamp(4rem, 8vw, 6rem); }

// ── Grade ──
.grid {
  display: grid; gap: 1rem; grid-template-columns: repeat(2, minmax(0, 1fr));
  @include below(md) { grid-template-columns: minmax(0, 1fr); }
  figure { margin: 0; border-radius: 6px; overflow: hidden; background: var(--night); position: relative; aspect-ratio: 4 / 3; }
  .wide { grid-column: 1 / -1; aspect-ratio: 16 / 8; @include below(md) { aspect-ratio: 4 / 3; } }
  img { width: 100%; height: 100%; object-fit: cover; display: block; }
  figcaption { position: absolute; left: 1rem; bottom: .8rem; color: #fff; font-size: .85rem; text-shadow: 0 1px 8px rgb(0 0 0 / .5); }
}

// ── Carrossel ──
.stage { position: relative; }
.main { border-radius: 8px; background: var(--night); }
.main figure { margin: 0; aspect-ratio: 16 / 9; @include below(md) { aspect-ratio: 4 / 3; } }
.main img { width: 100%; height: 100%; object-fit: cover; display: block; user-select: none; }
.nav {
  --focus: var(--focus-dark);
  position: absolute; z-index: 2; top: 50%; display: grid; place-items: center; width: 48px; height: 48px; margin-top: -24px; border: 0; border-radius: 50%;
  background: rgb(255 255 255 / .92); color: var(--text); cursor: pointer; box-shadow: 0 2px 12px rgb(0 0 0 / .22);
  transition: transform $dur-sm $ease-move;
  &:hover { transform: scale(1.06); }
}
.prev { left: 1rem; }
.next { right: 1rem; }
// Toque: arrasta com o dedo, sem setas por cima da foto.
@media (hover: none) { .nav { display: none; } }
.count {
  position: absolute; z-index: 2; right: 1rem; bottom: 1rem; margin: 0; padding: .3rem .65rem; border-radius: 999px;
  background: rgb(10 14 12 / .6); color: #fff; font-size: .8rem; font-variant-numeric: tabular-nums; letter-spacing: .08em;
}
.caption { min-height: 1.4em; margin: .7rem 0 .9rem; color: var(--muted); font-size: .92rem; }
.thumbs { margin: 0; }
.thumb { width: clamp(84px, 14vw, 132px); aspect-ratio: 4 / 3; border-radius: 6px; overflow: hidden; cursor: pointer; opacity: .5; transition: opacity $dur-sm $ease-move; }
.thumb:hover { opacity: .8; }
.thumb.swiper-slide-thumb-active { opacity: 1; }
.thumb img { width: 100%; height: 100%; object-fit: cover; display: block; user-select: none; }
@include reduced-motion { .nav, .thumb { transition: none; } }
</style>
