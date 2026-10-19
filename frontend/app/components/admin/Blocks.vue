<script setup lang="ts">
import type { AdminBlock, AdminMe } from '~/composables/useAdminApi'
import { CalendarX2, Trash2 } from 'lucide-vue-next'

// Datas bloqueadas: manutenção, uso próprio ou reserva feita no Airbnb e no Booking.
const props = defineProps<{ me: AdminMe | null }>()
const emit = defineEmits<{ changed: [] }>()

const api = useAdminApi()
const toast = useToast()
const blocks = ref<AdminBlock[]>([])
const loading = ref(true)

async function load() {
  try {
    blocks.value = (await api<{ data: AdminBlock[] }>('/blocked-dates')).data
  }
  catch (e) {
    toast.show(apiError(e), 'error')
  }
  finally {
    loading.value = false
  }
}
onMounted(load)

const form = reactive({ accommodation_id: 0, starts_on: '', ends_on: '', reason: '' })
watch(() => props.me, (me) => {
  if (me && !form.accommodation_id) form.accommodation_id = me.property.accommodations[0]?.id ?? 0
}, { immediate: true })
const error = ref('')
const saving = ref(false)
const REASONS = ['Reserva pelo Airbnb', 'Reserva pelo Booking', 'Manutenção', 'Uso próprio']

async function add() {
  error.value = ''
  saving.value = true
  try {
    await api('/blocked-dates', { method: 'POST', body: { ...form, reason: form.reason || null } })
    Object.assign(form, { starts_on: '', ends_on: '', reason: '' })
    toast.show('Datas bloqueadas. Elas já aparecem ocupadas no site.')
    await load()
    emit('changed')
  }
  catch (e) {
    error.value = apiError(e)
  }
  finally {
    saving.value = false
  }
}

const removingId = ref<number | null>(null)
async function remove(block: AdminBlock) {
  if (removingId.value) return
  removingId.value = block.id
  try {
    await api(`/blocked-dates/${block.id}`, { method: 'DELETE' })
    blocks.value = blocks.value.filter(b => b.id !== block.id)
    toast.show('Datas liberadas para reserva.')
    emit('changed')
  }
  catch (e) {
    toast.show(apiError(e), 'error')
  }
  finally {
    removingId.value = null
  }
}

const accName = (id: number) => props.me?.property.accommodations.find(a => a.id === id)?.name ?? ''
const range = (b: AdminBlock) => b.starts_on === b.ends_on
  ? formatIso(b.starts_on, { day: 'numeric', month: 'short' })
  : `${formatIso(b.starts_on, { day: 'numeric', month: 'short' })} → ${formatIso(b.ends_on, { day: 'numeric', month: 'short' })}`
</script>

