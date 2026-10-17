<script setup lang="ts">
import type { Property, Quote } from '~/composables/useApi'

const props = defineProps<{ property: Property }>()
const selected = defineModel<number[]>('selected', { required: true })

const api = useApi()
const tracking = useTracking()

const checkIn = ref('')
const checkOut = ref('')
const guests = ref(2)
const form = reactive({ guest_name: '', guest_phone: '', guest_email: '', notes: '' })

// Noites indisponíveis por acomodação (próximos 12 meses).
const unavailableById = ref<Record<number, string[]>>({})
onMounted(async () => {
  const from = new Date()
  const to = new Date(from.getFullYear() + 1, from.getMonth(), from.getDate())
  const iso = (d: Date) => `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`
  const res = await api<{ accommodations: { id: number, unavailable: string[] }[] }>('/availability', {
    query: { from: iso(from), to: iso(to) },
  })
  unavailableById.value = Object.fromEntries(res.accommodations.map(a => [a.id, a.unavailable]))
})

// Ao reservar mais de uma acomodação, o dia precisa estar livre em todas.
const unavailable = computed(() => [...new Set(selected.value.flatMap(id => unavailableById.value[id] ?? []))])

function toggle(id: number) {
  selected.value = selected.value.includes(id) ? selected.value.filter(x => x !== id) : [...selected.value, id]
}

const quote = ref<Quote | null>(null)
const errorMessage = ref('')
const sending = ref(false)
const done = ref<{ code: string } | null>(null)

watch([selected, checkIn, checkOut, guests], async () => {
  quote.value = null
  errorMessage.value = ''
  if (!selected.value.length || !checkIn.value || !checkOut.value) return
  try {
    quote.value = await api<Quote>('/quote', {
      method: 'POST',
      body: { accommodation_ids: selected.value, check_in: checkIn.value, check_out: checkOut.value, guests: guests.value },
    })
  }
  catch (e) {
    errorMessage.value = apiError(e)
  }
})

async function submit() {
  if (!quote.value) return
  sending.value = true
  errorMessage.value = ''
  try {
    const res = await api<{ data: { code: string, total: number } }>('/booking-requests', {
      method: 'POST',
      body: {
        accommodation_ids: selected.value,
        check_in: checkIn.value,
        check_out: checkOut.value,
        guests: guests.value,
        ...form,
        guest_email: form.guest_email || null,
      },
    })
    tracking.bookingRequested(res.data.total)
    done.value = { code: res.data.code }
  }
  catch (e) {
    errorMessage.value = apiError(e)
  }
  finally {
    sending.value = false
  }
}

const formatDate = (iso: string) => iso.split('-').reverse().join('/')
const whatsappLink = computed(() => {
  const number = props.property.whatsapp
  if (!number) return null
  const text = done.value ? `Olá! Fiz o pedido de reserva ${done.value.code} pelo site.` : 'Olá! Gostaria de informações sobre hospedagem.'
  return `https://wa.me/${number}?text=${encodeURIComponent(text)}`
})
</script>

