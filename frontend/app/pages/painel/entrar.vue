<script setup lang="ts">
import type { Property } from '~/composables/useApi'
import { ArrowRight, Lock, Mail, Sparkles } from 'lucide-vue-next'

definePageMeta({ layout: 'painel', pageTransition: { name: 'fade', mode: 'out-in' } })

const api = useAdminApi()
const site = useApi()
const token = useAdminToken()

useHead({ title: 'Entrar no painel', meta: [{ name: 'robots', content: 'noindex' }] })

const email = ref('')
const password = ref('')
const error = ref('')
const sending = ref(false)

// A pousada do site: nome, foto de capa e se é a demonstração (entra sem senha). Se a API
// falhar, a tela fica só com o login normal e um fundo liso.
const property = ref<Property | null>(null)
onMounted(async () => {
  property.value = await site<{ data: Property }>('/').then(res => res.data).catch(() => null)
})
const demo = computed(() => !!property.value?.demo)
const cover = computed(() => {
  const p = property.value
  return (p?.photos.find(ph => ph.featured) ?? p?.photos[0] ?? p?.accommodations[0]?.photos[0])?.url ?? null
})

async function enter(path: '/login' | '/demo-login', body?: Record<string, string>) {
  sending.value = true
  error.value = ''
  try {
    const res = await api<{ token: string }>(path, { method: 'POST', body })
    token.value = res.token
    await navigateTo('/painel')
  }
  catch (e) {
    error.value = apiError(e)
  }
  finally {
    sending.value = false
  }
}

const submit = () => enter('/login', { email: email.value, password: password.value })
</script>

<template>
  <main class="wrap">
    <aside class="art" aria-hidden="true">
      <Transition name="photo">
        <img v-if="cover" :src="cover" alt="" class="photo">
      </Transition>
      <div class="art-text">
        <p class="eyebrow">Hospedaki</p>
        <p class="name">{{ property?.name ?? 'Sua pousada' }}</p>
        <p v-if="property?.tagline" class="tag">{{ property.tagline }}</p>
      </div>
    </aside>

    <section class="side">
      <form class="box" @submit.prevent="submit">
        <div class="a-rise">
          <p class="eyebrow dark">Painel da pousada</p>
          <h1>Bem-vindo de volta</h1>
          <p class="sub">Pedidos, reservas, preços e a sua página, num lugar só.</p>
        </div>

        <Transition name="a-tab">
          <div v-if="demo" class="demo">
            <p><Sparkles :size="18" aria-hidden="true" /> <span><strong>Esta é uma demonstração.</strong> Entre como dono, sem senha, e teste à vontade: tudo volta ao início todo dia.</span></p>
            <button type="button" class="btn btn-amber" :disabled="sending" @click="enter('/demo-login')">Entrar como dono (demonstração) <ArrowRight :size="18" class="arrow" /></button>
            <p class="or"><span>ou entre com a sua conta</span></p>
          </div>
        </Transition>

        <label for="email" class="a-rise" style="--i: 1">E-mail
          <span class="field"><Mail :size="18" aria-hidden="true" /><input id="email" v-model="email" type="email" required autocomplete="username" placeholder="voce@suapousada.com.br"></span>
        </label>
        <label for="password" class="a-rise" style="--i: 2">Senha
          <span class="field"><Lock :size="18" aria-hidden="true" /><input id="password" v-model="password" type="password" required autocomplete="current-password" placeholder="••••••••"></span>
        </label>
        <Transition name="a-tab">
          <p v-if="error" class="error" role="alert">{{ error }}</p>
        </Transition>
        <button class="btn a-rise" :class="{ 'btn-ghost': demo }" style="--i: 3" :disabled="sending">{{ sending ? 'Entrando...' : 'Entrar' }} <ArrowRight v-if="!sending" :size="18" class="arrow" /></button>
        <NuxtLink v-if="demo" to="/hospedaki" class="back">Conheça o Hospedaki para pousadas</NuxtLink>
        <NuxtLink v-else to="/" class="back">Voltar ao site</NuxtLink>
      </form>
    </section>
  </main>
</template>

<style scoped lang="scss">
.wrap { min-height: 100vh; display: grid; grid-template-columns: minmax(0, 1.1fr) minmax(0, 1fr); @include below(lg) { grid-template-columns: minmax(0, 1fr); } }

// Foto da pousada: entra devagar e assenta (zoom leve), com o nome por cima.
.art { position: relative; overflow: hidden; background: var(--night); min-height: 100vh; @include below(lg) { min-height: 34vh; } }
.photo { position: absolute; inset: 0; width: 100%; height: 100%; object-fit: cover; }
.art::after { content: ""; position: absolute; inset: 0; background: linear-gradient(180deg, rgb(8 12 10 / .1) 30%, rgb(8 12 10 / .75)); }
.photo-enter-active { transition: opacity $dur-2xl $ease-enter, transform $dur-3xl $ease-enter; }
.photo-enter-from { opacity: 0; transform: scale($zoom-md); }
.art-text { position: absolute; z-index: 1; left: clamp(1.4rem, 4vw, 3rem); right: 1.4rem; bottom: clamp(1.4rem, 5vw, 3.2rem); color: #fff; }
.eyebrow { @include eyebrow(var(--amber-soft)); }
.eyebrow.dark { @include eyebrow(var(--brand-text)); }
.name { @include display(clamp(2.4rem, 5vw, 4rem)); margin: .4rem 0; }
.tag { margin: 0; color: rgb(255 255 255 / .82); max-width: 32ch; }
@include below(lg) { .tag { display: none; } .name { font-size: 2.2rem; } }

.side { display: grid; place-items: center; padding: clamp(1.6rem, 5vw, 3rem) max(16px, env(safe-area-inset-right, 0px)) clamp(2rem, 6vw, 3rem) max(16px, env(safe-area-inset-left, 0px)); }
.box { width: 100%; max-width: 400px; display: grid; gap: 1rem; }
@include below(lg) { .box { background: var(--a-card); border-radius: var(--a-radius); box-shadow: var(--a-shadow); padding: 1.4rem; margin-top: -3rem; position: relative; z-index: 2; } }
h1 { @include display(clamp(2.2rem, 4vw, 3rem)); margin: .4rem 0 .3rem; }
.sub { margin: 0; color: var(--muted); }
.demo { display: grid; gap: .8rem; background: var(--warn-soft); color: var(--warn-ink); border-radius: 16px; padding: 1rem; font-size: .9rem; }
.demo p { margin: 0; display: flex; gap: .5rem; align-items: flex-start; }
.demo svg { flex: none; margin-top: .1rem; }
.demo .btn { justify-content: center; }
.or { display: flex !important; align-items: center; gap: .6rem; color: var(--muted); font-size: .8rem; }
.or::before, .or::after { content: ""; flex: 1; height: 1px; background: color-mix(in srgb, var(--warn-ink) 20%, transparent); }
label { display: grid; gap: .35rem; font-weight: 700; font-size: .88rem; }
.field { position: relative; display: block; }
.field svg { position: absolute; left: .9rem; top: 50%; transform: translateY(-50%); color: var(--muted); pointer-events: none; }
.field input { padding-left: 2.6rem; }
.error { margin: 0; padding: .6rem .8rem; border-radius: 12px; background: color-mix(in srgb, var(--danger) 10%, white); color: var(--danger); font-weight: 700; font-size: .9rem; }
.btn { justify-content: center; }
.back { justify-self: center; display: inline-flex; align-items: center; min-height: 44px; color: var(--muted); font-size: .88rem; font-weight: 600; }
@include reduced-motion { .photo-enter-from { transform: none; } }
</style>
