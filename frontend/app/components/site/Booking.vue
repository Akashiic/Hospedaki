<script setup lang="ts">
import type { Property, Quote } from '~/composables/useApi'
import { accommodationFor } from '~/composables/usePersona'

const props = defineProps<{ property: Property }>()

// Começa com a acomodação vinda do link (?acomodacao=chale-master), depois a da escolha
// "a dois / com a família" feita na capa, e por fim a primeira.
const route = useRoute()
// Sem nenhuma acomodação ativa (reforma, por exemplo), a página mostra um aviso em vez do formulário.
const first = props.property.accommodations.find(a => a.slug === route.query.acomodacao)
  ?? accommodationFor(props.property, usePersona().value)
  ?? props.property.accommodations[0]
const selected = ref<number[]>(first ? [first.id] : [])

const api = useApi()
const tracking = useTracking()

// Datas também podem vir do link (?entrada=2026-10-23&saida=2026-10-25), como no aviso de fim de semana livre da capa.
const isoDate = (v: unknown) => (typeof v === 'string' && /^\d{4}-\d{2}-\d{2}$/.test(v) ? v : '')
const checkIn = ref(isoDate(route.query.entrada))
const checkOut = ref(isoDate(route.query.saida))
const guests = ref(2)
const form = reactive({ guest_name: '', guest_phone: '', guest_email: '', notes: '' })

// Janela de disponibilidade: de hoje (no fuso da pousada, o mesmo "hoje" do calendário) até
// 12 meses depois. O calendário não passa do fim dela, porque além dela tudo pareceria livre.
const today = useState('calendar-today', () => todayIn(props.property.timezone))
const windowEnd = computed(() => addMonthsIso(today.value, 12))

// Noites indisponíveis por acomodação dentro da janela.
const unavailableById = ref<Record<number, string[]>>({})
onMounted(async () => {
  try {
    const res = await api<{ accommodations: { id: number, unavailable: string[] }[] }>('/availability', {
      query: { from: today.value, to: windowEnd.value },
    })
    unavailableById.value = Object.fromEntries(res.accommodations.map(a => [a.id, a.unavailable]))
  }
  catch {
    // Sem a disponibilidade o calendário fica todo livre; o orçamento ainda confere as datas no servidor.
  }
})

// Ao reservar mais de uma acomodação, o dia precisa estar livre em todas.
const unavailable = computed(() => [...new Set(selected.value.flatMap(id => unavailableById.value[id] ?? []))])

// Reservada por hora (day use, eventos): vai sozinha, num dia só, com horário de início e de fim.
const accById = (id: number) => props.property.accommodations.find(a => a.id === id)
const hourlyAcc = computed(() => selected.value.map(accById).find(a => a?.booking_mode === 'hourly') ?? null)
const startTime = ref('')
const endTime = ref('')
// Estadia: horário previsto de chegada (opcional), a partir do check-in.
const arrivalTime = ref('')
const stayTimes = computed(() => {
  const acc = accById(selected.value[0] ?? 0)
  return { checkIn: acc?.check_in_time ?? props.property.check_in_time, checkOut: acc?.check_out_time ?? props.property.check_out_time }
})
const arrivals = computed(() => arrivalOptions(stayTimes.value.checkIn))
watch(hourlyAcc, (now, before) => {
  // Trocar entre estadia e reserva por hora recomeça a escolha das datas.
  if ((now?.id ?? null) !== (before?.id ?? null)) {
    checkOut.value = ''
    startTime.value = ''
    endTime.value = ''
  }
})

function toggle(id: number) {
  if (selected.value.includes(id)) {
    selected.value = selected.value.filter(x => x !== id)
    return
  }
  // Por hora não combina com outra acomodação: a escolhida toma o lugar das outras.
  if (accById(id)?.booking_mode === 'hourly' || hourlyAcc.value) {
    selected.value = [id]
    return
  }
  // Acomodações do mesmo imóvel (o Espaço inclui o apartamento) não vão juntas: a nova toma o lugar da outra.
  const unit = props.property.accommodations.find(a => a.id === id)?.shared_unit
  const others = unit ? selected.value.filter(x => props.property.accommodations.find(a => a.id === x)?.shared_unit !== unit) : selected.value
  selected.value = [...others, id]
}

// O orçamento guarda quantos hóspedes ele considerou, para o "Total para N pessoas".
const quote = ref<(Quote & { guests: number }) | null>(null)
const errorMessage = ref('')
const sending = ref(false)
const done = ref<{ code: string, extraUnpriced: number } | null>(null)

