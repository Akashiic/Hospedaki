<script setup lang="ts">
import type { Photo } from '~/composables/useApi'

// Capa em tela cheia: fotos em fusão lenta com leve zoom (efeito Ken Burns) e título que sobe palavra por palavra.
const props = defineProps<{ slides: Photo[], title: string, subtitle: string | null, place: string | null }>()
const emit = defineEmits<{ book: [], explore: [] }>()

// Cada foto fica t(8) = 5635ms sozinha + t(5) = 1330ms de fusão ≈ 7s por foto (escala em assets/scss/_motion.scss).
const DURATION = 5635 + 1330
const current = ref(0)
const previous = ref(-1)
// Passa sozinho só com a aba visível, sem movimento reduzido e sem o visitante ter pausado.
// O botão de pausa liga e desliga `autoplay`; o movimento reduzido só muda o valor inicial.
const autoplay = ref(true)
const visible = ref(true)
const playing = computed(() => autoplay.value && visible.value)
let timer: ReturnType<typeof setTimeout> | undefined

const words = computed(() => props.title.split(' '))
const pad = (n: number) => String(n).padStart(2, '0')

// Troca feita pelo visitante (toque nas barras ou deslizar) é anunciada; a automática, não.
const byUser = ref(false)
function go(index: number, user = false) {
  byUser.value = user
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
  visible.value = document.visibilityState === 'visible'
  schedule()
}

function toggleAutoplay() {
  autoplay.value = !autoplay.value
  schedule()
}

onMounted(() => {
  if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) autoplay.value = false
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
  if (Math.abs(dx) > 50) go(current.value + (dx < 0 ? 1 : -1), true)
}
</script>

<template>
  <section id="topo" class="hero" :style="{ '--duration': `${DURATION}ms`, '--words': words.length }" @touchstart.passive="onTouchStart" @touchend.passive="onTouchEnd">
    <!-- Só anuncia a troca de foto quando foi o visitante que trocou (com a passagem automática, calado). -->
    <div class="slides" :aria-live="playing && !byUser ? 'off' : 'polite'">
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
        <slot name="actions">
          <button type="button" class="btn btn-light" @click="emit('book')">Ver datas e reservar <span class="arrow">→</span></button>
          <button type="button" class="btn btn-outline-light" @click="emit('explore')">Conhecer o lugar</button>
        </slot>
      </div>
    </div>

    <div v-if="slides.length > 1" class="controls">
      <p class="caption">
        <span class="num">{{ pad(current + 1) }} / {{ pad(slides.length) }}</span>
        <Transition name="cap" mode="out-in"><span :key="current">{{ slides[current]?.caption }}</span></Transition>
      </p>
      <div class="track">
        <button type="button" class="pause" :aria-label="autoplay ? 'Pausar fotos' : 'Passar fotos sozinho'" @click="toggleAutoplay">
          <svg v-if="autoplay" viewBox="0 0 12 12" width="12" height="12" aria-hidden="true"><path fill="currentColor" d="M2 1h3v10H2zM7 1h3v10H7z" /></svg>
          <svg v-else viewBox="0 0 12 12" width="12" height="12" aria-hidden="true"><path fill="currentColor" d="M2.5 1 11 6l-8.5 5z" /></svg>
        </button>
        <div class="bars">
          <button
            v-for="(slide, i) in slides"
            :key="i"
            type="button"
            :class="{ on: i === current, done: i < current, paused: !playing }"
            :aria-label="`Foto ${i + 1}: ${slide.caption ?? ''}`"
            :aria-current="i === current ? 'true' : undefined"
            @click="go(i, true)"
          ><span :key="`${current}-${i}`" /></button>
        </div>
      </div>
    </div>

    <button type="button" class="scroll" aria-label="Rolar para conhecer" @click="emit('explore')"><span /></button>
  </section>
</template>

