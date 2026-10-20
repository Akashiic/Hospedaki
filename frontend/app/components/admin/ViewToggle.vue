<script setup lang="ts">
import type { ViewMode } from '~/composables/useViewMode'
import { LayoutGrid, List } from 'lucide-vue-next'

// Botões de lista e grade; a pílula escura desliza para o modo escolhido.
const mode = defineModel<ViewMode>({ required: true })
</script>

<template>
  <div class="toggle" role="group" aria-label="Modo de exibição" :class="mode">
    <span class="pill" aria-hidden="true" />
    <button type="button" :aria-pressed="mode === 'list'" aria-label="Ver em lista" title="Lista" @click="mode = 'list'"><List :size="18" /></button>
    <button type="button" :aria-pressed="mode === 'grid'" aria-label="Ver em grade" title="Grade" @click="mode = 'grid'"><LayoutGrid :size="18" /></button>
  </div>
</template>

<style scoped lang="scss">
.toggle { position: relative; display: inline-grid; grid-template-columns: 1fr 1fr; padding: 4px; border-radius: 14px; background: var(--a-card); box-shadow: var(--a-shadow); flex: none; }
.pill { position: absolute; top: 4px; bottom: 4px; left: 4px; width: calc(50% - 4px); border-radius: 10px; background: var(--night); transition: transform $dur-md $ease-enter; }
.grid .pill { transform: translateX(100%); }
button { position: relative; display: grid; place-items: center; width: 40px; height: 38px; border: 0; background: none; color: var(--muted); cursor: pointer; }
button[aria-pressed="true"] { color: var(--on-night); }
@include reduced-motion { .pill { transition: none; } }
</style>
