<script setup lang="ts">
import type { AdminBooking, AdminMe, AdminTab } from '~/composables/useAdminApi'
import { Inbox } from 'lucide-vue-next'

definePageMeta({ middleware: 'admin', layout: 'painel', pageTransition: { name: 'fade', mode: 'out-in' } })
useHead({ title: 'Painel da pousada', meta: [{ name: 'robots', content: 'noindex' }] })

const api = useAdminApi()
const token = useAdminToken()
const toast = useToast()
const route = useRoute()
const router = useRouter()

// A aba fica no endereço (/painel?aba=reservas): recarregar a página ou voltar não perde o lugar.
const TABS: AdminTab[] = ['inicio', 'pedidos', 'reservas', 'estatisticas', 'bloqueios', 'acomodacoes', 'paginas', 'identidade', 'contato', 'seo', 'integracoes', 'pagamentos']
// Links antigos: ?aba=pagina (antes do "Seu site") abre o Início em Páginas, e
// ?aba=configuracoes (antes de virar submenu) abre Identidade.
const LEGACY: Record<string, Record<string, string>> = { pagina: { aba: 'paginas', pagina: 'inicio' }, configuracoes: { aba: 'identidade' } }
const tab = computed<AdminTab>(() => {
  const aba = (LEGACY[route.query.aba as string]?.aba ?? route.query.aba) as AdminTab
  return TABS.includes(aba) ? aba : 'inicio'
})
const legacy = LEGACY[route.query.aba as string]
if (legacy) router.replace({ query: legacy })
function go(next: AdminTab) {
  if (next !== tab.value) router.push({ query: next === 'inicio' ? {} : { aba: next } })
  if (import.meta.client) window.scrollTo({ top: 0 })
}

const requestsView = useViewMode('pedidos')

const me = ref<AdminMe | null>(null)
const pending = ref<AdminBooking[]>([])
const busyId = ref<number | null>(null)
const loaded = ref(false)
const loadError = ref('')

async function load() {
  loadError.value = ''
  try {
    const [meRes, pendingRes] = await Promise.all([
      api<AdminMe>('/me'),
      // Pedidos esperando resposta cuja entrada ainda não passou; os vencidos ficam em Reservas → Passadas.
      api<{ data: AdminBooking[] }>('/bookings', { query: { scope: 'requests' } }),
    ])
    me.value = meRes
    pending.value = pendingRes.data
    loaded.value = true
  }
  catch (e) {
    loadError.value = apiError(e)
  }
}
onMounted(load)

// "Hoje" no fuso da pousada, o mesmo da API: diz se a estadia já começou ou terminou.
const today = computed(() => todayIn(me.value?.property.timezone ?? 'America/Sao_Paulo'))

async function act(booking: AdminBooking, action: 'confirm' | 'decline') {
  busyId.value = booking.id
  try {
    await api(`/bookings/${booking.id}/${action}`, { method: 'POST' })
    // O cartão sai da lista com animação; o aviso confirma o que aconteceu.
    pending.value = pending.value.filter(b => b.id !== booking.id)
    toast.show(action === 'confirm'
      ? `Reserva de ${booking.guest_name} confirmada! Avise pelo WhatsApp.`
      : `Pedido de ${booking.guest_name} recusado. As datas foram liberadas.`, action === 'confirm' ? 'success' : 'info')
  }
  catch (e) {
    toast.show(apiError(e), 'error')
  }
  finally {
    busyId.value = null
  }
}

async function logout() {
  await api('/logout', { method: 'POST' }).catch(() => {})
  token.value = null
  await navigateTo('/painel/entrar')
}

const notify = (text: string) => toast.show(text, /não|erro|falh/i.test(text) ? 'error' : 'success')
</script>

