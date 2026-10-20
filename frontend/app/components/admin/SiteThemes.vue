<script setup lang="ts">
import { BellRing, Check, Sparkles, UploadCloud } from 'lucide-vue-next'

// "Seu site → Temas" (em breve): trocar o layout do site inteiro por outro tema pronto ou enviar
// um layout feito por um designer. Ainda não troca nada: mostra o tema em uso, os que estão vindo
// e deixa o dono pedir para ser avisado (com um recado do que ele precisa).
const emit = defineEmits<{ message: [text: string] }>()
const { site, load, send } = useAdminSite()
onMounted(() => { if (!site.value) load() })

const THEMES = [
  { key: 'serra', name: 'Serra', text: 'Madeira, tons quentes e fotos grandes: para chalés e pousadas de montanha.', colors: ['#3b2a1e', '#c58b4e', '#efe4d2'] },
  { key: 'praia', name: 'Praia', text: 'Claro e arejado, areia e azul, com a reserva sempre à vista.', colors: ['#0f4c5c', '#5fb4c9', '#f6efe3'] },
  { key: 'urbano', name: 'Urbano', text: 'Minimalista e direto, para hotéis e flats na cidade.', colors: ['#111111', '#d4d4d4', '#ffffff'] },
  { key: 'campo', name: 'Campo', text: 'Verde, texturas naturais e ilustrações, para fazendas e glamping.', colors: ['#24402c', '#8fb06e', '#f3f1e7'] },
]

const note = ref('')
const busy = ref(false)
async function notify() {
  busy.value = true
  try {
    await send('/site/themes/notify', { method: 'PUT', body: { note: note.value.trim() || null } })
    emit('message', 'Pronto! Avisamos você quando os temas chegarem.')
  }
  catch (e) {
    emit('message', apiError(e))
  }
  finally {
    busy.value = false
  }
}
</script>

<template>
  <section class="themes">
    <header class="a-head a-rise">
      <p class="tag"><Sparkles :size="14" aria-hidden="true" /> Em breve</p>
      <h1>Temas e layouts</h1>
      <p>Logo você vai poder trocar o visual do site inteiro com um clique, ou enviar um layout feito por um designer. Enquanto isso, o tema atual se adapta às cores, fotos e textos da sua pousada.</p>
    </header>

    <section class="a-section a-rise" style="--i: 1">
      <h2>Em uso</h2>
      <div class="current">
        <div class="mock" aria-hidden="true" :style="{ '--c1': '#0e1411', '--c2': site?.identity.primary_color ?? 'var(--brand)', '--c3': '#f3f4ef' }">
          <span class="bar" /><span class="hero" /><span class="row"><i /><i /><i /></span>
        </div>
        <div>
          <p class="name">Clássico <span class="pill"><Check :size="13" aria-hidden="true" /> No ar</span></p>
          <p class="a-hint">Capa em tela cheia, "Um dia aqui", catálogo de acomodações e reserva com pagamento. Mude cores e logo em <NuxtLink :to="{ query: { aba: 'identidade' } }">Identidade</NuxtLink> e as seções em <NuxtLink :to="{ query: { aba: 'paginas' } }">Páginas</NuxtLink>.</p>
        </div>
      </div>
    </section>

    <section class="a-section a-rise" style="--i: 2">
      <h2>Chegando</h2>
      <div class="grid">
        <article v-for="t in THEMES" :key="t.key" class="theme">
          <div class="mock" aria-hidden="true" :style="{ '--c1': t.colors[0], '--c2': t.colors[1], '--c3': t.colors[2] }">
            <span class="bar" /><span class="hero" /><span class="row"><i /><i /><i /></span>
          </div>
          <p class="name">{{ t.name }} <span class="soon">Em breve</span></p>
          <p class="a-hint">{{ t.text }}</p>
        </article>
      </div>
    </section>

    <section class="a-section a-rise" style="--i: 3">
      <h2>Enviar um layout próprio</h2>
      <div class="drop" aria-disabled="true">
        <UploadCloud :size="28" aria-hidden="true" />
        <strong>Arraste o pacote do tema aqui</strong>
        <small>Em breve: um arquivo .zip com cores, fontes e o desenho das seções, feito no modelo do Hospedaki.</small>
      </div>
    </section>

    <section class="a-section notify a-rise" style="--i: 4">
      <template v-if="site?.themes_waitlist">
        <p class="done"><BellRing :size="20" aria-hidden="true" /> Você está na lista: avisamos assim que os temas chegarem.</p>
      </template>
      <form v-else class="ask" @submit.prevent="notify">
        <div class="a-field">
          <div class="a-field-head"><label for="themes-note">Quer ser avisado? Conte o que você precisa (opcional)</label></div>
          <textarea id="themes-note" v-model="note" rows="2" maxlength="500" placeholder="Ex.: um tema mais rústico, com a reserva no topo" />
        </div>
        <button class="btn btn-sm" :disabled="busy"><BellRing :size="15" aria-hidden="true" /> {{ busy ? 'Salvando...' : 'Quero ser avisado' }}</button>
      </form>
    </section>
  </section>