<template>
  <div class="blocks">
    <header class="page-head a-rise">
      <h1>Datas bloqueadas</h1>
      <p>Bloqueie as noites que não podem ser reservadas pelo site: manutenção, uso próprio ou uma reserva que chegou pelo Airbnb e pelo Booking.</p>
    </header>

    <div class="cols">
      <form class="a-card panel a-rise" style="--i: 1" @submit.prevent="add">
        <h2>Bloquear noites</h2>
        <label for="block-acc">Acomodação
          <select id="block-acc" v-model.number="form.accommodation_id">
            <option v-for="acc in me?.property.accommodations" :key="acc.id" :value="acc.id">{{ acc.name }}</option>
          </select>
        </label>
        <div class="row">
          <label for="block-from">Primeira noite <input id="block-from" v-model="form.starts_on" type="date" required></label>
          <label for="block-to">Última noite <input id="block-to" v-model="form.ends_on" type="date" required :min="form.starts_on"></label>
        </div>
        <label for="block-reason"><span>Motivo <span class="opt">opcional</span></span>
          <input id="block-reason" v-model="form.reason" placeholder="Ex.: reserva pelo Airbnb">
        </label>
        <div class="reasons">
          <button v-for="r in REASONS" :key="r" type="button" :aria-pressed="form.reason === r" @click="form.reason = r">{{ r }}</button>
        </div>
        <p v-if="error" class="err" role="alert">{{ error }}</p>
        <button class="btn" :disabled="saving">{{ saving ? 'Bloqueando...' : 'Bloquear datas' }}</button>
      </form>

      <section class="a-card panel a-rise" style="--i: 2" aria-labelledby="blocks-title">
        <h2 id="blocks-title">Bloqueios</h2>
        <div v-if="loading" class="skel"><span v-for="n in 3" :key="n" class="a-skeleton" /></div>
        <p v-else-if="!blocks.length" class="empty"><CalendarX2 :size="28" aria-hidden="true" /> Nenhuma data bloqueada. Todas as noites livres podem ser reservadas.</p>
        <TransitionGroup v-else tag="ul" name="a-list" class="list">
          <li v-for="b in blocks" :key="b.id">
            <span class="when">{{ range(b) }}</span>
            <span class="what"><strong>{{ accName(b.accommodation_id) }}</strong><small v-if="b.reason">{{ b.reason }}</small></span>
            <button type="button" class="icon" :disabled="removingId !== null" :aria-label="`Desbloquear ${accName(b.accommodation_id)}, ${range(b)}`" title="Desbloquear" @click="remove(b)">
              <Trash2 :size="18" />
            </button>
          </li>
        </TransitionGroup>
      </section>
    </div>
  </div>
</template>

<style scoped lang="scss">
.blocks { display: grid; gap: 1.2rem; }
.page-head h1 { @include display(clamp(2rem, 4vw, 2.8rem)); margin: 0 0 .3rem; }
.page-head p { margin: 0; color: var(--muted); max-width: 60ch; }
.cols { display: grid; gap: 1rem; grid-template-columns: minmax(0, 1fr) minmax(0, 1fr); align-items: start; @include below(lg) { grid-template-columns: minmax(0, 1fr); } }
.panel { padding: 1.3rem; display: grid; gap: .9rem; }
h2 { margin: 0; font-size: 1.35rem; }
label { display: grid; gap: .3rem; font-weight: 700; font-size: .88rem; }
.opt { font-weight: 400; color: var(--muted); }
.row { display: grid; grid-template-columns: 1fr 1fr; gap: .7rem; }
.reasons { display: flex; flex-wrap: wrap; gap: .35rem; margin-top: -.3rem; }
.reasons button {
  min-height: 34px; padding: 0 .75rem; border-radius: 999px; border: 0; background: var(--a-sunk); font: inherit; font-size: .8rem; font-weight: 600; color: var(--text); cursor: pointer;
  &[aria-pressed="true"] { background: var(--brand); color: var(--brand-ink); }
}
.err { margin: 0; color: var(--danger); font-weight: 700; }
.skel { display: grid; gap: .5rem; }
.skel span { height: 52px; }
.empty { margin: 0; display: grid; justify-items: center; gap: .5rem; padding: 1.6rem .5rem; color: var(--muted); text-align: center; }
.empty svg { color: var(--brand-text); }
.list { position: relative; list-style: none; margin: 0; padding: 0; display: grid; }
.list li { display: flex; align-items: center; gap: .8rem; padding: .7rem 0; border-top: 1px solid var(--a-sunk); background: var(--a-card); }
.list li:first-child { border-top: 0; }
.when { flex: none; min-width: 7.5rem; padding: .3rem .6rem; border-radius: 10px; background: var(--a-sunk); font-weight: 700; font-size: .84rem; text-align: center; font-variant-numeric: tabular-nums; }
.what { display: grid; flex: 1; min-width: 0; }
.what small { color: var(--muted); }
.icon { display: grid; place-items: center; width: 40px; height: 40px; border: 0; border-radius: 10px; background: none; color: var(--muted); cursor: pointer; }
.icon:hover { color: var(--danger); }
</style>
