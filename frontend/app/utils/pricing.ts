// Dinheiro e textos de preço por pessoa, num lugar só: cartões, vitrine, página da acomodação,
// orçamento e painel usam as mesmas frases e não se contradizem.
// Funções puras (testadas em tests/pricing.test.ts).
import type { Accommodation } from '../composables/useApi'

export function formatMoney(cents: number): string {
  return (cents / 100).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' })
}

/**
 * Quantas pessoas a pousada recebe ao mesmo tempo. Acomodações que dividem o imóvel (shared_unit)
 * não são reservadas juntas: delas conta só a maior.
 */
export function totalGuests(accs: Pick<Accommodation, 'max_guests' | 'shared_unit' | 'booking_mode'>[]): number {
  const units = new Map<string, number>()
  let total = 0
  // Espaços reservados por hora (salão de festas) não hospedam ninguém.
  for (const a of accs.filter(x => !isHourly(x))) {
    if (!a.shared_unit) total += a.max_guests
    else units.set(a.shared_unit, Math.max(units.get(a.shared_unit) ?? 0, a.max_guests))
  }
  return total + [...units.values()].reduce((n, v) => n + v, 0)
}

/**
 * Desconto de estadia longa e taxa de limpeza sobre a soma das diárias, como o PricingService:
 * o mensal vale a partir de 28 noites, senão o semanal a partir de 7; a limpeza entra uma vez, sem desconto.
 */
export function stayCharges(
  nightsTotal: number,
  nights: number,
  acc: Pick<Accommodation, 'cleaning_fee' | 'weekly_discount' | 'monthly_discount'>,
): { discount: number, discount_percent: number, cleaning_fee: number, total: number } {
  const percent = Math.min(100, nights >= 28 && acc.monthly_discount ? acc.monthly_discount : nights >= 7 && acc.weekly_discount ? acc.weekly_discount : 0)
  const discount = Math.floor(nightsTotal * percent / 100)
  const cleaning = acc.cleaning_fee ?? 0
  return { discount, discount_percent: percent, cleaning_fee: cleaning, total: nightsTotal - discount + cleaning }
}

/**
 * Valor digitado no painel ("450", "450,50", "1.250,00", "R$ 99,9") em centavos; null quando vazio
 * ou ilegível. Vírgula é o separador de centavos; ponto, o de milhar (ou de centavos, se for o único
 * e vier com 1 ou 2 casas, como "450.5").
 */
export function centsFromInput(value: string | null | undefined): number | null {
  let v = (value ?? '').replace(/[^\d.,]/g, '')
  if (!v) return null
  if (v.includes(',')) v = v.replace(/\./g, '').replace(',', '.')
  else if (/^\d+\.\d{1,2}$/.test(v)) { /* ponto decimal */ }
  else v = v.replace(/\./g, '')
  const n = Number(v)
  return Number.isFinite(n) ? Math.round(n * 100) : null
}

/** Centavos no formato do campo: 45000 → "450,00"; null → "". */
export function inputFromCents(cents: number | null | undefined): string {
  return cents == null ? '' : (cents / 100).toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
}

/** Reservada por hora (day use, eventos) em vez de por diária. */
export const isHourly = (acc: Pick<Accommodation, 'booking_mode'>) => acc.booking_mode === 'hourly'

/** "1 diária", "2 diárias". */
export function plural(n: number, one: string, many: string): string {
  return `${n} ${n === 1 ? one : many}`
}

export interface GuestPricing {
  /** Pessoas incluídas no valor da diária. */
  included: number
  /** Acréscimo por pessoa a mais, por diária, em centavos; null = a pousada ainda não informou. */
  perPerson: number | null
}

/**
 * Regra de pessoas a mais da acomodação, ou null quando a diária já vale para a lotação toda
 * (sem pessoas incluídas cadastradas, ou incluídas ≥ capacidade).
 */
export function guestPricing(acc: Pick<Accommodation, 'max_guests' | 'included_guests' | 'extra_guest_price'>): GuestPricing | null {
  if (acc.included_guests == null || acc.included_guests >= acc.max_guests) return null
  return { included: acc.included_guests, perPerson: acc.extra_guest_price }
}

export const guestCopy = {
  /** "Desconto semanal (10%)" a partir de 7 noites; "Desconto mensal (25%)" a partir de 28. */
  discountLine: (percent: number, nights: number) => `Desconto ${nights >= 28 ? 'mensal' : 'semanal'} (${percent}%)`,
  /** "para 2 pessoas" */
  forPeople: (n: number) => `para ${plural(n, 'pessoa', 'pessoas')}`,

  /** Linha do acréscimo nas fichas: valor conhecido ou "combinado com a pousada". */
  extraRule: (g: GuestPricing) => g.perPerson == null
    ? 'Pessoas a mais têm acréscimo, combinado com a pousada.'
    : `+ ${formatMoney(g.perPerson)} por pessoa a mais, por diária.`,

  /** Cartões: "a partir de R$ 497,00/diária para 2 pessoas · até 6 com acréscimo". */
  card: (acc: Pick<Accommodation, 'max_guests' | 'base_price' | 'included_guests' | 'extra_guest_price' | 'booking_mode' | 'hourly_price' | 'min_hours'>) => {
    if (isHourly(acc)) return guestCopy.hourly(acc)
    const g = guestPricing(acc)
    const from = `a partir de ${formatMoney(acc.base_price)}/diária`
    return g
      ? `${from} ${guestCopy.forPeople(g.included)} · até ${acc.max_guests} com acréscimo`
      : `até ${acc.max_guests} pessoas · ${from}`
  },

  /** Por hora: "R$ 150,00/hora · mínimo 4 horas · até 40 pessoas". */
  hourly: (acc: Pick<Accommodation, 'max_guests' | 'hourly_price' | 'min_hours'>) =>
    [acc.hourly_price != null && `${formatMoney(acc.hourly_price)}/hora`, (acc.min_hours ?? 1) > 1 && `mínimo ${plural(acc.min_hours!, 'hora', 'horas')}`, `até ${acc.max_guests} pessoas`]
      .filter(Boolean).join(' · '),

  /** Orçamento: "Total para 2 pessoas" quando há pessoas a mais sem valor informado. */
  quoteTotal: (people: number) => `Total ${guestCopy.forPeople(people)}`,

  /** Nota sob o total quando o acréscimo ainda não tem valor. */
  quoteNote: (unpriced: number) =>
    `Não inclui o acréscimo de ${plural(unpriced, 'pessoa a mais', 'pessoas a mais')}, combinado com a pousada na confirmação.`,

  /** Linha do acréscimo com valor conhecido: "4 pessoas a mais · 2 diárias". */
  quoteExtraLine: (extra: number, nights: number) =>
    `${plural(extra, 'pessoa a mais', 'pessoas a mais')} · ${plural(nights, 'diária', 'diárias')}`,

  /** Frase do pedido enviado. */
  sentNote: 'O acréscimo pelas pessoas a mais é combinado com a pousada na confirmação.',

  /** Painel do dono: "+ acréscimo de 4 pessoas a combinar". */
  panelUnpriced: (unpriced: number) => `+ acréscimo de ${plural(unpriced, 'pessoa', 'pessoas')} a combinar`,
}
