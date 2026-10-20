<script setup lang="ts">
import type { ColumnDatum } from '~/utils/stats'
// Colunas de uma série só (receita por mês, chegadas por dia da semana...). A coluna em destaque
// (o mês atual) vai na cor cheia; as outras num tom mais claro da mesma cor. Passar o mouse ou o
// foco do teclado mostra o valor; o rótulo fixo fica só na maior coluna e na em destaque.
const props = defineProps<{ data: ColumnDatum[], emphasize?: string | null, label: string, height?: number, format?: (n: number) => string }>()

const max = computed(() => Math.max(0, ...props.data.map(d => d.value)))
// Teto "redondo" para as linhas de referência (0, metade e teto).
const ceiling = computed(() => {
  if (max.value <= 0) return 1
  const p = 10 ** Math.floor(Math.log10(max.value))
  return Math.ceil(max.value / p) * p
})
const ticks = computed(() => [ceiling.value, ceiling.value / 2, 0])
const fmt = (n: number) => props.format ? props.format(n) : n.toLocaleString('pt-BR')
const peakKey = computed(() => props.data.find(d => d.value === max.value && max.value > 0)?.key)
const active = ref<string | null>(null)
</script>

<template>
  <div class="columns" :class="{ dense: data.length > 8 }" :style="{ '--h': `${height ?? 180}px` }">
    <div class="ticks" aria-hidden="true">
      <span v-for="t in ticks" :key="t">{{ fmt(t) }}</span>
    </div>
    <div class="plot" role="list" :aria-label="label">
      <span v-for="t in ticks" :key="`g${t}`" class="grid" :style="{ bottom: `${t / ceiling * 100}%` }" aria-hidden="true" />
      <div
        v-for="d in data"
        :key="d.key"
        class="col"
        role="listitem"
        tabindex="0"
        :aria-label="`${d.label}: ${d.display}${d.detail ? `, ${d.detail}` : ''}`"
        @mouseenter="active = d.key"
        @mouseleave="active = null"
        @focus="active = d.key"
        @blur="active = null"
      >
        <span class="bar-area">
          <span
            class="bar"
            :class="{ strong: !emphasize || d.key === emphasize, zero: d.value <= 0 }"
            :style="{ height: `${Math.max(d.value / ceiling * 100, d.value > 0 ? 1.5 : 0)}%` }"
          >
            <span v-if="d.key === peakKey || d.key === emphasize" class="cap">{{ d.display }}</span>
          </span>
          <Transition name="tip">
            <span v-if="active === d.key" class="tip" role="tooltip">
              <strong>{{ d.display }}</strong>
              <small>{{ d.label }}<template v-if="d.detail"> · {{ d.detail }}</template></small>
            </span>
          </Transition>
        </span>
        <span class="lbl" aria-hidden="true">{{ d.label }}</span>
      </div>
    </div>
  </div>
</template>

<style scoped lang="scss">
.columns { container-type: inline-size; display: grid; grid-template-columns: auto minmax(0, 1fr); gap: .5rem; }
.ticks { display: flex; flex-direction: column; justify-content: space-between; height: var(--h); font-size: .72rem; color: var(--muted); text-align: right; font-variant-numeric: tabular-nums; transform: translateY(-.4em); }
.plot { position: relative; display: flex; align-items: stretch; gap: 2px; min-width: 0; }
// Linhas de referência finas, contínuas e discretas.
.grid { position: absolute; left: 0; right: 0; height: 1px; background: var(--line); pointer-events: none; }
.col { flex: 1 1 0; min-width: 0; display: grid; grid-template-rows: var(--h) auto; justify-items: center; outline: none; cursor: default; }
.bar-area { position: relative; width: 100%; display: flex; align-items: flex-end; justify-content: center; }
// Coluna de até 24px, cantos de 4px só na ponta, nascendo da base. Hover: área inteira da coluna.
.bar { position: relative; width: min(24px, 70%); border-radius: 4px 4px 0 0; background: color-mix(in srgb, var(--brand) 38%, white); transition: opacity $dur-sm $ease-move; }
.bar.strong { background: var(--brand); }
.col:hover .bar, .col:focus-visible .bar { opacity: .85; }
.col:focus-visible .bar { outline: 2px solid var(--focus); outline-offset: 2px; }
.cap { position: absolute; bottom: calc(100% + 4px); left: 50%; transform: translateX(-50%); font-size: .72rem; font-weight: 700; color: var(--text); white-space: nowrap; }
.lbl { margin-top: .35rem; font-size: .72rem; color: var(--muted); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 100%; }
.tip {
  position: absolute; z-index: 3; bottom: calc(100% + 6px); left: 50%; transform: translateX(-50%);
  display: grid; gap: .05rem; padding: .45rem .6rem; border-radius: 10px; background: var(--night); color: var(--on-night);
  white-space: nowrap; pointer-events: none; box-shadow: 0 8px 20px -8px rgb(0 0 0 / .5);
  strong { font-size: .86rem; } small { font-size: .74rem; color: var(--on-night-muted); }
}
.tip-enter-active { transition: opacity $dur-xs $ease-enter; }
.tip-enter-from { opacity: 0; }
// Tela estreita com muitas colunas: um rótulo sim, outro não.
@container (max-width: 520px) { .dense .col:nth-of-type(even) .lbl { visibility: hidden; } }
</style>
