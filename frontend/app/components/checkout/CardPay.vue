<script setup lang="ts">
import type { Checkout } from '~/composables/useApi'
import { CreditCard, LockKeyhole } from 'lucide-vue-next'

// Cartão no checkout, com o visual do site e os campos seguros de cada gateway:
//  - Mercado Pago: número, validade e CVV são iframes do SDK, que devolve um token do cartão;
//  - Stripe: o Card Element (iframe) e a confirmação no navegador com o client_secret;
//  - Simulado (demonstração): campos comuns; só o final do número vai ao servidor.
// O número do cartão nunca passa pelo Hospedaki.
const props = defineProps<{ checkout: Checkout, token: string }>()
const emit = defineEmits<{ updated: [checkout: Checkout] }>()

const api = useCheckoutApi(props.token)
const card = computed(() => props.checkout.methods.card!)
const provider = computed(() => card.value.provider)

const email = ref(props.checkout.booking.guest_email ?? '')
const name = ref(props.checkout.booking.guest_name)
const installments = ref(1)
const busy = ref(false)
const error = ref('')
const ready = ref(provider.value === 'simulated')
const sdkError = ref('')

// Recusa vinda do gateway (cartão sem limite, dados errados...).
const declined = computed(() => props.checkout.payment?.method === 'card' && props.checkout.payment.status === 'failed' ? props.checkout.payment.message : null)

const installmentOptions = computed(() => Array.from({ length: card.value.installments }, (_, i) => i + 1))
const perInstallment = (n: number) => formatMoney(Math.round(card.value.total / n))

async function send(body: Record<string, unknown>) {
  const res = await api<Checkout>('/card', { method: 'POST', body: { email: email.value, ...body } })
  emit('updated', res)
  return res
}

// ── Simulado ──
const sim = reactive({ number: '', expiry: '', cvc: '' })
const formatNumber = (v: string) => v.replace(/\D/g, '').slice(0, 19).replace(/(\d{4})(?=\d)/g, '$1 ')
const formatExpiry = (v: string) => v.replace(/\D/g, '').slice(0, 4).replace(/^(\d{2})(\d)/, '$1/$2')
async function paySimulated() {
  const digits = sim.number.replace(/\D/g, '')
  if (digits.length < 13) {
    error.value = 'Confira o número do cartão.'
    return
  }
  await send({ last4: digits.slice(-4), brand: cardBrand(digits), installments: installments.value })
}

// ── Mercado Pago ──
let cardForm: any = null
async function mountMercadoPago() {
  await loadScript('https://sdk.mercadopago.com/js/v2')
  const mp = new (window as any).MercadoPago(card.value.public_key, { locale: 'pt-BR' })
  const style = { fontSize: '16px', color: '#19201b', placeholderColor: '#5a625a' }
  cardForm = mp.cardForm({
    amount: (card.value.total / 100).toFixed(2),
    iframe: true,
    form: {
      id: 'mp-card-form',
      cardNumber: { id: 'mp-card-number', placeholder: '0000 0000 0000 0000', style },
      expirationDate: { id: 'mp-expiration', placeholder: 'MM/AA', style },
      securityCode: { id: 'mp-cvv', placeholder: 'CVV', style },
      cardholderName: { id: 'mp-holder', placeholder: 'Como está no cartão' },
      issuer: { id: 'mp-issuer', placeholder: 'Banco emissor' },
      installments: { id: 'mp-installments', placeholder: 'Parcelas' },
      identificationType: { id: 'mp-doc-type', placeholder: 'Documento' },
      identificationNumber: { id: 'mp-doc-number', placeholder: 'CPF do titular' },
      cardholderEmail: { id: 'mp-email', placeholder: 'seu@email.com' },
    },
    callbacks: {
      onFormMounted: (e: unknown) => {
        if (e) sdkError.value = 'Não foi possível carregar o formulário do cartão. Recarregue a página ou pague no Pix.'
        else ready.value = true
      },
      // Parcelas além do limite da pousada saem da lista que o Mercado Pago monta.
      onInstallmentsReceived: () => nextTick(() => {
        const select = document.getElementById('mp-installments') as HTMLSelectElement | null
        Array.from(select?.options ?? []).forEach((o) => { if (Number(o.value) > card.value.installments) o.remove() })
      }),
      onSubmit: async (event: Event) => {
        event.preventDefault()
        const data = cardForm.getCardFormData()
        await run(() => send({
          token: data.token,
          payment_method_id: data.paymentMethodId,
          issuer_id: data.issuerId || null,
          installments: Number(data.installments) || 1,
          identification_type: data.identificationType,
          identification_number: data.identificationNumber,
        }))
      },
      onError: () => { error.value = 'Confira os dados do cartão.' },
    },
  })
}

