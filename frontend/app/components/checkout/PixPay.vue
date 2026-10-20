<script setup lang="ts">
import type { Checkout } from '~/composables/useApi'
import { Check, Copy, ExternalLink, QrCode } from 'lucide-vue-next'
import QRCode from 'qrcode'

// Pix no checkout: gera a cobrança, mostra o QR Code (desenhado aqui a partir do copia e cola) e
// espera o pagamento. A página pergunta a situação a cada poucos segundos; aprovado, ela troca
// sozinha para a confirmação. Na demonstração, um botão simula o pagamento.
const props = defineProps<{ checkout: Checkout, busy: boolean }>()
const emit = defineEmits<{ generate: [email: string | null], simulate: [] }>()

const pix = computed(() => props.checkout.methods.pix!)
const payment = computed(() => props.checkout.payment?.method === 'pix' && props.checkout.payment.status === 'pending' ? props.checkout.payment : null)
const email = ref(props.checkout.booking.guest_email ?? '')
const needsEmail = computed(() => !props.checkout.booking.guest_email)

const svg = ref('')
watch(() => payment.value?.qr_code, async (code) => {
  svg.value = code ? await QRCode.toString(code, { type: 'svg', margin: 1, errorCorrectionLevel: 'M', color: { dark: '#0e1411', light: '#ffffff' } }) : ''
}, { immediate: true })

const copied = ref(false)
async function copy() {
  if (!payment.value?.qr_code) return
  try {
    await navigator.clipboard.writeText(payment.value.qr_code)
    copied.value = true
    setTimeout(() => { copied.value = false }, 2500)
  }
  catch {
    (document.getElementById('pix-code') as HTMLTextAreaElement | null)?.select()
  }
}
</script>

