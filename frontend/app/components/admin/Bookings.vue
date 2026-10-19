<script setup lang="ts">
import type { AdminBooking } from '~/composables/useAdminApi'
import type { BookingScope } from '~/utils/bookings'

// Aba Reservas: todas as reservas da pousada, separadas por situação (próximas, em andamento,
// concluídas, passadas, canceladas), com contagem em cada filtro e busca por nome, código ou telefone.
const props = defineProps<{ today: string }>()
const emit = defineEmits<{ message: [text: string], changed: [] }>()

const api = useAdminApi()
const scope = ref<BookingScope>('upcoming')
const bookings = ref<AdminBooking[]>([])
const counts = ref<Partial<Record<BookingScope, number>>>({})
const search = ref('')
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
    <div class="filters" role="group" aria-label="Filtrar reservas">
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

    <div class="tools">
      <label class="search" for="booking-search">
        <span class="sr-only">Buscar reserva</span>
        <input id="booking-search" v-model="search" type="search" placeholder="Buscar por nome, código, telefone ou acomodação" autocomplete="off">
      </label>
      <p v-if="!loading && shown.length" class="summary">
        {{ plural(shown.length, 'reserva', 'reservas') }}<template v-if="confirmedTotal"> · {{ formatMoney(confirmedTotal) }} confirmados</template>
      </p>
    </div>

    <div v-if="error" class="load-error" role="alert">
      <p>Não foi possível carregar as reservas. {{ error }}</p>
      <button type="button" class="btn btn-sm" @click="load">Tentar de novo</button>
    </div>
    <p v-else-if="loading && !bookings.length" class="empty" aria-busy="true">Carregando...</p>
    <p v-else-if="!shown.length" class="empty">{{ emptyText }}</p>
    <div v-else class="list" :class="{ stale: loading }">
      <AdminBookingItem
        v-for="booking in shown"
        :key="booking.id"
        :booking="booking"
        :today="props.today"
        :busy="busyId === booking.id"
        @decline="cancel(booking)"
      />
    </div>
  </section>
</template>

<style scoped lang="scss">
.bookings { display: grid; gap: 1rem; }
.filters { display: flex; gap: .4rem; overflow-x: auto; padding-bottom: .2rem; scrollbar-width: thin; }
.filters button {
  display: inline-flex; align-items: center; gap: .45rem; flex: none;
  min-height: 40px; padding: 0 .9rem; border-radius: 999px; border: 1px solid var(--line); background: var(--card);
  font: inherit; font-size: .88rem; font-weight: 700; color: var(--text); cursor: pointer; white-space: nowrap;
  &[aria-pressed="true"] { background: var(--night); border-color: var(--night); color: var(--on-night); }
}
.n { min-width: 1.4rem; padding: .05rem .4rem; border-radius: 999px; background: var(--soft); color: var(--muted); font-size: .76rem; text-align: center; }
[aria-pressed="true"] .n { background: rgb(255 255 255 / .16); color: var(--on-night); }
.tools { display: flex; flex-wrap: wrap; align-items: center; gap: .6rem 1rem; }
.search { flex: 1 1 260px; }
.summary { margin: 0; color: var(--muted); font-size: .88rem; font-weight: 500; }
.list { display: grid; gap: 1rem; transition: opacity $dur-sm $ease-move; }
.list.stale { opacity: .55; }
.empty { background: var(--card); border: 1px dashed var(--line); border-radius: var(--radius); padding: 2rem 1.2rem; text-align: center; color: var(--muted); margin: 0; }
.load-error { background: var(--card); border: 1px solid var(--line); border-left: 4px solid var(--danger); border-radius: var(--radius); padding: 1rem 1.1rem; display: grid; gap: .8rem; justify-items: start; }
.load-error p { margin: 0; }
</style>
