<script setup lang="ts">
import type { AdminPayments, PaymentProvider } from '~/composables/useAdminApi'
import { CheckCircle2, Copy, CreditCard, KeyRound, QrCode, ShieldCheck, Unplug } from 'lucide-vue-next'

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

// ── Chaves de cada gateway ──
const keys = reactive({
  mercadopago: { public_key: '', access_token: '', webhook_secret: '' },
  stripe: { publishable_key: '', secret_key: '', webhook_secret: '' },
})
watch(data, (d) => {
  if (!d) return
  keys.mercadopago.public_key = d.gateways.mercadopago.public_key ?? ''
  keys.stripe.publishable_key = d.gateways.stripe.publishable_key ?? ''
}, { immediate: true })
const keyErrors = ref<Record<string, Record<string, string[]>>>({})
const keyBusy = ref<string | null>(null)

async function saveKeys(provider: 'mercadopago' | 'stripe') {
  keyBusy.value = provider
  keyErrors.value = { ...keyErrors.value, [provider]: {} }
  try {
    data.value = await api<AdminPayments>(`/payments/gateways/${provider}`, { method: 'PUT', body: { ...keys[provider] } })
    keys[provider].webhook_secret = ''
    if (provider === 'mercadopago') keys.mercadopago.access_token = ''
    else keys.stripe.secret_key = ''
    emit('message', `${providerName(provider)} conectado. As chaves foram testadas e estão funcionando.`)
  }
  catch (e) {
    keyErrors.value = { ...keyErrors.value, [provider]: apiFieldErrors(e) }
    if (!Object.keys(apiFieldErrors(e)).length) emit('message', apiError(e))
  }
  finally {
    keyBusy.value = null
  }
}

async function disconnect(provider: 'mercadopago' | 'stripe') {
  if (!confirm(`Desconectar o ${providerName(provider)}? As chaves salvas são apagadas e ele deixa de cobrar no site.`)) return
  keyBusy.value = provider
  try {
    data.value = await api<AdminPayments>(`/payments/gateways/${provider}`, { method: 'DELETE' })
    emit('message', `${providerName(provider)} desconectado.`)
  }
  catch (e) {
    emit('message', apiError(e))
  }
  finally {
    keyBusy.value = null
  }
}
const keyErr = (provider: string, field: string) => keyErrors.value[provider]?.[field]?.[0]

