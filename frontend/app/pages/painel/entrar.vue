<script setup lang="ts">
definePageMeta({ layout: 'painel', pageTransition: { name: 'fade', mode: 'out-in' } })

const api = useAdminApi()
const site = useApi()
const token = useAdminToken()

useHead({ title: 'Entrar no painel', meta: [{ name: 'robots', content: 'noindex' }] })

const email = ref('')
const password = ref('')
const error = ref('')
const sending = ref(false)

// Pousada de demonstração: entra sem senha. A pergunta vai à API, que sabe se a demo está ligada;
// se ela falhar, a tela fica só com o login normal.
const demo = ref(false)
onMounted(async () => {
  demo.value = await site<{ data: { demo?: boolean } }>('/').then(res => !!res.data.demo).catch(() => false)
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
    <form class="box" @submit.prevent="submit">
      <p class="eyebrow">Hospedaki</p>
      <h1>Painel da pousada</h1>
      <p class="sub">Entre para ver e confirmar seus pedidos de reserva.</p>
      <div v-if="demo" class="demo">
        <p><strong>Esta é uma demonstração.</strong> Entre como dono da pousada, sem senha, e teste à vontade: tudo volta ao início todo dia.</p>
        <button type="button" class="btn btn-amber" :disabled="sending" @click="enter('/demo-login')">Entrar como dono (demonstração)</button>
        <p class="or">ou entre com a sua conta</p>
      </div>
      <label for="email">E-mail <input id="email" v-model="email" type="email" required autocomplete="username"></label>
      <label for="password">Senha <input id="password" v-model="password" type="password" required autocomplete="current-password"></label>
      <p v-if="error" class="error" role="alert">{{ error }}</p>
      <button class="btn" :class="{ 'btn-ghost': demo }" :disabled="sending">{{ sending ? 'Entrando...' : 'Entrar' }}</button>
      <NuxtLink v-if="demo" to="/hospedaki" class="back">Conheça o Hospedaki para pousadas</NuxtLink>
      <NuxtLink v-else to="/" class="back">Voltar ao site</NuxtLink>
    </form>
  </main>
</template>

<style scoped lang="scss">
.wrap { min-height: 100vh; display: grid; place-items: center; padding-block: 2rem; padding-inline: max(16px, env(safe-area-inset-left, 0px)) max(16px, env(safe-area-inset-right, 0px)); background: var(--soft); }
.box { width: 100%; max-width: 380px; background: var(--card); border: 1px solid var(--line); border-radius: var(--radius); padding: 1.8rem; display: grid; gap: .9rem; }
h1 { margin: 0; font-size: 1.8rem; }
.sub { margin: 0; color: var(--muted); }
.demo { display: grid; gap: .7rem; background: var(--warn-soft); color: var(--warn-ink); border-radius: 10px; padding: .9rem; font-size: .9rem; }
.demo p { margin: 0; }
.demo .or { text-align: center; color: var(--muted); font-size: .82rem; }
.back { justify-self: center; display: inline-flex; align-items: center; min-height: 44px; color: var(--muted); font-size: .88rem; }
label { display: grid; gap: .3rem; font-weight: 700; font-size: .88rem; }
.error { color: var(--danger); font-weight: 700; margin: 0; }
</style>