<template>
  <AdminShell
    :tab="tab"
    :property-name="me?.property.name ?? null"
    :user-name="me?.user.name ?? null"
    :pending="pending.length"
    :demo="me?.property.demo"
    :icon-url="me?.property.icon_url"
    :style="me ? { '--brand': me.property.primary_color } : undefined"
    @go="go"
    @logout="logout"
  >
    <Transition name="a-tab" appear>
      <p v-if="me?.property.demo && tab === 'inicio'" class="demo-tip">
        <strong>Demonstração.</strong> Mexa à vontade: confirme pedidos, edite acomodações, mude a página inicial. Tudo volta ao início amanhã.
      </p>
    </Transition>

    <div v-if="loadError" class="a-card load-error" role="alert">
      <p>Não foi possível carregar o painel. {{ loadError }}</p>
      <button type="button" class="btn btn-sm" @click="load">Tentar de novo</button>
    </div>

    <Transition v-else name="a-tab" mode="out-in">
      <div :key="tab" class="view">
        <AdminOverview v-if="tab === 'inicio'" :me="me" :today="today" @go="go" />

        <section v-else-if="tab === 'pedidos'" class="requests">
          <header class="page-head with-tools a-rise">
            <div>
              <h1>Pedidos</h1>
              <p>Pedidos de reserva esperando sua resposta. As datas ficam seguras para o hóspede até você confirmar ou recusar.</p>
            </div>
            <AdminViewToggle v-if="pending.length > 1" v-model="requestsView" />
          </header>
          <AdminLoading v-if="!loaded" text="Carregando pedidos..." :cards="2" height="210px" />
          <div v-else-if="!pending.length" class="a-card empty a-rise" style="--i: 1">
            <span class="empty-ico"><Inbox :size="30" /></span>
            <h2>Tudo respondido!</h2>
            <p>Quando chegar um pedido novo, você recebe um e-mail e ele aparece aqui.</p>
          </div>
          <TransitionGroup v-else tag="div" name="a-list" class="items" :class="requestsView">
            <AdminBookingItem
              v-for="(booking, i) in pending"
              :key="booking.id"
              class="a-rise"
              :style="{ '--i': i + 1 }"
              :booking="booking"
              :today="today"
              :busy="busyId === booking.id"
              @confirm="act(booking, 'confirm')"
              @decline="act(booking, 'decline')"
            />
          </TransitionGroup>
        </section>

        <AdminBookings v-else-if="tab === 'reservas'" :today="today" @message="notify" @changed="load" />
        <AdminStats v-else-if="tab === 'estatisticas'" />
        <AdminBlocks v-else-if="tab === 'bloqueios'" :me="me" />
        <AdminAccommodations v-else-if="tab === 'acomodacoes'" :demo="me?.property.demo" @message="notify" @changed="load" />
        <AdminSitePages v-else-if="tab === 'paginas'" :me="me" :demo="me?.property.demo" @message="notify" />
        <AdminSiteSettings v-else-if="tab === 'identidade' || tab === 'contato'" :section="tab" :demo="me?.property.demo" @message="notify" @changed="load" />
        <AdminSitePayments v-else-if="tab === 'pagamentos'" @message="notify" />
        <AdminSiteSeo v-else-if="tab === 'seo' || tab === 'integracoes'" :section="tab" :accommodations="me?.property.accommodations ?? []" @message="notify" />
      </div>
    </Transition>
  </AdminShell>
</template>

<style scoped lang="scss">
.view { display: grid; gap: 1.2rem; }
.page-head h1 { @include display(clamp(2rem, 4vw, 2.8rem)); margin: 0 0 .3rem; }
.page-head p { margin: 0; color: var(--muted); max-width: 60ch; }
.requests { display: grid; gap: 1.2rem; max-width: 1240px; }
.page-head.with-tools { display: flex; flex-wrap: wrap; justify-content: space-between; align-items: end; gap: .8rem; }
.items { position: relative; display: grid; gap: 1rem; }
.items.list { max-width: 980px; }
.items.grid { grid-template-columns: repeat(auto-fill, minmax(min(100%, 340px), 1fr)); align-items: start; }
.empty { display: grid; justify-items: center; gap: .4rem; padding: 2.6rem 1.2rem; text-align: center; }
.empty h2 { margin: .4rem 0 0; font-size: 1.6rem; }
.empty p { margin: 0; color: var(--muted); }
.empty-ico { display: grid; place-items: center; width: 64px; height: 64px; border-radius: 20px; background: color-mix(in srgb, var(--brand) 14%, white); color: var(--brand-text); }
.load-error { padding: 1rem 1.2rem; display: grid; gap: .8rem; justify-items: start; border-left: 4px solid var(--danger); }
.load-error p { margin: 0; }
.demo-tip { margin: 0 0 1.2rem; padding: .8rem 1rem; border-radius: 14px; background: var(--warn-soft); color: var(--warn-ink); font-size: .92rem; }
</style>
