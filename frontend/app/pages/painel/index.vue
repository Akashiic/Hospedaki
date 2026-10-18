<script setup lang="ts">
import type { AdminBlock, AdminBooking, AdminMe } from '~/composables/useAdminApi'

definePageMeta({ middleware: 'admin', layout: 'painel', pageTransition: { name: 'fade', mode: 'out-in' } })
useHead({ title: 'Painel da pousada', meta: [{ name: 'robots', content: 'noindex' }] })

const api = useAdminApi()
const token = useAdminToken()

type Tab = 'pending' | 'upcoming' | 'blocks'
const tab = ref<Tab>('pending')

const me = ref<AdminMe | null>(null)
const pending = ref<AdminBooking[]>([])
const upcoming = ref<AdminBooking[]>([])
const blocks = ref<AdminBlock[]>([])
const busyId = ref<number | null>(null)
const message = ref('')
watch(tab, () => { message.value = '' })

// Estado da carga: os textos de "nenhum pedido" só aparecem depois de uma carga que deu certo,
// e uma falha mostra o erro com "Tentar de novo" (em vez de ficar em "Carregando..." para sempre).
const loaded = ref(false)
const loadError = ref('')

async function load() {
  loadError.value = ''
  try {
    const [meRes, pendingRes, upcomingRes, blocksRes] = await Promise.all([
      api<AdminMe>('/me'),
      // A API devolve as listas inteiras (sem paginação): o painel só mostra o que está pela frente.
      api<{ data: AdminBooking[] }>('/bookings', { query: { status: 'pending' } }),
      api<{ data: AdminBooking[] }>('/bookings', { query: { status: 'confirmed', upcoming: 1 } }),
      api<{ data: AdminBlock[] }>('/blocked-dates'),
    ])
    me.value = meRes
    pending.value = pendingRes.data
    upcoming.value = upcomingRes.data
    blocks.value = blocksRes.data
    loaded.value = true
  }
  catch (e) {
    loadError.value = apiError(e)
  }
}
onMounted(load)

async function act(booking: AdminBooking, action: 'confirm' | 'decline') {
  busyId.value = booking.id
  try {
    await api(`/bookings/${booking.id}/${action}`, { method: 'POST' })
    message.value = action === 'confirm'
      ? `Reserva de ${booking.guest_name} confirmada. Avise o hóspede pelo WhatsApp.`
      : `Pedido de ${booking.guest_name} encerrado. As datas foram liberadas.`
    await load()
  }
  catch (e) {
    message.value = apiError(e)
  }
  finally {
    busyId.value = null
  }
}

const newBlock = reactive({ accommodation_id: 0, starts_on: '', ends_on: '', reason: '' })
const blockError = ref('')
watch(me, (value) => {
  if (value && !newBlock.accommodation_id) newBlock.accommodation_id = value.property.accommodations[0]?.id ?? 0
})

async function addBlock() {
  blockError.value = ''
  try {
    await api('/blocked-dates', { method: 'POST', body: { ...newBlock, reason: newBlock.reason || null } })
    Object.assign(newBlock, { starts_on: '', ends_on: '', reason: '' })
    await load()
  }
  catch (e) {
    blockError.value = apiError(e)
  }
}

// Um clique por vez: o botão fica desativado até a lista voltar (dois cliques seguidos não
// mandam dois pedidos).
const removingId = ref<number | null>(null)
async function removeBlock(block: AdminBlock) {
  if (removingId.value) return
  removingId.value = block.id
  blockError.value = ''
  try {
    await api(`/blocked-dates/${block.id}`, { method: 'DELETE' })
    await load()
  }
  catch (e) {
    blockError.value = apiError(e)
  }
  finally {
    removingId.value = null
  }
}

async function logout() {
  await api('/logout', { method: 'POST' }).catch(() => {})
  token.value = null
  await navigateTo('/painel/entrar')
}

const accName = (id: number) => me.value?.property.accommodations.find(a => a.id === id)?.name ?? ''
const shortDate = (iso: string) => iso.split('-').reverse().slice(0, 2).join('/')
</script>

