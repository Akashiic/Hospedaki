<script setup lang="ts">
import type { AdminBooking } from '~/composables/useAdminApi'

// today: "hoje" no fuso da pousada, para saber se a estadia já começou ou terminou.
const props = defineProps<{ booking: AdminBooking, today: string, busy?: boolean }>()
const emit = defineEmits<{ confirm: [], decline: [] }>()
const askingDecline = ref(false)

const phase = computed(() => bookingPhase(props.booking, props.today))

const date = (iso: string) => formatIso(iso, { weekday: 'short', day: '2-digit', month: 'short' })

// Link de WhatsApp para falar com o hóspede (assume Brasil quando falta o código do país).
const whatsapp = computed(() => {
  let digits = props.booking.guest_phone.replace(/\D/g, '')
  if (digits.length <= 11) digits = `55${digits}`
  const text = `Olá, ${props.booking.guest_name.split(' ')[0]}! Aqui é da pousada, sobre o seu pedido de reserva ${props.booking.code}.`
  return `https://wa.me/${digits}?text=${encodeURIComponent(text)}`
})
</script>

<template>
  <article class="item" :class="[booking.status, phase]">
    <header>
      <div>
        <h3>{{ booking.guest_name }}</h3>
        <p class="code">Pedido {{ booking.code }}</p>
      </div>
      <span class="status">{{ PHASE_LABEL[phase] }}</span>
    </header>

    <dl>
      <div><dt>Entrada</dt><dd>{{ date(booking.check_in) }}</dd></div>
      <div><dt>Saída</dt><dd>{{ date(booking.check_out) }}</dd></div>
      <div><dt>Diárias</dt><dd>{{ booking.nights }}</dd></div>
      <div><dt>Hóspedes</dt><dd>{{ booking.guests }}</dd></div>
    </dl>

    <p class="accs">
      <span v-for="acc in booking.accommodations" :key="acc.id" class="chip">{{ acc.name }}</span>
      <span class="total">
        <strong>{{ formatMoney(booking.total) }}</strong>
        <!-- O total salvo não inclui o acréscimo das pessoas a mais sem valor cadastrado. -->
        <small v-if="booking.extra_guests_unpriced > 0" class="unpriced">{{ guestCopy.panelUnpriced(booking.extra_guests_unpriced) }}</small>
      </span>
    </p>
    <p v-if="booking.notes" class="notes">“{{ booking.notes }}”</p>

    <footer>
      <a :href="whatsapp" target="_blank" rel="noopener" class="btn btn-ghost btn-sm">WhatsApp {{ formatPhone(booking.guest_phone) }}</a>
      <!-- Estadia concluída ou pedido vencido não têm mais o que responder: fica só o contato. -->
      <template v-if="phase === 'request'">
        <template v-if="askingDecline">
          <span class="ask">Recusar este pedido?</span>
          <button type="button" class="btn btn-sm danger" :disabled="busy" @click="emit('decline')">Sim, recusar</button>
          <button type="button" class="btn btn-ghost btn-sm" @click="askingDecline = false">Voltar</button>
        </template>
        <template v-else>
          <button type="button" class="btn btn-ghost btn-sm" :disabled="busy" @click="askingDecline = true">Recusar</button>
          <button type="button" class="btn btn-sm" :disabled="busy" @click="emit('confirm')">Confirmar reserva</button>
        </template>
      </template>
      <template v-else-if="phase === 'upcoming' || phase === 'ongoing'">
        <button v-if="!askingDecline" type="button" class="btn btn-ghost btn-sm" @click="askingDecline = true">Cancelar reserva</button>
        <template v-else>
          <span class="ask">Cancelar e liberar as datas?</span>
          <button type="button" class="btn btn-sm danger" :disabled="busy" @click="emit('decline')">Sim, cancelar</button>
          <button type="button" class="btn btn-ghost btn-sm" @click="askingDecline = false">Voltar</button>
        </template>
      </template>
    </footer>
  </article>
</template>

<style scoped lang="scss">
.item { background: var(--card); border: 1px solid var(--line); border-left: 4px solid var(--line); border-radius: var(--radius); padding: 1rem 1.1rem; display: grid; gap: .8rem; min-width: 0; }
.item.pending { border-left-color: var(--warn); }
.item.confirmed { border-left-color: var(--brand); }
header { display: flex; justify-content: space-between; align-items: start; gap: 1rem; }
h3 { margin: 0; font-size: 1.15rem; }
.code { margin: .1rem 0 0; color: var(--muted); font-size: .82rem; font-variant-numeric: tabular-nums; }
.status { font-size: .78rem; font-weight: 700; padding: .25rem .6rem; border-radius: 999px; background: var(--soft); white-space: nowrap; }
.pending .status { background: var(--warn-soft); color: var(--warn-ink); }
.confirmed .status { background: color-mix(in srgb, var(--brand) 14%, white); color: var(--brand-text); }
.item.ongoing { border-left-color: var(--amber); }
.ongoing .status { background: var(--night); color: var(--amber-soft); }
.item.completed, .item.expired, .item.declined, .item.cancelled { border-left-color: var(--line); }
.completed .status, .expired .status { background: var(--soft); color: var(--muted); }
.declined .status, .cancelled .status { background: color-mix(in srgb, var(--danger) 10%, white); color: var(--danger); }
dl { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: .5rem; margin: 0; }
@include below(sm) { dl { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
dt { font-size: .72rem; text-transform: uppercase; letter-spacing: .08em; color: var(--muted); font-weight: 700; }
dd { margin: 0; font-weight: 700; font-variant-numeric: tabular-nums; }
.accs { display: flex; flex-wrap: wrap; align-items: center; gap: .4rem; margin: 0; }
.chip { background: var(--soft); border-radius: 999px; padding: .2rem .65rem; font-size: .85rem; font-weight: 500; }
.total { margin-left: auto; display: grid; justify-items: end; text-align: right; }
.total strong { font-family: var(--font-display); font-size: 1.15rem; font-variant-numeric: tabular-nums; }
.unpriced { color: var(--warn-ink); font-weight: 700; font-size: .82rem; }
.notes { margin: 0; color: var(--muted); font-style: italic; }
footer { display: flex; flex-wrap: wrap; justify-content: flex-end; align-items: center; gap: .5rem; border-top: 1px solid var(--line); padding-top: .8rem; }
footer > a { margin-right: auto; }
// Fundo e letra próprios: a letra não herda a --brand-ink da marca (preta nas marcas claras).
.danger { background: var(--danger); color: var(--danger-ink); }
.ask { font-weight: 700; font-size: .9rem; }
</style>
