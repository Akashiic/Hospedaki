// Reserva por hora (day use, eventos), sem Vue: horários de início possíveis num dia e até
// quando dá para ficar. Testado em tests/hours.test.ts.
// Horários são "HH:MM"; as reservas vão de hora cheia em hora cheia, como valida a API.

/** O dia de uma acomodação por hora, como a API devolve em /hours. */
export interface HoursDay {
  opens_at: string
  closes_at: string
  min_hours: number
  closed: boolean
  taken: { start: string, end: string }[]
  /** Hoje: horários antes deste já passaram. */
  not_before: string | null
}

export const toMinutes = (hm: string) => {
  const [h = 0, m = 0] = hm.split(':').map(Number)
  return h * 60 + m
}

export const fromMinutes = (minutes: number) =>
  `${String(Math.floor(minutes / 60)).padStart(2, '0')}:${String(minutes % 60).padStart(2, '0')}`

/** "14:00" → "14h"; "14:30" → "14h30". */
export function formatHour(hm: string | null | undefined): string {
  if (!hm) return ''
  const [h, m] = hm.split(':')
  return `${Number(h)}h${m && m !== '00' ? m : ''}`
}

/** Até quando dá para ficar começando em `start`: a próxima reserva ou o fechamento. */
export function freeUntil(start: string, day: HoursDay): number {
  const from = toMinutes(start)
  let until = toMinutes(day.closes_at)
  for (const r of day.taken) {
    const rs = toMinutes(r.start)
    if (rs >= from) until = Math.min(until, rs)
  }
  return until
}

/** O horário já está dentro de uma reserva? */
function insideTaken(minute: number, day: HoursDay): boolean {
  return day.taken.some(r => minute >= toMinutes(r.start) && minute < toMinutes(r.end))
}

/**
 * Horários de início do dia, de hora em hora: livre quando ainda não passou, não cai numa reserva
 * e cabe o mínimo de horas antes da próxima reserva ou do fechamento.
 */
export function startOptions(day: HoursDay): { time: string, free: boolean }[] {
  const options: { time: string, free: boolean }[] = []
  const open = toMinutes(day.opens_at)
  const close = toMinutes(day.closes_at)
  const need = Math.max(1, day.min_hours) * 60
  const notBefore = day.not_before ? toMinutes(day.not_before) : -1

  for (let m = open; m + need <= close; m += 60) {
    const time = fromMinutes(m)
    const free = !day.closed && m > notBefore && !insideTaken(m, day) && freeUntil(time, day) - m >= need
    options.push({ time, free })
  }
  return options
}

/** Durações possíveis (em horas) começando em `start`: do mínimo até o horário livre acabar. */
export function durationOptions(start: string, day: HoursDay): number[] {
  const hours = Math.floor((freeUntil(start, day) - toMinutes(start)) / 60)
  const options: number[] = []
  for (let h = Math.max(1, day.min_hours); h <= hours; h++) options.push(h)
  return options
}

/** Fim de uma reserva que começa em `start` e dura `hours`. */
export const endAt = (start: string, hours: number) => fromMinutes(toMinutes(start) + hours * 60)

/**
 * Horários de chegada para escolher numa estadia: de hora em hora, do check-in até as 23h.
 * O hóspede também pode deixar em branco ("ainda não sei").
 */
export function arrivalOptions(checkIn: string): string[] {
  const options: string[] = []
  for (let m = Math.ceil(toMinutes(checkIn || '14:00') / 60) * 60; m <= 23 * 60; m += 60) options.push(fromMinutes(m))
  return options
}
