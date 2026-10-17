<script setup lang="ts">
// Menu fixo: transparente sobre a capa, ganha fundo ao rolar. No celular abre em tela cheia.
defineProps<{ name: string, logoUrl: string | null }>()
const scrollTo = useScrollTo()
const solid = ref(false)
const open = ref(false)

const links = [
  { id: 'o-lugar', label: 'O lugar' },
  { id: 'acomodacoes', label: 'Acomodações' },
  { id: 'galeria', label: 'Galeria' },
  { id: 'contato', label: 'Contato' },
]

function go(id: string) {
  open.value = false
  scrollTo(id)
}

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
      <a href="#" class="brand" @click.prevent="go('topo')">
        <img v-if="logoUrl" :src="logoUrl" :alt="name">
        <span v-else>{{ name }}</span>
      </a>
      <nav class="links" aria-label="Principal">
        <a v-for="link in links" :key="link.id" :href="`#${link.id}`" @click.prevent="go(link.id)">{{ link.label }}</a>
      </nav>
      <a href="#reservar" class="btn cta" @click.prevent="go('reservar')">Reservar</a>
      <button type="button" class="burger" :aria-expanded="open" aria-label="Abrir menu" @click="open = !open">
        <span /><span />
      </button>
    </div>
    <div class="sheet" :aria-hidden="!open">
      <a v-for="(link, i) in links" :key="link.id" :href="`#${link.id}`" :style="{ '--i': i }" :tabindex="open ? 0 : -1" @click.prevent="go(link.id)">{{ link.label }}</a>
      <a href="#reservar" class="btn btn-light" :style="{ '--i': links.length }" :tabindex="open ? 0 : -1" @click.prevent="go('reservar')">Reservar agora</a>
    </div>
  </header>
</template>

<style scoped>
.nav { position: fixed; inset: 0 0 auto; z-index: 50; color: #fff; padding-top: env(safe-area-inset-top, 0px); transition: background-color .4s var(--ease), color .4s, box-shadow .4s; }
.nav.solid { background: rgb(14 20 17 / .94); backdrop-filter: saturate(1.4) blur(14px); -webkit-backdrop-filter: saturate(1.4) blur(14px); box-shadow: 0 1px 0 rgb(255 255 255 / .06); }
.bar { max-width: 1200px; margin: 0 auto; padding-inline: var(--gutter) var(--gutter-r); height: 72px; display: flex; align-items: center; gap: 2rem; transition: height .4s var(--ease); }
.solid .bar { height: 60px; }
.brand { color: inherit; text-decoration: none; font-family: var(--font-display); font-size: 1.55rem; letter-spacing: -.01em; margin-right: auto; white-space: nowrap; }
.brand img { height: 40px; display: block; }
.links { display: flex; gap: 1.8rem; }
.links a { color: rgb(255 255 255 / .82); text-decoration: none; font-weight: 500; font-size: .95rem; position: relative; padding-block: .3rem; }
.links a::after { content: ""; position: absolute; left: 0; right: 0; bottom: 0; height: 1px; background: currentColor; transform: scaleX(0); transform-origin: right; transition: transform .35s var(--ease); }
.links a:hover { color: #fff; }
.links a:hover::after { transform: scaleX(1); transform-origin: left; }
.cta { padding: .6rem 1.2rem; font-size: .92rem; background: var(--amber); color: var(--night); }
.burger { display: none; width: 44px; height: 44px; border: 0; background: none; cursor: pointer; position: relative; color: inherit; }
.burger span { position: absolute; left: 11px; right: 11px; height: 1.5px; background: currentColor; transition: transform .35s var(--ease); }
.burger span:first-child { top: 18px; }
.burger span:last-child { top: 25px; }
.open .burger span:first-child { transform: translateY(3.5px) rotate(45deg); }
.open .burger span:last-child { transform: translateY(-3.5px) rotate(-45deg); }
.sheet { display: none; }

@media (max-width: 860px) {
  .links, .cta { display: none; }
  .burger { display: block; }
  .sheet { display: flex; position: fixed; inset: 0; top: calc(60px + env(safe-area-inset-top, 0px)); background: var(--night); flex-direction: column; justify-content: center; gap: 1.2rem; padding-inline: var(--gutter) var(--gutter-r); padding-bottom: calc(4rem + env(safe-area-inset-bottom, 0px)); opacity: 0; pointer-events: none; transition: opacity .4s var(--ease); }
  .open .sheet { opacity: 1; pointer-events: auto; }
  .sheet a:not(.btn) { color: #fff; text-decoration: none; font-family: var(--font-display); font-size: 2.6rem; line-height: 1.1; }
  .sheet > * { transform: translateY(18px); opacity: 0; transition: transform .6s var(--ease), opacity .6s var(--ease); transition-delay: calc(var(--i) * 60ms + 80ms); }
  .open .sheet > * { transform: none; opacity: 1; }
  .sheet .btn { align-self: flex-start; margin-top: 1rem; }
}
</style>
