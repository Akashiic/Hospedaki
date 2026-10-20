<script setup lang="ts">
import type { Stats, StatsPeriod } from '~/utils/stats'
import { Table2 } from 'lucide-vue-next'

// Aba Estatísticas: os números do período (com a variação contra o período anterior) e os
// gráficos. Cada gráfico tem uma série só e pode virar tabela.
const api = useAdminApi()
const period = ref<StatsPeriod>('30d')
const stats = ref<Stats | null>(null)
const error = ref('')
const loading = ref(false)

async function load() {
  loading.value = true
  error.value = ''
  try {
    stats.value = await api<Stats>('/stats', { query: { period: period.value } })
  }
  catch (e) {
    error.value = apiError(e)
  }
  finally {
    loading.value = false
  }
}
watch(period, load, { immediate: true })

const k = computed(() => stats.value?.kpis)
// Sem reservas no período anterior inteiro (pousada nova no sistema), a comparação enganaria.
const compare = (now: number | null, before: number | null) => stats.value?.has_previous ? deltaPercent(now, before) : null
const prev = computed(() => stats.value?.previous)
const pct = (n: number | null | undefined) => n == null ? '–' : `${n.toLocaleString('pt-BR', { maximumFractionDigits: 1 })}%`
const range = computed(() => stats.value ? `${formatIso(stats.value.period.from, { day: 'numeric', month: 'short' })} a ${formatIso(stats.value.period.to, { day: 'numeric', month: 'short', year: 'numeric' })}` : '')

const year = computed(() => stats.value?.monthly.at(-1)?.month.slice(0, 4) ?? '')
const currentMonth = computed(() => stats.value?.monthly.at(-1)?.month ?? null)
const revenueByMonth = computed(() => (stats.value?.monthly ?? []).map(m => ({
  key: m.month, label: monthLabel(m.month, year.value), value: m.revenue, display: formatMoney(m.revenue),
  detail: m.occupancy != null ? `ocupação ${pct(m.occupancy)}` : undefined,
})))
const occupancyByMonth = computed(() => (stats.value?.monthly ?? []).map(m => ({
  key: m.month, label: monthLabel(m.month, year.value), value: m.occupancy, display: pct(m.occupancy),
})))
const occupancyTop = computed(() => Math.min(100, Math.max(20, Math.ceil(Math.max(0, ...(stats.value?.monthly ?? []).map(m => m.occupancy ?? 0)) / 20) * 20)))
const weekdays = computed(() => (stats.value?.weekdays ?? []).map((n, i) => ({
  key: String(i), label: WEEKDAY_LABELS[i]!, value: n, display: plural(n, 'entrada', 'entradas'),
})))
const arrivals = computed(() => {
  const counts = new Map((stats.value?.arrivals ?? []).map(a => [a.hour, a.count]))
  if (!counts.size) return []
  const hours = [...counts.keys()]
  const items = []
  for (let h = Math.min(...hours); h <= Math.max(...hours); h++) {
    const n = counts.get(h) ?? 0
    items.push({ key: String(h), label: `${h}h`, value: n, display: plural(n, 'chegada', 'chegadas') })
  }
  return items
})
const topAccommodation = computed(() => Math.max(1, ...(stats.value?.by_accommodation ?? []).map(a => a.revenue)))
const busiestArrival = computed(() => [...(stats.value?.arrivals ?? [])].sort((a, b) => b.count - a.count)[0])

// Cada gráfico pode virar tabela (os mesmos números, para ler ou copiar).
const tables = reactive<Record<string, boolean>>({})
</script>

