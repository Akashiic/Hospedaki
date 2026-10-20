<script setup lang="ts">
import type { Swiper as SwiperInstance } from 'swiper'
import type { Accommodation } from '~/composables/useApi'
import { ChevronLeft, ChevronRight, Clock, Moon, Users } from 'lucide-vue-next'
import { A11y, Keyboard } from 'swiper/modules'
import { Swiper, SwiperSlide } from 'swiper/vue'
import 'swiper/css'
import 'swiper/css/a11y'

// Cartão do catálogo: fotos para passar (arrastando no celular, setas no computador), selos,
// disponibilidade e o preço que importa agora: sem datas, o "a partir de"; com datas, o total
// exato (o mesmo orçamento do Reservar) e quanto fica no Pix.
export interface CatalogState {
  /** Com datas: livre, ocupada ou ainda carregando. Sem datas: null. */
  free: boolean | null
  /** Cabe todo mundo? */
  fits: boolean
  /** Próximo fim de semana livre (sem datas escolhidas). */
  weekend: { checkIn: string, checkOut: string } | null
  quote: { total: number, nights: number, pixTotal: number, pixPercent: number } | null
  quoteError: string | null
  quoting: boolean
}
const props = defineProps<{ acc: Accommodation, state: CatalogState, search: { checkIn: string, checkOut: string, guests: number } | null, eager?: boolean }>()

const hourly = computed(() => isHourly(props.acc))
const detail = computed(() => `/acomodacoes/${props.acc.slug}`)
const ok = computed(() => props.state.fits && props.state.free !== false)
// "Reservar" abre a página da acomodação já no formulário (#reservar), com as datas da busca.
const reserve = computed(() => {
  const q = new URLSearchParams()
  const s = props.search
  if (s && ok.value) {
    q.set('entrada', s.checkIn)
    if (!hourly.value) q.set('saida', s.checkOut)
    q.set('hospedes', String(s.guests))
  }
  else if (!s && props.state.weekend && !hourly.value) {
    q.set('entrada', props.state.weekend.checkIn)
    q.set('saida', props.state.weekend.checkOut)
  }
  const query = q.toString()
  return `${detail.value}${query ? `?${query}` : ''}#reservar`
})
const pix = computed(() => props.acc.payment?.pix_discount ?? 0)
const tag = computed(() => hourly.value ? 'Eventos e day use' : props.acc.best_for === 'casal' ? 'Ideal para casais' : props.acc.best_for === 'familia' ? 'Ideal para famílias' : null)
const extra = computed(() => props.acc.amenities.length - 3)

const availability = computed(() => {
  const s = props.state
  if (props.search) {
    if (!s.fits) return { tone: 'off', text: `Cabe até ${props.acc.max_guests} pessoas` }
    if (s.free === false) return { tone: 'off', text: hourly.value ? 'Ocupado nesse dia' : 'Ocupado nessas datas' }
    if (s.free) return { tone: 'ok', text: hourly.value ? 'Livre nesse dia' : 'Livre nas suas datas' }
    return null
  }
  return s.weekend && !hourly.value ? { tone: 'ok', text: `Fim de semana livre: ${formatRange(s.weekend.checkIn, s.weekend.checkOut)}` } : null
})

// ── Fotos (Swiper): arrastar no celular ou com o mouse, setas no computador; da última volta à primeira. ──
let swiper: SwiperInstance | null = null
const index = ref(0)
function onSwiper(instance: SwiperInstance) {
  swiper = instance
  if (prefersReducedMotion()) instance.params.speed = 0
}
// Até 8 pontinhos: com mais fotos, o último fica aceso dali em diante.
const dot = computed(() => Math.min(index.value, 7))
</script>

