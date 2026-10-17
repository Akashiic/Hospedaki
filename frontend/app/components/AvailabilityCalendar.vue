<script setup lang="ts">
// Calendário de diárias: o hóspede clica no dia de entrada e depois no de saída.
// Dias marcados como indisponíveis são noites ocupadas ou bloqueadas.
const props = defineProps<{ unavailable: string[] }>()
const checkIn = defineModel<string>('checkIn', { default: '' })
const checkOut = defineModel<string>('checkOut', { default: '' })

const weekDays = ['D', 'S', 'T', 'Q', 'Q', 'S', 'S']
const today = toIso(new Date())
const offset = ref(0)

function toIso(date: Date) {
  const y = date.getFullYear()
  const m = String(date.getMonth() + 1).padStart(2, '0')
  const d = String(date.getDate()).padStart(2, '0')
  return `${y}-${m}-${d}`
}

const blocked = computed(() => new Set(props.unavailable))

const months = computed(() => {
  const base = new Date()
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

function pick(day: string) {
  if (day < today) return
  if (!checkIn.value || checkOut.value || day <= checkIn.value) {
    if (blocked.value.has(day)) return
    checkIn.value = day
    checkOut.value = ''
    return
  }
  if (rangeIsFree(checkIn.value, day)) checkOut.value = day
}

function state(day: string) {
  if (day < today) return 'past'
  if (day === checkIn.value || day === checkOut.value) return 'selected'
  if (checkIn.value && checkOut.value && day > checkIn.value && day < checkOut.value) return 'range'
  if (blocked.value.has(day)) return 'blocked'
  return 'free'
}
</script>

<template>
  <div class="calendar">
    <div class="nav">
      <button type="button" :disabled="offset === 0" aria-label="Mês anterior" @click="offset--">‹</button>
      <button type="button" aria-label="Próximo mês" @click="offset++">›</button>
    </div>
    <div class="months">
      <div v-for="month in months" :key="month.label" class="month">
        <p class="label">{{ month.label }}</p>
        <div class="grid">
          <span v-for="(w, i) in weekDays" :key="i" class="weekday">{{ w }}</span>
          <template v-for="(day, i) in month.days" :key="i">
            <span v-if="!day" />
            <button v-else type="button" class="day" :class="state(day)" :disabled="day < today" @click="pick(day)">
              {{ Number(day.slice(8)) }}
            </button>
          </template>
        </div>
      </div>
    </div>
    <p class="legend"><span class="dot blocked" /> indisponível</p>
  </div>
</template>

<style scoped>
.nav { display: flex; justify-content: flex-end; gap: .5rem; }
.nav button { border: 1px solid var(--line); background: none; border-radius: 6px; width: 2rem; height: 2rem; cursor: pointer; }
.months { display: grid; gap: 1.5rem; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); }
.label { font-weight: 600; margin: .5rem 0; }
.label::first-letter { text-transform: uppercase; }
.grid { display: grid; grid-template-columns: repeat(7, 1fr); gap: 2px; text-align: center; }
.weekday { font-size: .75rem; color: var(--muted); }
.day { border: 0; background: none; padding: .45rem 0; border-radius: 6px; cursor: pointer; font: inherit; }
.day.free:hover { background: var(--soft); }
.day.past { color: var(--line); cursor: default; }
.day.blocked { color: var(--muted); text-decoration: line-through; cursor: not-allowed; }
.day.range { background: var(--soft); }
.day.selected { background: var(--brand); color: #fff; }
.legend { font-size: .8rem; color: var(--muted); }
.dot { display: inline-block; width: .6rem; height: .6rem; border-radius: 50%; background: var(--line); }
</style>
