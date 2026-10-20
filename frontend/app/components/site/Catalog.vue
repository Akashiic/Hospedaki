<script setup lang="ts">
import type { CatalogState } from '~/components/site/CatalogCard.vue'
import type { Property, Quote } from '~/composables/useApi'
import type { CatalogKind, CatalogSort } from '~/utils/catalog'
import { BadgeCheck, CalendarDays, Minus, Plus, ShieldCheck, Users, Zap } from 'lucide-vue-next'

// Acomodações como vitrine de loja: busca por datas e pessoas no topo, filtros e cartões que
// respondem na hora (livre ou não, quanto fica no total e no Pix). Sem datas, cada cartão mostra o
// "a partir de" e o próximo fim de semana livre. A busca fica no endereço (?entrada=&saida=&pessoas=)
// para o hóspede voltar, compartilhar ou recarregar sem perder nada.
const props = defineProps<{ property: Property }>()
const route = useRoute()
const router = useRouter()
const api = useApi()

const today = useState('calendar-today', () => todayIn(props.property.timezone))
const windowEnd = computed(() => addMonthsIso(today.value, 12))
const iso = (v: unknown) => (typeof v === 'string' && /^\d{4}-\d{2}-\d{2}$/.test(v) ? v : '')

const checkIn = ref(iso(route.query.entrada))
const checkOut = ref(iso(route.query.saida))
const guests = ref(Math.min(200, Math.max(1, Number(route.query.pessoas) || 2)))
const kind = ref<CatalogKind>(['estadias', 'eventos'].includes(route.query.tipo as string) ? route.query.tipo as CatalogKind : 'todas')
const sort = ref<CatalogSort>(['menor-preco', 'mais-espaco'].includes(route.query.ordem as string) ? route.query.ordem as CatalogSort : 'recomendadas')

// Saída sempre depois da entrada: mudar a entrada para depois da saída empurra a saída.
watch(checkIn, (v) => {
  if (v && (!checkOut.value || checkOut.value <= v)) checkOut.value = addDaysIso(v, 1)
})
const hasDates = computed(() => validRange(checkIn.value, checkOut.value, today.value))
const nights = computed(() => hasDates.value ? nightsBetween(checkIn.value, checkOut.value) : 0)
const search = computed(() => hasDates.value ? { checkIn: checkIn.value, checkOut: checkOut.value, guests: guests.value } : null)
function clearDates() {
  checkIn.value = ''
  checkOut.value = ''
}

watch([checkIn, checkOut, guests, kind, sort], () => {
  const query: Record<string, string> = {}
  if (hasDates.value) Object.assign(query, { entrada: checkIn.value, saida: checkOut.value })
  if (guests.value !== 2) query.pessoas = String(guests.value)
  if (kind.value !== 'todas') query.tipo = kind.value
  if (sort.value !== 'recomendadas') query.ordem = sort.value
  router.replace({ query })
})

const accommodations = computed(() => props.property.accommodations)
const hasHourly = computed(() => accommodations.value.some(isHourly))
const hasNightly = computed(() => accommodations.value.some(a => !isHourly(a)))
const pays = computed(() => accommodations.value.some(a => a.payment?.methods.length))

// ── Disponibilidade ──
// Uma busca só, de hoje até 4 meses (ou até a saída escolhida, se for mais longe).
const unavailable = ref<Record<number, string[]> | null>(null)
let loadedUntil = ''
async function loadAvailability() {
  const to = [addDaysIso(today.value, 120), hasDates.value ? addDaysIso(checkOut.value, 1) : ''].sort().at(-1)!
  if (loadedUntil >= to) return
  try {
    const res = await api<{ accommodations: { id: number, unavailable: string[] }[] }>('/availability', { query: { from: today.value, to } })
    unavailable.value = Object.fromEntries(res.accommodations.map(a => [a.id, a.unavailable]))
    loadedUntil = to
  }
  catch {
    // Sem a disponibilidade os cartões só não mostram "livre"; o Reservar confere de novo.
  }
}
onMounted(loadAvailability)
watch(checkOut, () => { if (hasDates.value) loadAvailability() })

