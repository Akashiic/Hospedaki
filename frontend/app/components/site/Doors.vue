<script setup lang="ts">
import type { Property } from '~/composables/useApi'
import { accommodationFor, type Persona } from '~/composables/usePersona'
import { formatRange } from '~/composables/useNextWeekend'

// "Duas portas" da capa: o visitante diz com quem vem e o resto da home se ajusta a ele.
const props = defineProps<{ property: Property }>()
const emit = defineEmits<{ chose: [Persona] }>()

const persona = usePersona()
const property = toRef(props, 'property')
const { free } = useNextWeekend(property)

const doors = computed(() => ([
  { id: 'casal' as const, ask: 'A dois', acc: accommodationFor(props.property, 'casal') },
  { id: 'familia' as const, ask: 'Com a família', acc: accommodationFor(props.property, 'familia') },
]).filter(d => d.acc))

// Só faz sentido perguntar quando há acomodações diferentes para cada caso.
const distinct = computed(() => new Set(doors.value.map(d => d.acc!.id)).size > 1)

function choose(id: Persona) {
  persona.value = id
  emit('chose', id)
}

const freeLink = computed(() => {
  if (!free.value) return null
  const acc = accommodationFor(props.property, persona.value)
  const slug = acc && free.value.accommodationIds.includes(acc.id) ? acc.slug : props.property.accommodations.find(a => a.id === free.value!.accommodationIds[0])?.slug
  return { path: '/reservar', query: { acomodacao: slug, entrada: free.value.checkIn, saida: free.value.checkOut } }
})
</script>

<template>
  <div class="doors">
    <template v-if="distinct">
      <p class="ask">Com quem você vem?</p>
      <div class="choices">
        <button
          v-for="door in doors"
          :key="door.id"
          type="button"
          class="door"
          :aria-pressed="persona === door.id"
          @click="choose(door.id)"
        >
          <span class="who">{{ door.ask }}</span>
          <span class="where">{{ door.acc!.name }} <span class="arrow" aria-hidden="true">↓</span></span>
        </button>
      </div>
    </template>
    <NuxtLink v-else to="/reservar" class="btn btn-light">Ver datas e reservar <span class="arrow">→</span></NuxtLink>

    <p class="free" :class="{ on: freeLink }" aria-live="polite">
      <template v-if="free && freeLink">
        <span class="dot" aria-hidden="true" />
        Próximo fim de semana livre:
        <NuxtLink :to="freeLink">{{ formatRange(free.checkIn, free.checkOut) }} <span class="arrow">→</span></NuxtLink>
      </template>
    </p>
  </div>
</template>

