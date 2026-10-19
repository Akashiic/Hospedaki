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
export function totalGuests(accs: Pick<Accommodation, 'max_guests' | 'shared_unit'>[]): number {
  const units = new Map<string, number>()
  let total = 0
  for (const a of accs) {
    if (!a.shared_unit) total += a.max_guests
    else units.set(a.shared_unit, Math.max(units.get(a.shared_unit) ?? 0, a.max_guests))
  }
  return total + [...units.values()].reduce((n, v) => n + v, 0)
}

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
  /** "para 2 pessoas" */
  forPeople: (n: number) => `para ${plural(n, 'pessoa', 'pessoas')}`,

  /** Linha do acréscimo nas fichas: valor conhecido ou "combinado com a pousada". */
  extraRule: (g: GuestPricing) => g.perPerson == null
    ? 'Pessoas a mais têm acréscimo, combinado com a pousada.'
    : `+ ${formatMoney(g.perPerson)} por pessoa a mais, por diária.`,

  /** Cartões: "a partir de R$ 497,00/diária para 2 pessoas · até 6 com acréscimo". */
  card: (acc: Pick<Accommodation, 'max_guests' | 'base_price' | 'included_guests' | 'extra_guest_price'>) => {
    const g = guestPricing(acc)
    const from = `a partir de ${formatMoney(acc.base_price)}/diária`
    return g
      ? `${from} ${guestCopy.forPeople(g.included)} · até ${acc.max_guests} com acréscimo`
      : `até ${acc.max_guests} pessoas · ${from}`
  },

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
