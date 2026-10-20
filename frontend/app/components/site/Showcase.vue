<script setup lang="ts">
import type { Accommodation } from '~/composables/useApi'

// Acomodações em quadros grandes, alternando lados, com troca de fotos em fusão.
// Quando a diária vale para menos gente que a capacidade, o preço diz para quantas pessoas é.
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
        <h2 v-reveal>{{ acc.name }}</h2>
        <p v-reveal class="desc" style="--i: 2">{{ acc.description }}</p>
        <ul v-if="isHourly(acc)" v-reveal class="tags" style="--i: 3">
          <li>até {{ acc.max_guests }} pessoas</li>
          <li>das {{ formatHour(acc.opens_at) }} às {{ formatHour(acc.closes_at) }}</li>
          <li v-if="(acc.min_hours ?? 1) > 1">mínimo {{ acc.min_hours }} horas</li>
        </ul>
        <ul v-else v-reveal class="tags" style="--i: 3">
          <li>até {{ acc.max_guests }} pessoas<template v-if="guestPricing(acc)"> (valor {{ guestCopy.forPeople(guestPricing(acc)!.included) }})</template></li>
          <li v-if="acc.min_nights > 1">mínimo {{ acc.min_nights }} diárias</li>
          <li v-if="acc.weekend_price">fim de semana {{ formatMoney(acc.weekend_price) }}</li>
        </ul>
        <div v-reveal class="buy" style="--i: 4">
          <p v-if="isHourly(acc)" class="price">
            <small>por hora, para eventos e day use</small>
            <strong>{{ formatMoney(acc.hourly_price ?? 0) }}</strong>
          </p>
          <p v-else class="price">
            <small>{{ guestPricing(acc) ? `diária ${guestCopy.forPeople(guestPricing(acc)!.included)}, a partir de` : 'diárias a partir de' }}</small>
            <strong>{{ formatMoney(acc.base_price) }}</strong>
          </p>
          <NuxtLink :to="`/reservar?acomodacao=${acc.slug}`" class="btn">{{ isHourly(acc) ? 'Ver horários' : 'Ver datas' }} <span class="arrow">→</span></NuxtLink>
          <NuxtLink :to="`/acomodacoes/${acc.slug}`" class="text-link">Ver detalhes <span class="sr-only">de {{ acc.name }}</span></NuxtLink>
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
// Botões de 44 × 44px para o dedo; o traço de 28px continua desenhado pelo ::before.
.thumbs { --focus: var(--focus-dark); position: absolute; right: calc(1rem - 8px); bottom: 0; display: flex; z-index: 2; }
.thumbs button { width: 44px; height: 44px; border: 0; padding: 0; background: none; cursor: pointer; position: relative; }
// O traço da foto atual acende por opacidade (cor não anima).
.thumbs button::before { content: ""; position: absolute; left: 8px; right: 8px; top: 21px; height: 2px; background: var(--on-photo); opacity: .45; transition: opacity $dur-md $ease-move; }
.thumbs button.on::before { opacity: 1; }
.cap { position: absolute; left: 1rem; bottom: .85rem; margin: 0; z-index: 2; color: #fff; font-size: .85rem; text-shadow: 0 1px 8px rgb(0 0 0 / .5); }
h2 { font-size: clamp(2.6rem, 5vw, 4rem); margin: 0 0 1rem; }
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
