<script setup lang="ts">
const route = useRoute()
const { property } = await useProperty()

const acc = computed(() => property.value.accommodations.find(a => a.slug === route.params.slug))
// fatal: também na navegação dentro do site mostra a página de erro (sem isso, ficava em branco).
if (!acc.value) throw createError({ statusCode: 404, statusMessage: 'Acomodação não encontrada', fatal: true })

const others = computed(() => property.value.accommodations.filter(a => a.id !== acc.value!.id))

useSeoMeta({
  title: `${acc.value.name} · ${property.value.name}`,
  description: acc.value.description ?? undefined,
  ogImage: acc.value.photos[0]?.url,
})
</script>

<template>
  <div v-if="acc">
    <SitePageHeader eyebrow="Acomodação" :title="acc.name" :image="acc.photos[0]?.url">
      <div class="header-actions">
        <NuxtLink :to="`/reservar?acomodacao=${acc.slug}`" class="btn btn-amber">Ver datas e reservar <span class="arrow">→</span></NuxtLink>
      </div>
    </SitePageHeader>

    <section class="container detail">
      <div class="about">
        <p v-reveal class="desc">{{ acc.description }}</p>
        <ul v-if="acc.amenities.length" class="amenities">
          <li v-for="(item, i) in acc.amenities" :key="item" v-reveal :style="{ '--i': Math.min(i, 8) }">{{ item }}</li>
        </ul>
      </div>

      <aside v-reveal class="facts" style="--i: 2">
        <!-- Mesma regra do PricingService: só as noites de sexta e sábado são de fim de semana. -->
        <div><span>Diária {{ acc.weekend_price ? '(dom. a qui.)' : '' }}</span><strong>{{ formatMoney(acc.base_price) }}</strong></div>
        <div v-if="acc.weekend_price"><span>Diária (sex. e sáb.)</span><strong>{{ formatMoney(acc.weekend_price) }}</strong></div>
        <div><span>Hóspedes</span><strong>até {{ acc.max_guests }} pessoas</strong></div>
        <div v-if="acc.min_nights > 1"><span>Mínimo</span><strong>{{ acc.min_nights }} diárias</strong></div>
        <div><span>Entrada e saída</span><strong>{{ property.check_in_time }} · {{ property.check_out_time }}</strong></div>
        <NuxtLink :to="`/reservar?acomodacao=${acc.slug}`" class="btn">Ver datas <span class="arrow">→</span></NuxtLink>
      </aside>
    </section>

    <section v-if="acc.photos.length > 1" class="container photos">
      <figure v-for="(photo, i) in acc.photos" :key="photo.url" v-reveal="'wipe'" :class="{ wide: i === 0 }" :style="{ '--i': i % 3 }">
        <img :src="photo.url" :alt="photo.caption ?? acc.name" loading="lazy" decoding="async">
        <figcaption v-if="photo.caption">{{ photo.caption }}</figcaption>
      </figure>
    </section>

    <section v-if="others.length" class="container others">
      <h2 v-reveal class="eyebrow">Também temos</h2>
      <LazySiteStayCards hydrate-on-visible :accommodations="others" />
    </section>
  </div>
</template>

<style scoped lang="scss">
.header-actions { margin-top: 1.6rem; }
.detail {
  @include section-pad(clamp(4rem, 9vw, 6.5rem), clamp(3rem, 6vw, 4.5rem));
  display: grid; grid-template-columns: minmax(0, 1.5fr) minmax(0, 1fr); gap: clamp(2rem, 6vw, 5rem); align-items: start;
  @include below(lg) { grid-template-columns: minmax(0, 1fr); }
}
.desc { @include display(clamp(1.7rem, 3.2vw, 2.5rem)); line-height: 1.2; margin: 0 0 2.2rem; text-wrap: pretty; }
.amenities {
  list-style: none; padding: 0; margin: 0; display: grid; grid-template-columns: repeat(auto-fit, minmax(min(100%, 240px), 1fr)); gap: 0 2rem;
  li { padding: .8rem 0; border-top: 1px solid var(--line); }
}
.facts {
  position: sticky; top: calc(var(--nav-h) + 1.5rem); background: var(--card); border-radius: var(--radius); padding: 1.4rem; display: grid; gap: 1rem;
  box-shadow: 0 1px 2px rgb(0 0 0 / .04), 0 18px 40px -28px rgb(20 40 30 / .35);
  div { display: grid; gap: .1rem; border-bottom: 1px solid var(--line); padding-bottom: .8rem; }
  // Só os rótulos das linhas (sem pegar a seta do botão "Ver datas").
  div > span { @include eyebrow(var(--muted), sm); }
  strong { font-family: var(--font-display); font-weight: 400; font-size: 1.6rem; font-variant-numeric: tabular-nums; }
}
.photos {
  display: grid; gap: 1rem; grid-template-columns: repeat(2, minmax(0, 1fr)); padding-bottom: clamp(4rem, 8vw, 6rem);
  @include below(md) { grid-template-columns: minmax(0, 1fr); }
  figure { margin: 0; border-radius: 6px; overflow: hidden; background: var(--night); position: relative; aspect-ratio: 4 / 3; }
  .wide { grid-column: 1 / -1; aspect-ratio: 16 / 8; @include below(md) { aspect-ratio: 4 / 3; } }
  img { width: 100%; height: 100%; object-fit: cover; display: block; }
  figcaption { position: absolute; left: 1rem; bottom: .8rem; color: #fff; font-size: .85rem; text-shadow: 0 1px 8px rgb(0 0 0 / .5); }
}
.others { padding-bottom: clamp(5rem, 10vw, 8rem); .eyebrow { margin-bottom: 1.5rem; } }
</style>
