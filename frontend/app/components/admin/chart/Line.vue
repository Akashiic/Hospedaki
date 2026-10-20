<script setup lang="ts">
import type { LineDatum } from '~/utils/stats'
// Linha de uma série só (ocupação por mês, em %). Linha de 2px com ponto no último valor;
// passar o mouse ou o foco numa coluna invisível mostra a régua vertical, o ponto e o valor.
const props = defineProps<{ data: LineDatum[], label: string, max?: number, height?: number }>()

const W = 600
const H = computed(() => props.height ?? 160)
const top = computed(() => props.max ?? Math.max(10, ...props.data.map(d => d.value ?? 0)))
const x = (i: number) => props.data.length > 1 ? (i / (props.data.length - 1)) * W : W / 2
const y = (v: number) => H.value - (v / top.value) * H.value
const points = computed(() => props.data.map((d, i) => d.value == null ? null : [x(i), y(d.value)] as const))
const path = computed(() => points.value.filter(Boolean).map((p, i) => `${i ? 'L' : 'M'}${p![0].toFixed(1)},${p![1].toFixed(1)}`).join(' '))
const area = computed(() => {
  const pts = points.value.filter(Boolean)
  if (!pts.length) return ''
  return `${path.value} L${pts.at(-1)![0]},${H.value} L${pts[0]![0]},${H.value} Z`
})
const lastIndex = computed(() => props.data.map(d => d.value != null).lastIndexOf(true))
const active = ref<number | null>(null)
</script>

<template>
  <div class="line" :style="{ '--h': `${H}px` }">
    <div class="ticks" aria-hidden="true"><span>{{ top }}%</span><span>{{ top / 2 }}%</span><span>0%</span></div>
    <div class="plot">
      <svg :viewBox="`0 0 ${W} ${H}`" preserveAspectRatio="none" aria-hidden="true">
        <line v-for="g in [0, H / 2, H]" :key="g" x1="0" :x2="W" :y1="g" :y2="g" class="grid" vector-effect="non-scaling-stroke" />
        <path :d="area" class="area" />
        <path :d="path" class="stroke" vector-effect="non-scaling-stroke" />
        <line v-if="active != null" :x1="x(active)" :x2="x(active)" y1="0" :y2="H" class="rule" vector-effect="non-scaling-stroke" />
      </svg>
      <!-- Pontos em HTML para não esticarem com o SVG. -->
      <span
        v-for="(p, i) in points"
        v-show="p && (i === lastIndex || i === active)"
        :key="`p${i}`"
        class="dot"
        :style="p ? { left: `${p[0] / W * 100}%`, top: `${p[1] / H * 100}%` } : {}"
        aria-hidden="true"
      />
      <span v-if="lastIndex >= 0 && active == null" class="end" :style="{ left: `${x(lastIndex) / W * 100}%`, top: `${points[lastIndex]![1] / H * 100}%` }">{{ data[lastIndex]!.display }}</span>
      <div class="hits" role="list" :aria-label="label">
        <div
          v-for="(d, i) in data"
          :key="d.key"
          class="hit"
          role="listitem"
          tabindex="0"
          :aria-label="`${d.label}: ${d.display}`"
          @mouseenter="active = i"
          @mouseleave="active = null"
          @focus="active = i"
          @blur="active = null"
        >
          <span v-if="active === i" class="tip" role="tooltip"><strong>{{ d.display }}</strong><small>{{ d.label }}</small></span>
        </div>
      </div>
    </div>
    <span />
    <div class="labels" aria-hidden="true"><span v-for="d in data" :key="d.key">{{ d.label }}</span></div>
  </div>
</template>

<style scoped lang="scss">
.line { container-type: inline-size; display: grid; grid-template-columns: auto minmax(0, 1fr); gap: .35rem .5rem; }
.ticks { display: flex; flex-direction: column; justify-content: space-between; height: var(--h); font-size: .72rem; color: var(--muted); text-align: right; transform: translateY(-.4em); }
.plot { position: relative; height: var(--h); min-width: 0; }
svg { position: absolute; inset: 0; width: 100%; height: 100%; overflow: visible; }
.grid { stroke: var(--line); stroke-width: 1; }
.area { fill: var(--brand); opacity: .1; }
.stroke { fill: none; stroke: var(--brand); stroke-width: 2; stroke-linejoin: round; stroke-linecap: round; }
.rule { stroke: var(--muted); stroke-width: 1; }
// Ponto de 10px com anel da cor do fundo, para não se perder sobre a linha.
.dot { position: absolute; width: 10px; height: 10px; border-radius: 50%; background: var(--brand); box-shadow: 0 0 0 2px var(--a-card); transform: translate(-50%, -50%); pointer-events: none; }
.end { position: absolute; transform: translate(-100%, calc(-100% - 8px)); font-size: .76rem; font-weight: 700; white-space: nowrap; pointer-events: none; }
.hits { position: absolute; inset: 0; display: flex; }
.hit { position: relative; flex: 1 1 0; outline: none; }
.hit:focus-visible { box-shadow: inset 0 0 0 2px var(--focus); border-radius: 6px; }
.tip {
  position: absolute; z-index: 3; bottom: calc(100% + 4px); left: 50%; transform: translateX(-50%);
  display: grid; padding: .45rem .6rem; border-radius: 10px; background: var(--night); color: var(--on-night); white-space: nowrap; pointer-events: none;
  strong { font-size: .86rem; } small { font-size: .74rem; color: var(--on-night-muted); }
}
.labels { display: flex; justify-content: space-between; font-size: .72rem; color: var(--muted); }
.labels span { flex: 1 1 0; text-align: center; min-width: 0; overflow: hidden; }
.labels span:first-child { text-align: left; }
.labels span:last-child { text-align: right; }
// Tela estreita: um rótulo de mês sim, outro não, para não encavalarem.
@container (max-width: 520px) { .labels span:nth-child(even) { visibility: hidden; } }
</style>
