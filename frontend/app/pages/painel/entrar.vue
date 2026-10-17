<script setup lang="ts">
const api = useAdminApi()
const token = useAdminToken()
const demo = useRuntimeConfig().public.demo

useHead({ title: 'Entrar no painel', meta: [{ name: 'robots', content: 'noindex' }] })

const email = ref(demo ? 'dono@exemplo.com.br' : '')
const password = ref(demo ? 'demonstracao' : '')
const error = ref('')
const sending = ref(false)

async function submit() {
  sending.value = true
  error.value = ''
  try {
    const res = await api<{ token: string }>('/login', { method: 'POST', body: { email: email.value, password: password.value } })
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
</script>

<template>
  <main class="wrap">
    <form class="box" @submit.prevent="submit">
      <p class="eyebrow">Hospedaki</p>
      <h1>Painel da pousada</h1>
      <p class="sub">Entre para ver e confirmar seus pedidos de reserva.</p>
      <p v-if="demo" class="demo">Prévia: é só clicar em Entrar.</p>
      <label for="email">E-mail <input id="email" v-model="email" type="email" required autocomplete="username"></label>
      <label for="password">Senha <input id="password" v-model="password" type="password" required autocomplete="current-password"></label>
      <p v-if="error" class="error">{{ error }}</p>
      <button class="btn" :disabled="sending">{{ sending ? 'Entrando...' : 'Entrar' }}</button>
    </form>
  </main>
</template>

<style scoped>
.wrap { min-height: 100vh; display: grid; place-items: center; padding-block: 2rem; padding-inline: max(16px, env(safe-area-inset-left, 0px)) max(16px, env(safe-area-inset-right, 0px)); background: var(--soft); }
.box { width: 100%; max-width: 380px; background: var(--card); border: 1px solid var(--line); border-radius: var(--radius); padding: 1.8rem; display: grid; gap: .9rem; }
h1 { margin: 0; font-size: 1.8rem; }
.sub { margin: 0; color: var(--muted); }
.demo { margin: 0; background: var(--soft); border-radius: 10px; padding: .6rem .8rem; font-size: .9rem; }
label { display: grid; gap: .3rem; font-weight: 700; font-size: .88rem; }
.error { color: var(--danger); font-weight: 700; margin: 0; }
</style>
