<script setup lang="ts">
import type { AdminDomain } from '~/composables/useAdminApi'
import { CheckCircle2, CircleAlert, Clock, Copy, ExternalLink, Globe, RefreshCw, Unlink } from 'lucide-vue-next'

// "Seu site → Configurações → Domínio": o endereço próprio do site (pousadadosol.com.br).
// O dono registra onde quiser (Registro.br, GoDaddy, Hostinger...), cola o domínio aqui e cria os
// registros de DNS que a tela mostra. A tela confere o DNS sozinha enquanto espera.
const props = defineProps<{ demo?: boolean }>()
const emit = defineEmits<{ message: [text: string] }>()
const api = useAdminApi()

const data = ref<AdminDomain | null>(null)
const loadError = ref('')
async function load() {
  loadError.value = ''
  try {
    data.value = await api<AdminDomain>('/site/domain')
    input.value = data.value.domain ?? ''
  }
  catch (e) {
    loadError.value = apiError(e)
  }
}
onMounted(load)

const input = ref('')
const busy = ref<'save' | 'check' | 'remove' | null>(null)
const error = ref('')
async function connect() {
  busy.value = 'save'
  error.value = ''
  try {
    data.value = await api<AdminDomain>('/site/domain', { method: 'PUT', body: { domain: input.value } })
    input.value = data.value.domain ?? ''
    emit('message', data.value.status === 'active' ? `${data.value.domain} já está no ar.` : `Domínio salvo. Agora crie os registros de DNS de ${data.value.domain}.`)
  }
  catch (e) {
    error.value = apiError(e)
  }
  finally {
    busy.value = null
  }
}
async function check(silent = false) {
  if (!data.value?.domain) return
  if (!silent) busy.value = 'check'
  try {
    const before = data.value.status
    data.value = await api<AdminDomain>('/site/domain/check', { method: 'POST' })
    if (data.value.status === 'active' && before !== 'active') emit('message', `Pronto: ${data.value.domain} está no ar.`)
    else if (!silent) emit('message', data.value.status === 'misconfigured' ? 'O domínio ainda aponta para outro lugar.' : 'Ainda esperando o DNS. Isso costuma levar de minutos a algumas horas.')
  }
  catch (e) {
    if (!silent) emit('message', apiError(e))
  }
  finally {
    busy.value = null
  }
}
async function remove() {
  if (!data.value?.domain || !confirm(`Desligar ${data.value.domain}? O site continua no endereço do Hospedaki.`)) return
  busy.value = 'remove'
  try {
    data.value = await api<AdminDomain>('/site/domain', { method: 'DELETE' })
    input.value = ''
    emit('message', 'Domínio desligado.')
  }
  catch (e) {
    emit('message', apiError(e))
  }
  finally {
    busy.value = null
  }
}

// Enquanto o DNS não fica pronto, confere sozinho a cada minuto (com a aba à vista).
let timer: ReturnType<typeof setInterval> | undefined
onMounted(() => {
  timer = setInterval(() => {
    if (data.value?.domain && data.value.status !== 'active' && document.visibilityState === 'visible') check(true)
  }, 60_000)
})
onBeforeUnmount(() => clearInterval(timer))

const STATUS = {
  active: { label: 'No ar', tone: 'ok', icon: CheckCircle2, text: 'O site já abre no seu domínio, com HTTPS (o cadeado) ativado.' },
  pending: { label: 'Esperando o DNS', tone: 'wait', icon: Clock, text: 'Crie os registros abaixo no site onde você comprou o domínio. Depois disso, leva de alguns minutos a algumas horas.' },
  misconfigured: { label: 'DNS apontando para outro lugar', tone: 'bad', icon: CircleAlert, text: 'O domínio ainda leva para outra hospedagem. Troque os registros pelos de baixo (e apague os antigos com o mesmo nome).' },
} as const
const status = computed(() => data.value?.status ? STATUS[data.value.status] : null)

