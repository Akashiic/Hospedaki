<script setup lang="ts">
import type { AdminBooking, AdminMe, AdminTab } from '~/composables/useAdminApi'
import { ArrowRight, BedDouble, CalendarX2, Inbox, LayoutTemplate, LogIn, LogOut, Moon, Sun, Sunrise, Users, Wallet } from 'lucide-vue-next'

// Tela Início: saudação, os números que importam hoje (contando até o valor), a agenda do dia
// e atalhos. Tudo sai de uma lista só (/bookings?scope=all), filtrada aqui com o "hoje" da pousada.
const props = defineProps<{ me: AdminMe | null, today: string }>()
const emit = defineEmits<{ go: [tab: AdminTab] }>()

const api = useAdminApi()
const bookings = ref<AdminBooking[]>([])
const loading = ref(true)
const error = ref('')

async function load() {
  error.value = ''
  try {
    bookings.value = (await api<{ data: AdminBooking[] }>('/bookings', { query: { scope: 'all' } })).data
  }
  catch (e) {
    error.value = apiError(e)
  }
  finally {
    loading.value = false
  }
}
onMounted(load)
defineExpose({ load })

const hour = new Date().getHours()
const greeting = hour < 12 ? 'Bom dia' : hour < 18 ? 'Boa tarde' : 'Boa noite'
const greetingIcon = hour < 12 ? Sunrise : hour < 18 ? Sun : Moon
const firstName = computed(() => {
  const name = props.me?.user.name ?? ''
  return /demonstra|dono da/i.test(name) ? '' : name.split(' ')[0]
})
// "segunda-feira, 19 de outubro" → só a primeira letra maiúscula.
const longDate = computed(() => {
  const text = formatIso(props.today, { weekday: 'long', day: 'numeric', month: 'long' })
  return text.charAt(0).toUpperCase() + text.slice(1)
})

const phaseOf = (b: AdminBooking) => bookingPhase(b, props.today)
const requests = computed(() => bookings.value.filter(b => phaseOf(b) === 'request'))
const ongoing = computed(() => bookings.value.filter(b => phaseOf(b) === 'ongoing'))
const weekEnd = computed(() => addDaysIso(props.today, 7))
const arriving = computed(() => bookings.value
  .filter(b => b.status === 'confirmed' && b.check_in >= props.today && b.check_in <= weekEnd.value)
  .sort((a, b) => a.check_in.localeCompare(b.check_in)))
const month = computed(() => props.today.slice(0, 7))
const monthRevenue = computed(() => bookings.value
  .filter(b => b.status === 'confirmed' && b.check_in.slice(0, 7) === month.value)
  .reduce((n, b) => n + b.total, 0))
const monthName = computed(() => formatIso(`${month.value}-01`, { month: 'long' }))

const checkIns = computed(() => bookings.value.filter(b => b.status === 'confirmed' && b.check_in === props.today))
const checkOuts = computed(() => bookings.value.filter(b => b.status === 'confirmed' && b.check_out === props.today))
const nextArrivals = computed(() => bookings.value
  .filter(b => phaseOf(b) === 'upcoming')
  .sort((a, b) => a.check_in.localeCompare(b.check_in))
  .slice(0, 4))

const nRequests = useCountUp(() => requests.value.length)
const nGuests = useCountUp(() => ongoing.value.reduce((n, b) => n + b.guests, 0))
const nArriving = useCountUp(() => arriving.value.length)
const nRevenue = useCountUp(() => Math.round(monthRevenue.value / 100))

const shortDate = (iso: string) => formatIso(iso, { day: 'numeric', month: 'short' })
const weekday = (iso: string) => formatIso(iso, { weekday: 'short' })
const initials = (name: string) => initialsOf(name)
const accNames = (b: AdminBooking) => b.accommodations.map(a => a.name).join(' + ')
</script>

