<script setup lang="ts">
import type { Property } from '~/composables/useApi'
import { accommodationFor } from '~/composables/usePersona'

// Barra fixa no rodapé do celular: aparece depois da capa e some perto do fim da página,
// para não cobrir o rodapé, que já tem os mesmos botões.
const props = defineProps<{ property: Property, whatsapp: string | null }>()
const persona = usePersona()
const shown = ref(false)

const bookLink = computed(() => {
  const acc = accommodationFor(props.property, persona.value)
  return acc ? { path: '/reservar', query: { acomodacao: acc.slug } } : '/reservar'
})

let ticking = false
function onScroll() {
  if (ticking) return
  ticking = true
  requestAnimationFrame(() => {
    ticking = false
    const y = window.scrollY
    const nearEnd = y + window.innerHeight > document.documentElement.scrollHeight - 520
    shown.value = y > window.innerHeight * 0.7 && !nearEnd
  })
}
onMounted(() => {
  onScroll()
  window.addEventListener('scroll', onScroll, { passive: true })
})
onBeforeUnmount(() => window.removeEventListener('scroll', onScroll))
</script>

<template>
  <div class="mobile-bar" :class="{ shown }" :inert="!shown">
    <NuxtLink :to="bookLink" class="btn btn-amber book">Ver datas e reservar</NuxtLink>
    <a v-if="whatsapp" :href="whatsapp" class="wa" target="_blank" rel="noopener" aria-label="Falar no WhatsApp">
      <svg viewBox="0 0 24 24" width="22" height="22" aria-hidden="true"><path fill="currentColor" d="M12 2a10 10 0 0 0-8.6 15.1L2 22l5-1.3A10 10 0 1 0 12 2Zm0 18.2a8.2 8.2 0 0 1-4.2-1.2l-.3-.2-3 .8.8-2.9-.2-.3A8.2 8.2 0 1 1 12 20.2Zm4.5-6.1c-.2-.1-1.5-.7-1.7-.8s-.4-.1-.6.1-.7.8-.8 1-.3.2-.5.1a6.7 6.7 0 0 1-3.3-2.9c-.3-.4.3-.4.7-1.4.1-.2 0-.3 0-.4l-.8-1.8c-.2-.5-.4-.4-.6-.4h-.5a1 1 0 0 0-.7.3 3 3 0 0 0-.9 2.2 5.2 5.2 0 0 0 1.1 2.8 11.9 11.9 0 0 0 4.6 4c1.7.7 2.4.8 3.2.7a2.8 2.8 0 0 0 1.8-1.3 2.3 2.3 0 0 0 .2-1.3c-.1-.1-.2-.2-.4-.3Z" /></svg>
    </a>
  </div>
</template>

<style scoped lang="scss">
// Entrada: a barra sobe em t(3) desacelerando; os botões aparecem por cima dela, um passo
// de 75ms depois cada (reservar → WhatsApp). Saída: tudo junto, um degrau abaixo, t(2), acelerando.
.mobile-bar {
  position: fixed; z-index: 40; left: 0; right: 0; bottom: 0;
  display: flex; gap: .6rem; align-items: center;
  padding: .7rem var(--gutter-r) calc(.7rem + env(safe-area-inset-bottom, 0px)) var(--gutter);
  background: rgb(14 20 18 / .92); backdrop-filter: blur(10px); -webkit-backdrop-filter: blur(10px);
  border-top: 1px solid rgb(255 255 255 / .08);
  transform: translateY(110%);
  transition: transform $dur-md $ease-exit;
  &.shown { transform: none; transition: transform $dur-lg $ease-enter; }
  @include above(md) { display: none; }
}
.mobile-bar > * {
  opacity: 0; transform: translateY($dist-sm);
  transition: opacity $dur-sm $ease-exit, transform $dur-sm $ease-exit;
}
.mobile-bar.shown > * {
  opacity: 1; transform: none;
  transition: opacity $dur-lg $ease-enter, transform $dur-xl $ease-enter;
  transition-delay: $stagger;
}
.mobile-bar.shown > * + * { transition-delay: 2 * $stagger; }
.book { flex: 1; }
.wa { flex: none; display: grid; place-items: center; width: 48px; height: 48px; border-radius: 50%; color: #fff; background: #1f9d55; }
@include reduced-motion {
  .mobile-bar, .mobile-bar.shown { transition-duration: $dur-sm; }
  .mobile-bar > *, .mobile-bar.shown > * { transform: none; }
}
</style>
