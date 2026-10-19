<script setup lang="ts">
import type { AdminAccommodation, AdminSeason } from '~/composables/useAdminApi'

// Temporadas e feriados: preço próprio (e mínimo de diárias, se quiser) num intervalo de datas.
// Cada linha salva na hora.
const props = defineProps<{ accommodation: AdminAccommodation }>()
const emit = defineEmits<{ updated: [acc: AdminAccommodation] }>()

const api = useAdminApi()
type Draft = Omit<AdminSeason, 'id'> & { id: number | null }
const empty = (): Draft => ({ id: null, name: '', starts_on: '', ends_on: '', price: props.accommodation.weekend_price ?? props.accommodation.base_price, weekend_price: null, min_nights: null })

const editing = ref<Draft | null>(null)
const busy = ref(false)
const error = ref('')

// Feriados comuns para começar rápido (datas do próximo ano em que caem).
const year = new Date().getFullYear()
const QUICK = [
  { name: 'Réveillon', starts_on: `${year}-12-29`, ends_on: `${year + 1}-01-01`, min_nights: 3 },
  { name: 'Natal', starts_on: `${year}-12-23`, ends_on: `${year}-12-25`, min_nights: 2 },
  { name: 'Férias de julho', starts_on: `${year + 1}-07-01`, ends_on: `${year + 1}-07-31`, min_nights: null },
]

function add(preset?: Partial<Draft>) {
  editing.value = { ...empty(), ...preset }
  error.value = ''
}
function edit(s: AdminSeason) {
  editing.value = { ...s }
  error.value = ''
}

async function save() {
  if (!editing.value) return
  busy.value = true
  error.value = ''
  const { id, ...body } = editing.value
  try {
    const res = await api<{ data: AdminAccommodation }>(
      id ? `/accommodations/${props.accommodation.id}/seasons/${id}` : `/accommodations/${props.accommodation.id}/seasons`,
      { method: id ? 'PUT' : 'POST', body },
    )
    emit('updated', res.data)
    editing.value = null
  }
  catch (e) {
    error.value = apiError(e)
  }
  finally {
    busy.value = false
  }
}

async function remove(s: AdminSeason) {
  if (!window.confirm(`Excluir "${s.name}"?`)) return
  busy.value = true
  try {
    const res = await api<{ data: AdminAccommodation }>(`/accommodations/${props.accommodation.id}/seasons/${s.id}`, { method: 'DELETE' })
    emit('updated', res.data)
  }
  catch (e) {
    error.value = apiError(e)
  }
  finally {
    busy.value = false
  }
}

const date = (iso: string) => formatIso(iso, { day: '2-digit', month: 'short', year: 'numeric' })
</script>

<template>
  <section class="card">
    <div class="title">
      <h3>Temporadas e feriados</h3>
      <p class="hint">Nessas datas vale o preço daqui, no lugar da diária normal. Dá para exigir um mínimo de diárias, como 3 no réveillon.</p>
    </div>

    <ul v-if="accommodation.seasons.length" class="list">
      <li v-for="s in accommodation.seasons" :key="s.id">
        <div>
          <strong>{{ s.name }}</strong>
          <span>{{ date(s.starts_on) }} a {{ date(s.ends_on) }}</span>
        </div>
        <div class="price">
          <strong>{{ formatMoney(s.price) }}</strong>
          <span>
            <template v-if="s.weekend_price">sex. e sáb. {{ formatMoney(s.weekend_price) }} · </template>
            <template v-if="s.min_nights">mín. {{ s.min_nights }} diárias</template>
          </span>
        </div>
        <div class="actions">
          <button type="button" class="link" :disabled="busy" @click="edit(s)">Editar</button>
          <button type="button" class="link del" :disabled="busy" @click="remove(s)">Excluir</button>
        </div>
      </li>
    </ul>

    <form v-if="editing" class="form" @submit.prevent="save">
      <label for="season-name">Nome <input id="season-name" v-model="editing.name" required maxlength="80" placeholder="Ex.: Carnaval"></label>
      <div class="row">
        <label for="season-from">Primeira noite <input id="season-from" v-model="editing.starts_on" type="date" required></label>
        <label for="season-to">Última noite <input id="season-to" v-model="editing.ends_on" type="date" required :min="editing.starts_on"></label>
      </div>
      <div class="row">
        <label for="season-price">Diária <AdminMoneyInput id="season-price" v-model="editing.price" required /></label>
        <label for="season-weekend"><span>Sexta e sábado <span class="opt">opcional</span></span> <AdminMoneyInput id="season-weekend" v-model="editing.weekend_price" placeholder="Igual" /></label>
        <label for="season-min"><span>Mínimo de diárias <span class="opt">opcional</span></span> <input id="season-min" v-model.number="editing.min_nights" type="number" min="1" max="90"></label>
      </div>
      <p v-if="error" class="err" role="alert">{{ error }}</p>
      <div class="buttons">
        <button type="button" class="btn btn-ghost btn-sm" @click="editing = null">Cancelar</button>
        <button class="btn btn-sm" :disabled="busy">{{ busy ? 'Salvando...' : editing.id ? 'Salvar temporada' : 'Adicionar temporada' }}</button>
      </div>
    </form>
    <div v-else class="quick">
      <button type="button" class="btn btn-ghost btn-sm" @click="add()">+ Nova temporada</button>
      <button v-for="q in QUICK" :key="q.name" type="button" class="chip" @click="add(q)">+ {{ q.name }}</button>
    </div>
    <p v-if="error && !editing" class="err" role="alert">{{ error }}</p>
  </section>
</template>

<style scoped lang="scss">
.card { background: var(--a-card); border-radius: var(--a-radius); box-shadow: var(--a-shadow); padding: 1.2rem; display: grid; gap: .9rem; min-width: 0; }
.title { display: grid; gap: .2rem; }
h3 { margin: 0; font-size: 1.25rem; }
.hint { margin: 0; color: var(--muted); font-size: .86rem; }
.list { list-style: none; margin: 0; padding: 0; display: grid; }
.list li { display: grid; grid-template-columns: minmax(0, 1.3fr) minmax(0, 1fr) auto; gap: .6rem 1rem; align-items: center; padding: .75rem 0; border-top: 1px solid var(--line); }
@include below(sm) { .list li { grid-template-columns: minmax(0, 1fr) auto; .price { grid-column: 1; } .actions { grid-row: 1 / 3; grid-column: 2; } } }
.list li > div { display: grid; }
.list span { color: var(--muted); font-size: .86rem; }
.price strong { font-variant-numeric: tabular-nums; }
.actions { display: flex; gap: .6rem; }
.link { background: none; border: 0; padding: .4rem 0; font: inherit; font-weight: 700; font-size: .88rem; color: var(--brand-text); cursor: pointer; }
.link.del { color: var(--danger); }
.form { display: grid; gap: .8rem; background: var(--a-sunk); border-radius: 14px; padding: 1rem; }
.form label { display: grid; gap: .3rem; font-weight: 700; font-size: .88rem; min-width: 0; }
.opt { font-weight: 400; color: var(--muted); }
.row { display: grid; gap: .8rem; grid-template-columns: repeat(auto-fit, minmax(min(100%, 160px), 1fr)); }
.buttons { display: flex; justify-content: flex-end; gap: .5rem; }
.quick { display: flex; flex-wrap: wrap; align-items: center; gap: .4rem; }
.chip { border: 1px dashed var(--line); background: none; border-radius: 999px; padding: .3rem .8rem; font: inherit; font-size: .84rem; cursor: pointer; min-height: 36px; }
.err { margin: 0; color: var(--danger); font-weight: 700; }
</style>
