<script setup lang="ts">
import type { Photo, StoryMoment } from '~/composables/useApi'

// "Um dia aqui": rolar a página é passar o dia na pousada. O céu de fundo muda de cor
// conforme o momento que está no meio da tela, e um sol (que vira lua) percorre um arco.
const props = defineProps<{ moments: StoryMoment[], fallbackPhotos: Photo[] }>()

const persona = usePersona()
const root = ref<HTMLElement>()
const panels = ref<HTMLElement[]>([])
const active = ref(0)

const textFor = (m: StoryMoment) => (persona.value === 'casal' && m.couple) || (persona.value === 'familia' && m.family) || m.text
const imageFor = (m: StoryMoment, i: number) => m.image ?? props.fallbackPhotos[i % Math.max(props.fallbackPhotos.length, 1)]?.url

// Céus por momento: manhã clara, tarde quente, noite funda. Do 4º momento em diante repete a noite.
const skyFor = (i: number) => (['dawn', 'day', 'night'] as const)[Math.min(i, 2)]
const isNight = (i: number) => skyFor(i) === 'night'

let ticking = false

// Movimento (tempos em assets/scss/_motion.scss): cada momento entra quando o texto cruza a
// linha áurea da tela (61,8% da altura, a partir do topo); a foto tem a sua própria deixa,
// porque no celular ela fica abaixo do texto. Uma vez dentro, fica dentro.
const GOLDEN_LINE = 0.618
const copyIn = ref<boolean[]>([])
const mediaIn = ref<boolean[]>([])
const parts = new WeakMap<HTMLElement, { copy: HTMLElement | null, media: HTMLElement | null, m: string }>()
const partsOf = (panel: HTMLElement) => {
  let p = parts.get(panel)
  if (!p) parts.set(panel, p = { copy: panel.querySelector('.copy'), media: panel.querySelector('.media'), m: '' })
  return p
}

// A cada quadro de rolagem: o momento ativo é o último cujo topo já passou do meio da tela,
// e o progresso de 0 a 1 da seção vira o ângulo do sol no arco.
// Por momento, --m (de -1 a 1: distância do centro do painel ao centro da tela, em alturas de tela)
// alimenta a profundidade da foto.
function onScroll() {
  if (ticking || !root.value) return
  ticking = true
  requestAnimationFrame(() => {
    ticking = false
    const el = root.value
    if (!el) return
    const vh = window.innerHeight
    const rect = el.getBoundingClientRect()
    const span = rect.height - vh
    const p = span > 0 ? Math.min(1, Math.max(0, -rect.top / span)) : 0
    const middle = vh / 2
    const line = vh * GOLDEN_LINE
    let current = 0
    // Primeiro todas as leituras…
    const frames = panels.value.map((panel, k) => {
      const i = Number(panel.dataset.i ?? k)
      const r = panel.getBoundingClientRect()
      if (r.top < middle) current = Math.max(current, i)
      const part = partsOf(panel)
      const m = Math.max(-1, Math.min(1, (r.top + r.height / 2 - middle) / vh)).toFixed(3)
      const copy = !copyIn.value[i] && (part.copy?.getBoundingClientRect().top ?? r.top) < line
      const media = !mediaIn.value[i] && (part.media?.getBoundingClientRect().top ?? r.top) < line
      return { panel, part, i, m, copy, media }
    })
    // …depois as escritas, para não forçar o navegador a recalcular o layout.
    el.style.setProperty('--p', p.toFixed(4))
    for (const f of frames) {
      if (f.m !== f.part.m) f.panel.style.setProperty('--m', f.part.m = f.m)
      if (f.copy) copyIn.value[f.i] = true
      if (f.media) mediaIn.value[f.i] = true
    }
    active.value = current
  })
}

onMounted(() => {
  onScroll()
  window.addEventListener('scroll', onScroll, { passive: true })
  window.addEventListener('resize', onScroll, { passive: true })
})
onBeforeUnmount(() => {
  window.removeEventListener('scroll', onScroll)
  window.removeEventListener('resize', onScroll)
})
</script>

