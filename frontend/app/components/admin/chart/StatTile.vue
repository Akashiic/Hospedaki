<script setup lang="ts">
import { ArrowDownRight, ArrowUpRight } from 'lucide-vue-next'

// Número do período com a variação contra o período anterior. A cor da variação diz se foi
// bom (para cancelamentos, subir é ruim: `downIsGood`) e vem sempre com seta e sinal.
const props = defineProps<{ label: string, value: string, delta?: number | null, downIsGood?: boolean, hint?: string, hero?: boolean }>()
const good = computed(() => props.delta == null || props.delta === 0 ? null : (props.delta > 0) !== props.downIsGood)
</script>

<template>
  <div class="tile a-card" :class="{ hero }">
    <p class="label">{{ label }}</p>
    <p class="value">{{ value }}</p>
    <p v-if="delta != null" class="delta" :class="{ good: good === true, bad: good === false }">
      <component :is="delta >= 0 ? ArrowUpRight : ArrowDownRight" :size="15" aria-hidden="true" />
      {{ delta > 0 ? '+' : '' }}{{ delta.toLocaleString('pt-BR', { maximumFractionDigits: 1 }) }}%
      <span>vs. período anterior</span>
    </p>
    <p v-else-if="hint" class="hint">{{ hint }}</p>
  </div>
</template>

<style scoped lang="scss">
.tile { display: grid; gap: .25rem; align-content: start; padding: 1rem 1.1rem; min-width: 0; }
.label { margin: 0; font-size: .84rem; font-weight: 600; color: var(--muted); }
// Número grande na mesma fonte do texto, com algarismos proporcionais (como pede o sistema de gráficos).
.value { margin: 0; font-family: var(--font-body); font-weight: 700; font-size: 1.7rem; line-height: 1.1; letter-spacing: -.01em; overflow-wrap: anywhere; }
.hero .value { font-size: clamp(1.8rem, 2.6vw, 2.3rem); white-space: nowrap; }
.delta { margin: .15rem 0 0; display: flex; align-items: center; gap: .2rem; flex-wrap: wrap; font-size: .82rem; font-weight: 700; color: var(--muted); }
.delta span { font-weight: 500; color: var(--muted); }
.delta.good { color: #1f7a45; }
.delta.bad { color: var(--danger); }
.hint { margin: .15rem 0 0; font-size: .82rem; color: var(--muted); }
</style>
