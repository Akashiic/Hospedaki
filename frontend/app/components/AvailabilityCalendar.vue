<script setup lang="ts">
import { dayNotes, dayState, isSelectable, maxMonthOffset, monthGrid, pickDay, type CalendarState } from '~/utils/calendar'
import { formatIso, todayIn } from '~/utils/dates'

// Calendário de diárias: o hóspede toca no dia de entrada e depois no de saída.
// As regras (o que pode ser entrada ou saída, rótulos) ficam em utils/calendar.ts.
// `unavailable` traz as noites ocupadas ou bloqueadas; `maxDate` é o fim (exclusivo) da janela de
// disponibilidade buscada, e o calendário não passa dela.
const props = defineProps<{ unavailable: string[], timezone: string, maxDate?: string }>()
const checkIn = defineModel<string>('checkIn', { default: '' })
const checkOut = defineModel<string>('checkOut', { default: '' })

const weekDays = ['D', 'S', 'T', 'Q', 'Q', 'S', 'S']
const offset = ref(0)

// "Hoje" no fuso da pousada, o mesmo dia que o backend valida. Calculado no servidor, vai junto
// no HTML e vale igual na hidratação. Uma aba esquecida aberta virando a meia-noite é conferida
// quando volta a ficar visível.
const today = useState('calendar-today', () => todayIn(props.timezone))
function refreshToday() {
  if (document.visibilityState === 'visible') today.value = todayIn(props.timezone)
}
onMounted(() => document.addEventListener('visibilitychange', refreshToday))
onBeforeUnmount(() => document.removeEventListener('visibilitychange', refreshToday))

const blocked = computed(() => new Set(props.unavailable))
const calendar = computed<CalendarState>(() => ({
  today: today.value,
  checkIn: checkIn.value,
  checkOut: checkOut.value,
  blocked: blocked.value,
  maxDate: props.maxDate,
}))

const lastOffset = computed(() => maxMonthOffset(today.value, props.maxDate))
watch(lastOffset, (max) => { if (offset.value > max) offset.value = max })

const months = computed(() => [0, 1].map(i => monthGrid(today.value, offset.value + i)))

function pick(day: string) {
  const next = pickDay(day, calendar.value)
  if (!next) return
  checkIn.value = next.checkIn
  checkOut.value = next.checkOut
}

// Nome completo para leitores de tela: "sexta-feira, 23 de outubro, entrada".
const label = (day: string) =>
  [formatIso(day, { weekday: 'long', day: 'numeric', month: 'long' }), ...dayNotes(day, calendar.value)].join(', ')
</script>

<template>
  <div class="calendar">
    <div class="nav">
      <button type="button" :disabled="offset === 0" aria-label="Mês anterior" @click="offset--">‹</button>
      <button type="button" :disabled="offset >= lastOffset" aria-label="Próximo mês" @click="offset++">›</button>
    </div>
    <p class="sr-only" aria-live="polite">{{ months.map(m => m.label).join(' e ') }}</p>
    <div class="months">
      <div v-for="month in months" :key="month.label" class="month" role="group" :aria-label="month.label">
        <p class="label" aria-hidden="true">{{ month.label }}</p>
        <div class="grid">
          <span v-for="(w, i) in weekDays" :key="i" class="weekday" aria-hidden="true">{{ w }}</span>
          <template v-for="(day, i) in month.days" :key="i">
            <span v-if="!day" />
            <button
              v-else
              type="button"
              class="day"
              :class="dayState(day, calendar)"
              :disabled="!isSelectable(day, calendar)"
              :aria-pressed="day === checkIn || day === checkOut"
              :aria-label="label(day)"
              @click="pick(day)"
            >
              {{ Number(day.slice(8)) }}
            </button>
          </template>
        </div>
      </div>
    </div>
    <p class="legend"><span class="dot blocked" aria-hidden="true" /> indisponível</p>
  </div>
</template>

<style scoped lang="scss">
.nav { display: flex; justify-content: flex-end; gap: .5rem; }
// Alvos de toque de 44px (setas e dias).
.nav button { border: 1px solid var(--line); background: none; border-radius: 8px; width: 2.75rem; height: 2.75rem; cursor: pointer; font: inherit; font-size: 1.2rem; color: var(--text); }
.nav button:disabled { color: var(--line); cursor: default; }
.months { display: grid; gap: 1.5rem; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); }
.label { font-weight: 600; margin: .5rem 0; }
.label::first-letter { text-transform: uppercase; }
.grid { display: grid; grid-template-columns: repeat(7, minmax(0, 1fr)); gap: 1px; text-align: center; }
.weekday { font-size: .75rem; color: var(--muted); }
.day { border: 0; background: none; min-height: 44px; padding: 0; border-radius: 8px; cursor: pointer; font: inherit; color: var(--text); }
.day.free:not(:disabled):hover { background: var(--soft); }
.day:disabled { cursor: default; }
.day.past, .day.closed { color: var(--line); }
.day.blocked { color: var(--muted); text-decoration: line-through; }
.day.blocked:disabled { cursor: not-allowed; }
.day.range { background: var(--soft); }
.day.selected { background: var(--brand); color: var(--brand-ink); }
.legend { font-size: .8rem; color: var(--muted); }
.dot { display: inline-block; width: .6rem; height: .6rem; border-radius: 50%; background: var(--line); }
@include below(sm) {
  .label, .legend { padding-inline: .35rem; }
}
</style>