<style scoped lang="scss">
// Coreografia da capa (tempos e curvas em assets/scss/_motion.scss):
//   lugar (0) → palavras do título (1…n, uma por passo de 75ms) → subtítulo (n+1) → ações/portas (n+1 ou n+2).
//   n é limitado a 4 para a entrada caber em ~1s. As fotos fundem em t(5) com a curva
//   simétrica e derivam em zoom linear (Ken Burns) durante todo o tempo em que estão à vista.
.hero { --focus: var(--focus-dark); position: relative; height: 100svh; min-height: 620px; max-height: 1100px; overflow: hidden; background: var(--night); color: #fff; display: flex; align-items: flex-end; isolation: isolate; --n: min(var(--words, 3), 4); }
.slides, .slide, .veil, .grain { position: absolute; inset: 0; margin: 0; }
.slide { opacity: 0; transition: opacity $dur-2xl $ease-move; }
.slide.active { opacity: 1; z-index: 1; }
.slide img { width: 100%; height: 100%; object-fit: cover; object-position: 62% 50%; transform: scale($zoom-lg); }
// A foto que sai continua o mesmo zoom enquanto some (mesma animação em .prev, então não reinicia nem salta).
.slide.active img, .slide.prev img { animation: kenburns calc(var(--duration) + #{$dur-2xl}) $ease-drift forwards; }
.slide:nth-child(2n) img { --kx: -1%; }
@keyframes kenburns { from { transform: scale($zoom-lg) translate3d(var(--kx, 1%), 1%, 0); } to { transform: scale(1) translate3d(0, 0, 0); } }
// Véu: claro no alto (a foto respira) e escuro onde o texto fica. No celular o texto ocupa
// do meio para baixo; no computador fica à esquerda, então um segundo véu escurece só esse lado.
.veil { z-index: 2; background: linear-gradient(180deg, rgb(8 12 10 / .55) 0%, rgb(8 12 10 / .2) 20%, rgb(8 12 10 / .62) 42%, rgb(8 12 10 / .88) 100%); }
@include above(md) {
  .veil { background: linear-gradient(90deg, rgb(8 12 10 / .62) 0%, rgb(8 12 10 / .38) 45%, rgb(8 12 10 / 0) 75%), linear-gradient(180deg, rgb(8 12 10 / .55) 0%, rgb(8 12 10 / .08) 26%, rgb(8 12 10 / .3) 50%, rgb(8 12 10 / .85) 100%); }
}
// Granulação discreta, sem modo de mistura (que obrigaria a recompor a tela a cada quadro do zoom).
.grain { z-index: 3; opacity: .035; pointer-events: none; background-image: url("data:image/svg+xml;utf8,<svg xmlns='http://www.w3.org/2000/svg' width='160' height='160'><filter id='n'><feTurbulence type='fractalNoise' baseFrequency='.9' numOctaves='2' stitchTiles='stitch'/></filter><rect width='100%' height='100%' filter='url(%23n)'/></svg>"); }
.content { position: relative; z-index: 4; width: 100%; max-width: 1200px; margin: 0 auto; padding-inline: var(--gutter) var(--gutter-r); padding-bottom: clamp(7rem, 16vh, 10rem); }
// Branco, e não âmbar: é a primeira linha, na parte mais clara do véu.
.place { @include eyebrow(var(--on-photo)); display: block; margin: 0 0 1rem; text-shadow: 0 1px 12px rgb(0 0 0 / .45); @include motion-entrance(0); }
// O título encolhe também pela altura da tela, para caber junto com as escolhas em notebooks baixos.
h1 { margin: 0; font-size: clamp(2.8rem, min(9vw, 10.5vh), 7.2rem); line-height: .98; max-width: 15ch; text-wrap: balance; }
.word { display: inline-block; overflow: hidden; vertical-align: top; padding-bottom: .08em; }
// Só transform (sem opacidade): o título costuma ser o maior texto da tela e não pode atrasar o LCP.
.word > span { display: inline-block; animation: rise $dur-xl $ease-enter both; animation-delay: calc((min(var(--i), 3) + 2) * #{$stagger}); }
.sub { max-width: 34rem; font-size: clamp(1rem, 1.6vw, 1.15rem); color: rgb(255 255 255 / .85); margin: 1.4rem 0 0; @include motion-entrance; animation-delay: calc((var(--n) + 2) * #{$stagger}); }
// Ações: o contêiner não anima; cada filho entra no seu passo a partir de --enter-at
// (logo após o título, ou após o subtítulo quando houver). As portas (SiteDoors) leem
// --enter-at para escalonar a pergunta e os dois botões por dentro.
.actions { display: flex; flex-wrap: wrap; gap: .8rem; margin-top: 2rem; --enter-at: calc((var(--n) + 2) * #{$stagger}); }
.sub + .actions { --enter-at: calc((var(--n) + 3) * #{$stagger}); }
.actions > .btn { @include motion-entrance; animation-delay: var(--enter-at); }
.actions > .btn + .btn { animation-delay: calc(var(--enter-at) + #{$stagger}); }
@keyframes rise { from { transform: translateY(105%); } to { transform: none; } }

.controls { position: absolute; z-index: 4; right: var(--gutter-r); bottom: 1.2rem; display: grid; gap: .2rem; justify-items: end; width: min(320px, 60vw); @include motion-entrance; animation-delay: calc((var(--n) + 5) * #{$stagger}); }
.caption { margin: 0; display: flex; gap: .8rem; align-items: baseline; font-size: .85rem; color: rgb(255 255 255 / .85); }
.num { font-variant-numeric: tabular-nums; letter-spacing: .1em; color: rgb(255 255 255 / .6); }
.track { display: flex; align-items: center; gap: .4rem; width: 100%; }
.pause { flex: none; display: grid; place-items: center; width: 44px; height: 44px; margin-left: -14px; border: 0; background: none; color: #fff; cursor: pointer; opacity: .85; transition: opacity $dur-sm $ease-move; }
.pause:hover { opacity: 1; }
.bars { flex: 1; display: grid; grid-auto-flow: column; grid-auto-columns: 1fr; gap: .4rem; }
// Linha de 2px, mas o botão tem 44px de altura para o dedo.
.bars button { height: 44px; border: 0; background: none; padding: 21px 0; cursor: pointer; }
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
  .controls { left: var(--gutter); right: var(--gutter-r); width: auto; justify-items: start; bottom: .6rem; }
  .scroll { display: none; }
  .grain { display: none; }
}

// Movimento reduzido: fotos paradas e sem zoom, trocas instantâneas, texto já no lugar.
@include reduced-motion {
  .slide.active img, .slide.prev img { animation: none; }
  .slide img { transform: none; }
  .place, .word > span, .sub, .actions > .btn, .controls { animation: none; }
  .scroll span { animation: none; opacity: 1; }
}
</style>
