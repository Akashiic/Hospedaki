<script setup lang="ts">
import type { Component } from 'vue'
import type { AdminTab } from '~/composables/useAdminApi'
import { ChevronDown, Globe, Palette, PanelsTopLeft, Phone, Plug, Search, SlidersHorizontal } from 'lucide-vue-next'

// "Seu site" no menu do painel: um dropdown com Páginas e Configurações, que é outro dropdown
// (Identidade, Contato, SEO e Integrações). Abre sozinho quando a aba atual está dentro dele.
// O mesmo menu serve a barra lateral (computador) e a folha "Mais" (celular).
const props = defineProps<{ tab: AdminTab, variant: 'side' | 'sheet' }>()
const emit = defineEmits<{ go: [tab: AdminTab] }>()

const settings: { tab: AdminTab, label: string, icon: Component }[] = [
  { tab: 'identidade', label: 'Identidade', icon: Palette },
  { tab: 'contato', label: 'Contato', icon: Phone },
  { tab: 'seo', label: 'SEO', icon: Search },
  { tab: 'integracoes', label: 'Integrações', icon: Plug },
]

const inSettings = computed(() => settings.some(i => i.tab === props.tab))
const inSite = computed(() => props.tab === 'paginas' || inSettings.value)
const siteOpen = ref(inSite.value)
const settingsOpen = ref(inSettings.value)
watch(inSite, (v) => { if (v) siteOpen.value = true })
watch(inSettings, (v) => { if (v) settingsOpen.value = true })

const id = useId()
</script>

<template>
  <div class="tree" :class="`in-${variant}`">
    <!-- Fechado com a aba atual lá dentro, o botão fica marcado para o dono saber onde está. -->
    <button type="button" class="item toggle" :class="{ here: inSite && !siteOpen }" :aria-expanded="siteOpen" :aria-controls="`${id}-site`" @click="siteOpen = !siteOpen">
      <Globe :size="20" aria-hidden="true" />
      <span>Seu site</span>
      <ChevronDown :size="18" class="chev" aria-hidden="true" />
    </button>
    <Transition name="drop">
      <div v-show="siteOpen" :id="`${id}-site`" class="branch">
        <button type="button" class="item" :aria-current="tab === 'paginas' ? 'page' : undefined" @click="emit('go', 'paginas')">
          <PanelsTopLeft :size="20" aria-hidden="true" />
          <span>Páginas</span>
        </button>
        <button type="button" class="item toggle" :class="{ here: inSettings && !settingsOpen }" :aria-expanded="settingsOpen" :aria-controls="`${id}-settings`" @click="settingsOpen = !settingsOpen">
          <SlidersHorizontal :size="20" aria-hidden="true" />
          <span>Configurações</span>
          <ChevronDown :size="18" class="chev" aria-hidden="true" />
        </button>
        <Transition name="drop">
          <div v-show="settingsOpen" :id="`${id}-settings`" class="branch">
            <button v-for="item in settings" :key="item.tab" type="button" class="item" :aria-current="tab === item.tab ? 'page' : undefined" @click="emit('go', item.tab)">
              <component :is="item.icon" :size="18" aria-hidden="true" />
              <span>{{ item.label }}</span>
            </button>
          </div>
        </Transition>
      </div>
    </Transition>
  </div>
</template>

<style scoped lang="scss">
.tree { display: grid; gap: .2rem; }
// Ramos com uma linha-guia à esquerda, como uma árvore de pastas.
.branch { position: relative; display: grid; gap: .2rem; padding-left: 1rem; margin-left: 1.15rem; }
.branch::before { content: ""; position: absolute; left: 0; top: .3rem; bottom: .3rem; width: 1px; background: var(--line); }

.item {
  position: relative; display: flex; align-items: center; gap: .75rem; width: 100%; min-height: 44px; padding: 0 .8rem;
  border: 0; border-radius: 12px; background: none; font: inherit; font-weight: 600; color: var(--muted); cursor: pointer; text-align: left;
  isolation: isolate;
  // Fundo da aba ativa entra por opacidade (o fundo em si não anima).
  &::before { content: ""; position: absolute; inset: 0; z-index: -1; border-radius: inherit; background: var(--a-sunk); opacity: 0; transition: opacity $dur-sm $ease-move; }
  &:hover::before { opacity: .7; }
  &[aria-current="page"], &.here { color: var(--text); }
  &[aria-current="page"]::before, &.here::before { opacity: 1; }
  &[aria-current="page"] svg, &.here svg { color: var(--brand-text); }
}
.branch .branch .item { min-height: 40px; font-size: .92rem; }
.chev { margin-left: auto; transition: transform $dur-md $ease-enter; }
.toggle[aria-expanded="true"] .chev { transform: rotate(180deg); }

// Folha "Mais" do celular: linhas mais altas, ícones na cor da marca.
// (classe "in-sheet", não "sheet": a raiz deste componente também recebe os estilos do Shell.)
.in-sheet .item { min-height: 52px; padding: 0 .4rem; color: var(--text); }
.in-sheet .item svg { color: var(--brand-text); }
.in-sheet .branch .branch .item { min-height: 48px; }

// O ramo abre descendo um pouco e aparecendo (só opacity e transform).
.drop-enter-active { transition: opacity $dur-md $ease-enter, transform $dur-lg $ease-enter; }
.drop-leave-active { transition: opacity $dur-xs $ease-exit, transform $dur-sm $ease-exit; }
.drop-enter-from, .drop-leave-to { opacity: 0; transform: translateY(-#{$dist-xs}); }
@include reduced-motion {
  .drop-enter-from, .drop-leave-to { transform: none; }
  .chev { transition: none; }
}
</style>
