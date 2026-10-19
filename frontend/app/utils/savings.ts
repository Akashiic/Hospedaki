// Conta da calculadora de economia da página do Hospedaki para pousadas (testada em tests/savings.test.ts).

/** Comissões de referência das plataformas, em %. Aproximadas: a taxa exata depende do contrato. */
export const PLATFORM_FEES = [
  { label: 'Airbnb', fee: 15.5 },
  { label: 'Booking.com', fee: 15 },
] as const

export interface Savings {
  /** Comissão paga hoje por mês, em centavos. */
  commissionMonth: number
  /** Comissão que deixa de ser paga por mês com a parte das reservas que vira direta, em centavos. */
  savedMonth: number
  savedYear: number
}

/**
 * @param monthlyRevenue  faturamento por mês com reservas das plataformas, em reais
 * @param feePercent      comissão da plataforma, em %
 * @param directPercent   parte dessas reservas que passa a ser feita direto no site, em %
 */
export function savings(monthlyRevenue: number, feePercent: number, directPercent: number): Savings {
  const clamp = (v: number, max = Infinity) => (Number.isFinite(v) ? Math.min(Math.max(v, 0), max) : 0)
  const revenueCents = Math.round(clamp(monthlyRevenue) * 100)
  const commissionMonth = Math.round(revenueCents * clamp(feePercent, 100) / 100)
  const savedMonth = Math.round(commissionMonth * clamp(directPercent, 100) / 100)

  return { commissionMonth, savedMonth, savedYear: savedMonth * 12 }
}
