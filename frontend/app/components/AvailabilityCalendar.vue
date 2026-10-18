<script setup lang="ts">
// Calendário de diárias: o hóspede clica no dia de entrada e depois no de saída.
// Dias marcados como indisponíveis são noites ocupadas ou bloqueadas.
const props = defineProps<{ unavailable: string[] }>()
const checkIn = defineModel<string>('checkIn', { default: '' })
const checkOut = defineModel<string>('checkOut', { default: '' })

const weekDays = ['D', 'S', 'T', 'Q', 'Q', 'S', 'S']
const offset = ref(0)

function toIso(date: Date) {
  const y = date.getFullYear()
  const m = String(date.getMonth() + 1).padStart(2, '0')
  const d = String(date.getDate()).padStart(2, '0')
  return `${y}-${m}-${d}`
}
const fromIso = (iso: string) => { const [y, m, d] = iso.split('-').map(Number); return new Date(y!, m! - 1, d!) }

// "Hoje" do servidor vai junto no HTML e é o mesmo na hidratação (sem divergir entre o relógio
// e o fuso do servidor e os do navegador); depois de montado, vale o dia do navegador do hóspede.
const today = useState('calendar-today', () => toIso(new Date()))
onMounted(() => { today.value = toIso(new Date()) })

const blocked = computed(() => new Set(props.unavailable))

const months = computed(() => {
  const base = fromIso(today.value)
  return [0, 1].map((i) => {
    const first = new Date(base.getFullYear(), base.getMonth() + offset.value + i, 1)
    const total = new Date(first.getFullYear(), first.getMonth() + 1, 0).getDate()
    const days: (string | null)[] = Array(first.getDay()).fill(null)
    for (let d = 1; d <= total; d++) days.push(toIso(new Date(first.getFullYear(), first.getMonth(), d)))
    return { label: first.toLocaleDateString('pt-BR', { month: 'long', year: 'numeric' }), days }
  })
})

// Há alguma noite ocupada entre a entrada e o dia anterior à saída?
function rangeIsFree(start: string, end: string) {
  for (const night of blocked.value) {
    if (night >= start && night < end) return false
  }
  return true
}

// Escolhendo a saída: um dia depois da entrada, sem noite ocupada no meio.
const choosingCheckOut = computed(() => !!checkIn.value && !checkOut.value)
const canCheckOut = (day: string) => choosingCheckOut.value && day > checkIn.value && rangeIsFree(checkIn.value, day)

// Dá para tocar no dia? Como saída válida, ou como nova entrada (noite livre).
function selectable(day: string) {
  if (day < today.value) return false
  return canCheckOut(day) || !blocked.value.has(day)
}

function pick(day: string) {
  if (!selectable(day)) return
  if (canCheckOut(day)) {
    checkOut.value = day
    return
  }
  checkIn.value = day
  checkOut.value = ''
}

function state(day: string) {
  if (day < today.value) return 'past'
  if (day === checkIn.value || day === checkOut.value) return 'selected'
  if (checkIn.value && checkOut.value && day > checkIn.value && day < checkOut.value) return 'range'
  if (blocked.value.has(day)) return 'blocked'
  return 'free'
}

// Nome completo para leitores de tela: "sexta-feira, 23 de outubro, entrada".
function label(day: string) {
  const date = fromIso(day).toLocaleDateString('pt-BR', { weekday: 'long', day: 'numeric', month: 'long' })
  const notes: string[] = []
  if (day === checkIn.value) notes.push('entrada')
  else if (day === checkOut.value) notes.push('saída')
  else if (state(day) === 'range') notes.push('dentro da estadia')
  if (day < today.value) notes.push('data passada')
  else if (blocked.value.has(day)) notes.push(canCheckOut(day) ? 'ocupado, disponível só para saída' : 'indisponível')
  else if (choosingCheckOut.value && day > checkIn.value && !canCheckOut(day)) notes.push('há noites ocupadas antes')
  return [date, ...notes].join(', ')
}
</script>

<template>
  <div class="calendar">
    <div class="nav">
      <button type="button" :disabled="offset === 0" aria-label="Mês anterior" @click="offset--">‹</button>
      <button type="button" aria-label="Próximo mês" @click="offset++">›</button>
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
              :class="state(day)"
              :disabled="!selectable(day)"
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
.day.past { color: var(--line); }
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