<template>
  <section class="stats">
    <header class="a-head a-rise">
      <h1>Estatísticas</h1>
      <p>Como a pousada está indo: receita, ocupação e o comportamento dos hóspedes.</p>
    </header>

    <div class="filters a-rise" style="--i: 1">
      <div class="periods" role="radiogroup" aria-label="Período">
        <button v-for="p in STATS_PERIODS" :key="p.key" type="button" role="radio" :aria-checked="period === p.key" @click="period = p.key">{{ p.label }}</button>
      </div>
      <p v-if="stats" class="range">{{ range }}</p>
    </div>

    <div v-if="error" class="a-card load-error" role="alert">
      <p>Não foi possível carregar as estatísticas. {{ error }}</p>
      <button type="button" class="btn btn-sm" @click="load">Tentar de novo</button>
    </div>
    <AdminLoading v-else-if="!stats" text="Calculando as estatísticas..." :cards="3" height="120px" />

    <div v-else class="body" :class="{ stale: loading }">
      <div class="kpis a-rise" style="--i: 2">
        <AdminChartStatTile hero label="Receita confirmada" :value="formatMoney(k!.revenue)" :delta="compare(k!.revenue, prev!.revenue)" />
        <AdminChartStatTile label="Ocupação" :value="pct(k!.occupancy)" :delta="compare(k!.occupancy, prev!.occupancy)" :hint="`${plural(k!.nights_sold, 'noite vendida', 'noites vendidas')}`" />
        <AdminChartStatTile label="Diária média" :value="k!.adr != null ? formatMoney(k!.adr) : '–'" :delta="compare(k!.adr, prev!.adr)" />
        <AdminChartStatTile label="Reservas" :value="String(k!.bookings)" :delta="compare(k!.bookings, prev!.bookings)" />
      </div>

      <div class="kpis small a-rise" style="--i: 3">
        <AdminChartStatTile label="Estadia média" :value="k!.avg_nights != null ? `${k!.avg_nights.toLocaleString('pt-BR')} ${k!.avg_nights === 1 ? 'noite' : 'noites'}` : '–'" hint="noites por reserva" />
        <AdminChartStatTile label="Antecedência" :value="k!.lead_days != null ? plural(k!.lead_days, 'dia', 'dias') : '–'" hint="entre o pedido e a chegada" />
        <AdminChartStatTile label="Pedidos aceitos" :value="pct(k!.conversion)" :hint="`de ${plural(k!.requests, 'pedido recebido', 'pedidos recebidos')}`" />
        <AdminChartStatTile label="Cancelamentos" :value="String(k!.cancellations)" hint="reservas confirmadas e depois canceladas" />
        <AdminChartStatTile v-if="k!.events || stats.by_accommodation.some(a => a.hourly)" label="Eventos e day use" :value="plural(k!.events, 'evento', 'eventos')" :hint="`${plural(k!.hours_sold, 'hora vendida', 'horas vendidas')}`" />
      </div>

      <div class="charts">
        <section class="a-card chart wide a-rise" style="--i: 4" aria-labelledby="c-rev">
          <div class="chart-head">
            <div><h2 id="c-rev">Receita por mês</h2><p>Últimos 12 meses. Cada noite conta no mês em que acontece.</p></div>
            <button type="button" class="table-btn" :aria-pressed="!!tables.rev" @click="tables.rev = !tables.rev"><Table2 :size="16" aria-hidden="true" /> {{ tables.rev ? 'Ver gráfico' : 'Ver tabela' }}</button>
          </div>
          <table v-if="tables.rev" class="data">
            <thead><tr><th>Mês</th><th>Receita</th><th>Ocupação</th></tr></thead>
            <tbody><tr v-for="m in stats.monthly" :key="m.month"><td>{{ monthLabel(m.month, year) }}</td><td>{{ formatMoney(m.revenue) }}</td><td>{{ pct(m.occupancy) }}</td></tr></tbody>
          </table>
          <AdminChartColumns v-else :data="revenueByMonth" :emphasize="currentMonth" :format="compactMoney" label="Receita por mês" />
        </section>

        <section class="a-card chart wide a-rise" style="--i: 5" aria-labelledby="c-occ">
          <div class="chart-head">
            <div><h2 id="c-occ">Ocupação por mês</h2><p>Noites vendidas sobre as noites disponíveis. Acomodações do mesmo imóvel contam como uma.</p></div>
            <button type="button" class="table-btn" :aria-pressed="!!tables.occ" @click="tables.occ = !tables.occ"><Table2 :size="16" aria-hidden="true" /> {{ tables.occ ? 'Ver gráfico' : 'Ver tabela' }}</button>
          </div>
          <table v-if="tables.occ" class="data">
            <thead><tr><th>Mês</th><th>Ocupação</th></tr></thead>
            <tbody><tr v-for="m in stats.monthly" :key="m.month"><td>{{ monthLabel(m.month, year) }}</td><td>{{ pct(m.occupancy) }}</td></tr></tbody>
          </table>
          <AdminChartLine v-else :data="occupancyByMonth" :max="occupancyTop" label="Ocupação por mês" />
        </section>

        <section class="a-card chart a-rise" style="--i: 6" aria-labelledby="c-acc">
          <div class="chart-head"><div><h2 id="c-acc">Receita por acomodação</h2><p>No período escolhido.</p></div></div>
          <ul class="bars">
            <li v-for="a in stats.by_accommodation" :key="a.id">
              <span class="name">{{ a.name }}</span>
              <span class="track"><span class="fill" :style="{ width: `${Math.max(a.revenue / topAccommodation * 100, a.revenue > 0 ? 1 : 0)}%` }" /></span>
              <span class="num"><strong>{{ formatMoney(a.revenue) }}</strong><small>{{ a.hourly ? plural(a.hours, 'hora', 'horas') : plural(a.nights, 'noite', 'noites') }}</small></span>
            </li>
          </ul>
        </section>

        <section class="a-card chart a-rise" style="--i: 7" aria-labelledby="c-wd">
          <div class="chart-head"><div><h2 id="c-wd">Dia da semana das entradas</h2><p>Quando as reservas começam, no período.</p></div></div>
          <AdminChartColumns :data="weekdays" :height="140" label="Entradas por dia da semana" />
        </section>

        <section class="a-card chart a-rise" style="--i: 8" aria-labelledby="c-arr">
          <div class="chart-head">
            <div>
              <h2 id="c-arr">Horário de chegada</h2>
              <p v-if="busiestArrival">O horário previsto informado pelos hóspedes. Mais comum: por volta das {{ busiestArrival.hour }}h.</p>
              <p v-else>O horário previsto informado pelos hóspedes ao reservar.</p>
            </div>
          </div>
          <AdminChartColumns v-if="arrivals.length" :data="arrivals" :height="140" label="Chegadas por horário" />
          <p v-else class="empty">Ainda sem horários de chegada neste período.</p>
        </section>
      </div>
    </div>
  </section>