async function copy(text: string) {
  try {
    await navigator.clipboard.writeText(text)
    emit('message', 'Endereço copiado.')
  }
  catch {
    emit('message', 'Não foi possível copiar. Selecione o texto e copie.')
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

        <!-- Na demonstração as chaves aparecem, mas travadas: o painel é público. -->
          <section v-for="(p, i) in PROVIDERS" :key="p.key" class="a-section gateway a-rise" :style="{ '--i': i + 2 }">
            <div class="gw-head">
              <h2><KeyRound :size="20" aria-hidden="true" /> {{ p.name }}</h2>
              <span v-if="data.gateways[p.key].configured" class="pill ok"><CheckCircle2 :size="14" aria-hidden="true" /> Conectado{{ data.gateways[p.key].test ? ' · teste' : '' }}</span>
              <span v-else class="pill">Não conectado</span>
            </div>

            <form class="keys" @submit.prevent="saveKeys(p.key)">
              <fieldset :disabled="data.demo" class="lock">
              <template v-if="p.key === 'mercadopago'">
                <p class="a-hint">No <a href="https://www.mercadopago.com.br/developers/panel/app" target="_blank" rel="noopener">painel de desenvolvedor ↗</a>, abra a sua aplicação e vá em <strong>Credenciais de produção</strong>. Use as de teste para experimentar sem cobrar.</p>
                <AdminTextField id="mp-public" v-model="keys.mercadopago.public_key" label="Public Key" placeholder="APP_USR-..." :error="keyErr('mercadopago', 'public_key')" />
                <div class="a-field">
                  <label for="mp-token">Access Token</label>
                  <input id="mp-token" v-model="keys.mercadopago.access_token" type="password" autocomplete="off" :placeholder="data.gateways.mercadopago.access_token_hint ? `Salvo (${data.gateways.mercadopago.access_token_hint}). Em branco, continua o mesmo.` : 'APP_USR-...'" :aria-invalid="!!keyErr('mercadopago', 'access_token') || undefined">
                  <small v-if="keyErr('mercadopago', 'access_token')" class="a-err">{{ keyErr('mercadopago', 'access_token') }}</small>
                </div>
              </template>
              <template v-else>
                <p class="a-hint">No <a href="https://dashboard.stripe.com/apikeys" target="_blank" rel="noopener">painel da Stripe ↗</a>, em <strong>Desenvolvedores → Chaves de API</strong>. O Pix precisa estar ativo em Configurações → Formas de pagamento.</p>
                <AdminTextField id="st-public" v-model="keys.stripe.publishable_key" label="Chave publicável" placeholder="pk_live_..." :error="keyErr('stripe', 'publishable_key')" />
                <div class="a-field">
                  <label for="st-secret">Chave secreta</label>
                  <input id="st-secret" v-model="keys.stripe.secret_key" type="password" autocomplete="off" :placeholder="data.gateways.stripe.secret_key_hint ? `Salva (${data.gateways.stripe.secret_key_hint}). Em branco, continua a mesma.` : 'sk_live_...'" :aria-invalid="!!keyErr('stripe', 'secret_key') || undefined">
                  <small v-if="keyErr('stripe', 'secret_key')" class="a-err">{{ keyErr('stripe', 'secret_key') }}</small>
                </div>
              </template>

              <div class="webhook">
                <p class="a-label">Notificações (webhook)</p>
                <p class="a-hint">{{ p.key === 'mercadopago' ? 'Em Webhooks, cadastre este endereço com o evento "Pagamentos"' : 'Em Desenvolvedores → Webhooks, adicione este endereço com os eventos payment_intent.succeeded e payment_intent.payment_failed' }} e cole a assinatura secreta abaixo. Assim a reserva se confirma no instante do pagamento.</p>
                <div class="url">
                  <code>{{ data.gateways[p.key].webhook_url }}</code>
                  <button type="button" class="icon-btn" aria-label="Copiar endereço" title="Copiar" @click="copy(data.gateways[p.key].webhook_url)"><Copy :size="16" /></button>
                </div>
                <div class="a-field">
                  <label :for="`${p.key}-whsec`">Assinatura secreta</label>
                  <input :id="`${p.key}-whsec`" v-model="keys[p.key].webhook_secret" type="password" autocomplete="off" :placeholder="data.gateways[p.key].webhook_secret ? 'Salva. Em branco, continua a mesma.' : (p.key === 'stripe' ? 'whsec_...' : 'Opcional, mas recomendada')">
                </div>
              </div>

              <p v-if="data.demo" class="a-hint">Na demonstração as chaves ficam travadas: o dinheiro de um visitante iria para a conta de outro.</p>
              <div class="gw-actions">
                <button type="submit" class="btn btn-sm" :disabled="keyBusy === p.key">{{ keyBusy === p.key ? 'Testando as chaves...' : (data.gateways[p.key].configured ? 'Salvar e testar' : 'Conectar') }}</button>
                <button v-if="data.gateways[p.key].configured" type="button" class="btn btn-ghost btn-sm" :disabled="keyBusy === p.key" @click="disconnect(p.key)"><Unplug :size="16" aria-hidden="true" /> Desconectar</button>
              </div>
              </fieldset>
            </form>
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
.layout { display: grid; gap: 1rem; align-items: start; grid-template-columns: minmax(0, 1fr); @include above(lg) { grid-template-columns: minmax(0, 1.4fr) minmax(0, 1fr); } }
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
.gw-head { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: .5rem; }
.gw-head h2 { margin: 0; }
.pill { display: inline-flex; align-items: center; gap: .3rem; padding: .25rem .6rem; border-radius: 999px; background: var(--a-sunk); color: var(--muted); font-size: .8rem; font-weight: 700; }
.pill.ok { background: var(--ok-soft); color: var(--ok-ink); }
.keys, .lock { display: grid; gap: .9rem; }
.lock { margin: 0; padding: 0; border: 0; min-width: 0; }
.lock:disabled { opacity: .7; }
.keys input { width: 100%; }
.webhook { display: grid; gap: .5rem; padding: .9rem; border-radius: 14px; background: var(--a-sunk); }
.webhook .a-label, .webhook .a-hint { margin: 0; }
.url { display: flex; align-items: center; gap: .4rem; min-width: 0; }
.url code { flex: 1; min-width: 0; padding: .5rem .6rem; border-radius: 10px; background: var(--a-card); font-size: .8rem; overflow-wrap: anywhere; }
.icon-btn { display: grid; place-items: center; flex: none; width: 40px; height: 40px; border: 0; border-radius: 10px; background: var(--a-card); color: var(--muted); cursor: pointer; }
.gw-actions { display: flex; flex-wrap: wrap; gap: .5rem; }
.gw-actions .btn { display: inline-flex; align-items: center; gap: .35rem; }
.steps { margin: 0; padding-left: 1.2rem; display: grid; gap: .55rem; font-size: .9rem; }
.steps strong { font-weight: 700; }
.list { margin: 0; padding-left: 1.1rem; display: grid; gap: .35rem; font-size: .88rem; }
.load-error { padding: 1rem 1.2rem; display: grid; gap: .8rem; justify-items: start; border-left: 4px solid var(--danger); }
.load-error p { margin: 0; }
</style>
