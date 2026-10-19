<script setup lang="ts">
import { CircleAlert, CircleCheck, Info, X } from 'lucide-vue-next'

const { toasts, dismiss } = useToast()
const icons = { success: CircleCheck, error: CircleAlert, info: Info }
</script>

<template>
  <div class="toasts" aria-live="polite">
    <TransitionGroup name="toast">
      <div v-for="t in toasts" :key="t.id" class="toast" :class="t.tone" :role="t.tone === 'error' ? 'alert' : 'status'">
        <component :is="icons[t.tone]" :size="20" aria-hidden="true" />
        <p>{{ t.text }}</p>
        <button type="button" aria-label="Fechar aviso" @click="dismiss(t.id)"><X :size="16" /></button>
      </div>
    </TransitionGroup>
  </div>
</template>

<style scoped lang="scss">
.toasts {
  position: fixed; z-index: 80; right: max(16px, env(safe-area-inset-right, 0px)); bottom: calc(1rem + env(safe-area-inset-bottom, 0px));
  display: grid; gap: .5rem; justify-items: end; pointer-events: none; width: min(380px, calc(100vw - 32px));
  // No celular fica acima da barra de navegação de baixo.
  @include below(lg) { bottom: calc(5.4rem + env(safe-area-inset-bottom, 0px)); }
}
.toast {
  pointer-events: auto; display: flex; align-items: center; gap: .7rem; width: 100%;
  padding: .75rem .6rem .75rem .9rem; border-radius: 14px;
  background: var(--night); color: var(--on-night); box-shadow: 0 16px 40px -16px rgb(0 0 0 / .45);
}
.toast p { margin: 0; flex: 1; font-size: .92rem; font-weight: 500; line-height: 1.35; }
.success svg:first-child { color: #7fd6a4; }
.error svg:first-child { color: #ff9b8f; }
.info svg:first-child { color: var(--amber); }
button { display: grid; place-items: center; width: 32px; height: 32px; border: 0; border-radius: 8px; background: none; color: var(--on-night-muted); cursor: pointer; }
.toast-enter-active { transition: opacity $dur-lg $ease-enter, transform $dur-xl $ease-enter; }
.toast-leave-active { transition: opacity $dur-sm $ease-exit, transform $dur-md $ease-exit; }
.toast-enter-from { opacity: 0; transform: translateY($dist-md) scale(.98); }
.toast-leave-to { opacity: 0; transform: translateX($dist-lg); }
.toast-move { transition: transform $dur-lg $ease-move; }
@include reduced-motion {
  .toast-enter-from, .toast-leave-to { transform: none; }
}
</style>
