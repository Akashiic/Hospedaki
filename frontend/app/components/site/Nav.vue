<script setup lang="ts">
// Menu fixo: transparente sobre a capa, ganha fundo ao rolar. No celular abre em tela cheia.
defineProps<{ name: string, logoUrl: string | null }>()
const solid = ref(false)
const open = ref(false)
const route = useRoute()
const root = ref<HTMLElement>()
const burger = ref<HTMLButtonElement>()
const sheet = ref<HTMLElement>()

const links = [
  { to: '/', label: 'Início' },
  { to: '/o-lugar', label: 'O lugar' },
  { to: '/acomodacoes', label: 'Acomodações' },
  { to: '/contato', label: 'Contato' },
]

// Fecha o menu do celular ao trocar de página.
watch(() => route.fullPath, () => { open.value = false })

let ticking = false
function onScroll() {
  if (ticking) return
  ticking = true
  requestAnimationFrame(() => {
    solid.value = window.scrollY > 40
    ticking = false
  })
}
onMounted(() => {
  onScroll()
  window.addEventListener('scroll', onScroll, { passive: true })
  window.addEventListener('keydown', onKeydown)
})
onBeforeUnmount(() => {
  window.removeEventListener('scroll', onScroll)
  window.removeEventListener('keydown', onKeydown)
  setOpen(false)
})

// Menu aberto: a página por trás não rola nem recebe foco (inert), o foco vai para o primeiro
// link e Esc fecha devolvendo o foco ao botão.
function setOpen(value: boolean) {
  if (!import.meta.client) return
  document.documentElement.style.overflow = value ? 'hidden' : ''
  for (const el of root.value?.parentElement?.children ?? []) {
    if (el !== root.value) (el as HTMLElement).inert = value
  }
}
watch(open, async (value, was) => {
  setOpen(value)
  if (value) {
    await nextTick()
    sheet.value?.querySelector<HTMLElement>('a')?.focus()
  }
  else if (was && root.value?.contains(document.activeElement)) {
    burger.value?.focus()
  }
})
function onKeydown(e: KeyboardEvent) {
  if (e.key === 'Escape' && open.value) {
    open.value = false
    burger.value?.focus()
  }
}
</script>

<template>
  <header ref="root" class="nav" :class="{ solid: solid || open, open }">
    <div class="bar">
      <NuxtLink to="/" class="brand">
        <img v-if="logoUrl" :src="logoUrl" :alt="name">
        <span v-else>{{ name }}</span>
      </NuxtLink>
      <nav class="links" aria-label="Principal">
        <NuxtLink v-for="link in links" :key="link.to" :to="link.to">{{ link.label }}</NuxtLink>
      </nav>
      <NuxtLink to="/reservar" class="btn cta">Reservar</NuxtLink>
      <button ref="burger" type="button" class="burger" :aria-expanded="open" aria-controls="menu-celular" :aria-label="open ? 'Fechar menu' : 'Abrir menu'" @click="open = !open">
        <span /><span />
      </button>
    </div>
    <div id="menu-celular" ref="sheet" class="sheet" :aria-hidden="!open">
      <NuxtLink v-for="(link, i) in links" :key="link.to" :to="link.to" :style="{ '--i': i }" :tabindex="open ? 0 : -1">{{ link.label }}</NuxtLink>
      <NuxtLink to="/reservar" class="btn btn-light" :style="{ '--i': links.length }" :tabindex="open ? 0 : -1">Reservar agora</NuxtLink>
    </div>
  </header>
</template>

