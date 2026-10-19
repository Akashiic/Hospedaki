<script setup lang="ts">
import type { AdminAccommodation } from '~/composables/useAdminApi'

// Aba Acomodações: a lista na ordem do site (com publicar/ocultar e reordenar) e o formulário
// de cada uma. "Nova acomodação" abre o formulário vazio.
defineProps<{ demo?: boolean }>()
const emit = defineEmits<{ message: [text: string], changed: [] }>()

const api = useAdminApi()
const list = ref<AdminAccommodation[]>([])
const loading = ref(true)
const error = ref('')
const busy = ref(false)
// null: lista; 'new': formulário vazio; número: editando essa acomodação.
const open = ref<number | 'new' | null>(null)
// A chave muda só ao abrir pela lista: criar uma acomodação nova mantém o mesmo formulário aberto.
const formKey = ref(0)
function openForm(target: number | 'new') {
  formKey.value++
  open.value = target
}
const opened = computed(() => typeof open.value === 'number' ? list.value.find(a => a.id === open.value) ?? null : null)

async function load() {
  error.value = ''
  try {
    list.value = (await api<{ data: AdminAccommodation[] }>('/accommodations')).data
  }
  catch (e) {
    error.value = apiError(e)
  }
  finally {
    loading.value = false
  }
}
onMounted(load)

function onSaved(acc: AdminAccommodation, created: boolean) {
  const i = list.value.findIndex(a => a.id === acc.id)
  if (i === -1) list.value.push(acc)
  else list.value[i] = acc
  if (created) open.value = acc.id
  emit('changed')
}
function onDeleted(id: number) {
  const name = list.value.find(a => a.id === id)?.name
  list.value = list.value.filter(a => a.id !== id)
  open.value = null
  emit('message', `${name ?? 'Acomodação'} excluída.`)
  emit('changed')
}

async function move(from: number, to: number) {
  if (to < 0 || to >= list.value.length) return
  const next = [...list.value]
  const [item] = next.splice(from, 1)
  next.splice(to, 0, item!)
  list.value = next
  busy.value = true
  try {
    await api('/accommodations/order', { method: 'PUT', body: { ids: next.map(a => a.id) } })
    emit('changed')
  }
  catch (e) {
    emit('message', apiError(e))
    await load()
  }
  finally {
    busy.value = false
  }
}

async function toggleActive(acc: AdminAccommodation) {
  busy.value = true
  try {
    const { photos, seasons, id, slug, shared_unit, sort_order, bookings_count, upcoming_bookings_count, ...fields } = acc
    const res = await api<{ data: AdminAccommodation }>(`/accommodations/${id}`, { method: 'PUT', body: { ...fields, active: !acc.active } })
    onSaved(res.data, false)
    emit('message', res.data.active ? `${acc.name} está publicada no site.` : `${acc.name} saiu do site e não recebe mais reservas.`)
  }
  catch (e) {
    emit('message', apiError(e))
  }
  finally {
    busy.value = false
  }
}

const fromPrice = (a: AdminAccommodation) => Math.min(a.base_price, a.weekend_price ?? a.base_price)
</script>

