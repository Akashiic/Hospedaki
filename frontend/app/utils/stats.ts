// Estatísticas do painel: tipos da API e formatos dos números (testados em tests/stats.test.ts).

export type StatsPeriod = '30d' | 'month' | '90d' | '12m'

export interface StatsKpis {
  revenue: number
  occupancy: number | null
  adr: number | null
  bookings: number
  nights_sold: number
  avg_nights: number | null
  lead_days: number | null
  conversion: number | null
  requests: number
  cancellations: number
  events: number
  hours_sold: number
}

export interface Stats {
  period: { key: StatsPeriod, from: string, to: string, days: number }
  kpis: StatsKpis
  previous: Pick<StatsKpis, 'revenue' | 'occupancy' | 'adr' | 'bookings'>
  /** Há reservas desde o começo do período anterior: dá para comparar. */
  has_previous: boolean
  monthly: { month: string, revenue: number, occupancy: number | null }[]
  by_accommodation: { id: number, name: string, hourly: boolean, revenue: number, nights: number, hours: number }[]
  weekdays: number[]
  arrivals: { hour: number, count: number }[]
}

export interface ColumnDatum { key: string, label: string, value: number, display: string, detail?: string }
export interface LineDatum { key: string, label: string, value: number | null, display: string }

export const STATS_PERIODS: { key: StatsPeriod, label: string }[] = [
  { key: '30d', label: 'Últimos 30 dias' },
  { key: 'month', label: 'Este mês' },
  { key: '90d', label: '90 dias' },
  { key: '12m', label: '12 meses' },
]

/** Variação em % contra o período anterior; null quando não dá para comparar (antes era zero). */
export function deltaPercent(now: number | null, before: number | null): number | null {
  if (now == null || before == null || before === 0) return null
  return Math.round(((now - before) / before) * 1000) / 10
}

/** Dinheiro curto para eixos e tiles: R$ 950, R$ 12,9 mil, R$ 1,2 mi. */
export function compactMoney(cents: number): string {
  const reais = cents / 100
  if (Math.abs(reais) >= 1_000_000) return `R$ ${(reais / 1_000_000).toLocaleString('pt-BR', { maximumFractionDigits: 1 })} mi`
  if (Math.abs(reais) >= 1_000) return `R$ ${(reais / 1_000).toLocaleString('pt-BR', { maximumFractionDigits: 1 })} mil`
  return `R$ ${Math.round(reais).toLocaleString('pt-BR')}`
}

/** "2026-10" → "out" (ou "out/25" quando o ano não é o atual). */
export function monthLabel(month: string, currentYear: string): string {
  const name = new Date(`${month}-15T12:00:00Z`).toLocaleDateString('pt-BR', { month: 'short', timeZone: 'UTC' }).replace('.', '')
  return month.startsWith(currentYear) ? name : `${name}/${month.slice(2, 4)}`
}

export const WEEKDAY_LABELS = ['dom', 'seg', 'ter', 'qua', 'qui', 'sex', 'sáb']
