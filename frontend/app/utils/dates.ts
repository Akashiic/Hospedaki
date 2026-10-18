// Datas de estadia como texto "AAAA-MM-DD", sem horário nem fuso: uma diária é um dia do
// calendário da pousada, e não um instante. As contas usam UTC por dentro para não sofrerem com
// horário de verão nem com o fuso do navegador. Funções puras (testadas em tests/calendar.test.ts).

/** "Hoje" no fuso da pousada (IANA, ex.: America/Sao_Paulo), o mesmo dia que o backend usa. */
export function todayIn(timeZone: string, now: Date = new Date()): string {
  let parts: Intl.DateTimeFormatPart[]
  try {
    parts = new Intl.DateTimeFormat('en-CA', { timeZone, year: 'numeric', month: '2-digit', day: '2-digit' }).formatToParts(now)
  }
  catch {
    // Fuso inválido no cadastro: melhor o dia do navegador do que quebrar a página.
    parts = new Intl.DateTimeFormat('en-CA', { year: 'numeric', month: '2-digit', day: '2-digit' }).formatToParts(now)
  }
  const get = (type: string) => parts.find(p => p.type === type)?.value ?? ''
  return `${get('year')}-${get('month')}-${get('day')}`
}

/** "2026-10-23" → Date à meia-noite UTC desse dia. */
export function fromIso(iso: string): Date {
  const [y, m, d] = iso.split('-').map(Number)
  return new Date(Date.UTC(y!, m! - 1, d!))
}

/** Date (lida em UTC) → "2026-10-23". */
export function toIso(date: Date): string {
  const y = date.getUTCFullYear()
  const m = String(date.getUTCMonth() + 1).padStart(2, '0')
  const d = String(date.getUTCDate()).padStart(2, '0')
  return `${y}-${m}-${d}`
}

export function addDaysIso(iso: string, days: number): string {
  const date = fromIso(iso)
  date.setUTCDate(date.getUTCDate() + days)
  return toIso(date)
}

/** Soma meses mantendo o dia; quando o mês de destino é mais curto, fica no último dia dele (31/01 + 1 → 28/02). */
export function addMonthsIso(iso: string, months: number): string {
  const [y, m, d] = iso.split('-').map(Number)
  const first = new Date(Date.UTC(y!, m! - 1 + months, 1))
  const last = new Date(Date.UTC(first.getUTCFullYear(), first.getUTCMonth() + 1, 0)).getUTCDate()
  first.setUTCDate(Math.min(d!, last))
  return toIso(first)
}

/** Quantos meses de calendário separam dois dias (outubro → janeiro seguinte = 3). */
export function monthsBetween(from: string, to: string): number {
  const [fy, fm] = from.split('-').map(Number)
  const [ty, tm] = to.split('-').map(Number)
  return (ty! - fy!) * 12 + (tm! - fm!)
}

/** Diárias entre a entrada e a saída. */
export function nightsBetween(checkIn: string, checkOut: string): number {
  return Math.round((fromIso(checkOut).getTime() - fromIso(checkIn).getTime()) / 86400000)
}

/** Dia da semana (0 = domingo … 6 = sábado). */
export function weekdayOf(iso: string): number {
  return fromIso(iso).getUTCDay()
}

/** Data por extenso em português, sem o fuso do navegador mudar o dia. */
export function formatIso(iso: string, options: Intl.DateTimeFormatOptions): string {
  return fromIso(iso).toLocaleDateString('pt-BR', { ...options, timeZone: 'UTC' })
}