// ── Stripe ──
let stripe: any = null
let stripeCard: any = null
const stripeMount = ref<HTMLElement | null>(null)
async function mountStripe() {
  await loadScript('https://js.stripe.com/v3/')
  stripe = (window as any).Stripe(card.value.public_key, { locale: 'pt-BR' })
  stripeCard = stripe.elements().create('card', {
    hidePostalCode: true,
    style: { base: { fontSize: '16px', color: '#19201b', fontFamily: 'Figtree, system-ui, sans-serif', '::placeholder': { color: '#5a625a' } }, invalid: { color: '#b42318' } },
  })
  stripeCard.mount(stripeMount.value)
  stripeCard.on('ready', () => { ready.value = true })
  stripeCard.on('change', (e: any) => { error.value = e.error?.message ?? '' })
}
async function payStripe() {
  const res = await send({})
  const secret = res.payment?.client_secret
  if (!secret) throw new Error('Não foi possível iniciar o pagamento.')
  const result = await stripe.confirmCardPayment(secret, { payment_method: { card: stripeCard, billing_details: { name: name.value, email: email.value } } })
  if (result.error) {
    error.value = result.error.message ?? 'O cartão foi recusado.'
    return
  }
  // Confirmado no navegador; o servidor confere com a Stripe antes de confirmar a reserva.
  emit('updated', await api<Checkout>('/', { query: { refresh: 1 } }))
}

async function run(task: () => Promise<unknown>) {
  if (busy.value) return
  busy.value = true
  error.value = ''
  try {
    await task()
  }
  catch (e) {
    error.value = (e as { data?: unknown }).data ? apiError(e) : (e as Error).message
  }
  finally {
    busy.value = false
  }
}

function submit() {
  if (provider.value === 'simulated') run(paySimulated)
  else if (provider.value === 'stripe') run(payStripe)
  // Mercado Pago: o próprio SDK escuta o envio do formulário (onSubmit).
}

onMounted(async () => {
  try {
    if (provider.value === 'mercadopago') await mountMercadoPago()
    if (provider.value === 'stripe') await mountStripe()
  }
  catch {
    sdkError.value = 'Não foi possível carregar o pagamento com cartão. Confira sua internet ou pague no Pix.'
  }
})
onBeforeUnmount(() => {
  cardForm?.unmount?.()
  stripeCard?.destroy?.()
})
</script>

