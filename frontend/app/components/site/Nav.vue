<script setup lang="ts">
// Menu fixo: transparente sobre a capa, ganha fundo ao rolar. No celular abre em tela cheia.
defineProps<{ name: string, logoUrl: string | null }>()
const solid = ref(false)
const open = ref(false)
const route = useRoute()

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
})
onBeforeUnmount(() => window.removeEventListener('scroll', onScroll))
watch(open, (value) => { document.documentElement.style.overflow = value ? 'hidden' : '' })
</script>

<template>
  <header class="nav" :class="{ solid: solid || open, open }">
    <div class="bar">
      <NuxtLink to="/" class="brand">
        <img v-if="logoUrl" :src="logoUrl" :alt="name">
        <span v-else>{{ name }}</span>
      </NuxtLink>
      <nav class="links" aria-label="Principal">
        <NuxtLink v-for="link in links" :key="link.to" :to="link.to">{{ link.label }}</NuxtLink>
      </nav>
      <NuxtLink to="/reservar" class="btn cta">Reservar</NuxtLink>
      <button type="button" class="burger" :aria-expanded="open" aria-label="Abrir menu" @click="open = !open">
        <span /><span />
      </button>
    </div>
    <div class="sheet" :aria-hidden="!open">
      <NuxtLink v-for="(link, i) in links" :key="link.to" :to="link.to" :style="{ '--i': i }" :tabindex="open ? 0 : -1">{{ link.label }}</NuxtLink>
      <NuxtLink to="/reservar" class="btn btn-light" :style="{ '--i': links.length }" :tabindex="open ? 0 : -1">Reservar agora</NuxtLink>
    </div>
  </header>
</template>

<style scoped lang="scss">
.nav { position: fixed; inset: 0 0 auto; z-index: 50; color: #fff; padding-top: env(safe-area-inset-top, 0px); transition: background-color $dur-lg $ease-move, color $dur-lg $ease-move, box-shadow $dur-lg $ease-move; }
.nav.solid { background: rgb(14 20 17 / .94); backdrop-filter: saturate(1.4) blur(14px); -webkit-backdrop-filter: saturate(1.4) blur(14px); box-shadow: 0 1px 0 rgb(255 255 255 / .06); }
.bar { max-width: 1200px; margin: 0 auto; padding-inline: var(--gutter) var(--gutter-r); height: 72px; display: flex; align-items: center; gap: 2rem; }
.solid .bar { height: 60px; }
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
  .sheet { display: flex; position: fixed; inset: 0; top: calc(60px + env(safe-area-inset-top, 0px)); background: var(--night); flex-direction: column; justify-content: center; gap: 1.2rem; padding-inline: var(--gutter) var(--gutter-r); padding-bottom: calc(4rem + env(safe-area-inset-bottom, 0px)); opacity: 0; pointer-events: none; transition: opacity $dur-sm $ease-exit; }
  .open .sheet { opacity: 1; pointer-events: auto; transition: opacity $dur-md $ease-enter; }
  .sheet a:not(.btn) { color: #fff; text-decoration: none; font-family: var(--font-display); font-size: 2.6rem; line-height: 1.1; }
  // Fechar: tudo sai junto, rápido. Abrir: itens entram um a um, passo de 75ms.
  .sheet > * { transform: translateY($dist-md); opacity: 0; transition: opacity $dur-sm $ease-exit, transform $dur-sm $ease-exit; }
  .open .sheet > * { transform: none; opacity: 1; @include motion-enter; transition-delay: calc((var(--i) + 1) * #{$stagger}); }
  .sheet .btn { align-self: flex-start; margin-top: 1rem; }
}
</style>