<template>
  <section id="reservar" class="booking-section">
    <div class="container">
      <div class="head">
        <p v-reveal class="eyebrow">Reserva</p>
        <h2 v-reveal style="--d: .1s">Escolha suas datas</h2>
        <p v-reveal class="hint" style="--d: .18s">Nada é cobrado agora. A pousada confirma o pedido e combina o pagamento com você.</p>
      </div>

      <Transition name="swap" mode="out-in">
        <div v-if="done" key="done" class="panel success">
          <p class="eyebrow">Pedido enviado</p>
          <h3>Obrigado, {{ form.guest_name.split(' ')[0] }}!</h3>
          <p>
            Seu código é <strong>{{ done.code }}</strong>. A pousada vai confirmar a disponibilidade
            e falar com você pelo WhatsApp.
          </p>
          <a v-if="whatsappLink" :href="whatsappLink" class="btn" target="_blank" rel="noopener">Falar no WhatsApp</a>
        </div>

        <div v-else key="form" v-reveal class="grid" style="--d: .2s">
          <div class="panel">
            <div class="choose" role="group" aria-label="Acomodações">
              <button
                v-for="acc in property.accommodations"
                :key="acc.id"
                type="button"
                class="chip"
                :aria-pressed="selected.includes(acc.id)"
                @click="toggle(acc.id)"
              >
                <span class="tick" aria-hidden="true" />{{ acc.name }}
              </button>
            </div>
            <p v-if="!selected.length" class="notice">Escolha pelo menos uma acomodação.</p>
            <AvailabilityCalendar v-model:check-in="checkIn" v-model:check-out="checkOut" :unavailable="unavailable" />
            <p class="hint step">
              <template v-if="checkIn && checkOut">Entrada <strong>{{ formatDate(checkIn) }}</strong> · Saída <strong>{{ formatDate(checkOut) }}</strong></template>
              <template v-else-if="checkIn">Agora toque no dia da saída.</template>
              <template v-else>Toque no dia da entrada.</template>
              · Check-in {{ property.check_in_time }} · Check-out {{ property.check_out_time }}
            </p>
          </div>

          <form class="panel form" @submit.prevent="submit">
            <h3>Seus dados</h3>
            <label for="guests">Hóspedes <input id="guests" v-model.number="guests" type="number" min="1" required></label>
            <label for="name">Nome <input id="name" v-model="form.guest_name" required autocomplete="name"></label>
            <label for="phone">WhatsApp <input id="phone" v-model="form.guest_phone" required type="tel" autocomplete="tel" placeholder="(11) 99999-9999"></label>
            <label for="email"><span>E-mail <span class="opt">opcional</span></span> <input id="email" v-model="form.guest_email" type="email" autocomplete="email"></label>
            <label for="notes"><span>Observações <span class="opt">opcional</span></span> <textarea id="notes" v-model="form.notes" rows="2" /></label>

            <Transition name="swap">
              <div v-if="quote" class="quote">
                <p v-for="item in quote.items" :key="item.accommodation_id" class="line">
                  <span>{{ item.name }} · {{ quote.nights }} diária(s)</span><span>{{ formatMoney(item.subtotal) }}</span>
                </p>
                <p class="line total"><span>Total</span><span>{{ formatMoney(quote.total) }}</span></p>
              </div>
            </Transition>
            <p v-if="errorMessage" class="error">{{ errorMessage }}</p>

            <button class="btn" :disabled="!quote || sending">
              {{ sending ? 'Enviando...' : 'Enviar pedido de reserva' }}
            </button>
          </form>
        </div>
      </Transition>
    </div>
  </section>
</template>

<style scoped>
.booking-section { background: var(--soft); padding-block: clamp(5rem, 10vw, 8rem); }
.head { margin-bottom: 2.5rem; }
h2 { font-size: clamp(2.8rem, 7vw, 5.5rem); margin: .4rem 0 .8rem; }
.hint { color: var(--muted); font-size: .95rem; margin: 0; }
.grid { display: grid; gap: 1.2rem; grid-template-columns: minmax(0, 1.45fr) minmax(0, 1fr); align-items: start; }
@media (max-width: 860px) { .grid { grid-template-columns: minmax(0, 1fr); } }
.panel { background: var(--card); border-radius: var(--radius); padding: clamp(1.1rem, 2.5vw, 1.6rem); min-width: 0; box-shadow: 0 1px 2px rgb(0 0 0 / .04), 0 18px 40px -28px rgb(20 40 30 / .35); }
.choose { display: flex; flex-wrap: wrap; gap: .5rem; margin-bottom: 1rem; }
.chip { display: inline-flex; align-items: center; gap: .5rem; font: inherit; font-weight: 700; border: 1.5px solid var(--line); background: none; color: var(--text); border-radius: 999px; padding: .5rem 1rem; cursor: pointer; transition: border-color .2s, background-color .2s, color .2s; }
.chip .tick { width: 14px; height: 14px; border-radius: 50%; border: 1.5px solid currentColor; transition: background-color .2s, box-shadow .2s; }
.chip[aria-pressed="true"] { border-color: var(--brand); background: var(--brand); color: var(--brand-ink); }
.chip[aria-pressed="true"] .tick { background: currentColor; box-shadow: inset 0 0 0 2.5px var(--brand); }
.notice { background: var(--soft); border-radius: 10px; padding: .7rem .9rem; margin: 0 0 1rem; font-weight: 500; }
.step { margin: 1rem 0 0; }
.form { display: grid; gap: .85rem; position: sticky; top: 76px; }
.form h3 { margin: 0; font-size: 1.9rem; }
.form label { display: grid; gap: .3rem; font-weight: 700; font-size: .88rem; }
.opt { font-weight: 400; color: var(--muted); }
.quote { background: var(--soft); border-radius: 10px; padding: .9rem 1rem; display: grid; gap: .35rem; }
.line { display: flex; justify-content: space-between; gap: 1rem; margin: 0; font-variant-numeric: tabular-nums; }
.line.total { font-family: var(--font-display); font-size: 1.7rem; border-top: 1px solid var(--line); padding-top: .5rem; margin-top: .2rem; }
.error { color: var(--danger); font-weight: 700; margin: 0; }
.success { max-width: 640px; }
.success h3 { font-size: clamp(2.2rem, 5vw, 3.2rem); margin: .4rem 0 .8rem; }
.swap-enter-active, .swap-leave-active { transition: opacity .45s var(--ease), transform .45s var(--ease); }
.swap-enter-from, .swap-leave-to { opacity: 0; transform: translateY(10px); }
</style>