// ── Total exato com as datas (o mesmo orçamento do Reservar) ──
const quotes = ref<Record<number, { quote: CatalogState['quote'], error: string | null }>>({})
const quoting = ref(false)
let round = 0
let timer: ReturnType<typeof setTimeout> | undefined
watch([search, unavailable], () => {
  clearTimeout(timer)
  timer = setTimeout(runQuotes, 250)
}, { immediate: true })
async function runQuotes() {
  const s = search.value
  const mine = ++round
  if (!s || !import.meta.client) {
    quotes.value = {}
    return
  }
  quoting.value = true
  const targets = accommodations.value.filter(a => !isHourly(a) && a.max_guests >= s.guests && free(a) !== false)
  const results = await Promise.all(targets.map(async (a) => {
    try {
      const q = await api<Quote>('/quote', { method: 'POST', body: { accommodation_ids: [a.id], check_in: s.checkIn, check_out: s.checkOut, guests: s.guests } })
      return [a.id, { quote: { total: q.total, nights: q.nights, pixTotal: q.payment?.pix_total ?? q.total, pixPercent: q.payment?.pix_percent ?? 0 }, error: null }] as const
    }
    catch (e) {
      return [a.id, { quote: null, error: apiError(e) }] as const
    }
  }))
  if (mine !== round) return
  quotes.value = Object.fromEntries(results)
  quoting.value = false
}

// Livre nas datas? (null enquanto a disponibilidade não chegou). Por hora: o dia da entrada.
function free(acc: Property['accommodations'][number]): boolean | null {
  if (!hasDates.value || !unavailable.value) return null
  const taken = unavailable.value[acc.id] ?? []
  return isHourly(acc) ? !taken.includes(checkIn.value) : nightsFree(taken, checkIn.value, checkOut.value)
}

const states = computed<Record<number, CatalogState>>(() => Object.fromEntries(accommodations.value.map((a) => {
  const q = quotes.value[a.id]
  return [a.id, {
    free: free(a),
    fits: !hasDates.value || a.max_guests >= guests.value,
    weekend: !hasDates.value && unavailable.value ? nextFreeWeekend(unavailable.value[a.id] ?? [], today.value, loadedUntil, a.min_nights) : null,
    quote: q?.quote ?? null,
    // Uma recusa do orçamento (mínimo de diárias, por exemplo) vira o aviso do cartão.
    quoteError: q?.error ?? null,
    quoting: quoting.value && !q,
  } satisfies CatalogState]
})))

const fits = (a: Property['accommodations'][number]) => states.value[a.id]!.fits && states.value[a.id]!.free !== false && !states.value[a.id]!.quoteError
const visible = computed(() => sortCatalog(
  accommodations.value.filter(a => kind.value === 'todas' || (kind.value === 'eventos') === isHourly(a)),
  sort.value,
  hasDates.value ? fits : undefined,
))
const available = computed(() => visible.value.filter(fits).length)
const summary = computed(() => {
  const n = visible.value.length
  if (!hasDates.value) return `${plural(n, 'acomodação', 'acomodações')}`
  if (!unavailable.value) return 'Procurando as livres...'
  const range = `${formatRange(checkIn.value, checkOut.value)} · ${plural(nights.value, 'diária', 'diárias')} · ${plural(guests.value, 'pessoa', 'pessoas')}`
  return available.value ? `${available.value} de ${n} livres · ${range}` : `Nenhuma livre · ${range}`
})
const maxGuests = computed(() => Math.max(1, ...accommodations.value.map(a => a.max_guests)))
const tooBig = computed(() => hasDates.value && guests.value > Math.max(...accommodations.value.filter(a => !isHourly(a)).map(a => a.max_guests), 0))

