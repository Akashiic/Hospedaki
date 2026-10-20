<script setup lang="ts">
import type { AdminGateway, AdminPayments, GatewayMode } from '~/composables/useAdminApi'
import { ChevronDown, Copy, ExternalLink, FlaskConical, Rocket, Trash2 } from 'lucide-vue-next'

// Um gateway (Mercado Pago ou Stripe) em acordeão. Dentro: ligar e desligar, o modo produção
// (desligado = testes) e as chaves do modo escolhido. Cada modo guarda as suas chaves.
//
// O botão de modo mostra as chaves daquele modo; se elas já estão salvas, o site passa a usar o
// modo na hora. Se não, o site continua no modo atual até as chaves novas serem salvas.
const props = defineProps<{ provider: 'mercadopago' | 'stripe', gateway: AdminGateway, demo: boolean }>()
const emit = defineEmits<{ updated: [data: AdminPayments], message: [text: string] }>()
const api = useAdminApi()

const INFO = {
  mercadopago: {
    name: 'Mercado Pago', mark: 'MP', color: '#00a6e4',
    publicLabel: 'Public Key', secretLabel: 'Access Token',
    placeholders: { test: ['TEST-...', 'TEST-...'], live: ['APP_USR-...', 'APP_USR-...'] },
    where: { test: 'Suas integrações → sua aplicação → Credenciais de teste', live: 'Suas integrações → sua aplicação → Credenciais de produção' },
    link: 'https://www.mercadopago.com.br/developers/panel/app',
    webhook: 'Em Webhooks, cadastre este endereço com o evento "Pagamentos" e cole a assinatura secreta.',
  },
  stripe: {
    name: 'Stripe', mark: 'S', color: '#635bff',
    publicLabel: 'Chave publicável', secretLabel: 'Chave secreta',
    placeholders: { test: ['pk_test_...', 'sk_test_...'], live: ['pk_live_...', 'sk_live_...'] },
    where: { test: 'Desenvolvedores → Chaves de API, com "Modo de teste" ligado', live: 'Desenvolvedores → Chaves de API, fora do modo de teste' },
    link: 'https://dashboard.stripe.com/apikeys',
    webhook: 'Em Desenvolvedores → Webhooks, adicione este endereço com payment_intent.succeeded e payment_intent.payment_failed e cole o segredo (whsec_...). Teste e produção têm segredos diferentes.',
  },
} as const
const info = computed(() => INFO[props.provider])

const open = ref(false)
const id = useId()
// Modo que o dono está vendo (começa no modo em uso).
const view = ref<GatewayMode>(props.gateway.mode)
watch(() => props.gateway.mode, (m) => { view.value = m })
const keys = computed(() => props.gateway[view.value])
const modeName = (m: GatewayMode) => m === 'live' ? 'produção' : 'testes'

const status = computed(() => {
  const g = props.gateway
  if (props.demo) return { label: 'Simulado na demonstração', tone: 'muted' }
  if (g.active) return { label: g.mode === 'live' ? 'Ativo · Produção' : 'Ativo · Testes', tone: g.mode === 'live' ? 'ok' : 'test' }
  if (g.test.configured || g.live.configured) return { label: 'Desligado', tone: 'muted' }
  return { label: 'Não configurado', tone: 'muted' }
})

const form = reactive({ public_key: '', secret_key: '', webhook_secret: '' })
watch([view, () => props.gateway], () => {
  form.public_key = keys.value.public_key ?? ''
  form.secret_key = ''
  form.webhook_secret = ''
}, { immediate: true })

const busy = ref(false)
const errors = ref<Record<string, string[]>>({})
const err = (f: string) => errors.value[f]?.[0]

async function run(task: () => Promise<AdminPayments>, done: (data: AdminPayments) => string) {
  busy.value = true
  errors.value = {}
  try {
    const data = await task()
    emit('updated', data)
    emit('message', done(data))
  }
  catch (e) {
    errors.value = apiFieldErrors(e)
    emit('message', apiError(e))
  }
  finally {
    busy.value = false
  }
}
const setState = (enabled: boolean, mode: GatewayMode) =>
  api<AdminPayments>(`/payments/gateways/${props.provider}/state`, { method: 'PUT', body: { enabled, mode } })