<template>
  <article class="card" :class="{ dim: search && !ok }">
    <div class="media">
      <Swiper
        class="track"
        :modules="[A11y, Keyboard]"
        :rewind="true"
        :speed="420"
        :grab-cursor="acc.photos.length > 1"
        :allow-touch-move="acc.photos.length > 1"
        :keyboard="{ enabled: true, onlyInViewport: true }"
        :a11y="carouselA11y(`Fotos de ${acc.name}`)"
        @swiper="onSwiper"
        @slide-change="(s: SwiperInstance) => index = s.realIndex"
      >
        <SwiperSlide v-for="(photo, i) in acc.photos" :key="photo.url">
          <NuxtLink :to="detail" class="slide" tabindex="-1" draggable="false">
            <img :src="photo.url" :alt="photo.caption ?? acc.name" :loading="eager && i === 0 ? 'eager' : 'lazy'" decoding="async" draggable="false">
          </NuxtLink>
        </SwiperSlide>
        <SwiperSlide v-if="!acc.photos.length">
          <NuxtLink :to="detail" class="slide empty" tabindex="-1" :aria-label="acc.name" />
        </SwiperSlide>
      </Swiper>
      <template v-if="acc.photos.length > 1">
        <button type="button" class="nav prev" :aria-label="`Foto anterior de ${acc.name}`" @click="swiper?.slidePrev()"><ChevronLeft :size="20" /></button>
        <button type="button" class="nav next" :aria-label="`Próxima foto de ${acc.name}`" @click="swiper?.slideNext()"><ChevronRight :size="20" /></button>
        <div class="dots" aria-hidden="true">
          <span v-for="(_, i) in acc.photos.slice(0, 8)" :key="i" :class="{ on: i === dot }" />
        </div>
      </template>
      <div class="corner">
        <SitePromoPixOffer v-if="pix" variant="badge" :percent="pix" />
        <span v-if="tag" class="tag">{{ tag }}</span>
      </div>
      <span v-if="availability" class="avail" :class="availability.tone"><span class="dot" aria-hidden="true" />{{ availability.text }}</span>
    </div>

    <div class="body">
      <h3><NuxtLink :to="detail">{{ acc.name }}</NuxtLink></h3>
      <ul class="facts">
        <li><Users :size="16" aria-hidden="true" /> até {{ acc.max_guests }} pessoas</li>
        <li v-if="hourly"><Clock :size="16" aria-hidden="true" /> {{ formatHour(acc.opens_at) }} às {{ formatHour(acc.closes_at) }}</li>
        <li v-else-if="acc.min_nights > 1"><Moon :size="16" aria-hidden="true" /> mín. {{ acc.min_nights }} diárias</li>
        <li v-else><Moon :size="16" aria-hidden="true" /> check-in {{ formatHour(acc.check_in_time) }}</li>
      </ul>
      <ul v-if="acc.amenities.length" class="amen" :aria-label="`Destaques de ${acc.name}`">
        <li v-for="a in acc.amenities.slice(0, 3)" :key="a">{{ a }}</li>
        <li v-if="extra > 0" class="more">+{{ extra }}</li>
      </ul>

      <div class="buy">
        <div class="price" aria-live="polite">
          <template v-if="hourly">
            <span class="value">{{ formatMoney(acc.hourly_price ?? 0) }}</span><span class="unit">/ hora</span>
            <small v-if="(acc.min_hours ?? 1) > 1">mínimo {{ acc.min_hours }} horas</small>
          </template>
          <template v-else-if="search && state.quote">
            <span class="value">{{ formatMoney(state.quote.total) }}</span><span class="unit">total</span>
            <small>{{ plural(state.quote.nights, 'diária', 'diárias') }} · {{ plural(search.guests, 'pessoa', 'pessoas') }}</small>
            <small v-if="state.quote.pixTotal < state.quote.total" class="pix-price">{{ formatMoney(state.quote.pixTotal) }} no Pix</small>
          </template>
          <template v-else-if="search && state.quoting">
            <span class="skeleton" aria-label="Calculando o total" />
          </template>
          <template v-else-if="search && ok && state.quoteError">
            <small class="warn">{{ state.quoteError }}</small>
          </template>
          <template v-else>
            <small>a partir de</small>
            <span class="value">{{ formatMoney(listPrice(acc)) }}</span><span class="unit">/ diária</span>
            <small v-if="pix" class="pix-price">{{ formatMoney(pixPrice(listPrice(acc), pix)) }} no Pix</small>
          </template>
        </div>
        <NuxtLink :to="search && !ok ? detail : reserve" class="btn btn-sm cta">
          {{ search && !ok ? 'Ver detalhes' : hourly ? 'Ver horários' : 'Reservar' }}
        </NuxtLink>
      </div>
    </div>
  </article>
</template>

<style scoped lang="scss">
.card {
  // Coluna com mínimo 0: sem ela, o carrossel de fotos empurra a largura do cartão (o Swiper mede o pai).
  display: grid; grid-template-columns: minmax(0, 1fr); grid-template-rows: auto 1fr; min-width: 0; border-radius: 18px; overflow: hidden; background: var(--card);
  box-shadow: 0 1px 2px rgb(0 0 0 / .05), 0 22px 44px -32px rgb(20 40 30 / .45);
}
// Fora das datas ou sem lugar: fica, mas apagado (troca na hora, sem animar cor).
.card.dim .media { opacity: .55; }
.card.dim .value { color: var(--muted); }

