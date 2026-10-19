<script setup lang="ts">
// Aviso da pousada de demonstração: leva ao painel do dono e à página do Hospedaki para pousadas.
// Fica preso no canto (no celular, logo abaixo do menu) e some ao fechar, até a aba ser fechada.
const closed = ref(false)
onMounted(() => {
  try { closed.value = sessionStorage.getItem('hospedaki_demo_chip') === 'closed' }
  catch { /* navegação privada sem sessionStorage: o aviso só não lembra que foi fechado */ }
})
function close() {
  closed.value = true
  try { sessionStorage.setItem('hospedaki_demo_chip', 'closed') }
  catch { /* idem */ }
}
</script>

<template>
  <aside v-if="!closed" class="demo-chip" aria-label="Demonstração do Hospedaki">
    <p><strong>Demonstração</strong> <span class="hide-sm">do Hospedaki</span></p>
    <NuxtLink to="/painel/entrar">Painel do dono</NuxtLink>
    <NuxtLink to="/hospedaki">Para pousadas</NuxtLink>
    <button type="button" aria-label="Fechar aviso de demonstração" @click="close">×</button>
  </aside>
</template>

<style scoped lang="scss">
.demo-chip {
  --focus: var(--focus-dark);
  position: fixed; z-index: 45; left: 50%; transform: translateX(-50%);
  top: calc(var(--nav-h) + .5rem);
  display: flex; align-items: center; gap: .2rem;
  max-width: calc(100vw - 32px);
  padding: .2rem .2rem .2rem .9rem;
  background: rgb(14 20 18 / .92); color: var(--on-night);
  backdrop-filter: blur(10px); -webkit-backdrop-filter: blur(10px);
  border: 1px solid rgb(233 183 107 / .45); border-radius: 999px;
  font-size: .82rem; white-space: nowrap;
  box-shadow: 0 8px 24px rgb(0 0 0 / .25);

  @include above(md) {
    top: auto; left: auto; transform: none;
    right: var(--gutter-r); bottom: calc(1.2rem + env(safe-area-inset-bottom, 0px));
    font-size: .88rem;
  }
}
p { margin: 0 .3rem 0 0; }
strong { color: var(--amber); }
.hide-sm { @include below(sm) { display: none; } }
a, button {
  display: inline-flex; align-items: center; min-height: 40px; padding: 0 .65rem;
  color: var(--on-night); font-weight: 700; text-decoration: underline; text-underline-offset: 3px;
  text-decoration-color: rgb(255 255 255 / .35); border-radius: 999px;
}
button { background: none; border: 0; font: inherit; font-size: 1.2rem; line-height: 1; cursor: pointer; text-decoration: none; color: var(--on-night-muted); padding: 0 .75rem; }
</style>
