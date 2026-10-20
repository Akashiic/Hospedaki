<script setup lang="ts">
import type { Checkout, PaymentMethod } from '~/composables/useApi'
import { CalendarCheck, CircleAlert, CreditCard, PartyPopper, QrCode } from 'lucide-vue-next'

// Checkout da reserva: o hóspede chega aqui depois de pedir a reserva (o link leva o token
// secreto dela) e paga no Pix ou no cartão, sem sair do site. A página pergunta a situação ao
// servidor de tempos em tempos; aprovado o pagamento, mostra a reserva confirmada.
const route = useRoute()
const token = String(route.params.token)
const api = useCheckoutApi(token)
const { property } = await useProperty()

useHead({ title: `Pagamento ${property.value.seo?.separator ?? '·'} ${property.value.name}`, meta: [{ name: 'robots', content: 'noindex, nofollow' }] })

const checkout = ref<Checkout | null>(null)
const loadError = ref('')
const notFound = ref(false)
const method = ref<PaymentMethod>('pix')
const busy = ref(false)
const actionError = ref('')

async function load(refresh = false) {
  try {
    checkout.value = await api<Checkout>('/', { query: refresh ? { refresh: 1 } : undefined })
    loadError.value = ''
  }
  catch (e) {
    if ((e as { statusCode?: number }).statusCode === 404) notFound.value = true
    else if (!checkout.value) loadError.value = apiError(e)
  }
}
onMounted(async () => {
  await load()
  // Abre no Pix quando ele existe; senão, no cartão.
  if (checkout.value && !checkout.value.methods.pix) method.value = 'card'
  if (checkout.value?.payment?.method) method.value = checkout.value.payment.method
})

const methods = computed(() => checkout.value?.methods)
const waiting = computed(() => checkout.value?.status === 'awaiting_payment')
const pendingPayment = computed(() => checkout.value?.payment?.status === 'pending')

// Enquanto espera um pagamento em aberto (Pix gerado, cartão em análise), pergunta a cada 4 s;
// sem pagamento em aberto, a cada 20 s (para perceber o fim do prazo).
let poll: ReturnType<typeof setTimeout> | undefined
function schedule() {
  clearTimeout(poll)
  if (!waiting.value) return
  poll = setTimeout(async () => {
    if (document.visibilityState === 'visible') await load(true)
    schedule()
  }, pendingPayment.value ? 4000 : 20000)
}
watch([waiting, pendingPayment], schedule)
onMounted(schedule)
onBeforeUnmount(() => clearTimeout(poll))

async function act(path: string, body: Record<string, unknown> = {}) {
  busy.value = true
  actionError.value = ''
  try {
    checkout.value = await api<Checkout>(path, { method: 'POST', body })
  }
  catch (e) {
    actionError.value = apiError(e)
    await load()
  }
  finally {
    busy.value = false
  }
}
const generatePix = (email: string | null) => act('/pix', email ? { email } : {})
const simulate = () => act('/simulate')

const b = computed(() => checkout.value?.booking)
const firstName = computed(() => b.value?.guest_name.trim().split(/\s+/)[0] ?? '')
const formatDate = (iso: string) => new Date(`${iso}T12:00:00`).toLocaleDateString('pt-BR', { weekday: 'short', day: '2-digit', month: 'short' })
const when = computed(() => {
  if (!b.value) return ''
  if (b.value.start_time) return `${formatDate(b.value.check_in)}, das ${formatHour(b.value.start_time)} às ${formatHour(b.value.end_time)}`
  return `${formatDate(b.value.check_in)} → ${formatDate(b.value.check_out)} · ${plural(b.value.nights, 'diária', 'diárias')}`
})
const paidWith = computed(() => checkout.value?.payment_method === 'pix' ? 'no Pix' : 'no cartão')
const whatsapp = computed(() => {
  const n = checkout.value?.property.whatsapp
  return n ? `https://wa.me/${n}?text=${encodeURIComponent(`Olá! Sobre a reserva ${checkout.value!.code}.`)}` : null
})
</script>