<template>
  <div class="overview">
    <header class="hello a-rise">
      <p class="date"><component :is="greetingIcon" :size="18" aria-hidden="true" /> {{ longDate }}</p>
      <h1>{{ greeting }}{{ firstName ? `, ${firstName}` : '' }}!</h1>
      <p class="lead">
        <template v-if="loading">Vendo como está a pousada...</template>
        <template v-else-if="requests.length">Você tem {{ plural(requests.length, 'pedido esperando', 'pedidos esperando') }} resposta.</template>
        <template v-else-if="checkIns.length">Hoje {{ checkIns.length === 1 ? 'chega um hóspede' : `chegam ${checkIns.length} hóspedes` }}. Tudo pronto?</template>
        <template v-else>Tudo em dia por aqui.</template>
      </p>
    </header>

    <div v-if="error" class="a-card error" role="alert">
      <p>Não foi possível carregar o resumo. {{ error }}</p>
      <button type="button" class="btn btn-sm" @click="load">Tentar de novo</button>
    </div>

    <section class="stats" aria-label="Resumo">
      <button type="button" class="stat a-card a-lift a-rise" :class="{ hot: requests.length }" style="--i: 1" @click="emit('go', 'pedidos')">
        <span class="ico"><Inbox :size="20" /></span>
        <span class="label">Pedidos esperando</span>
        <strong v-if="!loading">{{ nRequests }}</strong><span v-else class="a-skeleton num" />
        <span class="go">Responder <ArrowRight :size="14" /></span>
      </button>
      <button type="button" class="stat a-card a-lift a-rise" style="--i: 2" @click="emit('go', 'reservas')">
        <span class="ico"><Users :size="20" /></span>
        <span class="label">Hóspedes agora</span>
        <strong v-if="!loading">{{ nGuests }}</strong><span v-else class="a-skeleton num" />
        <span class="go">{{ ongoing.length ? plural(ongoing.length, 'reserva em andamento', 'reservas em andamento') : 'Ninguém hospedado' }}</span>
      </button>
      <button type="button" class="stat a-card a-lift a-rise" style="--i: 3" @click="emit('go', 'reservas')">
        <span class="ico"><LogIn :size="20" /></span>
        <span class="label">Chegadas em 7 dias</span>
        <strong v-if="!loading">{{ nArriving }}</strong><span v-else class="a-skeleton num" />
        <span class="go">Ver reservas <ArrowRight :size="14" /></span>
      </button>
      <div class="stat a-card a-rise money" style="--i: 4">
        <span class="ico"><Wallet :size="20" /></span>
        <span class="label">Confirmado em {{ monthName }}</span>
        <strong v-if="!loading">{{ formatMoney(nRevenue * 100).replace(',00', '') }}</strong><span v-else class="a-skeleton num" />
        <span class="go">sem comissão de aplicativo</span>
      </div>
    </section>

    <div class="cols">
      <section class="a-card block a-rise" style="--i: 5" aria-labelledby="today-title">
        <h2 id="today-title">Hoje na pousada</h2>
        <div v-if="loading" class="skel-list"><span v-for="n in 2" :key="n" class="a-skeleton row" /></div>
        <ul v-else-if="checkIns.length || checkOuts.length || ongoing.length" class="agenda">
          <li v-for="b in checkIns" :key="`in-${b.id}`">
            <span class="pill in"><LogIn :size="14" /> Chega</span>
            <span class="who"><strong>{{ b.guest_name }}</strong><small>{{ accNames(b) }} · {{ plural(b.guests, 'pessoa', 'pessoas') }}</small></span>
          </li>
          <li v-for="b in checkOuts" :key="`out-${b.id}`">
            <span class="pill out"><LogOut :size="14" /> Sai</span>
            <span class="who"><strong>{{ b.guest_name }}</strong><small>{{ accNames(b) }}</small></span>
          </li>
          <li v-for="b in ongoing.filter(o => o.check_in !== today)" :key="`stay-${b.id}`">
            <span class="pill stay">Hospedado</span>
            <span class="who"><strong>{{ b.guest_name }}</strong><small>{{ accNames(b) }} · até {{ shortDate(b.check_out) }}</small></span>
          </li>
        </ul>
        <p v-else class="calm">Nenhuma chegada ou saída hoje. Um bom dia para cuidar do jardim.</p>
      </section>

      <section class="a-card block a-rise" style="--i: 6" aria-labelledby="next-title">
        <div class="block-head">
          <h2 id="next-title">Próximas chegadas</h2>
          <button type="button" class="link" @click="emit('go', 'reservas')">Ver todas</button>
        </div>
        <div v-if="loading" class="skel-list"><span v-for="n in 3" :key="n" class="a-skeleton row" /></div>
        <ul v-else-if="nextArrivals.length" class="arrivals">
          <li v-for="b in nextArrivals" :key="b.id">
            <span class="cal"><small>{{ weekday(b.check_in) }}</small><strong>{{ b.check_in.slice(8) }}</strong></span>
            <span class="avatar" aria-hidden="true">{{ initials(b.guest_name) }}</span>
            <span class="who"><strong>{{ b.guest_name }}</strong><small>{{ accNames(b) }} · {{ plural(b.nights, 'diária', 'diárias') }}</small></span>
            <span class="total">{{ formatMoney(b.total) }}</span>
          </li>
        </ul>
        <p v-else class="calm">Nenhuma reserva confirmada pela frente. Divulgue o link do site nas suas redes!</p>
      </section>
    </div>

    <section class="quick a-rise" style="--i: 7" aria-label="Atalhos">
      <button type="button" class="a-card a-lift" @click="emit('go', 'bloqueios')"><CalendarX2 :size="20" /> Bloquear datas</button>
      <button type="button" class="a-card a-lift" @click="emit('go', 'acomodacoes')"><BedDouble :size="20" /> Acomodações e preços</button>
      <button type="button" class="a-card a-lift" @click="emit('go', 'pagina')"><LayoutTemplate :size="20" /> Personalizar a página</button>
    </section>
  </div>
