<script setup lang="ts">
import type { Property, Quote } from '~/composables/useApi'

const api = useApi()
const tracking = useTracking()

const { data: property, error } = await useAsyncData('property', () =>
  api<{ data: Property }>('/').then(res => res.data),
)

if (error.value || !property.value) {
  throw createError({ statusCode: 404, statusMessage: 'Pousada não encontrada', fatal: true })
}

useSeoMeta({
  title: property.value.name,
  description: property.value.description ?? undefined,
  ogTitle: property.value.name,
  ogDescription: property.value.description ?? undefined,
  ogImage: property.value.accommodations[0]?.photos[0]?.url,
})

const selected = ref<number[]>(property.value.accommodations.slice(0, 1).map(a => a.id))
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

function toggle(id: number, on: boolean) {
  selected.value = on ? [...selected.value, id] : selected.value.filter(x => x !== id)
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
  const number = property.value?.whatsapp
  if (!number) return null
  const text = done.value
    ? `Olá! Fiz o pedido de reserva ${done.value.code} pelo site.`
    : 'Olá! Gostaria de informações sobre hospedagem.'
  return `https://wa.me/${number}?text=${encodeURIComponent(text)}`
})
</script>

<template>
  <div v-if="property" :style="{ '--brand': property.primary_color }">
    <header class="hero">
      <div class="container">
        <img v-if="property.logo_url" :src="property.logo_url" :alt="property.name" class="logo">
        <h1>{{ property.name }}</h1>
        <p>{{ property.description }}</p>
        <a href="#reservar" class="btn">Ver datas e reservar</a>
      </div>
    </header>

    <main class="container">
      <section>
        <h2>Acomodações</h2>
        <div class="cards">
          <AccommodationCard
            v-for="accommodation in property.accommodations"
            :key="accommodation.id"
            :accommodation="accommodation"
            :selected="selected.includes(accommodation.id)"
            @update:selected="toggle(accommodation.id, $event)"
          />
        </div>
      </section>

      <section id="reservar" class="booking">
        <h2>Reservar</h2>

        <div v-if="done" class="success">
          <h3>Pedido enviado!</h3>
          <p>
            Seu código é <strong>{{ done.code }}</strong>. A pousada vai confirmar a disponibilidade
            e entrar em contato com você.
          </p>
          <a v-if="whatsappLink" :href="whatsappLink" class="btn" target="_blank" rel="noopener">Falar no WhatsApp</a>
        </div>

        <template v-else>
          <p v-if="!selected.length" class="hint">Escolha uma acomodação acima.</p>
          <AvailabilityCalendar v-model:check-in="checkIn" v-model:check-out="checkOut" :unavailable="unavailable" />

          <p class="hint">
            <template v-if="checkIn && checkOut">Entrada {{ formatDate(checkIn) }} · Saída {{ formatDate(checkOut) }}</template>
            <template v-else-if="checkIn">Agora escolha o dia da saída.</template>
            <template v-else>Escolha o dia da entrada.</template>
            · Check-in {{ property.check_in_time }} · Check-out {{ property.check_out_time }}
          </p>

          <form class="form" @submit.prevent="submit">
            <label>Hóspedes <input v-model.number="guests" type="number" min="1" required></label>
            <label>Nome <input v-model="form.guest_name" required autocomplete="name"></label>
            <label>WhatsApp <input v-model="form.guest_phone" required type="tel" autocomplete="tel"></label>
            <label>E-mail (opcional) <input v-model="form.guest_email" type="email" autocomplete="email"></label>
            <label class="full">Observações <textarea v-model="form.notes" rows="3" /></label>

            <div v-if="quote" class="quote full">
              <p v-for="item in quote.items" :key="item.accommodation_id">
                {{ item.name }}: {{ formatMoney(item.subtotal) }}
              </p>
              <p class="total">{{ quote.nights }} diária(s) · Total {{ formatMoney(quote.total) }}</p>
              <p class="hint">Sem pagamento agora. A pousada confirma o pedido e combina o pagamento com você.</p>
            </div>
            <p v-if="errorMessage" class="error full">{{ errorMessage }}</p>

            <button class="btn full" :disabled="!quote || sending">
              {{ sending ? 'Enviando...' : 'Enviar pedido de reserva' }}
            </button>
          </form>
        </template>
      </section>
    </main>

    <footer class="container footer">
      <p>{{ property.name }}<template v-if="property.city"> · {{ property.city }}/{{ property.state }}</template></p>
      <a v-if="whatsappLink && !done" :href="whatsappLink" target="_blank" rel="noopener">WhatsApp</a>
    </footer>
  </div>
</template>

<style scoped>
.hero { background: var(--brand); color: #fff; padding: 3rem 0; }
.hero .btn { background: #fff; color: var(--brand); }
.logo { height: 64px; }
h1 { margin: .5rem 0; font-size: 2rem; }
section { margin: 2.5rem 0; }
.cards { display: grid; gap: 1rem; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); }
.booking { background: #fff; border: 1px solid var(--line); border-radius: 12px; padding: 1.25rem; }
.form { display: grid; gap: 1rem; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); margin-top: 1rem; }
.form label { display: grid; gap: .25rem; font-weight: 600; font-size: .9rem; }
.full { grid-column: 1 / -1; }
.quote { background: var(--soft); border-radius: 8px; padding: .75rem 1rem; }
.quote p { margin: .2rem 0; }
.total { font-weight: 700; font-size: 1.1rem; }
.hint { color: var(--muted); font-size: .9rem; }
.error { color: #b42318; font-weight: 600; }
.success { background: var(--soft); border-radius: 8px; padding: 1rem; }
.footer { display: flex; justify-content: space-between; color: var(--muted); padding-bottom: 2rem; }
</style>