const KINDS: { value: CatalogKind, label: string }[] = [
  { value: 'todas', label: 'Todas' },
  { value: 'estadias', label: 'Para se hospedar' },
  { value: 'eventos', label: 'Eventos e day use' },
]
</script>

<template>
  <section class="catalog">
    <div class="container">
      <form class="search" role="search" aria-label="Buscar acomodações" @submit.prevent>
        <label class="field" for="cat-in">
          <span><CalendarDays :size="16" aria-hidden="true" /> Entrada</span>
          <input id="cat-in" v-model="checkIn" type="date" :min="today" :max="windowEnd">
        </label>
        <label class="field" for="cat-out">
          <span><CalendarDays :size="16" aria-hidden="true" /> Saída</span>
          <input id="cat-out" v-model="checkOut" type="date" :min="checkIn ? addDaysIso(checkIn, 1) : addDaysIso(today, 1)" :max="windowEnd">
        </label>
        <div class="field">
          <span id="cat-guests-label"><Users :size="16" aria-hidden="true" /> Pessoas</span>
          <div class="stepper" role="group" aria-labelledby="cat-guests-label">
            <button type="button" aria-label="Menos uma pessoa" :disabled="guests <= 1" @click="guests--"><Minus :size="16" /></button>
            <output aria-live="polite">{{ guests }}</output>
            <button type="button" aria-label="Mais uma pessoa" :disabled="guests >= maxGuests" @click="guests++"><Plus :size="16" /></button>
          </div>
        </div>
        <button v-if="checkIn || checkOut" type="button" class="clear" @click="clearDates">Limpar datas</button>
      </form>

      <div class="toolbar">
        <div v-if="hasHourly && hasNightly" class="kinds" role="radiogroup" aria-label="Tipo">
          <button v-for="k in KINDS" :key="k.value" type="button" role="radio" :aria-checked="kind === k.value" @click="kind = k.value">{{ k.label }}</button>
        </div>
        <p class="count" aria-live="polite">{{ summary }}</p>
        <label class="sort" for="cat-sort">Ordenar
          <select id="cat-sort" v-model="sort">
            <option value="recomendadas">Recomendadas</option>
            <option value="menor-preco">Menor preço</option>
            <option value="mais-espaco">Mais espaço</option>
          </select>
        </label>
      </div>

      <ul class="trust" aria-label="Por que reservar aqui">
        <li><BadgeCheck :size="18" aria-hidden="true" /> Reserva direta com a pousada, sem taxa de aplicativo</li>
        <li v-if="pays"><Zap :size="18" aria-hidden="true" /> Confirmação na hora, assim que o pagamento é aprovado</li>
        <li v-else><Zap :size="18" aria-hidden="true" /> A pousada responde rápido pelo WhatsApp</li>
        <li><ShieldCheck :size="18" aria-hidden="true" /> {{ pays ? 'Pix ou cartão num checkout seguro' : 'Você só paga depois que a pousada confirmar' }}</li>
      </ul>

      <p v-if="tooBig" class="notice">
        Nenhuma acomodação recebe {{ guests }} pessoas sozinha. Para grupos, dá para reservar mais de uma ao mesmo tempo em
        <NuxtLink :to="`/reservar?entrada=${checkIn}&saida=${checkOut}`">Reservar</NuxtLink>.
      </p>

      <TransitionGroup tag="div" name="cards" class="grid">
        <SiteCatalogCard v-for="(acc, i) in visible" :key="acc.id" :acc="acc" :state="states[acc.id]!" :search="search" :eager="i < 3" />
      </TransitionGroup>
      <div v-if="!visible.length" class="empty">
        <p>Nenhuma acomodação nesse filtro.</p>
        <button type="button" class="btn btn-sm" @click="kind = 'todas'">Ver todas</button>
      </div>
    </div>
  </section>
</template>