<template>
  <form id="mp-card-form" class="card-pay" novalidate @submit.prevent="submit">
    <div class="amount">
      <p class="label">Total no cartão</p>
      <p class="value">{{ formatMoney(card.total) }}</p>
      <p v-if="card.installments > 1" class="hint">em até {{ card.installments }}x</p>
    </div>

    <p v-if="sdkError" class="error" role="alert">{{ sdkError }}</p>

    <!-- Mercado Pago: as caixas com id recebem os campos seguros do SDK. -->
    <template v-if="provider === 'mercadopago'">
      <div class="field"><span class="lbl">Número do cartão</span><div id="mp-card-number" class="secure" /></div>
      <div class="row">
        <div class="field"><span class="lbl">Validade</span><div id="mp-expiration" class="secure" /></div>
        <div class="field"><span class="lbl">CVV</span><div id="mp-cvv" class="secure" /></div>
      </div>
      <label class="field" for="mp-holder"><span class="lbl">Nome no cartão</span><input id="mp-holder" v-model="name" autocomplete="cc-name"></label>
      <div class="row">
        <label class="field" for="mp-doc-type"><span class="lbl">Documento</span><select id="mp-doc-type" /></label>
        <label class="field" for="mp-doc-number"><span class="lbl">Número</span><input id="mp-doc-number" inputmode="numeric"></label>
      </div>
      <label class="field" for="mp-issuer" hidden><span class="lbl">Banco</span><select id="mp-issuer" /></label>
      <label class="field" for="mp-installments"><span class="lbl">Parcelas</span><select id="mp-installments" /></label>
      <label class="field" for="mp-email"><span class="lbl">E-mail</span><input id="mp-email" v-model="email" type="email" autocomplete="email" required></label>
    </template>

    <template v-else-if="provider === 'stripe'">
      <div class="field"><span class="lbl">Cartão</span><div ref="stripeMount" class="secure stripe" /></div>
      <label class="field" for="st-name"><span class="lbl">Nome no cartão</span><input id="st-name" v-model="name" autocomplete="cc-name" required></label>
      <label class="field" for="st-email"><span class="lbl">E-mail</span><input id="st-email" v-model="email" type="email" autocomplete="email" required></label>
    </template>

    <template v-else>
      <p class="demo">Demonstração: nenhum valor é cobrado. Use <strong>4242 4242 4242 4242</strong>; o final <strong>0002</strong> é recusado.</p>
      <label class="field" for="sim-number"><span class="lbl">Número do cartão</span>
        <input id="sim-number" :value="sim.number" inputmode="numeric" autocomplete="off" placeholder="0000 0000 0000 0000" required @input="sim.number = formatNumber(($event.target as HTMLInputElement).value)">
      </label>
      <div class="row">
        <label class="field" for="sim-exp"><span class="lbl">Validade</span>
          <input id="sim-exp" :value="sim.expiry" inputmode="numeric" autocomplete="off" placeholder="MM/AA" required @input="sim.expiry = formatExpiry(($event.target as HTMLInputElement).value)">
        </label>
        <label class="field" for="sim-cvc"><span class="lbl">CVV</span><input id="sim-cvc" v-model="sim.cvc" inputmode="numeric" autocomplete="off" maxlength="4" placeholder="123" required></label>
      </div>
      <label class="field" for="sim-name"><span class="lbl">Nome no cartão</span><input id="sim-name" v-model="name" required></label>
      <label v-if="card.installments > 1" class="field" for="sim-inst"><span class="lbl">Parcelas</span>
        <select id="sim-inst" v-model.number="installments">
          <option v-for="n in installmentOptions" :key="n" :value="n">{{ n === 1 ? `À vista, ${formatMoney(card.total)}` : `${n}x de ${perInstallment(n)}` }}</option>
        </select>
      </label>
      <label class="field" for="sim-email"><span class="lbl">E-mail</span><input id="sim-email" v-model="email" type="email" autocomplete="email" required></label>
    </template>

    <p v-if="error || declined" class="error" role="alert">{{ error || declined }}</p>
    <button class="btn pay" :disabled="busy || !ready || !!sdkError">
      <CreditCard :size="18" aria-hidden="true" /> {{ busy ? 'Processando...' : `Pagar ${formatMoney(card.total)}` }}
    </button>
    <p class="secure-note"><LockKeyhole :size="14" aria-hidden="true" /> Pagamento protegido por {{ provider === 'stripe' ? 'Stripe' : provider === 'mercadopago' ? 'Mercado Pago' : 'simulação' }}. Os dados do cartão não ficam com a pousada.</p>
  </form>
</template>

<style scoped lang="scss">
.card-pay { display: grid; gap: .85rem; }
.amount { display: grid; gap: .15rem; }
.label { @include eyebrow(var(--muted), sm); margin: 0; }
.value { margin: 0; font-family: var(--font-display); font-size: 2.6rem; line-height: 1.05; font-variant-numeric: tabular-nums; }
.hint { margin: 0; color: var(--muted); font-weight: 600; }
.row { display: grid; gap: .8rem; grid-template-columns: repeat(2, minmax(0, 1fr)); }
.field { display: grid; gap: .3rem; min-width: 0; }
.lbl { font-weight: 700; font-size: .88rem; }
// Caixa dos campos seguros (iframes do gateway) com a mesma cara dos campos do site.
.secure { height: 48px; padding: 0 .9rem; border: 1.5px solid var(--line); border-radius: 10px; background: var(--card); display: flex; align-items: center; }
.secure :deep(iframe) { width: 100%; height: 100%; }
.secure.stripe { display: block; padding: .85rem .9rem; height: auto; min-height: 48px; }
.demo { margin: 0; padding: .6rem .8rem; border-radius: 10px; background: var(--warn-soft); color: var(--warn-ink); font-size: .88rem; }
.error { margin: 0; color: var(--danger); font-weight: 700; }
.pay { display: inline-flex; justify-content: center; align-items: center; gap: .5rem; }
.secure-note { margin: 0; display: flex; align-items: center; gap: .35rem; color: var(--muted); font-size: .82rem; }
</style>