<template>
  <section class="checkout">
    <div class="container">
      <div v-if="notFound" class="panel state">
        <span class="ico warn"><CircleAlert :size="28" /></span>
        <h1>Link de pagamento não encontrado</h1>
        <p>Confira se o endereço está completo ou faça um novo pedido de reserva.</p>
        <NuxtLink to="/reservar" class="btn">Fazer um pedido</NuxtLink>
      </div>

      <div v-else-if="loadError" class="panel state" role="alert">
        <p>Não foi possível carregar o pagamento. {{ loadError }}</p>
        <button type="button" class="btn btn-sm" @click="load()">Tentar de novo</button>
      </div>

      <div v-else-if="!checkout" class="grid">
        <div class="panel skeleton-panel" />
        <div class="panel skeleton-panel short" />
      </div>

      <Transition v-else name="swap" mode="out-in">
        <div v-if="checkout.status === 'confirmed'" key="paid" class="panel state success">
          <span class="ico ok"><PartyPopper :size="28" /></span>
          <p class="eyebrow">Pagamento aprovado</p>
          <h1>Reserva confirmada, {{ firstName }}!</h1>
          <p>Seu código é <strong>{{ checkout.code }}</strong>. Pago {{ paidWith }}: {{ formatMoney(checkout.booking.total) }}<template v-if="checkout.booking.pix_discount"> (você economizou {{ formatMoney(checkout.booking.pix_discount) }})</template>.</p>
          <p class="when"><CalendarCheck :size="18" aria-hidden="true" /> {{ checkout.booking.accommodations.map(a => a.name).join(' + ') }} · {{ when }}</p>
          <p class="muted">A pousada já foi avisada. Guarde este código e, se precisar, fale com a gente pelo WhatsApp.</p>
          <div class="actions">
            <a v-if="whatsapp" :href="whatsapp" class="btn" target="_blank" rel="noopener">Falar no WhatsApp</a>
            <NuxtLink to="/" class="btn btn-ghost">Voltar ao site</NuxtLink>
          </div>
        </div>

        <div v-else-if="!waiting" key="closed" class="panel state">
          <span class="ico warn"><CircleAlert :size="28" /></span>
          <h1>{{ checkout.status === 'expired' ? 'O prazo para pagar acabou' : 'Esta reserva não está mais aberta' }}</h1>
          <p>{{ checkout.status === 'expired' ? 'As datas foram liberadas. Se ainda estiverem livres, é só fazer o pedido de novo.' : 'Fale com a pousada para saber mais.' }}</p>
          <p v-if="checkout.payment?.status === 'paid'" class="muted">Se você pagou e mesmo assim vê esta mensagem, fale com a pousada: o valor é devolvido.</p>
          <div class="actions">
            <NuxtLink to="/reservar" class="btn">Fazer um novo pedido</NuxtLink>
            <a v-if="whatsapp" :href="whatsapp" class="btn btn-ghost" target="_blank" rel="noopener">WhatsApp</a>
          </div>
        </div>

        <div v-else key="pay" class="grid">
          <div class="panel pay">
            <p class="eyebrow">Pagamento</p>
            <h1>Falta só pagar, {{ firstName }}</h1>
            <div v-if="checkout.due_at" class="hold">
              <p>Suas datas ficam guardadas por mais</p>
              <SitePromoFlipTimer :ends-at="checkout.due_at" size="sm" @ended="load(true)" />
            </div>

            <div v-if="methods?.pix && methods?.card" class="tabs" role="tablist" aria-label="Forma de pagamento">
              <button type="button" role="tab" :aria-selected="method === 'pix'" @click="method = 'pix'">
                <QrCode :size="18" aria-hidden="true" /> Pix
                <span v-if="methods.pix.discount" class="off">−{{ methods.pix.percent }}%</span>
              </button>
              <button type="button" role="tab" :aria-selected="method === 'card'" @click="method = 'card'">
                <CreditCard :size="18" aria-hidden="true" /> Cartão
              </button>
            </div>

            <p v-if="actionError" class="error" role="alert">{{ actionError }}</p>
            <Transition name="swap" mode="out-in">
              <CheckoutPixPay v-if="method === 'pix' && methods?.pix" key="pix" :checkout="checkout" :busy="busy" @generate="generatePix" @simulate="simulate" />
              <CheckoutCardPay v-else-if="methods?.card" key="card" :checkout="checkout" :token="token" @updated="checkout = $event" />
            </Transition>
          </div>

          <aside class="panel summary" aria-label="Resumo da reserva">
            <img v-if="checkout.booking.accommodations[0]?.photo" :src="checkout.booking.accommodations[0].photo" alt="" class="photo">
            <h2>{{ checkout.booking.accommodations.map(a => a.name).join(' + ') }}</h2>
            <p class="when">{{ when }}</p>
            <p class="muted">{{ plural(checkout.booking.guests, 'pessoa', 'pessoas') }} · reserva {{ checkout.code }}</p>
            <div class="lines">
              <p v-for="a in checkout.booking.accommodations" :key="a.name" class="line"><span>{{ a.name }}</span><span>{{ formatMoney(a.subtotal) }}</span></p>
              <p class="line total"><span>Total</span><span>{{ formatMoney(checkout.booking.total) }}</span></p>
              <p v-if="methods?.pix?.discount" class="line pix"><span>No Pix (−{{ methods.pix.percent }}%)</span><span>{{ formatMoney(methods.pix.total) }}</span></p>
            </div>
          </aside>
        </div>
      </Transition>
    </div>
  </section>
