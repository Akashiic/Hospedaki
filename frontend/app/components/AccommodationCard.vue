<script setup lang="ts">
import type { Accommodation } from '~/composables/useApi'

defineProps<{ accommodation: Accommodation }>()
const selected = defineModel<boolean>('selected', { default: false })
</script>

<template>
  <article class="card" :class="{ active: selected }">
    <img
      v-if="accommodation.photos[0]"
      :src="accommodation.photos[0].url"
      :alt="accommodation.photos[0].caption ?? accommodation.name"
      loading="lazy"
    >
    <div v-else class="placeholder">{{ accommodation.name }}</div>
    <div class="body">
      <h3>{{ accommodation.name }}</h3>
      <p>{{ accommodation.description }}</p>
      <p class="meta">
        Até {{ accommodation.max_guests }} pessoas · a partir de
        <strong>{{ formatMoney(accommodation.base_price) }}</strong>/diária
      </p>
      <label class="pick">
        <input v-model="selected" type="checkbox"> Quero reservar
      </label>
    </div>
  </article>
</template>

<style scoped>
.card { border: 2px solid var(--line); border-radius: 12px; overflow: hidden; background: #fff; }
.card.active { border-color: var(--brand); }
img, .placeholder { width: 100%; aspect-ratio: 4 / 3; object-fit: cover; display: block; }
.placeholder { display: grid; place-items: center; background: var(--soft); color: var(--muted); }
.body { padding: 1rem; }
h3 { margin: 0 0 .25rem; }
.meta { color: var(--muted); font-size: .9rem; }
.pick input { width: 1.1rem; height: 1.1rem; accent-color: var(--brand); }
.pick { display: flex; gap: .5rem; align-items: center; font-weight: 600; cursor: pointer; }
</style>
