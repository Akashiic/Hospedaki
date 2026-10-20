// Página de acomodações como catálogo: disponibilidade por acomodação, próximo fim de semana
// livre e ordenação. Funções puras (testadas em tests/catalog.test.ts).
import type { Accommodation } from '../composables/useApi'
import { addDaysIso, nightsBetween, weekdayOf } from './dates.ts'

export type CatalogSort = 'recomendadas' | 'menor-preco' | 'mais-espaco'
export type CatalogKind = 'todas' | 'estadias' | 'eventos'

/** As noites de uma estadia estão todas livres? (check_out é o dia da saída, sem noite.) */
export function nightsFree(unavailable: readonly string[], checkIn: string, checkOut: string): boolean {
  const taken = new Set(unavailable)
  for (let d = checkIn; d < checkOut; d = addDaysIso(d, 1)) if (taken.has(d)) return false
  return true
}

/**
 * Próximo fim de semana (noites de sexta e sábado) livre desta acomodação, a partir de hoje e
 * antes de `to`. Null quando ela exige mais de 2 diárias ou não há nenhum livre na janela.
 */
export function nextFreeWeekend(unavailable: readonly string[], today: string, to: string, minNights = 1): { checkIn: string, checkOut: string } | null {
  if (minNights > 2) return null
  const taken = new Set(unavailable)
  for (let friday = addDaysIso(today, (5 - weekdayOf(today) + 7) % 7); addDaysIso(friday, 2) <= to; friday = addDaysIso(friday, 7)) {
    if (!taken.has(friday) && !taken.has(addDaysIso(friday, 1))) return { checkIn: friday, checkOut: addDaysIso(friday, 2) }
  }
  return null
}

/** Preço de vitrine: a hora (por hora) ou a menor diária. */
export function listPrice(acc: Pick<Accommodation, 'base_price' | 'weekend_price' | 'hourly_price' | 'booking_mode'>): number {
  if (acc.booking_mode === 'hourly') return acc.hourly_price ?? 0
  return Math.min(acc.base_price, acc.weekend_price ?? acc.base_price)
}

/** Datas válidas para buscar: entrada hoje ou depois e saída depois da entrada. */
export function validRange(checkIn: string, checkOut: string, today: string): boolean {
  return /^\d{4}-\d{2}-\d{2}$/.test(checkIn) && /^\d{4}-\d{2}-\d{2}$/.test(checkOut) && checkIn >= today && nightsBetween(checkIn, checkOut) > 0
}

/**
 * Ordem dos cartões: as que servem (livres e com lugar para todos) primeiro, depois a ordem pedida.
 * Por preço ou espaço, as reservadas por hora vão depois das estadias (preço da hora e da diária
 * não se comparam). Empate fica na ordem que o dono escolheu no painel (a da lista recebida).
 */
export function sortCatalog<T extends Pick<Accommodation, 'id' | 'max_guests' | 'base_price' | 'weekend_price' | 'hourly_price' | 'booking_mode'>>(
  items: T[], sort: CatalogSort, fits: (acc: T) => boolean = () => true,
): T[] {
  const order = new Map(items.map((a, i) => [a.id, i]))
  const by = (a: T, b: T) => {
    if (sort === 'menor-preco') return listPrice(a) - listPrice(b)
    if (sort === 'mais-espaco') return b.max_guests - a.max_guests
    return 0
  }
  const hourly = (a: T) => Number(sort !== 'recomendadas' && a.booking_mode === 'hourly')
  return [...items].sort((a, b) => Number(fits(b)) - Number(fits(a)) || hourly(a) - hourly(b) || by(a, b) || order.get(a.id)! - order.get(b.id)!)
}
