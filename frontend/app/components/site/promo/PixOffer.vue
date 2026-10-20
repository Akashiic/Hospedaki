<script setup lang="ts">
import { Zap } from 'lucide-vue-next'

// Selo do desconto no Pix, com o preço já com desconto e, se a promoção tem prazo, o timer.
// "badge" é só o selo (para os cartões); "full" junta selo, preço e timer (página da acomodação).
// Acabando a promoção sem loop, o selo some (o backend também para de dar o desconto).
const props = defineProps<{ percent: number, endsAt?: string | null, loopHours?: number | null, price?: number | null, unit?: string, variant?: 'badge' | 'full' }>()

const ended = ref(false)
watch(() => props.endsAt, () => { ended.value = false })
const visible = computed(() => props.percent > 0 && !ended.value)
</script>

<template>
  <span v-if="visible && variant === 'badge'" class="pix-badge sm">
    <Zap :size="14" aria-hidden="true" /> −{{ percent }}% no Pix
  </span>
  <section v-else-if="visible" class="offer" aria-label="Desconto no Pix">
    <div class="row">
      <span class="pix-badge"><Zap :size="16" aria-hidden="true" /> −{{ percent }}% no Pix</span>
      <p v-if="price" class="price"><strong>{{ formatMoney(pixPrice(price, percent)) }}</strong> <span>{{ unit ?? 'no Pix' }}</span></p>
    </div>
    <div v-if="endsAt" class="ends">
      <p>{{ loopHours ? 'Oferta do dia. Termina em' : 'A promoção termina em' }}</p>
      <SitePromoFlipTimer :ends-at="endsAt" :loop-hours="loopHours" @ended="ended = true" />
    </div>
  </section>
</template>

<style scoped lang="scss">
// Selo em verde-água do Pix, com um brilho que passa de tempos em tempos (só transform e opacidade).
.pix-badge {
  position: relative; display: inline-flex; align-items: center; gap: .35rem; overflow: hidden; isolation: isolate;
  padding: .45rem .85rem; border-radius: 999px; font-weight: 800; font-size: .95rem; letter-spacing: .01em;
  color: var(--pix-ink); background: linear-gradient(135deg, var(--pix) 0%, var(--pix-deep) 100%);
  box-shadow: 0 6px 18px -8px color-mix(in srgb, var(--pix) 80%, transparent);
  &::after {
    content: ""; position: absolute; inset: 0; z-index: -1; pointer-events: none;
    background: linear-gradient(105deg, transparent 30%, rgb(255 255 255 / .38) 50%, transparent 70%);
    transform: translateX(-120%); animation: shine $dur-3xl $ease-move $dur-xl infinite;
  }
}
.pix-badge.sm { padding: .3rem .6rem; font-size: .8rem; }
@keyframes shine { 0% { transform: translateX(-120%); } 45%, 100% { transform: translateX(120%); } }

.offer {
  display: grid; gap: .9rem; padding: 1rem 1.1rem; border-radius: 18px;
  background: linear-gradient(160deg, var(--pix-soft), color-mix(in srgb, var(--pix-soft) 40%, white));
  box-shadow: inset 0 0 0 1px color-mix(in srgb, var(--pix) 25%, transparent);
}
.row { display: flex; flex-wrap: wrap; align-items: center; gap: .6rem 1rem; }
.price { margin: 0; color: var(--pix-deep); }
.price strong { font-size: 1.35rem; font-variant-numeric: tabular-nums; }
.price span { font-size: .9rem; font-weight: 600; }
.ends { display: grid; gap: .45rem; --timer-label: var(--pix-deep); --timer-colon: var(--pix-deep); }
.ends p { margin: 0; font-size: .85rem; font-weight: 700; color: var(--pix-deep); }

@include reduced-motion { .pix-badge::after { animation: none; opacity: 0; } }
</style>