<template>
  <section id="um-dia" ref="root" class="story" :class="`sky-${skyFor(active)}`">
    <div class="sky" aria-hidden="true">
      <span class="layer l-dawn" />
      <span class="layer l-day" />
      <span class="layer l-night" />
      <span class="stars" />
    </div>

    <div class="dial-track" aria-hidden="true">
      <div class="dial">
        <svg viewBox="0 0 120 64" class="arc"><path d="M8 60 A52 52 0 0 1 112 60" /></svg>
        <span class="arm"><span class="body" /></span>
        <Transition name="tick" mode="out-in">
          <span :key="active" class="clock">{{ moments[active]?.time }}</span>
        </Transition>
      </div>
    </div>

    <header class="intro container">
      <p v-reveal class="eyebrow">Um dia aqui</p>
      <h2 v-reveal style="--i: 1">Do café da manhã<br>à fogueira</h2>
      <p v-if="persona" v-reveal class="for" style="--i: 2">
        Contado para quem vem {{ persona === 'casal' ? 'a dois' : 'com a família' }}.
        <button type="button" @click="persona = persona === 'casal' ? 'familia' : 'casal'">Ver {{ persona === 'casal' ? 'com a família' : 'a dois' }}</button>
      </p>
    </header>

    <article
      v-for="(m, i) in moments"
      :key="m.title"
      ref="panels"
      :data-i="i"
      class="moment container"
      :class="{ night: isNight(i), flip: i % 2 === 1, on: i === active, in: copyIn[i], 'media-in': mediaIn[i] }"
    >
      <div class="copy">
        <p v-reveal class="when"><span class="time">{{ m.time }}</span> {{ m.period }}</p>
        <h3 v-reveal style="--i: 1">{{ m.title }}</h3>
        <div v-reveal style="--i: 2">
          <Transition name="swap" mode="out-in">
            <p :key="textFor(m)" class="text">{{ textFor(m) }}</p>
          </Transition>
        </div>
      </div>
      <figure v-if="imageFor(m, i)" v-reveal="'wipe'" class="media" style="--i: 1">
        <img :src="imageFor(m, i)" :alt="`${m.period}: ${m.title}`" loading="lazy" decoding="async">
      </figure>
    </article>

    <div class="outro container" :class="{ night: isNight(moments.length - 1) }">
      <slot />
    </div>
  </section>
</template>

