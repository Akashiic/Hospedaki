<script setup lang="ts">
// Um algarismo de placa que vira (split-flap), como os relógios de calendário.
// A metade de cima com o número velho cai e revela o novo; a de baixo com o novo desce por
// cima do velho. Só transform (rotateX) e opacidade animam. Sem movimento (reduced motion),
// a troca é instantânea: a animação dura 1ms e o fim dela assenta o número novo.
const props = defineProps<{ value: string }>()
const shown = ref(props.value)
const flipping = computed(() => shown.value !== props.value)
</script>

<template>
  <span class="digit" aria-hidden="true">
    <span class="half top"><span>{{ value }}</span></span>
    <span class="half bottom"><span>{{ shown }}</span></span>
    <template v-if="flipping">
      <span :key="`t${value}`" class="flap top"><span>{{ shown }}</span></span>
      <span :key="`b${value}`" class="flap bottom" @animationend="shown = value"><span>{{ value }}</span></span>
    </template>
  </span>
</template>

<style scoped lang="scss">
.digit {
  --h: 1.55em;
  position: relative; display: inline-block; width: 1.08em; height: var(--h);
  perspective: 300px; font-variant-numeric: tabular-nums; font-weight: 800; line-height: var(--h); text-align: center;
}
.half, .flap { position: absolute; left: 0; right: 0; height: 50%; overflow: hidden; background: var(--flip-bg, #1b2722); color: var(--flip-ink, #fff); }
.half > span, .flap > span { position: absolute; left: 0; right: 0; height: var(--h); }
.top { top: 0; border-radius: .2em .2em 0 0; }
.bottom { bottom: 0; border-radius: 0 0 .2em .2em; }
.bottom > span { top: calc(var(--h) / -2); }
// A dobra: um fio escuro entre as metades.
.bottom { box-shadow: inset 0 1px 0 rgb(0 0 0 / .45); }
.top { background: var(--flip-bg-top, #223029); }

.flap { backface-visibility: hidden; will-change: transform; }
.flap.top { transform-origin: 50% 100%; animation: fold $dur-md $ease-exit both; }
.flap.bottom { transform-origin: 50% 0; animation: land $dur-md $ease-enter $dur-md both; }
@keyframes fold { from { transform: rotateX(0); } to { transform: rotateX(-90deg); } }
@keyframes land { from { transform: rotateX(90deg); } to { transform: rotateX(0); } }

@include reduced-motion {
  .flap.top, .flap.bottom { animation-duration: 1ms; animation-delay: 0s; }
}
</style>
