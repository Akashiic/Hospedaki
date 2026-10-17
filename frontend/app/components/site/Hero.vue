<script setup lang="ts">
import type { Photo } from '~/composables/useApi'

// Capa em tela cheia: fotos em fusão lenta com leve zoom (efeito Ken Burns) e título que sobe palavra por palavra.
const props = defineProps<{ slides: Photo[], title: string, subtitle: string | null, place: string | null }>()
const emit = defineEmits<{ book: [], explore: [] }>()

// Cada foto fica t(8) = 5635ms sozinha + t(5) = 1330ms de fusão ≈ 7s por foto (escala em assets/scss/_motion.scss).
const DURATION = 5635 + 1330
const current = ref(0)
const previous = ref(-1)
const playing = ref(true)
let timer: ReturnType<typeof setTimeout> | undefined

const words = computed(() => props.title.split(' '))
const pad = (n: number) => String(n).padStart(2, '0')

function go(index: number) {
  if (index === current.value) return schedule()
  previous.value = current.value
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
  <section id="topo" class="hero" :style="{ '--duration': `${DURATION}ms`, '--words': words.length }" @touchstart.passive="onTouchStart" @touchend.passive="onTouchEnd">
    <div class="slides" aria-live="polite">
      <figure v-for="(slide, i) in slides" :key="slide.url" class="slide" :class="{ active: i === current, prev: i === previous }" :aria-hidden="i !== current">
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
        <template v-for="(word, i) in words" :key="i"><span class="word"><span :style="{ '--i': i }">{{ word }}</span></span>{{ i < words.length - 1 ? ' ' : '' }}</template>
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

<style scoped lang="scss">
// Coreografia da capa (tempos e curvas em assets/scss/_motion.scss):
//   lugar (0) → palavras do título (1…n, uma por passo de 75ms) → subtítulo (n+1) → botões (n+2).
//   n é limitado a 4 para a entrada caber em ~1s. As fotos fundem em t(5) com a curva
//   simétrica e derivam em zoom linear (Ken Burns) durante todo o tempo em que estão à vista.
.hero { position: relative; height: 100svh; min-height: 620px; max-height: 1100px; overflow: hidden; background: var(--night); color: #fff; display: flex; align-items: flex-end; isolation: isolate; --n: min(var(--words, 3), 4); }
.slides, .slide, .veil, .grain { position: absolute; inset: 0; margin: 0; }
.slide { opacity: 0; transition: opacity $dur-2xl $ease-move; }
.slide.active { opacity: 1; z-index: 1; }
.slide img { width: 100%; height: 100%; object-fit: cover; object-position: 62% 50%; transform: scale($zoom-lg); }
// A foto que sai continua o mesmo zoom enquanto some (mesma animação em .prev, então não reinicia nem salta).
.slide.active img, .slide.prev img { animation: kenburns calc(var(--duration) + #{$dur-2xl}) $ease-drift forwards; }
.slide:nth-child(2n) img { --kx: -1%; }
@keyframes kenburns { from { transform: scale($zoom-lg) translate3d(var(--kx, 1%), 1%, 0); } to { transform: scale(1) translate3d(0, 0, 0); } }
.veil { z-index: 2; background: linear-gradient(180deg, rgb(8 12 10 / .55) 0%, rgb(8 12 10 / 0) 28%, rgb(8 12 10 / .15) 55%, rgb(8 12 10 / .78) 100%); }
.grain { z-index: 3; opacity: .08; pointer-events: none; mix-blend-mode: overlay; background-image: url("data:image/svg+xml;utf8,<svg xmlns='http://www.w3.org/2000/svg' width='160' height='160'><filter id='n'><feTurbulence type='fractalNoise' baseFrequency='.9' numOctaves='2' stitchTiles='stitch'/></filter><rect width='100%' height='100%' filter='url(%23n)'/></svg>"); }
.content { position: relative; z-index: 4; width: 100%; max-width: 1200px; margin: 0 auto; padding-inline: var(--gutter) var(--gutter-r); padding-bottom: clamp(7rem, 16vh, 10rem); }
.place { margin: 0 0 1rem; text-transform: uppercase; letter-spacing: .24em; font-size: .75rem; font-weight: 700; color: var(--amber); text-shadow: 0 1px 12px rgb(0 0 0 / .45); @include motion-entrance(0); }
h1 { margin: 0; font-size: clamp(3rem, 9vw, 7.2rem); line-height: .98; max-width: 12ch; }
.word { display: inline-block; overflow: hidden; vertical-align: top; padding-bottom: .08em; }
// Só transform (sem opacidade): o título costuma ser o maior texto da tela e não pode atrasar o LCP.
.word > span { display: inline-block; animation: rise $dur-xl $ease-enter both; animation-delay: calc((min(var(--i), 3) + 2) * #{$stagger}); }
.sub { max-width: 34rem; font-size: clamp(1rem, 1.6vw, 1.15rem); color: rgb(255 255 255 / .85); margin: 1.4rem 0 0; @include motion-entrance; animation-delay: calc((var(--n) + 2) * #{$stagger}); }
.actions { display: flex; flex-wrap: wrap; gap: .8rem; margin-top: 2rem; @include motion-entrance; animation-delay: calc((var(--n) + 3) * #{$stagger}); }
@keyframes rise { from { transform: translateY(105%); } to { transform: none; } }

.controls { position: absolute; z-index: 4; right: var(--gutter-r); bottom: 2.2rem; display: grid; gap: .7rem; justify-items: end; width: min(320px, 60vw); @include motion-entrance; animation-delay: calc((var(--n) + 4) * #{$stagger}); }
.caption { margin: 0; display: flex; gap: .8rem; align-items: baseline; font-size: .85rem; color: rgb(255 255 255 / .85); }
.num { font-variant-numeric: tabular-nums; letter-spacing: .1em; color: rgb(255 255 255 / .6); }
.bars { display: grid; grid-auto-flow: column; grid-auto-columns: 1fr; gap: .4rem; width: 100%; }
.bars button { height: 22px; border: 0; background: none; padding: 10px 0; cursor: pointer; }
.bars button span { display: block; height: 2px; background: rgb(255 255 255 / .28); position: relative; overflow: hidden; }
.bars button span::after { content: ""; position: absolute; inset: 0; background: #fff; transform: scaleX(0); transform-origin: left; }
.bars .done span::after { transform: scaleX(1); }
.bars .on span::after { animation: fill var(--duration) $ease-drift forwards; }
.bars .on.paused span::after { animation: none; transform: scaleX(1); }
@keyframes fill { to { transform: scaleX(1); } }
// Legenda: entra em t(3) subindo d(-1); sai um degrau abaixo, t(2), acelerando.
.cap-enter-active { transition: opacity $dur-lg $ease-enter, transform $dur-lg $ease-enter; }
.cap-leave-active { transition: opacity $dur-md $ease-exit, transform $dur-md $ease-exit; }
.cap-enter-from { opacity: 0; transform: translateY($dist-xs); }
.cap-leave-to { opacity: 0; transform: translateY(-$dist-xs); }

.scroll { position: absolute; z-index: 4; left: 50%; bottom: 1.6rem; transform: translateX(-50%); width: 26px; height: 42px; border-radius: 14px; border: 1.5px solid rgb(255 255 255 / .55); background: none; cursor: pointer; }
// Indicador de rolar: laço de t(6), começa depois que a entrada terminou.
.scroll span { position: absolute; left: 50%; top: 8px; width: 3px; height: 8px; margin-left: -1.5px; border-radius: 2px; background: #fff; opacity: 0; animation: cue $dur-3xl $ease-move $dur-2xl infinite; }
@keyframes cue { 0% { opacity: 0; transform: translateY(0); } 30% { opacity: 1; } 100% { opacity: 0; transform: translateY($dist-md); } }

@include below(md) {
  .content { padding-bottom: 9.5rem; }
  .controls { left: var(--gutter); right: var(--gutter-r); width: auto; justify-items: start; bottom: 1.6rem; }
  .scroll { display: none; }
}

// Movimento reduzido: fotos paradas e sem zoom, trocas instantâneas, texto já no lugar.
@include reduced-motion {
  .slide.active img, .slide.prev img { animation: none; }
  .slide img { transform: none; }
  .place, .word > span, .sub, .actions, .controls { animation: none; }
  .scroll span { animation: none; opacity: 1; }
}
</style>
