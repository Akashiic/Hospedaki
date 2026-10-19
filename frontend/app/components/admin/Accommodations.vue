<script setup lang="ts">
import type { AdminAccommodation } from '~/composables/useAdminApi'
import { ArrowDown, ArrowUp, BedDouble, Eye, EyeOff, ImageOff, Plus } from 'lucide-vue-next'

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
    <header class="head a-rise">
      <div>
        <h1>Acomodações</h1>
        <p class="hint">Na ordem em que aparecem no site. Toque em uma para editar dados, preços, fotos e temporadas.</p>
      </div>
      <button type="button" class="btn" @click="openForm('new')"><Plus :size="18" aria-hidden="true" /> Nova acomodação</button>
    </header>

    <div v-if="error" class="a-card load-error" role="alert">
      <p>Não foi possível carregar as acomodações. {{ error }}</p>
      <button type="button" class="btn btn-sm" @click="load">Tentar de novo</button>
    </div>
    <div v-else-if="loading" class="skel" aria-busy="true"><span v-for="n in 3" :key="n" class="a-skeleton" /></div>
    <div v-else-if="!list.length" class="a-card empty">
      <span class="empty-ico"><BedDouble :size="30" aria-hidden="true" /></span>
      <p>Nenhuma acomodação ainda. Crie a primeira para ela aparecer no site.</p>
    </div>

    <TransitionGroup v-else tag="ol" name="a-list" class="list" :class="{ busy }">
      <li v-for="(a, i) in list" :key="a.id" class="a-card a-lift a-rise" :class="{ off: !a.active }" :style="{ '--i': i + 1 }">
        <button type="button" class="open" :aria-label="`Editar ${a.name}`" @click="openForm(a.id)">
          <span class="thumb">
            <img v-if="a.photos[0]" :src="a.photos[0].url" alt="" loading="lazy" decoding="async">
            <span v-else class="nophoto"><ImageOff :size="22" aria-hidden="true" /></span>
          </span>
          <span class="info">
            <strong>{{ a.name }}</strong>
            <span>a partir de {{ formatMoney(fromPrice(a)) }}/diária · até {{ a.max_guests }} pessoas · {{ plural(a.photos.length, 'foto', 'fotos') }}</span>
            <span class="badges">
              <span class="badge" :class="a.active ? 'on' : 'off'"><span class="a-dot" />{{ a.active ? 'Publicada' : 'Oculta' }}</span>
              <span v-if="a.upcoming_bookings_count" class="badge">{{ plural(a.upcoming_bookings_count, 'reserva pela frente', 'reservas pela frente') }}</span>
              <span v-if="a.best_for" class="badge">Destaque: {{ a.best_for === 'casal' ? 'a dois' : 'família' }}</span>
              <span v-if="!a.photos.length" class="badge warn">Falta foto</span>
            </span>
          </span>
        </button>
        <div class="tools">
          <button type="button" :disabled="busy || i === 0" :aria-label="`Subir ${a.name}`" @click="move(i, i - 1)"><ArrowUp :size="18" /></button>
          <button type="button" :disabled="busy || i === list.length - 1" :aria-label="`Descer ${a.name}`" @click="move(i, i + 1)"><ArrowDown :size="18" /></button>
          <button type="button" class="text" :disabled="busy" @click="toggleActive(a)">
            <component :is="a.active ? EyeOff : Eye" :size="16" aria-hidden="true" /> {{ a.active ? 'Ocultar' : 'Publicar' }}
          </button>
        </div>
      </li>
    </TransitionGroup>
  </section>
</template>

<style scoped lang="scss">
.accs { display: grid; gap: 1.1rem; max-width: 980px; }
.head { display: flex; flex-wrap: wrap; justify-content: space-between; align-items: end; gap: .8rem; }
.head h1 { @include display(clamp(2rem, 4vw, 2.8rem)); margin: 0 0 .3rem; }
.head .btn { gap: .4rem; }
.hint { margin: 0; color: var(--muted); }
.list { position: relative; list-style: none; margin: 0; padding: 0; display: grid; gap: .8rem; transition: opacity $dur-sm $ease-move; }
.list.busy { opacity: .7; }
.list li { display: grid; grid-template-columns: minmax(0, 1fr) auto; align-items: center; gap: .6rem; padding: .7rem .9rem .7rem .7rem; }
.list li.off .thumb img, .list li.off strong { opacity: .5; }
@include below(sm) { .list li { grid-template-columns: minmax(0, 1fr); } .tools { justify-content: flex-end; } }
.open { display: grid; grid-template-columns: 128px minmax(0, 1fr); gap: 1rem; align-items: center; background: none; border: 0; padding: 0; font: inherit; color: inherit; text-align: left; cursor: pointer; min-width: 0; }
@include below(sm) { .open { grid-template-columns: 92px minmax(0, 1fr); } }
.thumb { aspect-ratio: 4 / 3; border-radius: 12px; overflow: hidden; background: var(--a-sunk); display: grid; place-items: center; }
.thumb img { width: 100%; height: 100%; object-fit: cover; transition: transform $dur-2xl $ease-enter; }
@media (hover: hover) { .list li:hover .thumb img { transform: scale($zoom-sm); } }
.nophoto { color: var(--muted); }
.info { display: grid; gap: .3rem; min-width: 0; }
.info strong { font-family: var(--font-display); font-weight: 400; font-size: 1.45rem; line-height: 1.1; }
.info > span { color: var(--muted); font-size: .86rem; }
.badges { display: flex; flex-wrap: wrap; gap: .3rem; }
.badge { display: inline-flex; align-items: center; gap: .35rem; font-size: .74rem; font-weight: 700; padding: .18rem .6rem; border-radius: 999px; background: var(--a-sunk); color: var(--muted); }
.badge.on { background: color-mix(in srgb, var(--brand) 14%, white); color: var(--brand-text); }
.badge.off { background: var(--line); color: var(--text); }
.badge.warn { background: var(--warn-soft); color: var(--warn-ink); }
.tools { display: flex; align-items: center; gap: .3rem; }
.tools button { display: inline-grid; place-items: center; min-width: 40px; height: 40px; border: 0; background: var(--a-sunk); border-radius: 12px; font: inherit; color: var(--text); cursor: pointer; }
.tools button:disabled { opacity: .35; cursor: default; }
.tools .text { display: inline-flex; align-items: center; gap: .35rem; padding: 0 .85rem; font-weight: 700; font-size: .86rem; color: var(--brand-text); }
.skel { display: grid; gap: .8rem; }
.skel span { height: 116px; border-radius: var(--a-radius); }
.empty { display: grid; justify-items: center; gap: .6rem; padding: 2.4rem 1.2rem; text-align: center; color: var(--muted); }
.empty p { margin: 0; }
.empty-ico { display: grid; place-items: center; width: 64px; height: 64px; border-radius: 20px; background: var(--a-sunk); color: var(--brand-text); }
.load-error { padding: 1rem 1.2rem; display: grid; gap: .8rem; justify-items: start; border-left: 4px solid var(--danger); }
.load-error p { margin: 0; }
</style>
