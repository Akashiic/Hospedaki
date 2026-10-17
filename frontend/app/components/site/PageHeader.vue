<script setup lang="ts">
// Abertura das páginas internas: imagem escura com leve zoom e título grande.
defineProps<{ eyebrow: string, title: string, lead?: string | null, image?: string | null, compact?: boolean }>()
</script>

<template>
  <header class="page-header" :class="{ compact }">
    <img v-if="image" :src="image" alt="" class="bg" fetchpriority="high" decoding="async">
    <div class="veil" />
    <div class="container inner">
      <p class="eyebrow">{{ eyebrow }}</p>
      <h1>{{ title }}</h1>
      <p v-if="lead" class="lead">{{ lead }}</p>
      <slot />
    </div>
  </header>
</template>

<style scoped lang="scss">
// Coreografia da abertura (tempos e curvas em assets/scss/_motion.scss):
//   imagem assenta desde o primeiro quadro (zoom 1.056 → 1 em t(6)) e, sobre ela,
//   eyebrow (0) → título (1) → texto (2) → conteúdo extra (3), um passo de 75ms entre cada.
//   Tudo visível em ~0,8s. Puro CSS: toca já no HTML do servidor, sem esperar o JavaScript.
.page-header {
  position: relative; overflow: hidden; isolation: isolate;
  min-height: clamp(420px, 62svh, 640px);
  display: flex; align-items: flex-end;
  background: var(--night); color: #fff;

  &.compact { min-height: clamp(320px, 42svh, 440px); }
}
// Só transform, sem opacidade: a imagem costuma ser o LCP e precisa pintar no primeiro quadro.
.bg {
  position: absolute; inset: 0; width: 100%; height: 100%; object-fit: cover; object-position: 62% 50%;
  animation: settle $dur-3xl $ease-enter both;
}
.veil { position: absolute; inset: 0; background: linear-gradient(180deg, rgb(8 12 10 / .6) 0%, rgb(8 12 10 / .25) 45%, rgb(8 12 10 / .82) 100%); }
.inner { position: relative; z-index: 1; width: 100%; padding-top: 7rem; padding-bottom: clamp(2.5rem, 6vw, 4rem); }
.eyebrow { @include eyebrow(var(--amber)); @include motion-entrance(0); }
// Título: só sobe (sem opacidade), para não atrasar o LCP quando não há imagem.
h1 { @include display(clamp(3rem, 8vw, 6rem)); margin: .5rem 0 0; max-width: 14ch; @include motion-entrance(1, rise); }
.lead { max-width: 38rem; margin: 1.1rem 0 0; color: rgb(255 255 255 / .85); font-size: clamp(1rem, 1.5vw, 1.12rem); @include motion-entrance(2); }
.inner > :deep(:not(.eyebrow, h1, .lead)) { @include motion-entrance(3); }
@keyframes settle { from { transform: scale($zoom-md); } to { transform: scale(1); } }

@include reduced-motion {
  .bg, .eyebrow, h1, .lead, .inner > :deep(:not(.eyebrow, h1, .lead)) { animation: none; }
}
</style>
