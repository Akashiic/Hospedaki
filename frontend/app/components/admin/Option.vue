<script setup lang="ts">
import { Check } from 'lucide-vue-next'

// Opção que o dono liga com uma caixa de marcar; ligada, mostra o campo dela (slot).
const on = defineModel<boolean>({ required: true })
defineProps<{ id: string, label: string, hint?: string }>()
</script>

<template>
  <div class="option" :class="{ on }">
    <label class="head" :for="id">
      <input :id="id" v-model="on" type="checkbox" class="native">
      <span class="box" aria-hidden="true"><Check :size="14" :stroke-width="3" /></span>
      <span class="text">
        <strong>{{ label }}</strong>
        <small v-if="hint">{{ hint }}</small>
      </span>
    </label>
    <Transition name="reveal">
      <div v-if="on" class="field"><slot /></div>
    </Transition>
  </div>
</template>

<style scoped lang="scss">
.option { position: relative; border-radius: 14px; background: var(--a-card); isolation: isolate; }
// Fundo da opção ligada entra por opacidade.
.option::before { content: ""; position: absolute; inset: 0; z-index: -1; border-radius: inherit; background: var(--a-sunk); opacity: 0; transition: opacity $dur-sm $ease-move; }
.option.on::before { opacity: 1; }
.head { display: flex; align-items: center; gap: .8rem; min-height: 56px; padding: .6rem .8rem; cursor: pointer; }
.native { position: absolute; opacity: 0; width: 1px; height: 1px; }
.box {
  position: relative; display: grid; place-items: center; flex: none; width: 24px; height: 24px; border-radius: 8px;
  border: 2px solid color-mix(in srgb, var(--line) 60%, var(--muted)); color: var(--brand-ink); isolation: isolate;
  &::before { content: ""; position: absolute; inset: -2px; z-index: -1; border-radius: inherit; background: var(--brand); opacity: 0; transform: scale(.6); transition: opacity $dur-sm $ease-move, transform $dur-md $ease-enter; }
  svg { opacity: 0; transform: scale(.4); transition: opacity $dur-sm $ease-move, transform $dur-md $ease-enter; }
}
.on .box { border-color: var(--brand); }
.on .box::before, .on .box svg { opacity: 1; transform: none; }
.native:focus-visible + .box { outline: 3px solid var(--focus); outline-offset: 2px; }
.text { display: grid; gap: .1rem; }
.text strong { font-size: .95rem; }
.text small { color: var(--muted); font-size: .82rem; line-height: 1.35; }
.field { padding: 0 .8rem .8rem calc(.8rem + 24px + .8rem); }
@include below(sm) { .field { padding-left: .8rem; } }
.reveal-enter-active { transition: opacity $dur-lg $ease-enter, transform $dur-lg $ease-enter; }
.reveal-leave-active { transition: opacity $dur-xs $ease-exit; }
.reveal-enter-from { opacity: 0; transform: translateY(-#{$dist-xs}); }
.reveal-leave-to { opacity: 0; }
@include reduced-motion { .reveal-enter-from { transform: none; } }
</style>