<style scoped lang="scss">
// Coreografia (tempos em assets/scss/_motion.scss). --enter-at vem da capa (logo após o título):
//   pergunta (+0) → porta 1 (+75ms) → porta 2 (+150ms) → aviso do fim de semana (+225ms).
// Escolher: aperto imediato (t(-1), escala 1 − φ⁻⁹), a porta escolhida acende com um anel que se
// expande e some (t(4)) e a seta "mergulha" em direção à história (pico em 38,2% = 1/φ²), enquanto
// a outra porta recua para opacidade 1/φ. A rolagem até #um-dia começa junto.
.doors { display: grid; gap: .9rem; width: 100%; max-width: 34rem; }
.ask { margin: 0; font-size: .95rem; color: rgb(255 255 255 / .9); }
.ask, .doors > .btn { @include motion-entrance; animation-delay: var(--enter-at, 0ms); }
.choices { display: grid; grid-template-columns: 1fr 1fr; gap: .6rem; }
.door {
  position: relative; isolation: isolate;
  display: grid; gap: .15rem; text-align: left; cursor: pointer; font: inherit; color: #fff;
  padding: .85rem 1rem .9rem; border-radius: 14px; min-height: 4.6rem;
  // Fundo escuro translúcido no lugar do desfoque: lê bem sobre qualquer foto e não refaz o
  // desfoque a cada quadro do zoom da capa.
  background: rgb(8 12 10 / .5); border: 1px solid rgb(255 255 255 / .38);
  // Só opacidade e transform animam; escolher troca as cores na hora.
  transition: opacity $dur-md $ease-move, transform $dur-md $ease-enter;
  @include motion-entrance;
  animation-delay: calc(var(--enter-at, 0ms) + #{$stagger});
  & + & { animation-delay: calc(var(--enter-at, 0ms) + 2 * #{$stagger}); }
  &:hover { transform: translateY(-2px); }
  // Escurecer no hover: camada por baixo do texto que entra por opacidade (.5 + .25 ≈ .62 de fundo).
  &::before {
    content: ""; position: absolute; inset: 0; z-index: -1; border-radius: inherit; pointer-events: none;
    background: rgb(8 12 10 / .25); opacity: 0; transition: opacity $dur-sm $ease-move;
  }
  &:hover::before { opacity: 1; }
  // Aperto: responde no mesmo instante e solta com a curva de entrada.
  &:active { transform: scale($zoom-recede); transition-duration: $dur-instant; }
  &[aria-pressed="true"] { background: #fff; color: var(--night); border-color: #fff; .who { color: var(--muted); } }
  &[aria-pressed="true"]::before { opacity: 0; }
  // Anel de confirmação: só existe no instante da escolha.
  &::after {
    content: ""; position: absolute; inset: -1px; border-radius: inherit; pointer-events: none;
    box-shadow: 0 0 0 1.5px #fff; opacity: 0;
  }
  &[aria-pressed="true"]::after { animation: door-ring $dur-xl $ease-enter; }
}
// A porta não escolhida recua, sem sumir: dá para trocar de ideia.
.choices:has(.door[aria-pressed="true"]) .door[aria-pressed="false"]:not(:hover) { opacity: .618; }
.who { @include eyebrow(var(--amber-soft)); }
.where { font-family: var(--font-display); font-size: clamp(1.25rem, 2.4vw, 1.55rem); line-height: 1.1; }
.arrow { display: inline-block; font-family: var(--font-body); font-size: .8em; transition: transform $dur-md $ease-enter; }
.door:hover .arrow { transform: translateY(3px); }
.door[aria-pressed="true"] .arrow { animation: door-dive $dur-xl $ease-move; }
@keyframes door-ring { from { opacity: .9; transform: none; } to { opacity: 0; transform: scale($zoom-sm, $zoom-lg); } }
// A seta desce e some, e volta por cima: o corte cai em 38,2% do tempo.
@keyframes door-dive { 38.2% { transform: translateY($dist-xs); opacity: 0; } 38.3% { transform: translateY(-$dist-xs); opacity: 0; } to { transform: none; opacity: 1; } }

// O aviso ocupa a mesma altura antes e depois de chegar, para nada pular na capa.
// Se a data já vier do servidor, entra no fim da sequência; se chegar depois, sobe sozinho (t(3)/t(4)).
.free {
  margin: .2rem 0 0; min-height: 1.6em; font-size: .9rem; color: rgb(255 255 255 / .85);
  display: flex; flex-wrap: wrap; align-items: center; gap: .1rem .45rem;
  opacity: 0; transform: translateY($dist-xs); @include motion-enter;
  animation: motion-fade $dur-lg $ease-enter backwards;
  animation-delay: calc(var(--enter-at, 0ms) + 3 * #{$stagger});
  &.on { opacity: 1; transform: none; }
  // 44px de área de toque sem empurrar a linha.
  a { color: #fff; font-weight: 700; text-underline-offset: 3px; padding-block: .7rem; margin-block: -.7rem; }
}
.dot { position: relative; width: 7px; height: 7px; border-radius: 50%; background: #7fd49b; }
// Pulso de "ao vivo": um anel que cresce e some num laço de t(6) (só transform e opacidade).
.dot::after { content: ""; position: absolute; inset: 0; border-radius: 50%; background: inherit; animation: ping $dur-3xl $ease-enter infinite; }
@keyframes ping { from { transform: scale(1); opacity: .6; } 61.8%, to { transform: scale(2.618); opacity: 0; } }

@include below(sm) {
  .door { padding: .75rem .8rem; min-height: 4.2rem; }
}
@include reduced-motion {
  .ask, .doors > .btn, .door, .free, .door::after, .door[aria-pressed="true"]::after, .door[aria-pressed="true"] .arrow { animation: none; }
  .door:active { transform: none; }
  .dot::after { animation: none; opacity: 0; }
  .free { transform: none; }
}
</style>