<template>
  <div class="pix">
    <template v-if="!payment">
      <div class="amount">
        <p class="label">Total no Pix</p>
        <p class="value">{{ formatMoney(pix.total) }}</p>
        <p v-if="pix.discount" class="saving">Você economiza {{ formatMoney(pix.discount) }} <span class="tag">−{{ pix.percent }}%</span></p>
      </div>
      <div v-if="pix.promo_ends_at" class="promo">
        <p>O desconto do Pix termina em</p>
        <SitePromoFlipTimer :ends-at="pix.promo_ends_at" size="sm" />
      </div>
      <form class="go" @submit.prevent="emit('generate', needsEmail ? email : null)">
        <label v-if="needsEmail" for="pix-email">Seu e-mail <small>o comprovante vai para ele</small>
          <input id="pix-email" v-model="email" type="email" required autocomplete="email">
        </label>
        <button class="btn btn-pix" :disabled="busy"><QrCode :size="18" aria-hidden="true" /> {{ busy ? 'Gerando o Pix...' : 'Gerar o Pix' }}</button>
      </form>
    </template>

    <div v-else class="qr-area" aria-live="polite">
      <div class="qr">
        <div v-if="svg" class="qr-img" role="img" aria-label="QR Code do Pix" v-html="svg" />
        <div v-else class="qr-img skeleton" />
        <span v-if="payment.simulated" class="sim-tag">demonstração</span>
      </div>
      <div class="how">
        <p class="value">{{ formatMoney(payment.amount) }}</p>
        <ol>
          <li>Abra o app do seu banco e escolha <strong>Pix → Ler QR Code</strong>.</li>
          <li>Ou copie o código abaixo e use <strong>Pix copia e cola</strong>.</li>
          <li>Pague: a confirmação aparece aqui sozinha.</li>
        </ol>
        <label for="pix-code" class="sr-only">Pix copia e cola</label>
        <div class="code">
          <textarea id="pix-code" readonly rows="2" :value="payment.qr_code ?? ''" @focus="($event.target as HTMLTextAreaElement).select()" />
          <button type="button" class="btn btn-sm" @click="copy"><component :is="copied ? Check : Copy" :size="16" aria-hidden="true" /> {{ copied ? 'Copiado!' : 'Copiar' }}</button>
        </div>
        <p class="waiting"><span class="pulse" aria-hidden="true" /> Esperando o pagamento<template v-if="payment.expires_at">. O código vale até {{ new Date(payment.expires_at).toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' }) }}</template>.</p>
        <a v-if="payment.ticket_url" :href="payment.ticket_url" target="_blank" rel="noopener" class="text-link">Ver as instruções do pagamento <ExternalLink :size="14" aria-hidden="true" /></a>
        <button v-if="payment.simulated" type="button" class="btn btn-ghost btn-sm" :disabled="busy" @click="emit('simulate')">Simular o pagamento (demonstração)</button>
      </div>
    </div>
  </div>
</template>

<style scoped lang="scss">
.pix { display: grid; gap: 1.1rem; }
.amount { display: grid; gap: .15rem; }
.label { @include eyebrow(var(--muted), sm); margin: 0; }
.value { margin: 0; font-family: var(--font-display); font-size: 2.6rem; line-height: 1.05; font-variant-numeric: tabular-nums; }
.saving { margin: 0; color: var(--pix-deep); font-weight: 700; }
.tag { display: inline-block; margin-left: .3rem; padding: .1rem .5rem; border-radius: 999px; background: var(--pix); color: var(--pix-ink); font-size: .8rem; }
.promo { display: grid; gap: .4rem; padding: .8rem .9rem; border-radius: 14px; background: var(--pix-soft); --timer-label: var(--pix-deep); --timer-colon: var(--pix-deep); }
.promo p { margin: 0; font-size: .85rem; font-weight: 700; color: var(--pix-deep); }
.go { display: grid; gap: .8rem; }
.go label { display: grid; gap: .3rem; font-weight: 700; font-size: .88rem; }
.go small { font-weight: 400; color: var(--muted); }
.btn-pix { display: inline-flex; justify-content: center; align-items: center; gap: .5rem; background: var(--pix); color: var(--pix-ink); }
.qr-area { display: grid; gap: 1.2rem; grid-template-columns: minmax(0, 1fr); @include above(md) { grid-template-columns: 220px minmax(0, 1fr); } align-items: start; }
.qr { position: relative; justify-self: center; width: min(100%, 240px); padding: .7rem; border-radius: 18px; background: #fff; box-shadow: inset 0 0 0 1px var(--line), 0 18px 40px -26px rgb(0 0 0 / .4); }
.qr-img { aspect-ratio: 1; }
.qr-img :deep(svg) { display: block; width: 100%; height: 100%; }
.skeleton { border-radius: 10px; background: var(--soft); }
.sim-tag { position: absolute; left: 50%; bottom: -.7rem; transform: translateX(-50%); padding: .15rem .6rem; border-radius: 999px; background: var(--warn-soft); color: var(--warn-ink); font-size: .72rem; font-weight: 800; white-space: nowrap; }
.how { display: grid; gap: .7rem; min-width: 0; }
.how .value { font-size: 2.1rem; }
ol { margin: 0; padding-left: 1.2rem; display: grid; gap: .3rem; font-size: .92rem; }
.code { display: flex; gap: .5rem; align-items: stretch; }
.code textarea { flex: 1; min-width: 0; resize: none; font-family: ui-monospace, monospace; font-size: .78rem; line-height: 1.35; }
.code .btn { display: inline-flex; align-items: center; gap: .35rem; flex: none; }
.waiting { margin: 0; display: flex; align-items: center; gap: .5rem; color: var(--muted); font-size: .9rem; font-weight: 600; }
.pulse { position: relative; flex: none; width: 10px; height: 10px; border-radius: 50%; background: var(--pix); }
.pulse::after { content: ""; position: absolute; inset: 0; border-radius: 50%; background: var(--pix); animation: ping $dur-3xl $ease-enter infinite; }
@keyframes ping { from { transform: scale(1); opacity: .6; } to { transform: scale(2.6); opacity: 0; } }
.text-link { display: inline-flex; align-items: center; gap: .3rem; font-weight: 700; color: var(--brand-text); }
@include reduced-motion { .pulse::after { animation: none; } }
</style>