// Orçamento: espera o hóspede parar de mexer (300ms, digitar "10" em hóspedes não pede o "1")
// e descarta a resposta de qualquer pedido que já ficou velho, para o valor mostrado ser sempre
// o da seleção atual (que é a que vai no pedido).
watch([selected, checkIn, checkOut, guests, startTime, endTime], (_, __, onCleanup) => {
  if (import.meta.server) return
  quote.value = null
  errorMessage.value = ''
  if (!selected.value.length || !checkIn.value) return
  if (hourlyAcc.value ? !startTime.value || !endTime.value : !checkOut.value) return
  const controller = new AbortController()
  const asked = guests.value
  const timer = setTimeout(async () => {
    try {
      const result = await api<Quote>('/quote', {
        method: 'POST',
        body: { accommodation_ids: selected.value, check_in: checkIn.value, guests: asked, ...when.value },
        signal: controller.signal,
      })
      if (!controller.signal.aborted) quote.value = { ...result, guests: asked }
    }
    catch (e) {
      if (!controller.signal.aborted) errorMessage.value = apiError(e)
    }
  }, 300)
  onCleanup(() => {
    clearTimeout(timer)
    controller.abort()
  })
}, { immediate: true })

// Datas e horários que vão no orçamento e no pedido.
const when = computed(() => hourlyAcc.value
  ? { start_time: startTime.value, end_time: endTime.value }
  : { check_out: checkOut.value, arrival_time: arrivalTime.value || null })

async function submit() {
  if (!quote.value) return
  sending.value = true
  errorMessage.value = ''
  try {
    const res = await api<{ data: { code: string, total: number, extra_guests_unpriced?: number } }>('/booking-requests', {
      method: 'POST',
      body: {
        accommodation_ids: selected.value,
        check_in: checkIn.value,
        ...when.value,
        guests: guests.value,
        ...form,
        guest_email: form.guest_email || null,
      },
    })
    tracking.bookingRequested(res.data.total)
    done.value = { code: res.data.code, extraUnpriced: res.data.extra_guests_unpriced ?? quote.value?.extra_guests_unpriced ?? 0 }
  }
  catch (e) {
    errorMessage.value = apiError(e)
  }
  finally {
    sending.value = false
  }
}

// Pessoas cobertas pelo total quando parte do acréscimo ainda não tem valor.
const coveredGuests = computed(() => quote.value ? quote.value.guests - quote.value.extra_guests_unpriced : 0)

const formatDate = (iso: string) => iso.split('-').reverse().join('/')
const whatsappLink = computed(() => {
  const number = props.property.whatsapp
  if (!number) return null
  const text = done.value ? `Olá! Fiz o pedido de reserva ${done.value.code} pelo site.` : 'Olá! Gostaria de informações sobre hospedagem.'
  return `https://wa.me/${number}?text=${encodeURIComponent(text)}`
})
</script>