</template>

<style scoped lang="scss">
.stats { display: grid; gap: 1.2rem; max-width: 1240px; }
.filters { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: .6rem 1rem; }
.periods { display: inline-flex; flex-wrap: wrap; gap: 4px; padding: 4px; border-radius: 14px; background: var(--a-card); box-shadow: var(--a-shadow); }
.periods button {
  min-height: 40px; padding: 0 .9rem; border: 0; border-radius: 10px; background: none; font: inherit; font-size: .88rem; font-weight: 700; color: var(--muted); cursor: pointer;
  &[aria-checked="true"] { background: var(--night); color: var(--on-night); }
}
.range { margin: 0; color: var(--muted); font-size: .88rem; }
.body { display: grid; gap: 1rem; transition: opacity $dur-sm $ease-move; }
.body.stale { opacity: .6; }
.kpis { display: grid; gap: .8rem; grid-template-columns: repeat(auto-fit, minmax(min(100%, 200px), 1fr)); }
.kpis.small :deep(.value) { font-size: 1.35rem; }
.charts { display: grid; gap: 1rem; grid-template-columns: repeat(auto-fit, minmax(min(100%, 380px), 1fr)); }
.chart { display: grid; gap: 1rem; align-content: start; padding: 1.1rem 1.2rem 1.2rem; min-width: 0; }
.chart.wide { grid-column: 1 / -1; }
.chart-head { display: flex; justify-content: space-between; align-items: start; gap: .8rem; }
.chart-head h2 { margin: 0; font-family: var(--font-body); font-size: 1.05rem; font-weight: 700; }
.chart-head p { margin: .2rem 0 0; color: var(--muted); font-size: .84rem; }
.table-btn { flex: none; display: inline-flex; align-items: center; gap: .35rem; min-height: 36px; padding: 0 .7rem; border: 1px solid var(--line); border-radius: 10px; background: var(--card); font: inherit; font-size: .8rem; font-weight: 700; color: var(--text); cursor: pointer; }
.data { width: 100%; border-collapse: collapse; font-size: .88rem; font-variant-numeric: tabular-nums; }
.data th { text-align: left; font-size: .76rem; color: var(--muted); font-weight: 700; padding: .4rem .5rem; border-bottom: 1px solid var(--line); }
.data td { padding: .4rem .5rem; border-bottom: 1px solid var(--a-sunk); }
// Barras horizontais: uma cor, a trilha num tom claro dela; rótulo e valor em texto comum.
.bars { list-style: none; margin: 0; padding: 0; display: grid; gap: .7rem; }
.bars li { display: grid; grid-template-columns: minmax(0, 9rem) minmax(0, 1fr) auto; align-items: center; gap: .7rem; }
.name { font-size: .88rem; font-weight: 600; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.track { height: 14px; border-radius: 0 4px 4px 0; background: var(--a-sunk); overflow: hidden; }
.fill { display: block; height: 100%; border-radius: 0 4px 4px 0; background: var(--brand); }
.num { display: grid; justify-items: end; line-height: 1.15; }
.num strong { font-size: .88rem; }
.num small { font-size: .74rem; color: var(--muted); }
.empty { margin: 0; color: var(--muted); font-size: .9rem; padding: 1.5rem 0; text-align: center; }
.load-error { padding: 1rem 1.2rem; display: grid; gap: .8rem; justify-items: start; border-left: 4px solid var(--danger); }
.load-error p { margin: 0; }
</style>
