<script setup lang="ts">
import type { AdminBooking } from '~/composables/useAdminApi'
import type { BookingScope } from '~/utils/bookings'
import { CalendarSearch, Search } from 'lucide-vue-next'

// Aba Reservas: todas as reservas da pousada, separadas por situação (próximas, em andamento,
// concluídas, passadas, canceladas), com contagem em cada filtro e busca por nome, código ou telefone.
const props = defineProps<{ today: string }>()
const emit = defineEmits<{ message: [text: string], changed: [] }>()

const api = useAdminApi()
const scope = ref<BookingScope>('upcoming')
const bookings = ref<AdminBooking[]>([])
const counts = ref<Partial<Record<BookingScope, number>>>({})
const search = ref('')
const view = useViewMode('reservas')
const loading = ref(true)
const error = ref('')
const busyId = ref<number | null>(null)

// Respostas fora de ordem (trocar de filtro rápido) não podem sobrescrever a lista do filtro atual.
let request = 0
async function load() {
  const id = ++request
  loading.value = true
  error.value = ''
  try {
    const res = await api<{ data: AdminBooking[], meta: { counts: Record<BookingScope, number> } }>('/bookings', { query: { scope: scope.value } })
    if (id !== request) return
    bookings.value = res.data
    counts.value = res.meta.counts
  }
  catch (e) {
    if (id === request) error.value = apiError(e)
  }
  finally {
    if (id === request) loading.value = false
  }
}
onMounted(load)
watch(scope, load)

const shown = computed(() => bookings.value.filter(b => matchesSearch(b, search.value)))
const emptyText = computed(() => search.value.trim()
  ? `Nenhuma reserva encontrada para "${search.value.trim()}" neste filtro.`
  : BOOKING_SCOPES.find(s => s.scope === scope.value)?.empty)
// Soma do que já entrou ou vai entrar: só reservas confirmadas contam.
const confirmedTotal = computed(() => shown.value.filter(b => b.status === 'confirmed').reduce((n, b) => n + b.total, 0))

async function cancel(booking: AdminBooking) {
  busyId.value = booking.id
  try {
    await api(`/bookings/${booking.id}/decline`, { method: 'POST' })
    emit('message', `Reserva de ${booking.guest_name} cancelada. As datas foram liberadas.`)
    emit('changed')
    await load()
  }
  catch (e) {
    emit('message', apiError(e))
  }
  finally {
    busyId.value = null
  }
}
</script>

<template>
  <section class="bookings">
    <header class="page-head a-rise">
      <h1>Reservas</h1>
      <p>Todas as estadias da pousada, das que estão por vir às que já passaram.</p>
    </header>

    <div class="filters a-rise" style="--i: 1" role="group" aria-label="Filtrar reservas">
      <button
        v-for="s in BOOKING_SCOPES"
        :key="s.scope"
        type="button"
        :aria-pressed="scope === s.scope"
        @click="scope = s.scope"
      >
        {{ s.label }}
        <span v-if="counts[s.scope] !== undefined" class="n">{{ counts[s.scope] }}</span>
      </button>
    </div>

    <div class="tools a-rise" style="--i: 2">
      <label class="search" for="booking-search">
        <Search :size="18" aria-hidden="true" />
        <span class="sr-only">Buscar reserva</span>
        <input id="booking-search" v-model="search" type="search" placeholder="Buscar por nome, código, telefone ou acomodação" autocomplete="off">
      </label>
      <AdminViewToggle v-model="view" />
      <Transition name="a-tab">
        <p v-if="!loading && shown.length" class="summary">
          {{ plural(shown.length, 'reserva', 'reservas') }}<template v-if="confirmedTotal"> · <strong>{{ formatMoney(confirmedTotal) }}</strong> confirmados</template>
        </p>
      </Transition>
    </div>

    <div v-if="error" class="a-card load-error" role="alert">
      <p>Não foi possível carregar as reservas. {{ error }}</p>
      <button type="button" class="btn btn-sm" @click="load">Tentar de novo</button>
    </div>
    <AdminLoading v-else-if="loading && !bookings.length" text="Carregando reservas..." :cards="2" height="190px" />
    <div v-else-if="!shown.length" class="a-card empty">
      <span class="empty-ico"><CalendarSearch :size="28" aria-hidden="true" /></span>
      <p>{{ emptyText }}</p>
    </div>
    <TransitionGroup v-else tag="div" name="a-list" class="items" :class="[view, { stale: loading }]">
      <AdminBookingItem
        v-for="(booking, i) in shown"
        :key="booking.id"
        class="a-rise"
        :style="{ '--i': Math.min(i, 6) }"
        :booking="booking"
        :today="props.today"
        :busy="busyId === booking.id"
        @decline="cancel(booking)"
      />
    </TransitionGroup>
  </section>