<style scoped lang="scss">
.catalog { background: var(--soft); padding: clamp(1.5rem, 4vw, 2.5rem) 0 clamp(4rem, 9vw, 6.5rem); }
// Busca: um cartão branco no topo, respondendo enquanto o hóspede escolhe.
.search {
  display: grid; gap: .6rem; grid-template-columns: repeat(3, minmax(0, 1fr)) auto; align-items: end;
  padding: .9rem; border-radius: 18px; background: var(--card); box-shadow: 0 18px 40px -22px rgb(20 40 30 / .45);
  @include below(md) { grid-template-columns: repeat(2, minmax(0, 1fr)); }
}
.field { display: grid; gap: .3rem; min-width: 0; }
.field > span { display: inline-flex; align-items: center; gap: .35rem; font-weight: 700; font-size: .82rem; color: var(--muted); }
.field input { width: 100%; min-height: 48px; }
.stepper { display: flex; align-items: center; justify-content: space-between; min-height: 48px; padding: 0 .3rem; border: 1.5px solid var(--line); border-radius: 10px; }
.stepper button { display: grid; place-items: center; width: 40px; height: 40px; border: 0; border-radius: 8px; background: var(--soft); color: var(--text); cursor: pointer; }
.stepper button:disabled { opacity: .4; cursor: default; }
.stepper output { font-weight: 800; font-size: 1.05rem; font-variant-numeric: tabular-nums; }
.clear { align-self: center; min-height: 44px; padding: 0 .8rem; border: 0; background: none; font: inherit; font-weight: 700; color: var(--brand-text); cursor: pointer; text-decoration: underline; text-underline-offset: 3px; }
@include below(md) { .clear { justify-self: start; } }

.toolbar { display: flex; flex-wrap: wrap; align-items: center; gap: .7rem 1.2rem; margin: 1.4rem 0 .8rem; }
.kinds { display: flex; flex-wrap: wrap; gap: .4rem; }
.kinds button {
  min-height: 40px; padding: 0 .9rem; border-radius: 999px; border: 1.5px solid var(--line); background: var(--card);
  font: inherit; font-weight: 700; font-size: .88rem; color: var(--text); cursor: pointer;
  &[aria-checked="true"] { background: var(--text); border-color: var(--text); color: var(--card); }
}
.count { margin: 0; flex: 1; min-width: 12rem; color: var(--muted); font-weight: 600; font-size: .92rem; }
.sort { display: inline-flex; align-items: center; gap: .5rem; font-weight: 700; font-size: .88rem; }
.sort select { min-height: 40px; }
.trust { list-style: none; margin: 0 0 1.4rem; padding: 0; display: flex; flex-wrap: wrap; gap: .5rem 1.4rem; color: var(--ink-soft-day); font-size: .88rem; font-weight: 600; }
.trust li { display: inline-flex; align-items: center; gap: .4rem; }
.trust svg { color: var(--brand-text); flex: none; }
.notice { margin: 0 0 1rem; padding: .8rem 1rem; border-radius: 12px; background: var(--warn-soft); color: var(--warn-ink); font-weight: 600; }
.notice a { color: inherit; }
.grid { position: relative; display: grid; gap: clamp(1rem, 2.5vw, 1.6rem); grid-template-columns: repeat(auto-fill, minmax(min(100%, 320px), 1fr)); }
.empty { display: grid; justify-items: center; gap: .8rem; padding: 3rem 1rem; color: var(--muted); }
.empty p { margin: 0; }
// Reordenar: os cartões deslizam para o novo lugar.
.cards-move { transition: transform $dur-lg $ease-move; }
.cards-enter-active { @include motion-enter; }
.cards-leave-active { position: absolute; opacity: 0; transition: opacity $dur-xs $ease-exit; }
.cards-enter-from { opacity: 0; transform: translateY($dist-sm); }
@include reduced-motion { .cards-move { transition: none; } .cards-enter-from { transform: none; } }
</style>
