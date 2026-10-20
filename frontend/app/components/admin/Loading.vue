<script setup lang="ts">
import { LoaderCircle } from 'lucide-vue-next'

// Carregando bem à vista: ícone girando, o que está sendo buscado e cartões de esqueleto.
// Se passar de 4 s (a API do plano gratuito do Render "dorme" e leva uns segundos para
// acordar), explica a demora para ninguém achar que travou.
withDefaults(defineProps<{ text?: string, cards?: number, height?: string }>(), { text: 'Carregando...', cards: 3, height: '150px' })

const slow = ref(false)
let timer: ReturnType<typeof setTimeout> | undefined
onMounted(() => { timer = setTimeout(() => { slow.value = true }, 4000) })
onBeforeUnmount(() => clearTimeout(timer))
</script>

<template>
  <div class="loading" role="status" aria-live="polite">
    <p class="label">
      <LoaderCircle :size="20" class="spin" aria-hidden="true" />
      <span>{{ text }}</span>
    </p>
    <Transition name="a-tab">
      <p v-if="slow" class="slow">O servidor está acordando, isso leva alguns segundos na primeira visita.</p>
    </Transition>
    <div class="cards" aria-hidden="true">
      <div v-for="n in cards" :key="n" class="card a-card" :style="{ height, '--i': n }">
        <span class="a-skeleton dot" />
        <span class="lines"><span class="a-skeleton l1" /><span class="a-skeleton l2" /></span>
        <span class="a-skeleton block" />
      </div>
    </div>
  </div>
</template>

<style scoped lang="scss">
.loading { display: grid; gap: .8rem; }
.label { display: flex; align-items: center; gap: .6rem; margin: 0; font-weight: 700; color: var(--brand-text); }
.spin { animation: spin 900ms linear infinite; }
@keyframes spin { to { transform: rotate(1turn); } }
.slow { margin: 0; padding: .6rem .9rem; border-radius: 12px; background: var(--warn-soft); color: var(--warn-ink); font-size: .9rem; }
.cards { display: grid; gap: 1rem; }
.card { display: grid; grid-template-columns: auto minmax(0, 1fr); grid-template-rows: auto 1fr; gap: .9rem; padding: 1.1rem 1.2rem; overflow: hidden; animation: pulse 1.6s $ease-move infinite; animation-delay: calc(var(--i) * 160ms); }
.dot { width: 44px; height: 44px; border-radius: 50%; }
.lines { display: grid; gap: .45rem; align-content: center; }
.l1 { height: 14px; width: 40%; }
.l2 { height: 10px; width: 25%; }
.block { grid-column: 1 / -1; border-radius: 14px; }
@keyframes pulse { 50% { opacity: .6; } }
@include reduced-motion { .spin { animation-duration: 2.4s; } .card { animation: none; } }
</style>
