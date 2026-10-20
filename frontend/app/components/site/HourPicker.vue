<script setup lang="ts">
import type { Accommodation } from '~/composables/useApi'
import type { HoursDay } from '~/utils/hours'

// Reserva por hora: depois do dia, o hóspede escolhe o horário de início e quantas horas fica.
// Os horários já reservados, os que já passaram e os sem tempo mínimo até a próxima reserva
// aparecem riscados. O valor sai do orçamento da API, como numa estadia.
const props = defineProps<{ accommodation: Accommodation, date: string }>()
const start = defineModel<string>('start', { default: '' })
const end = defineModel<string>('end', { default: '' })

const api = useApi()
const day = ref<HoursDay | null>(null)
const loading = ref(false)
const failed = ref(false)

watch(() => [props.accommodation.id, props.date], async (_, __, onCleanup) => {
  start.value = ''
  end.value = ''
  day.value = null
  failed.value = false
  if (!props.date) return
  let stale = false
  onCleanup(() => { stale = true })
  loading.value = true
  try {
    const res = await api<HoursDay>('/hours', { query: { accommodation_id: props.accommodation.id, date: props.date } })
    if (!stale) day.value = res
  }
  catch {
    if (!stale) failed.value = true
  }
  finally {
    if (!stale) loading.value = false
  }
}, { immediate: true })

const starts = computed(() => day.value ? startOptions(day.value) : [])
const durations = computed(() => day.value && start.value ? durationOptions(start.value, day.value) : [])
const hours = computed(() => start.value && end.value ? (toMinutes(end.value) - toMinutes(start.value)) / 60 : 0)

function pickStart(time: string) {
  start.value = time
  // Começa com o mínimo; se o hóspede já tinha escolhido uma duração que ainda cabe, mantém.
  const keep = hours.value && durations.value.includes(hours.value) ? hours.value : durations.value[0]
  end.value = keep ? endAt(time, keep) : ''
}
const pickHours = (h: number) => { end.value = endAt(start.value, h) }
</script>

<template>
  <div class="hours" aria-live="polite">
    <p v-if="loading" class="hint">Vendo os horários livres...</p>
    <p v-else-if="failed" class="hint">Não foi possível carregar os horários. Tente outro dia ou fale com a pousada.</p>
    <p v-else-if="day && !starts.some(s => s.free)" class="notice">Não há horário livre neste dia. Escolha outro no calendário.</p>

    <template v-else-if="day">
      <div class="group">
        <p class="label" id="start-label">Começa às</p>
        <div class="grid" role="radiogroup" aria-labelledby="start-label">
          <button
            v-for="s in starts"
            :key="s.time"
            type="button"
            role="radio"
            class="slot"
            :aria-checked="start === s.time"
            :disabled="!s.free"
            @click="pickStart(s.time)"
          >
            {{ formatHour(s.time) }}
          </button>
        </div>
      </div>

      <Transition name="swap">
        <div v-if="start" class="group">
          <p class="label" id="dur-label">Por quanto tempo</p>
          <div class="grid wide" role="radiogroup" aria-labelledby="dur-label">
            <button
              v-for="h in durations"
              :key="h"
              type="button"
              role="radio"
              class="slot"
              :aria-checked="hours === h"
              @click="pickHours(h)"
            >
              <strong>{{ h }}h</strong>
              <small>até {{ formatHour(endAt(start, h)) }}</small>
            </button>
          </div>
        </div>
      </Transition>
      <p class="hint">
        Funciona das {{ formatHour(day.opens_at) }} às {{ formatHour(day.closes_at) }} · mínimo de {{ plural(day.min_hours, 'hora', 'horas') }}
        <template v-if="accommodation.hourly_price"> · {{ formatMoney(accommodation.hourly_price) }} a hora</template>
      </p>
    </template>
  </div>
</template>

<style scoped lang="scss">
.hours { display: grid; gap: 1rem; margin-top: 1.2rem; }
.group { display: grid; gap: .5rem; }
.label { margin: 0; font-weight: 700; font-size: .88rem; }
.grid { display: grid; gap: .4rem; grid-template-columns: repeat(auto-fill, minmax(64px, 1fr)); }
.grid.wide { grid-template-columns: repeat(auto-fill, minmax(92px, 1fr)); }
// Escolher troca as cores na hora (regra de movimento: cor não anima).
.slot {
  display: grid; justify-items: center; gap: .05rem; min-height: 44px; padding: .4rem; border-radius: 10px;
  border: 1.5px solid var(--line); background: var(--card); color: var(--text); font: inherit; font-weight: 700; cursor: pointer;
  small { font-size: .74rem; font-weight: 500; color: var(--muted); }
  &[aria-checked="true"] { border-color: var(--brand); background: var(--brand); color: var(--brand-ink); small { color: inherit; opacity: .85; } }
  &:disabled { cursor: default; color: var(--muted); background: var(--soft); text-decoration: line-through; opacity: .6; }
}
.hint { margin: 0; color: var(--muted); font-size: .9rem; }
.notice { margin: 0; background: var(--soft); border-radius: 10px; padding: .7rem .9rem; font-weight: 500; }
.swap-enter-active { @include motion-enter; }
.swap-enter-from { opacity: 0; transform: translateY($dist-xs); }
</style>
