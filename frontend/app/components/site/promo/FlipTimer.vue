<script setup lang="ts">
// Contagem regressiva da promoção em placas que viram, com dias (quando falta mais de um dia),
// horas, minutos e segundos. Em loop, ao zerar recomeça do ciclo (loopHours); sem loop, para
// no zero e avisa (ended). Só conta no navegador: no servidor sai o esqueleto do mesmo tamanho.
const props = defineProps<{ endsAt: string, loopHours?: number | null, size?: 'sm' | 'md' }>()
const emit = defineEmits<{ ended: [] }>()

const now = ref<number | null>(null)
let timer: ReturnType<typeof setInterval> | undefined
onMounted(() => {
  now.value = Date.now()
  timer = setInterval(() => { now.value = Date.now() }, 1000)
})
onBeforeUnmount(() => clearInterval(timer))

const target = computed(() => now.value == null ? null : promoTarget(Date.parse(props.endsAt), props.loopHours, now.value))
const left = computed(() => splitRemaining(target.value == null || now.value == null ? 0 : target.value - now.value))
watch(target, (t, before) => { if (t == null && before != null) emit('ended') })

const pad = (n: number) => String(n).padStart(2, '0')
const units = computed(() => [
  ...(left.value.days > 0 ? [{ key: 'd', value: pad(Math.min(99, left.value.days)), label: left.value.days === 1 ? 'dia' : 'dias' }] : []),
  { key: 'h', value: pad(left.value.hours), label: 'horas' },
  { key: 'm', value: pad(left.value.minutes), label: 'min' },
  { key: 's', value: pad(left.value.seconds), label: 'seg' },
])
// Para leitores de tela: um texto que muda a cada minuto, não a cada segundo.
const spoken = computed(() => {
  const l = left.value
  const parts = [l.days && plural(l.days, 'dia', 'dias'), l.hours && plural(l.hours, 'hora', 'horas'), plural(l.minutes, 'minuto', 'minutos')].filter(Boolean)
  return `Termina em ${parts.join(', ')}`
})
</script>

<template>
  <div class="timer" :class="size ?? 'md'" role="timer" :aria-label="now == null ? 'Promoção por tempo limitado' : spoken">
    <template v-if="now != null">
      <template v-for="(u, i) in units" :key="u.key">
        <span v-if="i" class="colon" aria-hidden="true">:</span>
        <span class="unit">
          <span class="pair">
            <SitePromoFlipDigit :value="u.value[0]!" />
            <SitePromoFlipDigit :value="u.value[1]!" />
          </span>
          <small aria-hidden="true">{{ u.label }}</small>
        </span>
      </template>
    </template>
    <template v-else>
      <template v-for="i in 3" :key="i">
        <span v-if="i > 1" class="colon" aria-hidden="true">:</span>
        <span class="unit"><span class="pair"><span class="ghost" /><span class="ghost" /></span><small aria-hidden="true">&nbsp;</small></span>
      </template>
    </template>
  </div>
</template>

<style scoped lang="scss">
.timer { display: inline-flex; align-items: flex-start; gap: .25em; font-size: 1.5rem; }
.timer.sm { font-size: 1.05rem; }
.unit { display: grid; justify-items: center; gap: .25rem; }
.pair { display: inline-flex; gap: .12em; filter: drop-shadow(0 .12em .2em rgb(0 0 0 / .18)); }
small { font-size: .68rem; font-weight: 700; letter-spacing: .06em; text-transform: uppercase; color: var(--timer-label, var(--muted)); }
.sm small { font-size: .6rem; }
.colon { line-height: 1.55em; font-weight: 800; color: var(--timer-colon, var(--muted)); }
.ghost { display: inline-block; width: 1.08em; height: 1.55em; border-radius: .2em; background: var(--flip-bg, #1b2722); opacity: .85; }
</style>