// Liga ou desliga, no modo em uso (ou no que está na tela, se só ele tem chaves).
function toggleEnabled() {
  const mode = props.gateway[props.gateway.mode].configured ? props.gateway.mode : view.value
  const enabled = !props.gateway.enabled
  run(() => setState(enabled, mode), () => enabled ? `${info.value.name} ativado no modo ${modeName(mode)}.` : `${info.value.name} desativado: o site para de cobrar por ele.`)
}

// Produção ligada ou não. Com as chaves daquele modo salvas, o site já troca.
function toggleMode() {
  const next: GatewayMode = view.value === 'live' ? 'test' : 'live'
  view.value = next
  if (props.gateway[next].configured && props.gateway.mode !== next) {
    run(() => setState(props.gateway.enabled, next), () => next === 'live'
      ? `${info.value.name} em produção: os pagamentos agora são de verdade.`
      : `${info.value.name} em testes: nenhum valor é cobrado de verdade.`)
  }
}
const pendingSwitch = computed(() => view.value !== props.gateway.mode && !keys.value.configured)

async function saveKeys() {
  const mode = view.value
  await run(async () => {
    let data = await api<AdminPayments>(`/payments/gateways/${props.provider}`, { method: 'PUT', body: { mode, ...form } })
    // O botão de modo diz qual modo o dono quer: salvas as chaves, o site passa a usar esse modo.
    if (data.gateways[props.provider].mode !== mode) data = await setState(data.gateways[props.provider].enabled, mode)
    return data
  }, data => `Chaves de ${modeName(mode)} salvas e testadas.${data.gateways[props.provider].active ? ` ${info.value.name} ativo em ${modeName(mode)}.` : ''}`)
}