.media { position: relative; min-width: 0; aspect-ratio: 4 / 3; background: var(--night); }
.track { height: 100%; }
.slide { display: block; height: 100%; }
.slide img { display: block; width: 100%; height: 100%; object-fit: cover; user-select: none; }
.nav {
  --focus: var(--focus-dark);
  position: absolute; z-index: 2; top: 50%; display: grid; place-items: center; width: 40px; height: 40px; margin-top: -20px; border: 0; border-radius: 50%;
  background: rgb(255 255 255 / .92); color: var(--text); cursor: pointer; box-shadow: 0 2px 10px rgb(0 0 0 / .2);
  opacity: 0; transition: opacity $dur-sm $ease-move;
}
.prev { left: .6rem; }
.next { right: .6rem; }
.media:hover .nav, .nav:focus-visible { opacity: 1; }
// Toque: as setas ficam escondidas e o dedo arrasta as fotos.
@media (hover: none) { .nav { display: none; } }
.dots { position: absolute; z-index: 2; left: 50%; bottom: .7rem; transform: translateX(-50%); display: flex; gap: 5px; }
.dots span { width: 6px; height: 6px; border-radius: 50%; background: #fff; opacity: .5; transition: opacity $dur-sm $ease-move, transform $dur-sm $ease-move; }
.dots span.on { opacity: 1; transform: scale(1.25); }
.corner { position: absolute; z-index: 2; top: .7rem; left: .7rem; right: .7rem; display: flex; flex-wrap: wrap; justify-content: space-between; align-items: flex-start; gap: .4rem; pointer-events: none; }
.tag { margin-left: auto; padding: .3rem .6rem; border-radius: 999px; background: rgb(255 255 255 / .94); color: var(--text); font-size: .76rem; font-weight: 800; box-shadow: 0 2px 8px rgb(0 0 0 / .15); }
.avail {
  position: absolute; z-index: 2; left: .7rem; bottom: 2rem; display: inline-flex; align-items: center; gap: .4rem; max-width: calc(100% - 1.4rem);
  padding: .3rem .6rem; border-radius: 999px; background: rgb(255 255 255 / .95); font-size: .78rem; font-weight: 800; box-shadow: 0 2px 8px rgb(0 0 0 / .15);
}
.avail .dot { width: 8px; height: 8px; flex: none; border-radius: 50%; }
.avail.ok { color: var(--ok-ink); }
.avail.ok .dot { background: #2f9e5f; }
.avail.off { color: var(--danger); }
.avail.off .dot { background: var(--danger); }

.body { display: grid; grid-template-rows: auto auto 1fr auto; gap: .6rem; padding: 1rem 1.1rem 1.1rem; min-width: 0; }
h3 { margin: 0; font-size: 1.65rem; line-height: 1.1; }
h3 a { color: inherit; text-decoration: none; }
h3 a:hover { text-decoration: underline; text-underline-offset: 3px; }
.facts { list-style: none; margin: 0; padding: 0; display: flex; flex-wrap: wrap; gap: .3rem .9rem; color: var(--muted); font-size: .88rem; }
.facts li { display: inline-flex; align-items: center; gap: .3rem; }
.amen { list-style: none; margin: 0; padding: 0; display: flex; flex-wrap: wrap; gap: .35rem; align-content: start; }
.amen li { padding: .2rem .55rem; border-radius: 999px; background: var(--soft); font-size: .78rem; }
.amen .more { color: var(--muted); }
.buy { display: flex; flex-wrap: wrap; align-items: flex-end; justify-content: space-between; gap: .6rem; padding-top: .8rem; border-top: 1px solid var(--line); }
.price { display: grid; grid-template-columns: auto 1fr; align-items: baseline; column-gap: .3rem; min-width: 0; }
.price small { grid-column: 1 / -1; color: var(--muted); font-size: .8rem; }
.value { font-family: var(--font-display); font-size: 1.75rem; line-height: 1.1; font-variant-numeric: tabular-nums; }
.unit { color: var(--muted); font-size: .85rem; }
.price .pix-price { color: var(--pix-deep); font-weight: 800; }
.price .warn { color: var(--warn-ink); font-weight: 700; }
.skeleton { grid-column: 1 / -1; display: block; width: 130px; height: 2rem; border-radius: 8px; background: var(--soft); }
.cta { flex: none; }
@include reduced-motion { .dots span { transition: none; } }
</style>