// Onde fica a tela de DNS em cada registrador.
const REGISTRARS = [
  { key: 'registro', name: 'Registro.br', steps: ['Entre em registro.br e abra o domínio.', 'Em "DNS", escolha "Editar zona" (use os servidores do Registro.br).', 'Adicione os registros abaixo e salve.'] },
  { key: 'godaddy', name: 'GoDaddy', steps: ['Em "Meus produtos", clique em "DNS" ao lado do domínio.', 'Edite o registro A com nome @ (ou crie) e o CNAME www.', 'Use os valores abaixo e salve.'] },
  { key: 'hostinger', name: 'Hostinger', steps: ['Em "Domínios", escolha o domínio e abra "DNS / Nameservers".', 'Apague o registro A antigo do @ e crie os abaixo.', 'Salve as alterações.'] },
  { key: 'cloudflare', name: 'Cloudflare', steps: ['Abra o domínio e vá em "DNS → Records".', 'Crie os registros abaixo com a nuvem cinza ("DNS only").', 'Salve. Com a nuvem laranja, o HTTPS do site pode falhar.'] },
  { key: 'outro', name: 'Outro', steps: ['Procure a área "DNS", "Zona DNS" ou "Gerenciar DNS" do domínio.', 'Crie os registros abaixo (troque os que tiverem o mesmo nome).', 'Salve e volte aqui: a conferência é automática.'] },
] as const
const registrar = ref<typeof REGISTRARS[number]['key']>('registro')
const steps = computed(() => REGISTRARS.find(r => r.key === registrar.value)!.steps)