<template>
  <section class="booking-section">
    <div class="container">

      <Transition name="swap" mode="out-in">
        <div v-if="done" key="done" class="panel success">
          <p class="eyebrow">Pedido enviado</p>
          <h2>Obrigado, {{ form.guest_name.trim().split(/\s+/)[0] }}!</h2>
          <p>
            Seu código é <strong>{{ done.code }}</strong>. A pousada vai confirmar
            {{ hourlyAcc ? 'o horário' : 'a disponibilidade' }} e falar com você pelo WhatsApp.
          </p>
          <p v-if="done.extraUnpriced > 0">{{ guestCopy.sentNote }}</p>
          <a v-if="whatsappLink" :href="whatsappLink" class="btn" target="_blank" rel="noopener">Falar no WhatsApp</a>
        </div>

        <div v-else-if="!property.accommodations.length" key="empty" class="panel empty">
          <h2>Reservas pelo site pausadas</h2>
          <p>No momento não há acomodações disponíveis para reservar por aqui. Fale com a pousada para saber das próximas datas.</p>
          <a v-if="whatsappLink" :href="whatsappLink" class="btn" target="_blank" rel="noopener">Falar no WhatsApp</a>
        </div>

        <div v-else key="form" v-reveal class="grid" style="--i: 3">
          <div class="panel dates">
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
            <AvailabilityCalendar
              v-model:check-in="checkIn"
              v-model:check-out="checkOut"
              :unavailable="unavailable"
              :timezone="property.timezone"
              :max-date="windowEnd"
              :single="!!hourlyAcc"
            />
            <p v-if="hourlyAcc" class="hint step">
              <template v-if="checkIn">Dia <strong>{{ formatDate(checkIn) }}</strong><template v-if="startTime && endTime">, das <strong>{{ formatHour(startTime) }}</strong> às <strong>{{ formatHour(endTime) }}</strong></template></template>
              <template v-else>Toque no dia do seu evento ou day use.</template>
            </p>
            <p v-else class="hint step">
              <template v-if="checkIn && checkOut">Entrada <strong>{{ formatDate(checkIn) }}</strong> · Saída <strong>{{ formatDate(checkOut) }}</strong></template>
              <template v-else-if="checkIn">Agora toque no dia da saída.</template>
              <template v-else>Toque no dia da entrada.</template>
              · Check-in {{ formatHour(stayTimes.checkIn) }} · Check-out {{ formatHour(stayTimes.checkOut) }}
            </p>
            <SiteHourPicker v-if="hourlyAcc && checkIn" v-model:start="startTime" v-model:end="endTime" :accommodation="hourlyAcc" :date="checkIn" />
          </div>

          <form class="panel form" @submit.prevent="submit">
            <h2>Seus dados</h2>
            <p v-if="property.demo" class="demo-note">Demonstração: use um nome de teste. O pedido aparece no painel aberto da demo, que qualquer visitante vê, e é apagado no dia seguinte.</p>
            <label for="guests">{{ hourlyAcc ? 'Pessoas' : 'Hóspedes' }} <input id="guests" v-model.number="guests" type="number" min="1" required></label>
            <label for="name">Nome <input id="name" v-model="form.guest_name" required autocomplete="name"></label>
            <label for="phone">WhatsApp <input id="phone" v-model="form.guest_phone" required type="tel" autocomplete="tel" placeholder="(11) 99999-9999"></label>
            <label for="email"><span>E-mail <span class="opt">opcional</span></span> <input id="email" v-model="form.guest_email" type="email" autocomplete="email"></label>
            <label v-if="!hourlyAcc" for="arrival"><span>Chegada prevista <span class="opt">opcional</span></span>
              <select id="arrival" v-model="arrivalTime">
                <option value="">Ainda não sei</option>
                <option v-for="t in arrivals" :key="t" :value="t">Por volta das {{ formatHour(t) }}</option>
              </select>
            </label>
            <label for="notes"><span>Observações <span class="opt">opcional</span></span> <textarea id="notes" v-model="form.notes" rows="2" /></label>

            <!-- Orçamento e erros são anunciados por leitores de tela quando chegam. -->
            <div aria-live="polite">
              <Transition name="swap">
                <div v-if="quote" class="quote">
                  <template v-for="item in quote.items" :key="item.accommodation_id">
                    <p v-if="item.hours" class="line">
                      <span>{{ item.name }} · {{ plural(item.hours, 'hora', 'horas') }}<template v-if="item.hourly_price"> × {{ formatMoney(item.hourly_price) }}</template></span>
                      <span>{{ formatMoney(item.nights_total) }}</span>
                    </p>
                    <p v-else class="line">
                      <span>{{ item.name }} · {{ plural(quote.nights, 'diária', 'diárias') }}</span>
                      <span>{{ formatMoney(item.nights_total - item.extra_guests_subtotal) }}</span>
                    </p>
                    <p v-if="item.extra_guests_subtotal > 0" class="line">
                      <span>{{ guestCopy.quoteExtraLine(item.extra_guests, quote.nights) }}</span>
                      <span>{{ formatMoney(item.extra_guests_subtotal) }}</span>
                    </p>
                    <p v-if="item.discount > 0" class="line discount">
                      <span>{{ guestCopy.discountLine(item.discount_percent, quote.nights) }}</span>
                      <span>− {{ formatMoney(item.discount) }}</span>
                    </p>
                    <p v-if="item.cleaning_fee > 0" class="line">
                      <span>Taxa de limpeza</span>
                      <span>{{ formatMoney(item.cleaning_fee) }}</span>
                    </p>
                  </template>
                  <p class="line total">
                    <span>{{ quote.extra_guests_unpriced > 0 ? guestCopy.quoteTotal(coveredGuests) : 'Total' }}</span>
                    <span>{{ formatMoney(quote.total) }}</span>
                  </p>
                  <p v-if="quote.extra_guests_unpriced > 0" class="note">{{ guestCopy.quoteNote(quote.extra_guests_unpriced) }}</p>
                </div>
              </Transition>
            </div>
            <p v-if="errorMessage" id="booking-error" class="error" role="alert">{{ errorMessage }}</p>

            <button class="btn" :disabled="!quote || sending" :aria-describedby="errorMessage ? 'booking-error' : undefined">
              {{ sending ? 'Enviando...' : 'Enviar pedido de reserva' }}
            </button>
          </form>
        </div>
      </Transition>
    </div>
  </section>
