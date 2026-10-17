<script setup lang="ts">
import type { AdminBooking } from '~/composables/useAdminApi'

const props = defineProps<{ booking: AdminBooking, busy?: boolean }>()
const emit = defineEmits<{ confirm: [], decline: [] }>()
const askingDecline = ref(false)

const statusLabel: Record<AdminBooking['status'], string> = {
  pending: 'Aguardando você',
  confirmed: 'Confirmada',
  declined: 'Recusada',
  cancelled: 'Cancelada',
}

const date = (iso: string) => new Date(`${iso}T12:00:00`).toLocaleDateString('pt-BR', { weekday: 'short', day: '2-digit', month: 'short' })

// Link de WhatsApp para falar com o hóspede (assume Brasil quando falta o código do país).
const whatsapp = computed(() => {
  let digits = props.booking.guest_phone.replace(/\D/g, '')
  if (digits.length <= 11) digits = `55${digits}`
  const text = `Olá, ${props.booking.guest_name.split(' ')[0]}! Aqui é da pousada, sobre o seu pedido de reserva ${props.booking.code}.`
  return `https://wa.me/${digits}?text=${encodeURIComponent(text)}`
})
</script>

<template>
  <article class="item" :class="booking.status">
    <header>
      <div>
        <h3>{{ booking.guest_name }}</h3>
        <p class="code">Pedido {{ booking.code }}</p>
      </div>
      <span class="status">{{ statusLabel[booking.status] }}</span>
    </header>

    <dl>
      <div><dt>Entrada</dt><dd>{{ date(booking.check_in) }}</dd></div>
      <div><dt>Saída</dt><dd>{{ date(booking.check_out) }}</dd></div>
      <div><dt>Diárias</dt><dd>{{ booking.nights }}</dd></div>
      <div><dt>Hóspedes</dt><dd>{{ booking.guests }}</dd></div>
    </dl>

    <p class="accs">
      <span v-for="acc in booking.accommodations" :key="acc.id" class="chip">{{ acc.name }}</span>
      <strong class="total">{{ formatMoney(booking.total) }}</strong>
    </p>
    <p v-if="booking.notes" class="notes">“{{ booking.notes }}”</p>

    <footer>
      <a :href="whatsapp" target="_blank" rel="noopener" class="btn btn-ghost small">WhatsApp {{ booking.guest_phone }}</a>
      <template v-if="booking.status === 'pending'">
        <template v-if="askingDecline">
          <span class="ask">Recusar este pedido?</span>
          <button type="button" class="btn small danger" :disabled="busy" @click="emit('decline')">Sim, recusar</button>
          <button type="button" class="btn btn-ghost small" @click="askingDecline = false">Voltar</button>
        </template>
        <template v-else>
          <button type="button" class="btn btn-ghost small" :disabled="busy" @click="askingDecline = true">Recusar</button>
          <button type="button" class="btn small" :disabled="busy" @click="emit('confirm')">Confirmar reserva</button>
        </template>
      </template>
      <template v-else-if="booking.status === 'confirmed'">
        <button v-if="!askingDecline" type="button" class="btn btn-ghost small" @click="askingDecline = true">Cancelar reserva</button>
        <template v-else>
          <span class="ask">Cancelar e liberar as datas?</span>
          <button type="button" class="btn small danger" :disabled="busy" @click="emit('decline')">Sim, cancelar</button>
          <button type="button" class="btn btn-ghost small" @click="askingDecline = false">Voltar</button>
        </template>
      </template>
    </footer>
  </article>
</template>

<style scoped>
.item { background: var(--card); border: 1px solid var(--line); border-left: 4px solid var(--line); border-radius: var(--radius); padding: 1rem 1.1rem; display: grid; gap: .8rem; min-width: 0; }
.item.pending { border-left-color: #d99a1e; }
.item.confirmed { border-left-color: var(--brand); }
header { display: flex; justify-content: space-between; align-items: start; gap: 1rem; }
h3 { margin: 0; font-size: 1.15rem; }
.code { margin: .1rem 0 0; color: var(--muted); font-size: .82rem; font-variant-numeric: tabular-nums; }
.status { font-size: .78rem; font-weight: 700; padding: .25rem .6rem; border-radius: 999px; background: var(--soft); white-space: nowrap; }
.pending .status { background: #fbefd3; color: #7a5208; }
.confirmed .status { background: color-mix(in srgb, var(--brand) 14%, white); color: var(--brand); }
dl { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: .5rem; margin: 0; }
@media (max-width: 520px) { dl { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
dt { font-size: .72rem; text-transform: uppercase; letter-spacing: .08em; color: var(--muted); font-weight: 700; }
dd { margin: 0; font-weight: 700; font-variant-numeric: tabular-nums; }
.accs { display: flex; flex-wrap: wrap; align-items: center; gap: .4rem; margin: 0; }
.chip { background: var(--soft); border-radius: 999px; padding: .2rem .65rem; font-size: .85rem; font-weight: 500; }
.total { margin-left: auto; font-family: var(--font-display); font-size: 1.15rem; font-variant-numeric: tabular-nums; }
.notes { margin: 0; color: var(--muted); font-style: italic; }
footer { display: flex; flex-wrap: wrap; justify-content: flex-end; align-items: center; gap: .5rem; border-top: 1px solid var(--line); padding-top: .8rem; }
footer > a { margin-right: auto; }
.small { padding: .5rem 1rem; font-size: .88rem; }
.danger { background: var(--danger); }
.ask { font-weight: 700; font-size: .9rem; }
</style>
