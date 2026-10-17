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
const heroPhoto = computed(() => property.value?.accommodations[0]?.photos[0]?.url)
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
    <header class="hero" :style="heroPhoto ? { backgroundImage: `url(&quot;${heroPhoto}&quot;)` } : undefined">
      <div class="shade" />
      <div class="container hero-inner">
        <img v-if="property.logo_url" :src="property.logo_url" :alt="property.name" class="logo">
        <p class="eyebrow light">{{ property.city }}<template v-if="property.state"> · {{ property.state }}</template></p>
        <h1>{{ property.name }}</h1>
        <p class="lead">{{ property.description }}</p>
        <div class="hero-actions">
          <a href="#reservar" class="btn">Ver datas e reservar</a>
          <a v-if="whatsappLink" :href="whatsappLink" class="btn btn-light" target="_blank" rel="noopener">WhatsApp</a>
        </div>
      </div>
    </header>

    <section class="facts container" aria-label="Informações">
      <div><span class="eyebrow">Check-in</span><strong>a partir das {{ property.check_in_time }}</strong></div>
      <div><span class="eyebrow">Check-out</span><strong>até {{ property.check_out_time }}</strong></div>
      <div><span class="eyebrow">Reserva direta</span><strong>sem taxa de aplicativo</strong></div>
    </section>

    <main class="container">
      <section class="block">
        <p class="eyebrow">Onde você vai ficar</p>
        <h2>Acomodações</h2>
        <p class="hint">Selecione uma ou mais para ver as datas livres.</p>
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

      <section id="reservar" class="block">
        <p class="eyebrow">Reserva</p>
        <h2>Escolha suas datas</h2>

        <div v-if="done" class="panel success">
          <h3>Pedido enviado!</h3>
          <p>
            Seu código é <strong>{{ done.code }}</strong>. A pousada vai confirmar a disponibilidade
            e entrar em contato com você pelo WhatsApp.
          </p>
          <a v-if="whatsappLink" :href="whatsappLink" class="btn" target="_blank" rel="noopener">Falar no WhatsApp</a>
        </div>

        <div v-else class="booking">
          <div class="panel">
            <p v-if="!selected.length" class="notice">Selecione uma acomodação acima para começar.</p>
            <AvailabilityCalendar v-model:check-in="checkIn" v-model:check-out="checkOut" :unavailable="unavailable" />
            <p class="hint step">
              <template v-if="checkIn && checkOut">Entrada <strong>{{ formatDate(checkIn) }}</strong> · Saída <strong>{{ formatDate(checkOut) }}</strong></template>
              <template v-else-if="checkIn">Agora toque no dia da saída.</template>
              <template v-else>Toque no dia da entrada.</template>
            </p>
          </div>

          <form class="panel form" @submit.prevent="submit">
            <h3>Seus dados</h3>
            <label for="guests">Hóspedes <input id="guests" v-model.number="guests" type="number" min="1" required></label>
            <label for="name">Nome <input id="name" v-model="form.guest_name" required autocomplete="name"></label>
            <label for="phone">WhatsApp <input id="phone" v-model="form.guest_phone" required type="tel" autocomplete="tel" placeholder="(11) 99999-9999"></label>
            <label for="email"><span>E-mail <span class="opt">opcional</span></span> <input id="email" v-model="form.guest_email" type="email" autocomplete="email"></label>
            <label for="notes"><span>Observações <span class="opt">opcional</span></span> <textarea id="notes" v-model="form.notes" rows="2" /></label>

            <div v-if="quote" class="quote">
              <p v-for="item in quote.items" :key="item.accommodation_id" class="line">
                <span>{{ item.name }} · {{ quote.nights }} diária(s)</span><span>{{ formatMoney(item.subtotal) }}</span>
              </p>
              <p class="line total"><span>Total</span><span>{{ formatMoney(quote.total) }}</span></p>
              <p class="hint">Nada é cobrado agora. A pousada confirma o pedido e combina o pagamento com você.</p>
            </div>
            <p v-if="errorMessage" class="error">{{ errorMessage }}</p>

            <button class="btn" :disabled="!quote || sending">
              {{ sending ? 'Enviando...' : 'Enviar pedido de reserva' }}
            </button>
          </form>
        </div>
      </section>
    </main>

    <footer class="footer">
      <div class="container footer-inner">
        <p><strong>{{ property.name }}</strong><template v-if="property.city"> · {{ property.city }}/{{ property.state }}</template></p>
        <p v-if="property.email">{{ property.email }}</p>
      </div>
    </footer>
  </div>