</template>

<style scoped lang="scss">
// Faixa escura no topo: o menu (claro, feito para ficar sobre fotos) continua legível, e os
// cartões começam sobre ela.
.checkout {
  min-height: 100vh; padding: calc(var(--nav-h) + clamp(1.5rem, 4vw, 3rem)) 0 clamp(4rem, 8vw, 6rem);
  background: linear-gradient(var(--night) 0 calc(var(--nav-h) + 8rem), var(--soft) 0);
}
.grid { display: grid; gap: 1.2rem; grid-template-columns: minmax(0, 1.45fr) minmax(0, 1fr); align-items: start; }
// No celular, o pagamento vem primeiro e o resumo vai embaixo, sem a foto.
@include below(lg) { .grid { grid-template-columns: minmax(0, 1fr); } .summary { position: static; } .photo { display: none; } }
.panel { background: var(--card); border-radius: var(--radius); padding: clamp(1.1rem, 2.5vw, 1.8rem); min-width: 0; box-shadow: 0 1px 2px rgb(0 0 0 / .04), 0 18px 40px -28px rgb(20 40 30 / .35); }
.pay { display: grid; gap: 1.1rem; }
h1 { margin: 0; font-size: clamp(2rem, 4.5vw, 2.8rem); line-height: 1.05; }
.eyebrow { margin: 0; }
.hold { display: flex; flex-wrap: wrap; align-items: center; gap: .4rem 1rem; padding: .7rem .9rem; border-radius: 14px; background: var(--soft); }
.hold p { margin: 0; font-weight: 700; font-size: .9rem; color: var(--muted); }
.tabs { display: grid; grid-template-columns: 1fr 1fr; gap: .4rem; padding: .3rem; border-radius: 14px; background: var(--soft); }
.tabs button {
  display: inline-flex; justify-content: center; align-items: center; gap: .45rem; min-height: 48px; border: 0; border-radius: 11px;
  background: none; font: inherit; font-weight: 800; color: var(--muted); cursor: pointer;
  &[aria-selected="true"] { background: var(--card); color: var(--text); box-shadow: 0 1px 2px rgb(0 0 0 / .08), 0 6px 16px -10px rgb(0 0 0 / .3); }
}
.off { padding: .1rem .45rem; border-radius: 999px; background: var(--pix); color: var(--pix-ink); font-size: .75rem; }
.error { margin: 0; color: var(--danger); font-weight: 700; }
.summary { display: grid; gap: .5rem; position: sticky; top: calc(var(--nav-h) + 1rem); }
.photo { width: 100%; aspect-ratio: 16 / 9; object-fit: cover; border-radius: 10px; margin-bottom: .4rem; }
.summary h2 { margin: 0; font-size: 1.6rem; }
.when { margin: 0; display: flex; align-items: center; gap: .4rem; font-weight: 700; }
.muted { margin: 0; color: var(--muted); font-size: .92rem; }
.lines { display: grid; gap: .35rem; margin-top: .4rem; padding: .9rem 1rem; border-radius: 10px; background: var(--soft); }
.line { display: flex; justify-content: space-between; gap: 1rem; margin: 0; font-variant-numeric: tabular-nums; }
.line.total { font-family: var(--font-display); font-size: 1.6rem; border-top: 1px solid var(--line); padding-top: .4rem; margin-top: .2rem; }
.line.pix { color: var(--pix-deep); font-weight: 800; }
.state { max-width: 640px; margin: 0 auto; display: grid; gap: .8rem; justify-items: start; }
.state p { margin: 0; }
.ico { display: grid; place-items: center; width: 60px; height: 60px; border-radius: 18px; }
.ico.ok { background: var(--ok-soft); color: var(--ok-ink); }
.ico.warn { background: var(--warn-soft); color: var(--warn-ink); }
.actions { display: flex; flex-wrap: wrap; gap: .6rem; margin-top: .4rem; }
.skeleton-panel { min-height: 420px; background: color-mix(in srgb, var(--card) 70%, var(--soft)); }
.skeleton-panel.short { min-height: 260px; }
.swap-enter-active { @include motion-enter; }
.swap-leave-active { @include motion-exit; }
.swap-enter-from { opacity: 0; transform: translateY($dist-sm); }
.swap-leave-to { opacity: 0; transform: translateY(-$dist-xs); }
</style>
