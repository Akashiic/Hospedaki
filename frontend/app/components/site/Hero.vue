<script setup lang="ts">
import type { Photo } from '~/composables/useApi'

// Capa em tela cheia: fotos em fusão lenta com leve zoom (efeito Ken Burns) e título que sobe palavra por palavra.
const props = defineProps<{ slides: Photo[], title: string, subtitle: string | null, place: string | null }>()
const emit = defineEmits<{ book: [], explore: [] }>()

const DURATION = 6500
const current = ref(0)
const playing = ref(true)
let timer: ReturnType<typeof setTimeout> | undefined

const words = computed(() => props.title.split(' '))
const pad = (n: number) => String(n).padStart(2, '0')

function go(index: number) {
  current.value = (index + props.slides.length) % props.slides.length
  schedule()
}

function schedule() {
  clearTimeout(timer)
  if (playing.value && props.slides.length > 1) timer = setTimeout(() => go(current.value + 1), DURATION)
}

function onVisibility() {
  playing.value = document.visibilityState === 'visible'
  schedule()
}

onMounted(() => {
  if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) playing.value = false
  schedule()
  document.addEventListener('visibilitychange', onVisibility)
})
onBeforeUnmount(() => {
  clearTimeout(timer)
  document.removeEventListener('visibilitychange', onVisibility)
})

// Deslizar com o dedo no celular.
let startX = 0
const onTouchStart = (e: TouchEvent) => { startX = e.touches[0]!.clientX }
function onTouchEnd(e: TouchEvent) {
  const dx = e.changedTouches[0]!.clientX - startX
  if (Math.abs(dx) > 50) go(current.value + (dx < 0 ? 1 : -1))
}
</script>

<template>
  <section id="topo" class="hero" :style="{ '--duration': `${DURATION}ms` }" @touchstart.passive="onTouchStart" @touchend.passive="onTouchEnd">
    <div class="slides" aria-live="polite">
      <figure v-for="(slide, i) in slides" :key="slide.url" class="slide" :class="{ active: i === current }" :aria-hidden="i !== current">
        <img
          :src="slide.url"
          :alt="slide.caption ?? ''"
          :loading="i === 0 ? 'eager' : 'lazy'"
          :fetchpriority="i === 0 ? 'high' : 'auto'"
          decoding="async"
        >
      </figure>
    </div>
    <div class="veil" />
    <div class="grain" />

    <div class="content">
      <p v-if="place" class="place">{{ place }}</p>
      <h1>
        <span v-for="(word, i) in words" :key="i" class="word"><span :style="{ '--i': i }">{{ word }}</span></span>
      </h1>
      <p v-if="subtitle" class="sub">{{ subtitle }}</p>
      <div class="actions">
        <button type="button" class="btn btn-light" @click="emit('book')">Ver datas e reservar <span class="arrow">→</span></button>
        <button type="button" class="btn btn-outline-light" @click="emit('explore')">Conhecer o lugar</button>
      </div>
    </div>

    <div v-if="slides.length > 1" class="controls">
      <p class="caption">
        <span class="num">{{ pad(current + 1) }} / {{ pad(slides.length) }}</span>
        <Transition name="cap" mode="out-in"><span :key="current">{{ slides[current]?.caption }}</span></Transition>
      </p>
      <div class="bars">
        <button
          v-for="(slide, i) in slides"
          :key="i"
          type="button"
          :class="{ on: i === current, done: i < current, paused: !playing }"
          :aria-label="`Foto ${i + 1}: ${slide.caption ?? ''}`"
          @click="go(i)"
        ><span :key="`${current}-${i}`" /></button>
      </div>
    </div>

    <button type="button" class="scroll" aria-label="Rolar para conhecer" @click="emit('explore')"><span /></button>
  </section>
</template>