</template>

<style scoped>
.hero { position: relative; background: var(--brand) center / cover no-repeat; color: #fff; }
.shade { position: absolute; inset: 0; background: linear-gradient(180deg, rgb(20 28 22 / .25), rgb(20 28 22 / .7)); }
.hero-inner { position: relative; padding-block: 5.5rem 3.5rem; max-width: 1080px; }
.eyebrow.light { color: #ffffffcc; }
.logo { height: 64px; }
h1 { margin: .3rem 0 .6rem; font-size: clamp(2.3rem, 6vw, 3.8rem); }
.lead { max-width: 36rem; font-size: 1.1rem; margin: 0 0 1.6rem; color: #ffffffe6; }
.hero-actions { display: flex; gap: .7rem; flex-wrap: wrap; }
.hero .btn { background: #fff; color: var(--brand); }
.hero .btn-light { background: transparent; color: #fff; border: 1.5px solid #ffffffb3; }
.facts { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 1px; background: var(--line); border: 1px solid var(--line); border-radius: var(--radius); overflow: hidden; margin-top: -1.8rem; position: relative; padding: 0; max-width: calc(1080px - 32px); width: calc(100% - 32px); }
.facts div { background: var(--card); padding: 1rem 1.2rem; display: grid; gap: .15rem; }
@media (max-width: 640px) { .facts { grid-template-columns: minmax(0, 1fr); } .facts div { grid-template-columns: 7rem 1fr; align-items: baseline; padding-block: .75rem; } }
.block { margin: 3.5rem 0; }
h2 { font-size: clamp(1.7rem, 4vw, 2.3rem); margin: .2rem 0 .4rem; }
.hint { color: var(--muted); font-size: .92rem; margin: 0 0 1.2rem; }
.cards { display: grid; gap: 1.2rem; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); }
.booking { display: grid; gap: 1.2rem; grid-template-columns: minmax(0, 1.4fr) minmax(0, 1fr); align-items: start; }
@media (max-width: 820px) { .booking { grid-template-columns: minmax(0, 1fr); } }
.panel { background: var(--card); border: 1px solid var(--line); border-radius: var(--radius); padding: 1.2rem; min-width: 0; }
.notice { background: var(--soft); border-radius: 10px; padding: .7rem .9rem; margin: 0 0 1rem; font-weight: 500; }
.step { margin: 1rem 0 0; }
.form { display: grid; gap: .85rem; position: sticky; top: 1rem; }
.form h3 { margin: 0; font-size: 1.25rem; }
.form label { display: grid; gap: .3rem; font-weight: 700; font-size: .88rem; }
.opt { font-weight: 400; color: var(--muted); }
.quote { background: var(--soft); border-radius: 10px; padding: .9rem 1rem; display: grid; gap: .35rem; }
.line { display: flex; justify-content: space-between; gap: 1rem; margin: 0; font-variant-numeric: tabular-nums; }
.line.total { font-family: var(--font-display); font-size: 1.25rem; font-weight: 700; border-top: 1px solid var(--line); padding-top: .5rem; margin-top: .2rem; }
.quote .hint { margin: .3rem 0 0; font-size: .85rem; }
.error { color: var(--danger); font-weight: 700; margin: 0; }
.success { background: var(--soft); }
.success h3 { margin-top: 0; font-size: 1.5rem; }
.footer { border-top: 1px solid var(--line); color: var(--muted); }
.footer-inner { display: flex; justify-content: space-between; flex-wrap: wrap; gap: .5rem 1.5rem; padding-block: 1.5rem 2.5rem; }
.footer p { margin: 0; }
</style>