<template>
  <AdminAccommodationForm
    v-if="open !== null"
    :key="formKey"
    :accommodation="opened"
    :demo="demo"
    @saved="onSaved"
    @deleted="onDeleted"
    @back="open = null"
  />

  <section v-else class="accs">
    <header class="head">
      <div>
        <h2>Acomodações</h2>
        <p class="hint">Na ordem em que aparecem no site. Clique para editar dados, preços, fotos e temporadas.</p>
      </div>
      <button type="button" class="btn btn-sm" @click="openForm('new')">+ Nova acomodação</button>
    </header>

    <div v-if="error" class="load-error" role="alert">
      <p>Não foi possível carregar as acomodações. {{ error }}</p>
      <button type="button" class="btn btn-sm" @click="load">Tentar de novo</button>
    </div>
    <p v-else-if="loading" class="empty" aria-busy="true">Carregando...</p>
    <p v-else-if="!list.length" class="empty">Nenhuma acomodação ainda. Crie a primeira para ela aparecer no site.</p>

    <ol v-else class="list" :class="{ busy }">
      <li v-for="(a, i) in list" :key="a.id" :class="{ off: !a.active }">
        <button type="button" class="open" :aria-label="`Editar ${a.name}`" @click="openForm(a.id)">
          <span class="thumb">
            <img v-if="a.photos[0]" :src="a.photos[0].url" alt="" loading="lazy" decoding="async">
            <span v-else class="nophoto">Sem foto</span>
          </span>
          <span class="info">
            <strong>{{ a.name }}</strong>
            <span>a partir de {{ formatMoney(fromPrice(a)) }}/diária · até {{ a.max_guests }} pessoas · {{ plural(a.photos.length, 'foto', 'fotos') }}</span>
            <span class="badges">
              <span class="badge" :class="a.active ? 'on' : 'off'">{{ a.active ? 'Publicada' : 'Oculta' }}</span>
              <span v-if="a.upcoming_bookings_count" class="badge">{{ plural(a.upcoming_bookings_count, 'reserva pela frente', 'reservas pela frente') }}</span>
              <span v-if="a.best_for" class="badge">Destaque: {{ a.best_for === 'casal' ? 'a dois' : 'família' }}</span>
              <span v-if="!a.photos.length" class="badge warn">Falta foto</span>
            </span>
          </span>
        </button>
        <div class="tools">
          <button type="button" :disabled="busy || i === 0" :aria-label="`Subir ${a.name}`" @click="move(i, i - 1)">↑</button>
          <button type="button" :disabled="busy || i === list.length - 1" :aria-label="`Descer ${a.name}`" @click="move(i, i + 1)">↓</button>
          <button type="button" class="text" :disabled="busy" @click="toggleActive(a)">{{ a.active ? 'Ocultar' : 'Publicar' }}</button>
        </div>
      </li>
    </ol>
  </section>
</template>

<style scoped lang="scss">
.accs { display: grid; gap: 1rem; }
.head { display: flex; flex-wrap: wrap; justify-content: space-between; align-items: end; gap: .8rem; }
.head h2 { margin: 0; font-size: 1.6rem; }
.hint { margin: .2rem 0 0; color: var(--muted); font-size: .9rem; }
.list { list-style: none; margin: 0; padding: 0; display: grid; gap: .7rem; transition: opacity $dur-sm $ease-move; }
.list.busy { opacity: .6; }
.list li { display: grid; grid-template-columns: minmax(0, 1fr) auto; align-items: center; gap: .6rem; background: var(--card); border: 1px solid var(--line); border-radius: var(--radius); padding: .6rem .8rem .6rem .6rem; }
.list li.off .thumb, .list li.off strong { opacity: .55; }
@include below(sm) { .list li { grid-template-columns: minmax(0, 1fr); } .tools { justify-content: flex-end; } }
.open { display: grid; grid-template-columns: 112px minmax(0, 1fr); gap: .9rem; align-items: center; background: none; border: 0; padding: 0; font: inherit; color: inherit; text-align: left; cursor: pointer; min-width: 0; }
@include below(sm) { .open { grid-template-columns: 84px minmax(0, 1fr); } }
.thumb { aspect-ratio: 4 / 3; border-radius: 10px; overflow: hidden; background: var(--soft); display: grid; place-items: center; }
.thumb img { width: 100%; height: 100%; object-fit: cover; }
.nophoto { font-size: .75rem; color: var(--muted); }
.info { display: grid; gap: .25rem; min-width: 0; }
.info strong { font-size: 1.1rem; }
.info > span { color: var(--muted); font-size: .86rem; }
.badges { display: flex; flex-wrap: wrap; gap: .3rem; }
.badge { font-size: .74rem; font-weight: 700; padding: .12rem .55rem; border-radius: 999px; background: var(--soft); color: var(--muted); }
.badge.on { background: color-mix(in srgb, var(--brand) 14%, white); color: var(--brand-text); }
.badge.off { background: var(--line); color: var(--text); }
.badge.warn { background: var(--warn-soft); color: var(--warn-ink); }
.tools { display: flex; align-items: center; gap: .3rem; }
.tools button { min-width: 40px; height: 40px; border: 1px solid var(--line); background: var(--card); border-radius: 10px; font: inherit; cursor: pointer; }
.tools button:disabled { opacity: .35; cursor: default; }
.tools .text { padding: 0 .8rem; font-weight: 700; font-size: .86rem; color: var(--brand-text); }
.empty { background: var(--card); border: 1px dashed var(--line); border-radius: var(--radius); padding: 2rem 1.2rem; text-align: center; color: var(--muted); margin: 0; }
.load-error { background: var(--card); border: 1px solid var(--line); border-left: 4px solid var(--danger); border-radius: var(--radius); padding: 1rem 1.1rem; display: grid; gap: .8rem; justify-items: start; }
.load-error p { margin: 0; }
</style>