</template>

<style scoped lang="scss">
.themes { display: grid; gap: 1.2rem; max-width: 1100px; }
.tag { display: inline-flex; align-items: center; gap: .3rem; margin: 0 0 .2rem; padding: .2rem .6rem; border-radius: 999px; background: var(--warn-soft); color: var(--warn-ink); font-size: .76rem; font-weight: 800; }
.current { display: grid; gap: 1rem; grid-template-columns: minmax(0, 1fr); @include above(md) { grid-template-columns: 220px minmax(0, 1fr); } align-items: center; }
.name { display: flex; align-items: center; gap: .5rem; margin: 0; font-weight: 800; font-size: 1.05rem; }
.pill { display: inline-flex; align-items: center; gap: .2rem; padding: .15rem .5rem; border-radius: 999px; background: var(--ok-soft); color: var(--ok-ink); font-size: .74rem; }
.soon { padding: .15rem .5rem; border-radius: 999px; background: var(--a-sunk); color: var(--muted); font-size: .72rem; font-weight: 800; }
.grid { display: grid; gap: 1rem; grid-template-columns: repeat(auto-fill, minmax(min(100%, 210px), 1fr)); }
.theme { display: grid; gap: .45rem; align-content: start; }
.theme .a-hint { margin: 0; }
// Miniatura do layout: barra do menu, capa e três cartões, nas cores do tema.
.mock { display: grid; gap: 6px; padding: 8px; aspect-ratio: 16 / 10; border-radius: 12px; background: var(--c3); box-shadow: inset 0 0 0 1px rgb(0 0 0 / .08); grid-template-rows: 10px 1fr 34%; }
.mock .bar { border-radius: 3px; background: var(--c1); }
.mock .hero { border-radius: 6px; background: linear-gradient(135deg, var(--c1), var(--c2)); }
.mock .row { display: grid; grid-template-columns: repeat(3, 1fr); gap: 6px; }
.mock .row i { border-radius: 5px; background: color-mix(in srgb, var(--c2) 45%, var(--c3)); }
.theme .mock { opacity: .85; }
.drop { display: grid; justify-items: center; gap: .3rem; padding: 1.6rem 1rem; border-radius: 14px; border: 2px dashed var(--line); color: var(--muted); text-align: center; cursor: not-allowed; }
.drop strong { color: var(--text); }
.drop small { max-width: 44ch; }
.notify .done { margin: 0; display: flex; align-items: center; gap: .5rem; font-weight: 700; color: var(--ok-ink); }
.ask { display: grid; gap: .7rem; justify-items: start; }
.ask .a-field { width: 100%; }
.ask textarea { width: 100%; }
.ask .btn { display: inline-flex; align-items: center; gap: .35rem; }
</style>
