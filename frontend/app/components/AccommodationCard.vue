<script setup lang="ts">
import type { Accommodation } from '~/composables/useApi'

const props = defineProps<{ accommodation: Accommodation }>()
const selected = defineModel<boolean>('selected', { default: false })
const current = ref(0)
const photo = computed(() => props.accommodation.photos[current.value])
</script>

<template>
  <article class="card" :class="{ active: selected }">
    <div class="media">
      <img v-if="photo" :src="photo.url" :alt="photo.caption ?? accommodation.name" loading="lazy">
      <div v-else class="placeholder">{{ accommodation.name }}</div>
      <div v-if="accommodation.photos.length > 1" class="dots">
        <button
          v-for="(p, i) in accommodation.photos"
          :key="i"
          type="button"
          :class="{ on: i === current }"
          :aria-label="`Foto ${i + 1}`"
          @click="current = i"
        />
      </div>
    </div>
    <div class="body">
      <div class="head">
        <h3>{{ accommodation.name }}</h3>
        <span class="guests">até {{ accommodation.max_guests }} pessoas</span>
      </div>
      <p class="desc">{{ accommodation.description }}</p>
      <div class="foot">
        <p class="price">
          <small>a partir de</small>
          <strong>{{ formatMoney(accommodation.base_price) }}</strong>
          <small>/ diária</small>
        </p>
        <label class="pick" :class="{ on: selected }">
          <input v-model="selected" type="checkbox">
          {{ selected ? 'Selecionado' : 'Selecionar' }}
        </label>
      </div>
    </div>
  </article>
</template>

<style scoped>
.card { display: flex; flex-direction: column; border: 1.5px solid var(--line); border-radius: var(--radius); overflow: hidden; background: var(--card); transition: border-color .15s, box-shadow .15s; }
.card.active { border-color: var(--brand); box-shadow: 0 10px 30px -18px var(--brand); }
.media { position: relative; }
img, .placeholder { width: 100%; max-width: 100%; aspect-ratio: 4 / 3; object-fit: cover; display: block; }
.placeholder { display: grid; place-items: center; background: var(--soft); color: var(--muted); }
.dots { position: absolute; bottom: .7rem; left: 0; right: 0; display: flex; justify-content: center; gap: .4rem; }
.dots button { width: .55rem; height: .55rem; border-radius: 50%; border: 0; background: #ffffff99; cursor: pointer; padding: 0; }
.dots button.on { background: #fff; }
.body { padding: 1.1rem 1.2rem 1.2rem; display: flex; flex-direction: column; gap: .6rem; flex: 1; }
.head { display: flex; justify-content: space-between; align-items: baseline; gap: 1rem; }
h3 { margin: 0; font-size: 1.4rem; }
.guests { color: var(--muted); font-size: .85rem; white-space: nowrap; }
.desc { margin: 0; color: var(--muted); flex: 1; }
.foot { display: flex; justify-content: space-between; align-items: center; gap: 1rem; flex-wrap: wrap; border-top: 1px solid var(--line); padding-top: .9rem; }
.price { margin: 0; display: flex; align-items: baseline; gap: .3rem; flex-wrap: wrap; }
.price strong { font-family: var(--font-display); font-size: 1.3rem; }
.price small { color: var(--muted); }
.pick { display: inline-flex; gap: .45rem; align-items: center; font-weight: 700; cursor: pointer; border: 1.5px solid var(--brand); color: var(--brand); border-radius: 999px; padding: .45rem .95rem; font-size: .9rem; }
.pick.on { background: var(--brand); color: var(--brand-ink); }
.pick input { width: 1rem; height: 1rem; accent-color: var(--brand); margin: 0; }
</style>