</template>

<style scoped lang="scss">
.bookings { display: grid; gap: 1.1rem; max-width: 1240px; }
.page-head h1 { @include display(clamp(2rem, 4vw, 2.8rem)); margin: 0 0 .3rem; }
.page-head p { margin: 0; color: var(--muted); }
.filters { display: flex; gap: .4rem; overflow-x: auto; padding: .2rem .1rem .4rem; margin: 0 -.1rem; scrollbar-width: none; }
.filters button {
  position: relative; isolation: isolate; display: inline-flex; align-items: center; gap: .45rem; flex: none;
  min-height: 42px; padding: 0 1rem; border-radius: 999px; border: 0; background: var(--a-card); box-shadow: var(--a-shadow);
  font: inherit; font-size: .88rem; font-weight: 700; color: var(--muted); cursor: pointer; white-space: nowrap;
  // O fundo escuro do filtro ativo entra por opacidade.
  &::before { content: ""; position: absolute; inset: 0; border-radius: inherit; background: var(--night); opacity: 0; transition: opacity $dur-sm $ease-move; z-index: -1; }
  &[aria-pressed="true"] { color: var(--on-night); }
  &[aria-pressed="true"]::before { opacity: 1; }
}
.n { min-width: 1.4rem; padding: .05rem .4rem; border-radius: 999px; background: var(--a-sunk); color: var(--muted); font-size: .74rem; text-align: center; }
[aria-pressed="true"] .n { background: rgb(255 255 255 / .16); color: var(--on-night); }
.tools { display: flex; flex-wrap: wrap; align-items: center; gap: .6rem 1rem; }
.search { position: relative; flex: 1 1 280px; max-width: 640px; }
.search svg { position: absolute; left: .9rem; top: 50%; transform: translateY(-50%); color: var(--muted); pointer-events: none; }
.search input { padding-left: 2.6rem; border: 0; border-radius: 14px; box-shadow: var(--a-shadow); min-height: 46px; }
.summary { margin: 0; color: var(--muted); font-size: .9rem; }
.summary strong { color: var(--text); }
.items { position: relative; display: grid; gap: 1rem; transition: opacity $dur-sm $ease-move; }
// Lista com largura de leitura; grade com colunas de no mínimo 320 px (o cartão se ajeita à coluna).
.items.list { max-width: 980px; }
.items.grid { grid-template-columns: repeat(auto-fill, minmax(min(100%, 320px), 1fr)); align-items: start; }
.summary { flex-basis: 100%; }
.items.stale { opacity: .55; }
.empty { display: grid; justify-items: center; gap: .6rem; padding: 2.4rem 1.2rem; text-align: center; color: var(--muted); }
.empty p { margin: 0; }
.empty-ico { display: grid; place-items: center; width: 60px; height: 60px; border-radius: 18px; background: var(--a-sunk); color: var(--brand-text); }
.load-error { padding: 1rem 1.2rem; display: grid; gap: .8rem; justify-items: start; border-left: 4px solid var(--danger); }
.load-error p { margin: 0; }
</style>