async function removeKeys() {
  const mode = view.value
  if (!confirm(`Apagar as chaves de ${modeName(mode)} do ${info.value.name}?${props.gateway.mode === mode ? ' Ele deixa de cobrar no site.' : ''}`)) return
  await run(() => api<AdminPayments>(`/payments/gateways/${props.provider}`, { method: 'DELETE', body: { mode } }), () => `Chaves de ${modeName(mode)} apagadas.`)
}

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
  <section class="gw a-card" :class="{ open }">
    <h3 class="head-wrap">
      <button type="button" class="head" :aria-expanded="open" :aria-controls="`${id}-body`" @click="open = !open">
        <span class="mark" :style="{ background: info.color }" aria-hidden="true">{{ info.mark }}</span>
        <span class="title">
          <strong>{{ info.name }}</strong>
          <small>{{ open ? 'Toque para fechar' : 'Toque para configurar' }}</small>
        </span>
        <span class="pill" :class="status.tone"><span class="a-dot" />{{ status.label }}</span>
        <ChevronDown :size="20" class="chev" aria-hidden="true" />
      </button>
    </h3>

    <Transition name="drop">
      <div v-show="open" :id="`${id}-body`" class="body">
        <fieldset :disabled="demo || busy" class="lock">
          <div class="switches">
            <button type="button" class="switch" role="switch" :aria-checked="gateway.enabled" :disabled="!gateway.test.configured && !gateway.live.configured" @click="toggleEnabled">
              <span class="track"><span class="thumb" /></span>
              <span class="sw-text"><strong>Gateway ativo</strong><small>{{ gateway.enabled ? 'Cobra no site' : (gateway.test.configured || gateway.live.configured ? 'Não cobra no site' : 'Salve as chaves para ativar') }}</small></span>
            </button>
            <button type="button" class="switch live" role="switch" :aria-checked="view === 'live'" @click="toggleMode">
              <span class="track"><span class="thumb" /></span>
              <span class="sw-text"><strong>Modo produção</strong><small>{{ view === 'live' ? 'Pagamentos de verdade' : 'Desligado: modo de testes' }}</small></span>
            </button>
          </div>

          <p class="mode-banner" :class="view">
            <component :is="view === 'live' ? Rocket : FlaskConical" :size="16" aria-hidden="true" />
            <span v-if="view === 'live'">Chaves de <strong>produção</strong>: os pagamentos são cobrados de verdade.</span>
            <span v-else>Chaves de <strong>teste</strong>: use os cartões e o Pix de teste do {{ info.name }}; nada é cobrado.</span>
          </p>
          <p v-if="pendingSwitch" class="note" role="status">
            Ainda não há chaves de {{ modeName(view) }}. Cole e salve abaixo{{ gateway.active ? `; até lá o site continua em ${modeName(gateway.mode)}` : '' }}.
          </p>

          <form class="keys" @submit.prevent="saveKeys">
            <p class="a-hint">Onde encontrar: <strong>{{ info.where[view] }}</strong>. <a :href="info.link" target="_blank" rel="noopener">Abrir o painel <ExternalLink :size="12" aria-hidden="true" /></a></p>
            <AdminTextField :id="`${id}-pub`" v-model="form.public_key" :label="info.publicLabel" :placeholder="info.placeholders[view][0]" :error="err('public_key')" />
            <div class="a-field">
              <div class="a-field-head"><label :for="`${id}-secret`">{{ info.secretLabel }}</label></div>
              <input :id="`${id}-secret`" v-model="form.secret_key" type="password" autocomplete="off" :placeholder="keys.secret_hint ? `${keys.secret_hint} salva; vazio mantém` : info.placeholders[view][1]" :aria-invalid="!!err('secret_key') || undefined">
              <small v-if="err('secret_key')" class="a-err">{{ err('secret_key') }}</small>
            </div>
            <details class="webhook">
              <summary>Notificações automáticas (webhook) <span v-if="keys.webhook_secret" class="saved">configurado</span></summary>
              <p class="a-hint">{{ info.webhook }} Assim a reserva se confirma no instante do pagamento.</p>
              <div class="url">
                <code>{{ gateway.webhook_url }}</code>
                <button type="button" class="icon-btn" aria-label="Copiar endereço" title="Copiar" @click="copy(gateway.webhook_url)"><Copy :size="16" /></button>
              </div>
              <div class="a-field">
                <div class="a-field-head"><label :for="`${id}-whsec`">Assinatura secreta ({{ modeName(view) }})</label></div>
                <input :id="`${id}-whsec`" v-model="form.webhook_secret" type="password" autocomplete="off" :placeholder="keys.webhook_secret ? 'Salva; vazio mantém' : (provider === 'stripe' ? 'whsec_...' : 'Opcional, mas recomendada')">
              </div>
            </details>
            <div class="actions">
              <button type="submit" class="btn btn-sm" :disabled="busy">{{ busy ? 'Testando as chaves...' : `Salvar chaves de ${modeName(view)}` }}</button>
              <button v-if="keys.configured" type="button" class="btn btn-ghost btn-sm" @click="removeKeys"><Trash2 :size="15" aria-hidden="true" /> Apagar</button>
            </div>
          </form>
        </fieldset>
        <p v-if="demo" class="a-hint">Na demonstração as chaves ficam travadas: o dinheiro de um visitante iria para a conta de outro.</p>
      </div>
    </Transition>
  </section>
</template>

