<script setup lang="ts">
import type { AdminBooking } from '~/composables/useAdminApi'
import { ArrowRight, Check, MessageCircle, Moon, Users, X } from 'lucide-vue-next'

// today: "hoje" no fuso da pousada, para saber se a estadia já começou ou terminou.
const props = defineProps<{ booking: AdminBooking, today: string, busy?: boolean }>()
const emit = defineEmits<{ confirm: [], decline: [] }>()
const askingDecline = ref(false)

const phase = computed(() => bookingPhase(props.booking, props.today))

const day = (iso: string) => formatIso(iso, { day: 'numeric', month: 'short' })
const weekday = (iso: string) => formatIso(iso, { weekday: 'short' })
// Iniciais do hóspede para o avatar ("Família Oliveira" → "FO").
const initials = computed(() => initialsOf(props.booking.guest_name))
// Recebido há quanto tempo (só nos pedidos): ajuda a responder primeiro quem espera mais.
const received = computed(() => {
  const hours = Math.max(0, Math.round((Date.now() - new Date(props.booking.created_at).getTime()) / 36e5))
  if (hours < 1) return 'agora há pouco'
  if (hours < 24) return `há ${hours} h`
  return `há ${plural(Math.round(hours / 24), 'dia', 'dias')}`
})

// Link de WhatsApp para falar com o hóspede (assume Brasil quando falta o código do país).
const whatsapp = computed(() => {
  let digits = props.booking.guest_phone.replace(/\D/g, '')
  if (digits.length <= 11) digits = `55${digits}`
  const text = `Olá, ${props.booking.guest_name.split(' ')[0]}! Aqui é da pousada, sobre o seu pedido de reserva ${props.booking.code}.`
  return `https://wa.me/${digits}?text=${encodeURIComponent(text)}`
})
</script>

<template>
  <article class="item a-card" :class="[booking.status, phase]">
    <header>
      <span class="avatar" aria-hidden="true">{{ initials }}</span>
      <div class="who">
        <h3>{{ booking.guest_name }}</h3>
        <p class="code">{{ booking.code }}<template v-if="phase === 'request'"> · recebido {{ received }}</template></p>
      </div>
      <span class="status"><span class="a-dot" />{{ PHASE_LABEL[phase] }}</span>
    </header>

    <div class="stay">
      <div class="dates">
        <span class="d"><small>{{ weekday(booking.check_in) }}</small><strong>{{ day(booking.check_in) }}</strong></span>
        <ArrowRight :size="18" class="arrow" aria-hidden="true" />
        <span class="d"><small>{{ weekday(booking.check_out) }}</small><strong>{{ day(booking.check_out) }}</strong></span>
      </div>
      <div class="facts">
        <span><Moon :size="15" aria-hidden="true" /> {{ plural(booking.nights, 'diária', 'diárias') }}</span>
        <span><Users :size="15" aria-hidden="true" /> {{ plural(booking.guests, 'hóspede', 'hóspedes') }}</span>
        <span v-for="acc in booking.accommodations" :key="acc.id" class="chip">{{ acc.name }}</span>
      </div>
      <div class="total">
        <strong>{{ formatMoney(booking.total) }}</strong>
        <!-- O total salvo não inclui o acréscimo das pessoas a mais sem valor cadastrado. -->
        <small v-if="booking.extra_guests_unpriced > 0" class="unpriced">{{ guestCopy.panelUnpriced(booking.extra_guests_unpriced) }}</small>
      </div>
    </div>

    <p v-if="booking.notes" class="notes">“{{ booking.notes }}”</p>

    <footer>
      <a :href="whatsapp" target="_blank" rel="noopener" class="wa"><MessageCircle :size="18" aria-hidden="true" /> <span>{{ formatPhone(booking.guest_phone) }}</span></a>
      <!-- Estadia concluída ou pedido vencido não têm mais o que responder: fica só o contato. -->
      <Transition name="swap" mode="out-in">
        <div v-if="phase === 'request' && askingDecline" key="ask" class="actions">
          <span class="ask">Recusar este pedido?</span>
          <button type="button" class="btn btn-sm danger" :disabled="busy" @click="emit('decline')">Sim, recusar</button>
          <button type="button" class="btn btn-ghost btn-sm" @click="askingDecline = false">Voltar</button>
        </div>
        <div v-else-if="phase === 'request'" key="req" class="actions">
          <button type="button" class="btn btn-ghost btn-sm" :disabled="busy" @click="askingDecline = true"><X :size="16" aria-hidden="true" /> Recusar</button>
          <button type="button" class="btn btn-sm" :disabled="busy" @click="emit('confirm')"><Check :size="16" aria-hidden="true" /> {{ busy ? 'Confirmando...' : 'Confirmar' }}</button>
        </div>
        <div v-else-if="(phase === 'upcoming' || phase === 'ongoing') && askingDecline" key="ask2" class="actions">
          <span class="ask">Cancelar e liberar as datas?</span>
          <button type="button" class="btn btn-sm danger" :disabled="busy" @click="emit('decline')">Sim, cancelar</button>
          <button type="button" class="btn btn-ghost btn-sm" @click="askingDecline = false">Voltar</button>
        </div>
        <div v-else-if="phase === 'upcoming' || phase === 'ongoing'" key="conf" class="actions">
          <button type="button" class="btn btn-ghost btn-sm" @click="askingDecline = true">Cancelar reserva</button>
        </div>
      </Transition>
    </footer>
  </article>
