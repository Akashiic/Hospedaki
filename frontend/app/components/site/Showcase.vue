<script setup lang="ts">
import type { Accommodation } from '~/composables/useApi'

// Acomodações em quadros grandes, alternando lados, com troca de fotos em fusão.
defineProps<{ accommodations: Accommodation[] }>()
const photoIndex = reactive<Record<number, number>>({})
</script>

<template>
  <section class="showcase">

    <article v-for="(acc, index) in accommodations" :key="acc.id" class="container row" :class="{ flip: index % 2 === 1 }">
      <div v-reveal="'wipe'" class="frame">
        <img
          v-for="(photo, i) in acc.photos"
          :key="photo.url"
          :src="photo.url"
          :alt="photo.caption ?? acc.name"
          :class="{ on: i === (photoIndex[acc.id] ?? 0) }"
          loading="lazy"
          decoding="async"
        >
        <div v-if="acc.photos.length > 1" class="thumbs">
          <button
            v-for="(photo, i) in acc.photos"
            :key="i"
            type="button"
            :class="{ on: i === (photoIndex[acc.id] ?? 0) }"
            :aria-label="photo.caption ?? `Foto ${i + 1}`"
            @click="photoIndex[acc.id] = i"
          />
        </div>
        <p v-if="acc.photos[photoIndex[acc.id] ?? 0]?.caption" class="cap">{{ acc.photos[photoIndex[acc.id] ?? 0]?.caption }}</p>
      </div>

      <div class="text">
        <h3 v-reveal>{{ acc.name }}</h3>
        <p v-reveal class="desc" style="--i: 2">{{ acc.description }}</p>
        <ul v-reveal class="tags" style="--i: 3">
          <li>até {{ acc.max_guests }} pessoas</li>
          <li v-if="acc.min_nights > 1">mínimo {{ acc.min_nights }} diárias</li>
          <li v-if="acc.weekend_price">fim de semana {{ formatMoney(acc.weekend_price) }}</li>
        </ul>
        <div v-reveal class="buy" style="--i: 4">
          <p class="price"><small>diárias a partir de</small><strong>{{ formatMoney(acc.base_price) }}</strong></p>
          <NuxtLink :to="`/reservar?acomodacao=${acc.slug}`" class="btn">Ver datas <span class="arrow">→</span></NuxtLink>
          <NuxtLink :to="`/acomodacoes/${acc.slug}`" class="text-link">Conhecer o {{ acc.name }}</NuxtLink>
        </div>
      </div>
    </article>
  </section>
</template>

<style scoped lang="scss">
.showcase { @include section-pad(clamp(4rem, 9vw, 7rem)); }
.row { display: grid; grid-template-columns: minmax(0, 1.35fr) minmax(0, 1fr); gap: clamp(2rem, 5vw, 5rem); align-items: center; }
.row + .row { margin-top: clamp(4rem, 9vw, 7.5rem); }
.flip .frame { order: 2; }
.frame { position: relative; aspect-ratio: 4 / 3; max-width: 100%; border-radius: 6px; overflow: hidden; background: var(--night); }
.frame img { position: absolute; inset: 0; width: 100%; height: 100%; object-fit: cover; opacity: 0; transform: scale($zoom-sm); transition: opacity $dur-2xl $ease-move, transform $dur-4xl $ease-enter; }
.frame img.on { opacity: 1; transform: scale(1); }
.frame:hover img.on { transform: scale($zoom-sm); }
.thumbs { position: absolute; right: 1rem; bottom: 1rem; display: flex; gap: .4rem; z-index: 2; }
.thumbs button { width: 28px; height: 3px; border: 0; padding: 8px 0; background: none; cursor: pointer; position: relative; }
.thumbs button::before { content: ""; position: absolute; left: 0; right: 0; top: 7px; height: 2px; background: rgb(255 255 255 / .45); transition: background-color $dur-md $ease-move; }
.thumbs button.on::before { background: #fff; }
.cap { position: absolute; left: 1rem; bottom: .85rem; margin: 0; z-index: 2; color: #fff; font-size: .85rem; text-shadow: 0 1px 8px rgb(0 0 0 / .5); }
h3 { font-size: clamp(2.6rem, 5vw, 4rem); margin: 0 0 1rem; }
.desc { color: var(--muted); font-size: 1.05rem; margin: 0 0 1.3rem; max-width: 34rem; }
.tags { list-style: none; padding: 0; margin: 0 0 1.8rem; display: flex; flex-wrap: wrap; gap: .5rem; }
.tags li { border: 1px solid var(--line); border-radius: 999px; padding: .3rem .8rem; font-size: .85rem; }
.buy { display: flex; flex-wrap: wrap; align-items: center; gap: 1rem 1.6rem; border-top: 1px solid var(--line); padding-top: 1.4rem; }
.price { margin: 0; display: grid; }
.price small { color: var(--muted); font-size: .8rem; }
.price strong { font-family: var(--font-display); font-weight: 400; font-size: 2rem; line-height: 1.1; font-variant-numeric: tabular-nums; }
@include below(lg) {
  .row { grid-template-columns: minmax(0, 1fr); }
  .flip .frame { order: 0; }
}
</style>