</template>

<style scoped lang="scss">
.overview { display: grid; gap: clamp(1rem, 2.4vw, 1.6rem); }
.hello h1 { @include display(clamp(2.3rem, 5vw, 3.4rem)); margin: .3rem 0 .4rem; }
.date { display: flex; align-items: center; gap: .45rem; margin: 0; color: var(--muted); font-weight: 600; }
.date svg { color: var(--amber); }
.lead { margin: 0; color: var(--muted); font-size: 1.05rem; }

.stats { display: grid; gap: clamp(.7rem, 1.6vw, 1rem); grid-template-columns: repeat(4, minmax(0, 1fr)); @include below(xl) { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
.stat {
  display: grid; gap: .25rem; align-content: start; padding: 1.1rem 1.1rem 1rem; border: 0; font: inherit; color: inherit; text-align: left; cursor: pointer; min-width: 0;
  strong { font-family: var(--font-display); font-weight: 400; font-size: clamp(2.2rem, 4vw, 2.8rem); line-height: 1.05; font-variant-numeric: tabular-nums; }
}
.stat.money { cursor: default; }
.stat .ico { display: grid; place-items: center; width: 38px; height: 38px; border-radius: 12px; background: var(--a-sunk); color: var(--brand-text); margin-bottom: .45rem; }
.stat.hot .ico { background: var(--warn-soft); color: var(--warn-ink); }
.stat.hot strong { color: var(--warn-ink); }
.label { color: var(--muted); font-size: .86rem; font-weight: 600; }
.go { display: inline-flex; align-items: center; gap: .3rem; color: var(--muted); font-size: .8rem; font-weight: 600; }
.num { display: block; width: 3.2rem; height: 2.4rem; margin: .2rem 0; }

.cols { display: grid; gap: clamp(.7rem, 1.6vw, 1rem); grid-template-columns: minmax(0, 1fr) minmax(0, 1.2fr); @include below(lg) { grid-template-columns: minmax(0, 1fr); } }
.block { padding: 1.2rem 1.2rem 1rem; display: grid; gap: .8rem; align-content: start; }
.block h2 { margin: 0; font-size: 1.35rem; }
.block-head { display: flex; justify-content: space-between; align-items: center; }
.link { background: none; border: 0; padding: .4rem 0; font: inherit; font-weight: 700; font-size: .88rem; color: var(--brand-text); cursor: pointer; }
.calm { margin: 0; color: var(--muted); padding: .6rem 0 .4rem; }
.skel-list { display: grid; gap: .6rem; }
.skel-list .row { height: 52px; }
ul { list-style: none; margin: 0; padding: 0; display: grid; }
.agenda li, .arrivals li { display: flex; align-items: center; gap: .8rem; padding: .7rem 0; border-top: 1px solid var(--a-sunk); }
.agenda li:first-child, .arrivals li:first-child { border-top: 0; }
.who { display: grid; min-width: 0; flex: 1; }
.who strong { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.who small { color: var(--muted); overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.pill { display: inline-flex; align-items: center; gap: .3rem; flex: none; padding: .25rem .6rem; border-radius: 999px; font-size: .76rem; font-weight: 800; }
.pill.in { background: color-mix(in srgb, var(--brand) 14%, white); color: var(--brand-text); }
.pill.out { background: var(--warn-soft); color: var(--warn-ink); }
.pill.stay { background: var(--a-sunk); color: var(--muted); }
.cal { display: grid; justify-items: center; width: 44px; flex: none; padding: .25rem 0; border-radius: 10px; background: var(--a-sunk); line-height: 1.1; }
.cal small { font-size: .66rem; text-transform: uppercase; font-weight: 800; color: var(--muted); letter-spacing: .06em; }
.cal strong { font-family: var(--font-display); font-weight: 400; font-size: 1.35rem; }
.avatar { display: grid; place-items: center; width: 38px; height: 38px; border-radius: 50%; flex: none; background: color-mix(in srgb, var(--brand) 14%, white); color: var(--brand-text); font-size: .8rem; font-weight: 800; }
.total { font-weight: 700; font-variant-numeric: tabular-nums; font-size: .92rem; }
@include below(sm) { .arrivals .avatar { display: none; } }

.quick { display: grid; gap: .7rem; grid-template-columns: repeat(auto-fit, minmax(min(100%, 210px), 1fr)); }
.quick button { display: flex; align-items: center; gap: .7rem; min-height: 60px; padding: 0 1.1rem; border: 0; font: inherit; font-weight: 700; color: var(--text); cursor: pointer; text-align: left; }
.quick svg { color: var(--brand-text); flex: none; }
.error { padding: 1rem 1.2rem; display: grid; gap: .7rem; justify-items: start; }
.error p { margin: 0; }
</style>