<style scoped lang="scss">
.gw { padding: 0; overflow: hidden; min-width: 0; container-type: inline-size; }
.head-wrap { margin: 0; font: inherit; }
.head {
  position: relative; display: flex; align-items: center; gap: .75rem; width: 100%; min-height: 72px; padding: .9rem 1rem;
  border: 0; background: none; font: inherit; color: var(--text); text-align: left; cursor: pointer; isolation: isolate;
  &::before { content: ""; position: absolute; inset: 0; z-index: -1; background: var(--a-sunk); opacity: 0; transition: opacity $dur-sm $ease-move; }
  &:hover::before { opacity: .6; }
  &:focus-visible { outline: 3px solid var(--focus); outline-offset: -3px; }
}
.mark { display: grid; place-items: center; flex: none; width: 42px; height: 42px; border-radius: 12px; color: #fff; font-weight: 800; font-size: .95rem; letter-spacing: .02em; }
.title { display: grid; flex: 1; min-width: 0; }
.title strong { font-size: 1.05rem; }
.title small { color: var(--muted); font-size: .8rem; }
.pill { display: inline-flex; align-items: center; gap: .35rem; flex: none; padding: .25rem .6rem; border-radius: 999px; font-size: .76rem; font-weight: 800; background: var(--a-sunk); color: var(--muted); white-space: nowrap; }
.pill.ok { background: var(--ok-soft); color: var(--ok-ink); }
.pill.test { background: var(--warn-soft); color: var(--warn-ink); }
.chev { flex: none; color: var(--muted); transition: transform $dur-md $ease-move; }
.open .chev { transform: rotate(180deg); }
@container (max-width: 380px) { .pill { display: none; } }

.body { display: grid; gap: .9rem; padding: .2rem 1rem 1.1rem; }
.lock { display: grid; gap: .9rem; margin: 0; padding: 0; border: 0; min-width: 0; }
.lock:disabled { opacity: .7; }
.switches { display: grid; gap: .5rem; grid-template-columns: repeat(auto-fit, minmax(min(100%, 190px), 1fr)); }
.switch {
  display: flex; align-items: center; gap: .65rem; min-height: 56px; padding: .55rem .7rem; border-radius: 12px; border: 0;
  background: var(--a-sunk); font: inherit; color: var(--text); text-align: left; cursor: pointer;
  &:disabled { cursor: not-allowed; opacity: .6; }
  &:focus-visible { outline: 3px solid var(--focus); outline-offset: 2px; }
}
.track { position: relative; flex: none; width: 42px; height: 24px; border-radius: 999px; background: color-mix(in srgb, var(--line) 70%, var(--muted)); }
.thumb { position: absolute; top: 3px; left: 3px; width: 18px; height: 18px; border-radius: 50%; background: #fff; box-shadow: 0 1px 3px rgb(0 0 0 / .25); transition: transform $dur-sm $ease-move; }
.switch[aria-checked="true"] .track { background: var(--brand); }
.switch.live[aria-checked="true"] .track { background: var(--ok-ink); }
.switch[aria-checked="true"] .thumb { transform: translateX(18px); }
.sw-text { display: grid; min-width: 0; }
.sw-text strong { font-size: .9rem; }
.sw-text small { color: var(--muted); font-size: .78rem; }
.mode-banner { margin: 0; display: flex; align-items: center; gap: .45rem; padding: .55rem .75rem; border-radius: 10px; font-size: .85rem; }
.mode-banner.test { background: var(--warn-soft); color: var(--warn-ink); }
.mode-banner.live { background: var(--ok-soft); color: var(--ok-ink); }
.note { margin: 0; padding: .55rem .75rem; border-radius: 10px; background: var(--a-sunk); font-size: .85rem; font-weight: 600; }
.keys { display: grid; gap: .8rem; }
.keys input { width: 100%; }
.keys a { display: inline-flex; align-items: center; gap: .2rem; font-weight: 700; color: var(--brand-text); }
.webhook { padding: .7rem .8rem; border-radius: 12px; background: var(--a-sunk); }
.webhook[open] { display: grid; gap: .55rem; }
.webhook summary { cursor: pointer; font-weight: 700; font-size: .88rem; }
.webhook .a-hint { margin: 0; }
.saved { margin-left: .3rem; padding: .05rem .45rem; border-radius: 999px; background: var(--ok-soft); color: var(--ok-ink); font-size: .72rem; }
.url { display: flex; align-items: center; gap: .4rem; min-width: 0; }
.url code { flex: 1; min-width: 0; padding: .5rem .6rem; border-radius: 10px; background: var(--a-card); font-size: .78rem; overflow-wrap: anywhere; }
.icon-btn { display: grid; place-items: center; flex: none; width: 40px; height: 40px; border: 0; border-radius: 10px; background: var(--a-card); color: var(--muted); cursor: pointer; }
.actions { display: flex; flex-wrap: wrap; gap: .5rem; }
.actions .btn { display: inline-flex; align-items: center; gap: .35rem; }
.drop-enter-active { transition: opacity $dur-md $ease-enter, transform $dur-lg $ease-enter; }
.drop-leave-active { transition: opacity $dur-xs $ease-exit; }
.drop-enter-from { opacity: 0; transform: translateY(-#{$dist-xs}); }
.drop-leave-to { opacity: 0; }
@include reduced-motion { .drop-enter-from { transform: none; } .chev { transition: none; } }
</style>