<style scoped>
.hero { position: relative; height: 100svh; min-height: 620px; max-height: 1100px; overflow: hidden; background: var(--night); color: #fff; display: flex; align-items: flex-end; isolation: isolate; }
.slides, .slide, .veil, .grain { position: absolute; inset: 0; margin: 0; }
.slide { opacity: 0; transition: opacity 1.6s var(--ease); }
.slide.active { opacity: 1; z-index: 1; }
.slide img { width: 100%; height: 100%; object-fit: cover; object-position: 62% 50%; transform: scale(1.12); will-change: transform; }
.slide.active img { animation: kenburns calc(var(--duration) + 1600ms) linear forwards; }
@keyframes kenburns { from { transform: scale(1.12) translate3d(1%, 1%, 0); } to { transform: scale(1.0) translate3d(0, 0, 0); } }
.veil { z-index: 2; background: linear-gradient(180deg, rgb(8 12 10 / .55) 0%, rgb(8 12 10 / 0) 28%, rgb(8 12 10 / .15) 55%, rgb(8 12 10 / .78) 100%); }
.grain { z-index: 3; opacity: .08; pointer-events: none; mix-blend-mode: overlay; background-image: url("data:image/svg+xml;utf8,<svg xmlns='http://www.w3.org/2000/svg' width='160' height='160'><filter id='n'><feTurbulence type='fractalNoise' baseFrequency='.9' numOctaves='2' stitchTiles='stitch'/></filter><rect width='100%' height='100%' filter='url(%23n)'/></svg>"); }
.content { position: relative; z-index: 4; width: 100%; max-width: 1200px; margin: 0 auto; padding-inline: var(--gutter) var(--gutter-r); padding-bottom: clamp(7rem, 16vh, 10rem); }
.place { margin: 0 0 1rem; text-transform: uppercase; letter-spacing: .24em; font-size: .75rem; font-weight: 700; color: var(--amber); text-shadow: 0 1px 12px rgb(0 0 0 / .45); animation: fade 1s .05s var(--ease) both; }
h1 { margin: 0; font-size: clamp(3rem, 9vw, 7.2rem); line-height: .98; max-width: 12ch; }
.word { display: inline-block; overflow: hidden; vertical-align: top; padding-bottom: .08em; margin-right: .22em; }
.word > span { display: inline-block; animation: rise 1.1s var(--ease) both; animation-delay: calc(var(--i) * 70ms + 80ms); }
.sub { max-width: 34rem; font-size: clamp(1rem, 1.6vw, 1.15rem); color: rgb(255 255 255 / .85); margin: 1.4rem 0 0; animation: fade 1.2s .45s var(--ease) both; }
.actions { display: flex; flex-wrap: wrap; gap: .8rem; margin-top: 2rem; animation: fade 1.2s .55s var(--ease) both; }
@keyframes rise { from { transform: translateY(105%); } to { transform: none; } }
@keyframes fade { from { opacity: 0; transform: translateY(12px); } to { opacity: 1; transform: none; } }

.controls { position: absolute; z-index: 4; right: var(--gutter-r); bottom: 2.2rem; display: grid; gap: .7rem; justify-items: end; width: min(320px, 60vw); }
.caption { margin: 0; display: flex; gap: .8rem; align-items: baseline; font-size: .85rem; color: rgb(255 255 255 / .85); }
.num { font-variant-numeric: tabular-nums; letter-spacing: .1em; color: rgb(255 255 255 / .6); }
.bars { display: grid; grid-auto-flow: column; grid-auto-columns: 1fr; gap: .4rem; width: 100%; }
.bars button { height: 22px; border: 0; background: none; padding: 10px 0; cursor: pointer; }
.bars button span { display: block; height: 2px; background: rgb(255 255 255 / .28); position: relative; overflow: hidden; }
.bars button span::after { content: ""; position: absolute; inset: 0; background: #fff; transform: scaleX(0); transform-origin: left; }
.bars .done span::after { transform: scaleX(1); }
.bars .on span::after { animation: fill var(--duration) linear forwards; }
.bars .on.paused span::after { animation: none; transform: scaleX(1); }
@keyframes fill { to { transform: scaleX(1); } }
.cap-enter-active, .cap-leave-active { transition: opacity .5s, transform .5s var(--ease); }
.cap-enter-from { opacity: 0; transform: translateY(6px); }
.cap-leave-to { opacity: 0; transform: translateY(-6px); }

.scroll { position: absolute; z-index: 4; left: 50%; bottom: 1.6rem; transform: translateX(-50%); width: 26px; height: 42px; border-radius: 14px; border: 1.5px solid rgb(255 255 255 / .55); background: none; cursor: pointer; }
.scroll span { position: absolute; left: 50%; top: 8px; width: 3px; height: 8px; margin-left: -1.5px; border-radius: 2px; background: #fff; animation: cue 1.8s var(--ease) infinite; }
@keyframes cue { 0% { opacity: 0; transform: translateY(0); } 30% { opacity: 1; } 100% { opacity: 0; transform: translateY(14px); } }

@media (max-width: 720px) {
  .content { padding-bottom: 9.5rem; }
  .controls { left: var(--gutter); right: var(--gutter-r); width: auto; justify-items: start; bottom: 1.6rem; }
  .scroll { display: none; }
}
</style>
