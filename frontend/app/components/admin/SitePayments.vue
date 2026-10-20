<script setup lang="ts">
import type { AdminPayments, PaymentProvider } from '~/composables/useAdminApi'
import { CreditCard, QrCode, ShieldCheck } from 'lucide-vue-next'

// "Seu site → Configurações → Pagamentos" (avançado): conecta o Mercado Pago e a Stripe e escolhe
// quem cobra o Pix e quem cobra o cartão. Com um deles ligado, o pedido do site vira pagamento:
// o hóspede paga no checkout e a reserva se confirma sozinha. As chaves secretas nunca voltam
// do servidor, só o final delas; em branco, a chave salva continua valendo.
const emit = defineEmits<{ message: [text: string] }>()
const api = useAdminApi()

const data = ref<AdminPayments | null>(null)
const loadError = ref('')
async function load() {
  loadError.value = ''
  try {
    data.value = await api<AdminPayments>('/payments')
  }
  catch (e) {
    loadError.value = apiError(e)
  }
}
onMounted(load)

const PROVIDERS: { key: 'mercadopago' | 'stripe', name: string }[] = [
  { key: 'mercadopago', name: 'Mercado Pago' },
  { key: 'stripe', name: 'Stripe' },
]
const providerName = (p: PaymentProvider | null) => p === 'simulated' ? 'Simulado' : PROVIDERS.find(x => x.key === p)?.name ?? 'Desligado'

// ── Quem cobra o quê ──
interface Choice { pix: PaymentProvider | null, card: PaymentProvider | null, installments: number }
const choice = ref<Choice | null>(null)
const fromData = (): Choice => ({ pix: data.value!.pix, card: data.value!.card, installments: data.value!.installments })
watch(data, (d) => { if (d) choice.value = fromData() })
const dirty = computed(() => !!data.value && !!choice.value && JSON.stringify(choice.value) !== JSON.stringify(fromData()))
const options = computed<(PaymentProvider | null)[]>(() => [...(data.value?.configured ?? []), null])

const saving = ref(false)
const saveError = ref('')
async function saveChoice() {
  if (!choice.value) return
  saving.value = true
  saveError.value = ''
  try {
    data.value = await api<AdminPayments>('/payments/settings', { method: 'PUT', body: choice.value })
    emit('message', choice.value.pix || choice.value.card
      ? 'Pagamentos salvos. Os próximos pedidos do site já vão para o checkout.'
      : 'Pagamento online desligado. Os pedidos voltam a esperar a sua confirmação.')
  }
  catch (e) {
    saveError.value = apiError(e)
  }
  finally {
    saving.value = false
  }
}

</script>

<template>
  <section class="payments">
    <header class="a-head a-rise">
      <p class="tag">Avançado</p>
      <h1>Pagamentos</h1>
      <p>Receba as reservas pelo site, no Pix ou no cartão, pelo Mercado Pago ou pela Stripe. Aprovado o pagamento, a reserva se confirma sozinha. O desconto no Pix e a promoção ficam em cada <NuxtLink :to="{ query: { aba: 'acomodacoes' } }">acomodação</NuxtLink>.</p>
    </header>

    <div v-if="loadError" class="a-card load-error" role="alert">
      <p>Não foi possível carregar os pagamentos. {{ loadError }}</p>
      <button type="button" class="btn btn-sm" @click="load">Tentar de novo</button>
    </div>
    <AdminLoading v-else-if="!data || !choice" text="Carregando pagamentos..." :cards="2" height="200px" />

    <div v-else class="layout">
      <div class="col">
        <p v-if="data.demo" class="demo a-rise" role="note">
          <strong>Demonstração:</strong> os pagamentos são simulados e nenhum valor é cobrado. Faça uma reserva no site para ver o checkout: o Pix tem um botão para simular o pagamento e no cartão use 4242 4242 4242 4242 (o final 0002 é recusado).
        </p>

        <form class="a-form" @submit.prevent="saveChoice">
          <section class="a-section a-rise" style="--i: 1">
            <h2>Como o hóspede paga</h2>
            <p v-if="!data.configured.length" class="a-hint">Conecte o Mercado Pago ou a Stripe abaixo para receber pelo site. Sem gateway, o pedido espera a sua confirmação e o pagamento é combinado com o hóspede.</p>
            <div class="methods">
              <div class="method">
                <div class="method-head"><span class="ico pix"><QrCode :size="20" aria-hidden="true" /></span><div><strong id="pix-label">Pix</strong><small>Na hora, com QR Code ou copia e cola</small></div></div>
                <div class="chips" role="radiogroup" aria-labelledby="pix-label">
                  <button v-for="o in options" :key="o ?? 'off'" type="button" role="radio" :aria-checked="choice.pix === o" @click="choice.pix = o">{{ providerName(o) }}</button>
                </div>
              </div>
              <div class="method">
                <div class="method-head"><span class="ico"><CreditCard :size="20" aria-hidden="true" /></span><div><strong id="card-label">Cartão de crédito</strong><small>Os dados do cartão vão direto ao gateway</small></div></div>
                <div class="chips" role="radiogroup" aria-labelledby="card-label">
                  <button v-for="o in options" :key="o ?? 'off'" type="button" role="radio" :aria-checked="choice.card === o" @click="choice.card = o">{{ providerName(o) }}</button>
                </div>
                <label v-if="choice.card && choice.card !== 'stripe'" class="a-field narrow" for="pay-installments">
                  <span class="a-label">Parcelar em até</span>
                  <select id="pay-installments" v-model.number="choice.installments">
                    <option v-for="n in 12" :key="n" :value="n">{{ n === 1 ? 'À vista (1x)' : `${n}x` }}</option>
                  </select>
                  <small class="a-hint">Os juros do parcelamento seguem o que está configurado na sua conta do Mercado Pago.</small>
                </label>
                <small v-else-if="choice.card === 'stripe'" class="a-hint">Na Stripe, o cartão é cobrado à vista.</small>
              </div>
            </div>
          </section>
          <AdminSaveBar :dirty="dirty" :saving="saving" :error="saveError" label="Salvar" @undo="choice = fromData()" />
        </form>

        <section class="gateways a-rise" style="--i: 2" aria-labelledby="gw-title">
          <div class="gw-intro">
            <h2 id="gw-title">Gateways</h2>
            <p class="a-hint">Abra para colar as chaves, ligar ou desligar e escolher entre testes e produção. Cada modo guarda as suas chaves.</p>
          </div>
          <div class="gw-grid">
            <AdminPaymentGateway
              v-for="p in PROVIDERS"
              :key="p.key"
              :provider="p.key"
              :gateway="data.gateways[p.key]"
              :demo="data.demo"
              @updated="data = $event"
              @message="emit('message', $event)"
            />
          </div>
        </section>
      </div>

      <aside class="side a-rise" style="--i: 2">
        <div class="a-section">
          <h2>Como funciona</h2>
          <ol class="steps">
            <li><strong>O hóspede pede a reserva</strong> e vai para o checkout do site. As datas ficam seguras por 30 minutos.</li>
            <li><strong>Paga no Pix ou no cartão</strong> sem sair do site da pousada.</li>
            <li><strong>Aprovado, a reserva se confirma</strong> e você recebe um e-mail. Não precisa fazer nada.</li>
            <li><strong>Sem pagar no prazo</strong>, o pedido expira e as datas voltam a ficar livres.</li>
          </ol>
          <p class="a-hint">O dinheiro cai na sua conta do gateway, com as taxas dele. Estornos são feitos pelo painel do Mercado Pago ou da Stripe.</p>
        </div>
        <div class="a-section">
          <h2><ShieldCheck :size="20" aria-hidden="true" /> Segurança</h2>
          <ul class="list">
            <li>As chaves secretas ficam criptografadas e nunca aparecem de novo no painel.</li>
            <li>Os dados do cartão são digitados em campos do próprio gateway: não passam pelo Hospedaki.</li>
            <li>A confirmação sempre é conferida com o gateway, com o valor certo.</li>
          </ul>
        </div>
      </aside>
    </div>
  </section>
