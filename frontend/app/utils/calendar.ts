// Regras do calendário de diárias, sem Vue: o componente AvailabilityCalendar só desenha e
// chama estas funções (testadas em tests/calendar.test.ts).
//
// Convenções: os dias são "AAAA-MM-DD"; `blocked` traz as NOITES ocupadas ou bloqueadas (a noite
// do dia X vai de X a X + 1); `maxDate` é o fim, exclusivo, da janela de disponibilidade buscada:
// conhecemos as noites de hoje até a véspera de maxDate.
import { addMonthsIso, formatIso, monthsBetween } from './dates.ts'

export interface CalendarState {
  today: string
  checkIn: string
  checkOut: string
  blocked: ReadonlySet<string>
  maxDate?: string
}

export type DayState = 'past' | 'closed' | 'selected' | 'range' | 'blocked' | 'free'

/** Nenhuma noite ocupada de `start` até a véspera de `end`? */
export function rangeIsFree(start: string, end: string, blocked: ReadonlySet<string>): boolean {
  for (const night of blocked) {
    if (night >= start && night < end) return false
  }
  return true
}

/** O hóspede já tocou na entrada e agora escolhe a saída. */
export function choosingCheckOut(s: CalendarState): boolean {
  return !!s.checkIn && !s.checkOut
}

/**
 * Serve como saída: depois da entrada, sem noite ocupada no meio e dentro da janela conhecida.
 * O próprio dia da saída pode estar ocupado (é a noite seguinte, que não entra na estadia).
 */
export function canCheckOut(day: string, s: CalendarState): boolean {
  return choosingCheckOut(s)
    && day > s.checkIn
    && (!s.maxDate || day <= s.maxDate)
    && rangeIsFree(s.checkIn, day, s.blocked)
}

/** Serve como entrada: hoje em diante, noite livre e já dentro da agenda buscada. */
export function canCheckIn(day: string, s: CalendarState): boolean {
  return day >= s.today && !s.blocked.has(day) && (!s.maxDate || day < s.maxDate)
}

/** Dá para tocar no dia? Como saída válida ou como nova entrada. */
export function isSelectable(day: string, s: CalendarState): boolean {
  if (day < s.today) return false
  return canCheckOut(day, s) || canCheckIn(day, s)
}

/** Resultado de tocar num dia: a nova seleção, ou null quando o dia não pode ser escolhido. */
export function pickDay(day: string, s: CalendarState): { checkIn: string, checkOut: string } | null {
  if (canCheckOut(day, s)) return { checkIn: s.checkIn, checkOut: day }
  if (canCheckIn(day, s)) return { checkIn: day, checkOut: '' }
  return null
}

/** Aparência do dia (classe CSS). */
export function dayState(day: string, s: CalendarState): DayState {
  if (day < s.today) return 'past'
  if (day === s.checkIn || day === s.checkOut) return 'selected'
  if (s.checkIn && s.checkOut && day > s.checkIn && day < s.checkOut) return 'range'
  if (s.maxDate && day >= s.maxDate && !canCheckOut(day, s)) return 'closed'
  if (s.blocked.has(day)) return 'blocked'
  return 'free'
}

/** Complementos do nome do dia para leitores de tela ("entrada", "indisponível"…). */
export function dayNotes(day: string, s: CalendarState): string[] {
  const notes: string[] = []
  if (day === s.checkIn) notes.push('entrada')
  else if (day === s.checkOut) notes.push('saída')
  else if (dayState(day, s) === 'range') notes.push('dentro da estadia')

  if (day < s.today) notes.push('data passada')
  else if (s.maxDate && day >= s.maxDate && !canCheckOut(day, s)) notes.push('agenda ainda não aberta')
  else if (s.blocked.has(day)) notes.push(canCheckOut(day, s) ? 'ocupado, disponível só para saída' : 'indisponível')
  else if (choosingCheckOut(s) && day > s.checkIn && !canCheckOut(day, s)) {
    // O dia está livre, mas não fecha esta estadia: tocar nele recomeça a escolha a partir dele.
    notes.push('há noites ocupadas antes; escolher este dia começa uma nova entrada')
  }
  return notes
}

/** Último deslocamento de mês permitido (o calendário mostra dois meses por vez). */
export function maxMonthOffset(today: string, maxDate?: string): number {
  if (!maxDate) return Number.POSITIVE_INFINITY
  return Math.max(0, monthsBetween(today, maxDate) - 1)
}

export interface CalendarMonth {
  label: string
  days: (string | null)[]
}

/** Grade de um mês: espaços vazios até o primeiro dia da semana e depois os dias. */
export function monthGrid(today: string, offset: number): CalendarMonth {
  const first = `${addMonthsIso(`${today.slice(0, 7)}-01`, offset)}`
  const [y, m] = first.split('-').map(Number)
  const total = new Date(Date.UTC(y!, m!, 0)).getUTCDate()
  const lead = new Date(Date.UTC(y!, m! - 1, 1)).getUTCDay()
  const days: (string | null)[] = Array(lead).fill(null)
  for (let d = 1; d <= total; d++) days.push(`${first.slice(0, 8)}${String(d).padStart(2, '0')}`)
  return { label: formatIso(first, { month: 'long', year: 'numeric' }), days }
}