</template>

<style scoped lang="scss">
.item { padding: 1.1rem 1.2rem; display: grid; gap: .9rem; min-width: 0; overflow: hidden; container-type: inline-size; }
// Faixa de cor da situação à esquerda.
.item::before { content: ""; position: absolute; inset: 0 auto 0 0; width: 4px; background: var(--tone, var(--line)); }
.request { --tone: var(--warn); }
.upcoming { --tone: var(--brand); }
.ongoing { --tone: var(--amber); }

header { display: flex; align-items: center; gap: .8rem; }
.avatar { display: grid; place-items: center; flex: none; width: 44px; height: 44px; border-radius: 50%; background: color-mix(in srgb, var(--brand) 14%, white); color: var(--brand-text); font-weight: 800; font-size: .88rem; }
.request .avatar { background: var(--warn-soft); color: var(--warn-ink); }
.who { flex: 1; min-width: 0; }
h3 { margin: 0; font-size: 1.3rem; line-height: 1.15; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.code { margin: .1rem 0 0; color: var(--muted); font-size: .8rem; font-variant-numeric: tabular-nums; letter-spacing: .02em; }
.status { display: inline-flex; align-items: center; gap: .4rem; flex: none; font-size: .76rem; font-weight: 800; padding: .3rem .65rem; border-radius: 999px; background: var(--a-sunk); color: var(--muted); }
.request .status { background: var(--warn-soft); color: var(--warn-ink); }
.upcoming .status { background: color-mix(in srgb, var(--brand) 14%, white); color: var(--brand-text); }
.ongoing .status { background: var(--night); color: var(--amber-soft); }
.declined .status, .cancelled .status { background: color-mix(in srgb, var(--danger) 10%, white); color: var(--danger); }
@container (max-width: 440px) { .status { position: absolute; top: .9rem; right: .9rem; } header { padding-right: 6.5rem; } }

.stay { display: grid; grid-template-columns: auto minmax(0, 1fr) auto; align-items: center; gap: .6rem 1.2rem; padding: .8rem .9rem; border-radius: 14px; background: var(--a-sunk); }
// O cartão se ajusta à própria largura (lista larga ou coluna da grade), não à da tela.
@container (max-width: 600px) { .stay { grid-template-columns: minmax(0, 1fr) auto; .facts { grid-column: 1 / -1; grid-row: 2; } } }
@container (max-width: 380px) {
  .stay { grid-template-columns: minmax(0, 1fr); .total { justify-items: start; text-align: left; } }
  .d strong { font-size: 1.15rem; }
}
.dates { display: flex; align-items: center; gap: .6rem; }
.d { display: grid; line-height: 1.15; }
.d small { font-size: .68rem; font-weight: 800; text-transform: uppercase; letter-spacing: .08em; color: var(--muted); }
.d strong { font-family: var(--font-display); font-weight: 400; font-size: 1.35rem; white-space: nowrap; }
.arrow { color: var(--muted); }
.facts { display: flex; flex-wrap: wrap; align-items: center; gap: .35rem .9rem; color: var(--muted); font-size: .86rem; font-weight: 600; }
.facts span { display: inline-flex; align-items: center; gap: .3rem; }
.chip { background: var(--a-card); color: var(--text); border-radius: 999px; padding: .15rem .6rem; font-weight: 600; }
.total { display: grid; justify-items: end; text-align: right; }
.total strong { font-family: var(--font-display); font-weight: 400; font-size: 1.45rem; font-variant-numeric: tabular-nums; white-space: nowrap; }
.unpriced { color: var(--warn-ink); font-weight: 700; font-size: .78rem; }
.notes { margin: 0; padding-left: .9rem; border-left: 3px solid var(--a-sunk); color: var(--muted); font-style: italic; }

footer { display: flex; flex-wrap: wrap; justify-content: space-between; align-items: center; gap: .6rem; }
.wa { display: inline-flex; align-items: center; gap: .45rem; min-height: 44px; padding: 0 .9rem 0 .7rem; border-radius: 999px; background: color-mix(in srgb, #1f9d55 12%, white); color: #157541; font-weight: 700; font-size: .88rem; text-decoration: none; }
.actions { display: flex; flex-wrap: wrap; align-items: center; justify-content: flex-end; gap: .5rem; margin-left: auto; }
.actions .btn { gap: .35rem; }
// Fundo e letra próprios: a letra não herda a --brand-ink da marca (preta nas marcas claras).
.danger { background: var(--danger); color: var(--danger-ink); }
.ask { font-weight: 700; font-size: .9rem; }
.swap-enter-active { transition: opacity $dur-md $ease-enter, transform $dur-md $ease-enter; }
.swap-leave-active { transition: opacity $dur-xs $ease-exit; }
.swap-enter-from { opacity: 0; transform: translateX($dist-xs); }
.swap-leave-to { opacity: 0; }
@include reduced-motion { .swap-enter-from { transform: none; } }
</style>