</template>

<style scoped lang="scss">
.payments { display: grid; gap: 1.2rem; max-width: 1240px; }
.tag { @include eyebrow(var(--brand-text), sm); margin: 0 0 .2rem; }
.layout { display: grid; gap: 1rem; align-items: start; grid-template-columns: minmax(0, 1fr); @include above(lg) { grid-template-columns: minmax(0, 2fr) minmax(280px, 1fr); } }
.col, .side { display: grid; gap: 1rem; min-width: 0; }
h2 { display: flex; align-items: center; gap: .5rem; }
h2 svg { color: var(--brand-text); }
.demo { margin: 0; padding: .8rem 1rem; border-radius: 14px; background: var(--warn-soft); color: var(--warn-ink); font-size: .92rem; }
.methods { display: grid; gap: .8rem; }
.method { display: grid; gap: .7rem; padding: .9rem; border-radius: 14px; background: var(--a-sunk); }
.method-head { display: flex; gap: .7rem; align-items: center; }
.method-head strong { display: block; }
.method-head small { color: var(--muted); font-size: .84rem; }
.ico { display: grid; place-items: center; flex: none; width: 40px; height: 40px; border-radius: 12px; background: var(--a-card); color: var(--brand-text); }
.ico.pix { color: var(--pix); }
.chips { display: flex; flex-wrap: wrap; gap: .4rem; }
.chips button {
  min-height: 44px; padding: 0 .9rem; border-radius: 12px; border: 1px solid var(--line); background: var(--card);
  font: inherit; font-size: .9rem; font-weight: 700; color: var(--text); cursor: pointer;
  &[aria-checked="true"] { background: var(--brand); border-color: var(--brand); color: var(--brand-ink); }
}
.narrow { max-width: 300px; }
// Gateways em acordeão: lado a lado quando a coluna tem espaço para os dois.
.gateways { display: grid; gap: .7rem; container-type: inline-size; }
.gw-intro h2 { margin: 0 0 .2rem; font-family: var(--font-body); font-size: 1.15rem; font-weight: 700; }
.gw-intro .a-hint { margin: 0; }
.gw-grid { display: grid; gap: 1rem; align-items: start; grid-template-columns: minmax(0, 1fr); }
@container (min-width: 620px) { .gw-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
// No computador, "Como funciona" e "Segurança" acompanham a rolagem.
.side { @include above(lg) { position: sticky; top: 1.5rem; } }
.steps { margin: 0; padding-left: 1.2rem; display: grid; gap: .55rem; font-size: .9rem; }
.steps strong { font-weight: 700; }
.list { margin: 0; padding-left: 1.1rem; display: grid; gap: .35rem; font-size: .88rem; }
.load-error { padding: 1rem 1.2rem; display: grid; gap: .8rem; justify-items: start; border-left: 4px solid var(--danger); }
.load-error p { margin: 0; }
</style>