<style scoped lang="scss">
// ── Coreografia de "Um dia aqui" (tempos, curvas e distâncias em assets/scss/_motion.scss) ──
// Céu: fusão entre camadas em t(5) com a curva simétrica; o anoitecer é mais lento, t(6), e as
//   estrelas acendem só depois, com atraso de t(3). Voltar da noite é mais rápido (t(5), estrelas t(2)).
// Relógio de sol: o braço segue o progresso da seção (--p) e alisa os saltos da roda do mouse em
//   t(1). O sol vira lua por fusão de duas camadas: o sol encolhe para 1/φ e a lua gira o ângulo
//   áureo (137,5°) até o lugar. A cor do traço troca de uma vez, no meio da fusão do céu.
// Momentos: quando o texto cruza a linha áurea da tela, entram hora/período (0) → título (+75ms)
//   → texto (+150ms) → cortina da foto (+225ms no desktop; no celular a foto tem deixa própria).
//   A foto, já um pouco ampliada (1.056), assenta em 1.034 por t(6) enquanto a cortina sobe (t(5)),
//   e desliza até ±6px conforme o painel atravessa a tela (profundidade, só translate).
// Cores dos três céus. A troca é uma fusão de opacidade entre camadas fixas (nada repinta).
.story {
  --ink-day: var(--night);
  position: relative; isolation: isolate; color: var(--ink-day);
  overflow: clip; // o céu preso não vaza sobre o rodapé (clip, e não hidden, para o sticky continuar valendo)
  --p: 0;
}
.sky { position: sticky; top: 0; height: 100vh; height: 100lvh; margin-bottom: -100vh; margin-bottom: -100lvh; z-index: -1; overflow: hidden; }
.layer, .stars { position: absolute; inset: 0; opacity: 0; transition: opacity $dur-2xl $ease-move; }
.stars { transition: opacity $dur-md $ease-exit; }
.l-dawn { background: linear-gradient(180deg, #f6efe4 0%, #f3e3cf 55%, #eed6bd 100%); }
.l-day { background: linear-gradient(180deg, #f4e6cf 0%, #ecdcc0 45%, #cfe2da 100%); }
.l-night { background: radial-gradient(120% 70% at 70% 100%, #3a2a1a 0%, rgb(58 42 26 / 0) 55%), linear-gradient(180deg, #0c1416 0%, #142124 60%, #1b2a2b 100%); }
.stars {
  background-image:
    radial-gradient(1px 1px at 12% 18%, #fff 50%, transparent 51%), radial-gradient(1px 1px at 32% 8%, #fff 50%, transparent 51%),
    radial-gradient(1.5px 1.5px at 58% 22%, #fff 50%, transparent 51%), radial-gradient(1px 1px at 78% 12%, #fff 50%, transparent 51%),
    radial-gradient(1px 1px at 88% 30%, #fff 50%, transparent 51%), radial-gradient(1.5px 1.5px at 22% 36%, #fff 50%, transparent 51%),
    radial-gradient(1px 1px at 46% 40%, #fff 50%, transparent 51%), radial-gradient(1px 1px at 66% 6%, #fff 50%, transparent 51%);
}
.sky-dawn .l-dawn, .sky-day .l-day, .sky-night .l-night { opacity: 1; }
.sky-night .l-night { transition-duration: $dur-3xl; }
.sky-night .stars { opacity: .55; transition: opacity $dur-3xl $ease-enter $dur-lg; }

// Relógio de sol: fica preso no canto enquanto a seção passa; o braço gira de -90° a 90°.
.dial-track { position: sticky; top: 76px; height: 0; z-index: 2; pointer-events: none; }
.dial { position: absolute; right: var(--gutter-r); top: 0; width: 92px; height: 64px; }
.arc { position: absolute; inset: 0; width: 100%; height: auto; overflow: visible;
  path { fill: none; stroke: currentColor; stroke-opacity: .28; stroke-width: 1.2; stroke-dasharray: 2 4; }
}
.arm { position: absolute; left: 50%; bottom: 4px; width: 0; height: 40px; transform-origin: 50% 100%; transform: rotate(calc(-90deg + var(--p) * 180deg)); transition: transform $dur-sm $ease-enter; }
.body { position: absolute; top: -7px; left: -7px; width: 14px; height: 14px; }
.body::before, .body::after { content: ""; position: absolute; inset: 0; border-radius: 50%; transition: opacity $dur-2xl $ease-move, transform $dur-2xl $ease-enter; }
.body::before { background: var(--amber); box-shadow: 0 0 18px 4px rgb(233 183 107 / .45); }
.body::after { background: #f1efe6; box-shadow: 0 0 16px 3px rgb(241 239 230 / .35), inset -4px -2px 0 0 #c9c6b8; opacity: 0; transform: scale(.618) rotate(-137.5deg); }
.sky-night .body::before { opacity: 0; transform: scale(.618); }
.sky-night .body::after { opacity: 1; transform: none; }
.clock { position: absolute; left: 0; right: 0; bottom: -1.2rem; text-align: center; font-size: .75rem; font-weight: 700; letter-spacing: .14em; font-variant-numeric: tabular-nums; }
.tick-enter-active { transition: opacity $dur-lg $ease-enter, transform $dur-lg $ease-enter; }
.tick-leave-active { transition: opacity $dur-md $ease-exit, transform $dur-md $ease-exit; }
.tick-enter-from { opacity: 0; transform: translateY($dist-xs); }
.tick-leave-to { opacity: 0; transform: translateY(-$dist-xs); }
// Cor em degrau único no meio da fusão do céu (quando o contraste se inverte), não animada.
.dial, .clock { color: var(--ink-day); transition: color 0s linear $dur-2xl * .5; }
.sky-night .dial, .sky-night .clock { color: #f3f1ea; transition-delay: $dur-3xl * .5; }

.intro { padding-top: clamp(6rem, 14vw, 10rem); padding-bottom: clamp(2rem, 6vw, 4rem); }
.intro h2 { @include display(clamp(2.8rem, 8vw, 6.4rem)); line-height: .98; margin: .6rem 0 0; max-width: 12ch; }
.for { margin: 1.4rem 0 0; color: var(--muted); display: flex; flex-wrap: wrap; gap: .3rem .8rem; align-items: baseline; }
.for button { font: inherit; font-weight: 700; color: var(--brand); background: none; border: 0; padding: 0; cursor: pointer; text-decoration: underline; text-underline-offset: 3px; }

.moment {
  min-height: 100vh; min-height: 100svh; display: grid; align-items: center; gap: clamp(1.5rem, 5vw, 4.5rem);
  padding-block: clamp(3rem, 8vw, 6rem);
  @include above(lg) { grid-template-columns: 5fr 6fr; &.flip { grid-template-columns: 6fr 5fr; .copy { order: 2; } } }
}
.moment { --wipe: #f3e3cf; }
.moment.flip { --wipe: #ecdcc0; }
.moment.night { --wipe: #142124; color: #f3f1ea; .when { color: var(--amber); } .text { color: rgb(243 241 234 / .78); } }
.when { margin: 0; @include eyebrow(var(--brand)); display: flex; align-items: baseline; gap: .7rem; }
.time { @include display(clamp(3.2rem, 8vw, 5.5rem)); letter-spacing: -.02em; text-transform: none; color: inherit; line-height: 1; }
.copy h3 { @include display(clamp(2.2rem, 5vw, 3.8rem)); line-height: 1.02; margin: .8rem 0 0; }
.text { margin: 1.1rem 0 0; font-size: clamp(1.05rem, 1.6vw, 1.2rem); line-height: 1.6; color: var(--muted); max-width: 36ch; text-wrap: pretty; }
.media { margin: 0; border-radius: 8px; overflow: hidden; aspect-ratio: 4 / 5; max-width: 100%; box-shadow: 0 40px 80px -40px rgb(10 14 12 / .45);
  @include above(lg) { aspect-ratio: 5 / 6; }
  // scale e translate são propriedades separadas: o assentar tem transição, a profundidade
  // muda a cada quadro sem transição. A folga de 1.034 cobre os ±6px (foto ≥ 360px de altura).
  img { width: 100%; height: 100%; object-fit: cover; scale: $zoom-md; translate: 0 calc(var(--m, 0) * #{-$dist-xs}); transition: scale $dur-3xl $ease-enter; }
}
.moment.media-in .media img { scale: $zoom-sm; }

// Entrada dos momentos: substitui a deixa do v-reveal (borda da tela) pela linha áurea.
// Sem JavaScript não existe .has-reveal, então tudo fica visível.
.has-reveal .moment:not(.in) .copy .reveal { opacity: 0; transform: translate3d(0, $dist-lg, 0); }
.has-reveal .moment.in .copy .reveal { opacity: 1; transform: none; }
.has-reveal .moment:not(.media-in) .media::after { transform: none; }
.has-reveal .moment.media-in .media::after { transform: scaleY(0); transition-delay: 0s; }
@include above(lg) { .has-reveal .moment.media-in .media::after { transition-delay: 3 * $stagger; } }

// Troca de texto ao mudar quem vem: vira a página — sai subindo (t(2)), entra subindo (t(3)).
.swap-enter-active { transition: opacity $dur-lg $ease-enter, transform $dur-lg $ease-enter; }
.swap-leave-active { transition: opacity $dur-md $ease-exit, transform $dur-md $ease-exit; }
.swap-enter-from { opacity: 0; transform: translateY($dist-xs); }
.swap-leave-to { opacity: 0; transform: translateY(-$dist-xs); }

.outro { padding-block: clamp(2rem, 6vw, 4rem) clamp(5rem, 10vw, 8rem); }
.outro.night { color: #f3f1ea; }

@include below(md) {
  .dial { width: 64px; height: 46px; }
  .arm { height: 28px; }
  .dial-track { top: 70px; }
  .moment { min-height: auto; padding-block: 3.5rem; }
}

@include reduced-motion {
  .arm { transform: rotate(0deg); transition: none; }
  .media img, .moment.media-in .media img { scale: 1; translate: 0 0; transition: none; }
  .layer, .stars { transition-duration: $dur-sm; }
  .body::before, .body::after { transform: none; }
  .has-reveal .moment .copy .reveal { opacity: 1; transform: none; }
  .has-reveal .moment .media::after { display: none; }
  .swap-enter-from, .swap-leave-to { transform: none; }
}
</style>