<template>
  <div class="page" :style="me ? { '--brand': me.property.primary_color } : undefined">
    <header class="top">
      <div class="container top-inner">
        <div>
          <p class="eyebrow">Painel</p>
          <h1>{{ me?.property.name ?? (loadError ? 'Painel da pousada' : 'Carregando...') }}</h1>
        </div>
        <div class="top-actions">
          <NuxtLink to="/" class="link">Ver site</NuxtLink>
          <button type="button" class="link" @click="logout">Sair</button>
        </div>
      </div>
      <nav class="container tabs" aria-label="Seções">
        <button type="button" :aria-pressed="tab === 'pending'" @click="tab = 'pending'">
          Pedidos <span v-if="pending.length" class="count">{{ pending.length }}</span>
        </button>
        <button type="button" :aria-pressed="tab === 'upcoming'" @click="tab = 'upcoming'">Próximas reservas</button>
        <button type="button" :aria-pressed="tab === 'blocks'" @click="tab = 'blocks'">Datas bloqueadas</button>
      </nav>
    </header>

    <main class="container content">
      <p v-if="message" class="toast" role="status">{{ message }}</p>

      <div v-if="loadError" class="load-error" role="alert">
        <p>Não foi possível carregar o painel. {{ loadError }}</p>
        <button type="button" class="btn btn-sm" @click="load">Tentar de novo</button>
      </div>

      <p v-else-if="!loaded" class="empty" aria-busy="true">Carregando...</p>

      <section v-else-if="tab === 'pending'" class="list">
        <p v-if="!pending.length" class="empty">Nenhum pedido esperando resposta. Quando chegar um, você recebe um e-mail e ele aparece aqui.</p>
        <AdminBookingItem
          v-for="booking in pending"
          :key="booking.id"
          :booking="booking"
          :busy="busyId === booking.id"
          @confirm="act(booking, 'confirm')"
          @decline="act(booking, 'decline')"
        />
      </section>

      <section v-else-if="tab === 'upcoming'" class="list">
        <p v-if="!upcoming.length" class="empty">Nenhuma reserva confirmada pela frente.</p>
        <AdminBookingItem
          v-for="booking in upcoming"
          :key="booking.id"
          :booking="booking"
          :busy="busyId === booking.id"
          @decline="act(booking, 'decline')"
        />
      </section>

      <section v-else class="blocks">
        <form class="panel block-form" @submit.prevent="addBlock">
          <h2>Bloquear datas</h2>
          <p class="hint">Use para manutenção, uso próprio ou reservas feitas no Airbnb e Booking.</p>
          <label for="acc">Acomodação
            <select id="acc" v-model.number="newBlock.accommodation_id">
              <option v-for="acc in me?.property.accommodations" :key="acc.id" :value="acc.id">{{ acc.name }}</option>
            </select>
          </label>
          <div class="row">
            <label for="from">Primeira noite <input id="from" v-model="newBlock.starts_on" type="date" required></label>
            <label for="to">Última noite <input id="to" v-model="newBlock.ends_on" type="date" required :min="newBlock.starts_on"></label>
          </div>
          <label for="reason"><span>Motivo <span class="opt">opcional</span></span> <input id="reason" v-model="newBlock.reason" placeholder="Ex.: reserva pelo Airbnb"></label>
          <p v-if="blockError" class="error" role="alert">{{ blockError }}</p>
          <button class="btn">Bloquear</button>
        </form>

        <div class="panel">
          <h2>Bloqueios</h2>
          <p v-if="!blocks.length" class="hint">Nenhuma data bloqueada.</p>
          <ul class="block-list">
            <li v-for="block in blocks" :key="block.id">
              <div>
                <strong>{{ accName(block.accommodation_id) }}</strong>
                <span>{{ shortDate(block.starts_on) }} a {{ shortDate(block.ends_on) }}<template v-if="block.reason"> · {{ block.reason }}</template></span>
              </div>
              <button type="button" class="link" :disabled="removingId !== null" @click="removeBlock(block)">{{ removingId === block.id ? 'Desbloqueando...' : 'Desbloquear' }}</button>
            </li>
          </ul>
        </div>
      </section>
    </main>
  </div>
</template>

<style scoped lang="scss">
.page { min-height: 100vh; background: var(--soft); }
.top { background: var(--card); border-bottom: 1px solid var(--line); }
.top-inner { display: flex; justify-content: space-between; align-items: end; gap: 1rem; padding-block: 1.2rem .6rem; }
h1 { margin: .1rem 0 0; font-size: 1.6rem; }
.top-actions { display: flex; gap: .6rem; }
.link { background: none; border: 0; padding: .6rem .2rem; font: inherit; font-weight: 700; color: var(--brand-text); cursor: pointer; text-decoration: none; }
.link:disabled { opacity: .5; cursor: default; }
.tabs { display: flex; gap: .3rem; overflow-x: auto; }
.tabs button { font: inherit; font-weight: 700; background: none; border: 0; border-bottom: 3px solid transparent; padding: .7rem .6rem; color: var(--muted); cursor: pointer; white-space: nowrap; display: inline-flex; align-items: center; gap: .4rem; }
.tabs button[aria-pressed="true"] { color: var(--text); border-bottom-color: var(--brand); }
.count { background: var(--warn); color: var(--night); border-radius: 999px; font-size: .75rem; padding: .05rem .5rem; }
.content { padding-block: 1.5rem 3rem; max-width: 860px; }
.toast { background: var(--card); border: 1px solid var(--line); border-radius: 10px; padding: .75rem 1rem; font-weight: 500; margin: 0 0 1rem; }
.list { display: grid; gap: 1rem; }
.load-error { background: var(--card); border: 1px solid var(--line); border-left: 4px solid var(--danger); border-radius: var(--radius); padding: 1rem 1.1rem; display: grid; gap: .8rem; justify-items: start; }
.load-error p { margin: 0; font-weight: 500; }
.empty { background: var(--card); border: 1px dashed var(--line); border-radius: var(--radius); padding: 2rem 1.2rem; text-align: center; color: var(--muted); margin: 0; }
.blocks { display: grid; gap: 1rem; grid-template-columns: minmax(0, 1fr) minmax(0, 1fr); align-items: start; }
@include below(md) { .blocks { grid-template-columns: minmax(0, 1fr); } }
.panel { background: var(--card); border: 1px solid var(--line); border-radius: var(--radius); padding: 1.2rem; min-width: 0; }
.panel h2 { margin: 0 0 .3rem; font-size: 1.2rem; }
.block-form { display: grid; gap: .8rem; }
.block-form label { display: grid; gap: .3rem; font-weight: 700; font-size: .88rem; }
.row { display: grid; grid-template-columns: 1fr 1fr; gap: .7rem; }
.hint { color: var(--muted); font-size: .9rem; margin: 0; }
.opt { font-weight: 400; color: var(--muted); }
.error { color: var(--danger); font-weight: 700; margin: 0; }
.block-list { list-style: none; margin: .6rem 0 0; padding: 0; display: grid; }
.block-list li { display: flex; justify-content: space-between; align-items: center; gap: 1rem; padding: .7rem 0; border-top: 1px solid var(--line); }
.block-list li div { display: grid; }
.block-list span { color: var(--muted); font-size: .9rem; }
</style>