async function copy(text: string) {
  try {
    await navigator.clipboard.writeText(text)
    emit('message', `Copiado: ${text}`)
  }
  catch {
    emit('message', 'Não foi possível copiar. Selecione o texto e copie.')
  }
}
const checkedAt = computed(() => data.value?.checked_at ? new Date(data.value.checked_at).toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' }) : null)
</script>

<template>
  <section class="domain">
    <header class="a-head a-rise">
      <h1>Domínio</h1>
      <p>Use o endereço da sua pousada, como <strong>pousadadosol.com.br</strong>, no lugar do endereço do Hospedaki. O domínio continua seu, registrado onde você quiser.</p>
    </header>

    <div v-if="loadError" class="a-card load-error" role="alert">
      <p>Não foi possível carregar o domínio. {{ loadError }}</p>
      <button type="button" class="btn btn-sm" @click="load">Tentar de novo</button>
    </div>
    <AdminLoading v-else-if="!data" text="Carregando domínio..." :cards="2" height="200px" />

    <div v-else class="layout">
      <div class="col">
        <p v-if="props.demo" class="demo a-rise" role="note">Na demonstração o domínio fica travado. No site da sua pousada, é só colar o seu aqui.</p>

        <section class="a-section a-rise" style="--i: 1">
          <h2><Globe :size="20" aria-hidden="true" /> Endereço do site</h2>
          <div class="current">
            <div>
              <span class="a-label">Endereço do Hospedaki</span>
              <a :href="data.default_url" target="_blank" rel="noopener">{{ data.default_url.replace(/^https?:\/\//, '') }} <ExternalLink :size="12" aria-hidden="true" /></a>
            </div>
            <div v-if="data.domain">
              <span class="a-label">Seu domínio</span>
              <a :href="`https://${data.domain}`" target="_blank" rel="noopener">{{ data.domain }} <ExternalLink :size="12" aria-hidden="true" /></a>
            </div>
          </div>
          <div v-if="status" class="status" :class="status.tone" role="status">
            <component :is="status.icon" :size="20" aria-hidden="true" />
            <div>
              <strong>{{ status.label }}</strong>
              <p>{{ status.text }}</p>
              <small v-if="checkedAt">Conferido às {{ checkedAt }}{{ data.status !== 'active' ? ' · confere sozinho a cada minuto' : '' }}</small>
            </div>
            <button v-if="data.status !== 'active'" type="button" class="btn btn-sm btn-ghost" :disabled="busy === 'check'" @click="check()">
              <RefreshCw :size="15" :class="{ spin: busy === 'check' }" aria-hidden="true" /> Conferir agora
            </button>
          </div>

          <form class="connect" @submit.prevent="connect">
            <div class="a-field">
              <div class="a-field-head"><label for="domain-input">{{ data.domain ? 'Trocar o domínio' : 'Seu domínio' }}</label></div>
              <input id="domain-input" v-model="input" type="text" inputmode="url" autocomplete="off" spellcheck="false" placeholder="pousadadosol.com.br" :disabled="props.demo" :aria-invalid="!!error || undefined">
              <small v-if="error" class="a-err">{{ error }}</small>
              <small v-else class="a-hint">Sem https:// e sem www. O www passa a levar para o mesmo site.</small>
            </div>
            <div class="actions">
              <button type="submit" class="btn btn-sm" :disabled="props.demo || busy === 'save' || !input.trim() || input.trim() === data.domain">{{ busy === 'save' ? 'Ligando...' : data.domain ? 'Trocar' : 'Ligar domínio' }}</button>
              <button v-if="data.domain" type="button" class="btn btn-sm btn-ghost" :disabled="props.demo || busy === 'remove'" @click="remove"><Unlink :size="15" aria-hidden="true" /> Desligar</button>
            </div>
          </form>
        </section>

        <section v-if="data.domain && data.status !== 'active'" class="a-section a-rise" style="--i: 2">
          <h2>Aponte o DNS para o site</h2>
          <p class="a-hint">Onde você comprou o domínio:</p>
          <div class="registrars" role="tablist" aria-label="Registrador">
            <button v-for="r in REGISTRARS" :key="r.key" type="button" role="tab" :aria-selected="registrar === r.key" @click="registrar = r.key">{{ r.name }}</button>
          </div>
          <ol class="steps">
            <li v-for="s in steps" :key="s">{{ s }}</li>
          </ol>
          <div class="records" role="table" aria-label="Registros de DNS">
            <div class="row head" role="row"><span role="columnheader">Tipo</span><span role="columnheader">Nome</span><span role="columnheader">Valor</span><span /></div>
            <div v-for="r in [...data.records, ...data.verification.map(v => ({ type: v.type, name: v.domain, value: v.value }))]" :key="r.type + r.name" class="row" role="row">
              <span role="cell"><code>{{ r.type }}</code></span>
              <span role="cell"><code>{{ r.name }}</code></span>
              <span role="cell" class="value"><code>{{ r.value }}</code></span>
              <button type="button" class="icon-btn" :aria-label="`Copiar ${r.value}`" title="Copiar" @click="copy(r.value)"><Copy :size="15" /></button>
            </div>
          </div>
          <p class="a-hint">TTL: pode deixar o padrão. Se já existir um registro com o mesmo tipo e nome, troque o valor em vez de criar outro.</p>
        </section>
      </div>

      <aside class="side a-rise" style="--i: 2">
        <div class="a-section">
          <h2>Como funciona</h2>
          <ol class="list">
            <li><strong>Registre o domínio</strong> no <a href="https://registro.br" target="_blank" rel="noopener">Registro.br</a> (.com.br) ou em qualquer registrador.</li>
            <li><strong>Cole aqui</strong> e crie os registros de DNS que aparecerem.</li>
            <li><strong>Pronto:</strong> o site abre no seu domínio com HTTPS, e o endereço do Hospedaki continua funcionando.</li>
          </ol>
        </div>
        <div v-if="!data.automatic" class="a-section">
          <h2>Ligação no servidor</h2>
          <p class="a-hint">Depois que o DNS estiver certo, a equipe do Hospedaki libera o domínio no servidor do site (com a integração da Vercel configurada, isso acontece sozinho).</p>
        </div>
        <div class="a-section">
          <h2>E-mail no domínio</h2>
          <p class="a-hint">Os registros acima não mexem no e-mail (MX). Se você já usa contato@seudominio, ele continua funcionando.</p>
        </div>
      </aside>
    </div>
  </section>
</template>

<style scoped lang="scss">
.domain { display: grid; gap: 1.2rem; max-width: 1240px; }
.layout { display: grid; gap: 1rem; align-items: start; grid-template-columns: minmax(0, 1fr); @include above(lg) { grid-template-columns: minmax(0, 1.7fr) minmax(260px, 1fr); } }
.col { display: grid; gap: 1rem; min-width: 0; }
.side { display: grid; gap: 1rem; @include above(lg) { position: sticky; top: 1.5rem; } }
h2 { display: flex; align-items: center; gap: .5rem; }
h2 svg { color: var(--brand-text); }
.demo { margin: 0; padding: .8rem 1rem; border-radius: 14px; background: var(--warn-soft); color: var(--warn-ink); font-size: .92rem; }
.current { display: flex; flex-wrap: wrap; gap: .8rem 2rem; }
.current > div { display: grid; gap: .15rem; }
.current a { display: inline-flex; align-items: center; gap: .3rem; font-weight: 700; color: var(--brand-text); overflow-wrap: anywhere; }
.status { display: flex; align-items: flex-start; gap: .7rem; padding: .8rem .9rem; border-radius: 14px; }
.status > div { flex: 1; min-width: 0; }
.status p { margin: .15rem 0; font-size: .88rem; }
.status small { opacity: .8; font-size: .78rem; }
.status.ok { background: var(--ok-soft); color: var(--ok-ink); }
.status.wait { background: var(--warn-soft); color: var(--warn-ink); }
.status.bad { background: color-mix(in srgb, var(--danger) 10%, white); color: var(--danger); }
.status .btn { flex: none; display: inline-flex; align-items: center; gap: .3rem; }
.spin { animation: spin .9s linear infinite; }
@keyframes spin { to { transform: rotate(360deg); } }
.connect { display: grid; gap: .7rem; }
.connect input { width: 100%; }
.actions { display: flex; flex-wrap: wrap; gap: .5rem; }
.actions .btn { display: inline-flex; align-items: center; gap: .3rem; }
.registrars { display: flex; flex-wrap: wrap; gap: .4rem; }
.registrars button {
  min-height: 40px; padding: 0 .8rem; border-radius: 10px; border: 1px solid var(--line); background: var(--card);
  font: inherit; font-weight: 700; font-size: .86rem; color: var(--text); cursor: pointer;
  &[aria-selected="true"] { background: var(--night); border-color: var(--night); color: #fff; }
}
.steps, .list { margin: 0; padding-left: 1.2rem; display: grid; gap: .35rem; font-size: .9rem; }
.records { display: grid; border-radius: 12px; overflow: hidden; box-shadow: inset 0 0 0 1px var(--line); }
.row { display: grid; grid-template-columns: 70px minmax(60px, .6fr) minmax(0, 1.4fr) 44px; align-items: center; gap: .5rem; padding: .45rem .6rem; }
.row + .row { border-top: 1px solid var(--line); }
.row.head { background: var(--a-sunk); font-size: .76rem; font-weight: 800; color: var(--muted); text-transform: uppercase; letter-spacing: .04em; }
.row code { font-size: .84rem; overflow-wrap: anywhere; }
.icon-btn { display: grid; place-items: center; width: 36px; height: 36px; border: 0; border-radius: 9px; background: var(--a-sunk); color: var(--muted); cursor: pointer; }
.load-error { padding: 1rem 1.2rem; display: grid; gap: .8rem; justify-items: start; border-left: 4px solid var(--danger); }
.load-error p { margin: 0; }
@include reduced-motion { .spin { animation-duration: 2.5s; } }
</style>