</template>

<style scoped lang="scss">
.booking-section { background: var(--soft); @include section-pad(clamp(2.5rem, 6vw, 4rem), clamp(5rem, 10vw, 8rem)); }
.hint { color: var(--muted); font-size: .95rem; margin: 0; }
.grid { display: grid; gap: 1.2rem; grid-template-columns: minmax(0, 1.45fr) minmax(0, 1fr); align-items: start; }
@include below(lg) { .grid { grid-template-columns: minmax(0, 1fr); } }
.panel { background: var(--card); border-radius: var(--radius); padding: clamp(1.1rem, 2.5vw, 1.6rem); min-width: 0; box-shadow: 0 1px 2px rgb(0 0 0 / .04), 0 18px 40px -28px rgb(20 40 30 / .35); }
.choose { display: flex; flex-wrap: wrap; gap: .5rem; margin-bottom: 1rem; }
// Marcar e desmarcar troca as cores na hora (regra de movimento: cor não anima).
.chip { display: inline-flex; align-items: center; gap: .5rem; min-height: 44px; font: inherit; font-weight: 700; border: 1.5px solid var(--line); background: none; color: var(--text); border-radius: 999px; padding: .5rem 1rem; cursor: pointer; }
.chip .tick { width: 14px; height: 14px; border-radius: 50%; border: 1.5px solid currentColor; }
.chip[aria-pressed="true"] { border-color: var(--brand); background: var(--brand); color: var(--brand-ink); }
.chip[aria-pressed="true"] .tick { background: currentColor; box-shadow: inset 0 0 0 2.5px var(--brand); }
.notice { background: var(--soft); border-radius: 10px; padding: .7rem .9rem; margin: 0 0 1rem; font-weight: 500; }
.step { margin: 1rem 0 0; }
.form { display: grid; gap: .85rem; position: sticky; top: calc(var(--nav-h) + 1rem); }
.form h2 { margin: 0; font-size: 1.9rem; }
.form label { display: grid; gap: .3rem; font-weight: 700; font-size: .88rem; }
.opt { font-weight: 400; color: var(--muted); }
.discount { color: var(--brand-text); font-weight: 700; }
.quote { background: var(--soft); border-radius: 10px; padding: .9rem 1rem; display: grid; gap: .35rem; }
.line { display: flex; justify-content: space-between; gap: 1rem; margin: 0; font-variant-numeric: tabular-nums; }
.line.total { font-family: var(--font-display); font-size: 1.7rem; line-height: 1.15; align-items: baseline; border-top: 1px solid var(--line); padding-top: .5rem; margin-top: .2rem; }
.note { margin: 0; color: var(--muted); font-size: .88rem; }
.demo-note { margin: 0; padding: .6rem .8rem; border-radius: 10px; background: var(--warn-soft); color: var(--warn-ink); font-size: .88rem; }
.error { color: var(--danger); font-weight: 700; margin: 0; }
.success, .empty { max-width: 640px; }
.empty h2 { font-size: clamp(1.8rem, 4vw, 2.4rem); margin: 0 0 .6rem; }
.empty p { margin: 0 0 1.2rem; color: var(--muted); }
// Celular estreito: o calendário usa quase toda a largura para os dias terem 44px; o resto
// do painel mantém o respiro de antes.
@include below(sm) {
  .dates { padding-inline: .4rem; }
  .dates > :not(.calendar) { margin-inline: .7rem; }
}
.success h2 { font-size: clamp(2.2rem, 5vw, 3.2rem); margin: .4rem 0 .8rem; }
.swap-enter-active { @include motion-enter; }
.swap-leave-active { @include motion-exit; }
.swap-enter-from { opacity: 0; transform: translateY($dist-sm); }
.swap-leave-to { opacity: 0; transform: translateY(-$dist-xs); }
</style>