<style scoped lang="scss">
// O fundo sólido é uma camada própria que só muda de opacidade; a barra tem altura fixa (60px,
// a mesma de --nav-h) e, sobre a capa, desce 6px por transform, para respirar sem mexer no layout.
.nav { --focus: var(--focus-dark); position: fixed; inset: 0 0 auto; z-index: 50; color: #fff; padding-top: env(safe-area-inset-top, 0px); }
.nav::before {
  content: ""; position: absolute; inset: 0; z-index: -1; pointer-events: none;
  background: rgb(14 20 17 / .94); box-shadow: 0 1px 0 rgb(255 255 255 / .06);
  opacity: 0; transition: opacity $dur-lg $ease-move;
}
.nav.solid::before { opacity: 1; backdrop-filter: saturate(1.4) blur(14px); -webkit-backdrop-filter: saturate(1.4) blur(14px); }
.bar { max-width: 1200px; margin: 0 auto; padding-inline: var(--gutter) var(--gutter-r); height: 60px; display: flex; align-items: center; gap: 2rem; transform: translateY(6px); transition: transform $dur-lg $ease-move; }
.solid .bar { transform: none; }
.brand { color: inherit; text-decoration: none; font-family: var(--font-display); font-size: 1.55rem; letter-spacing: -.01em; margin-right: auto; white-space: nowrap; }
.brand img { height: 40px; display: block; }
.links { display: flex; gap: 1.8rem; }
.links a { color: rgb(255 255 255 / .82); text-decoration: none; font-weight: 500; font-size: .95rem; position: relative; padding-block: .3rem; }
.links a::after { content: ""; position: absolute; left: 0; right: 0; bottom: 0; height: 1px; background: currentColor; transform: scaleX(0); transform-origin: right; transition: transform $dur-md $ease-move; }
.links a:hover, .links a.router-link-exact-active { color: #fff; }
.links a:hover::after, .links a.router-link-exact-active::after { transform: scaleX(1); transform-origin: left; }
.cta { padding: .6rem 1.2rem; font-size: .92rem; background: var(--amber); color: var(--night); }
.burger { display: none; width: 44px; height: 44px; border: 0; background: none; cursor: pointer; position: relative; color: inherit; }
.burger span { position: absolute; left: 11px; right: 11px; height: 1.5px; background: currentColor; transition: transform $dur-md $ease-move; }
.burger span:first-child { top: 18px; }
.burger span:last-child { top: 25px; }
.open .burger span:first-child { transform: translateY(3.5px) rotate(45deg); }
.open .burger span:last-child { transform: translateY(-3.5px) rotate(-45deg); }
.sheet { display: none; }

@include below(lg) {
  .links, .cta { display: none; }
  .burger { display: block; }
  // "safe center": se não couber (celular deitado), começa do topo e rola dentro do menu.
  .sheet { display: flex; position: fixed; inset: 0; top: var(--nav-h); background: var(--night); flex-direction: column; justify-content: safe center; gap: 1.2rem; padding-inline: var(--gutter) var(--gutter-r); padding-block: 1.5rem calc(4rem + env(safe-area-inset-bottom, 0px)); overflow-y: auto; overscroll-behavior: contain; opacity: 0; pointer-events: none; transition: opacity $dur-sm $ease-exit; }
  .open .sheet { opacity: 1; pointer-events: auto; transition: opacity $dur-md $ease-enter; }
  .sheet a:not(.btn) { color: #fff; text-decoration: none; font-family: var(--font-display); font-size: 2.6rem; line-height: 1.1; }
  // Fechar: tudo sai junto, rápido. Abrir: itens entram um a um, passo de 75ms.
  .sheet > * { transform: translateY($dist-md); opacity: 0; transition: opacity $dur-sm $ease-exit, transform $dur-sm $ease-exit; }
  .open .sheet > * { transform: none; opacity: 1; @include motion-enter; transition-delay: calc((var(--i) + 1) * #{$stagger}); }
  .sheet .btn { align-self: flex-start; margin-top: 1rem; }
}
// Celular deitado: letras menores, mas cada link continua com 44px de altura para o toque.
@media (max-height: 480px) {
  .sheet { gap: 0; padding-block: 1rem 1.5rem; }
  .sheet a:not(.btn) { font-size: 1.8rem; min-height: 44px; display: flex; align-items: center; }
  .sheet .btn { margin-top: .4rem; }
}
</style>
