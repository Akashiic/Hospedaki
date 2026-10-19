<script setup lang="ts">
// Serviços opcionais da pousada (café da manhã, decoração...), combinados pelo WhatsApp.
defineProps<{ extras: { title: string, text: string }[], whatsapp: string | null }>()
</script>

<template>
  <section class="container extras">
    <p v-reveal class="eyebrow">Para deixar a estadia melhor</p>
    <h2 v-reveal style="--i: 1">Serviços opcionais</h2>
    <ul class="list">
      <li v-for="(e, i) in extras" :key="e.title" v-reveal :style="{ '--i': Math.min(i, 3) }">
        <h3>{{ e.title }}</h3>
        <p>{{ e.text }}</p>
      </li>
    </ul>
    <p v-reveal class="how">
      Combine na hora do pedido ou depois, pelo WhatsApp.
      <a v-if="whatsapp" :href="whatsapp" class="text-link" target="_blank" rel="noopener">Perguntar no WhatsApp <span aria-hidden="true">→</span></a>
    </p>
  </section>
</template>

<style scoped lang="scss">
.extras { padding-block: clamp(3rem, 7vw, 5rem) clamp(5rem, 10vw, 8rem); }
.eyebrow { @include eyebrow; }
h2 { font-size: clamp(2.2rem, 5vw, 3.4rem); margin: .8rem 0 2rem; }
.list { list-style: none; margin: 0; padding: 0; display: grid; grid-template-columns: repeat(auto-fill, minmax(min(100%, 250px), 1fr)); gap: 1.6rem 2rem; }
.list li { border-top: 1px solid var(--line); padding-top: 1.1rem; }
h3 { margin: 0 0 .3rem; font-size: 1.45rem; }
.list p { margin: 0; color: var(--muted); }
.how { margin: 2.4rem 0 0; color: var(--muted); display: flex; flex-wrap: wrap; align-items: center; gap: .3rem 1rem; }
</style>
