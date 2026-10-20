// Promoção do Pix: quando o timer acaba e quanto falta. Com loop, a promoção recomeça na hora
// em que acaba e dura loopHours de novo (como o backend faz em Accommodation::pixPromo).

/** Fim do ciclo atual (ms), ou null quando a promoção já acabou e não recomeça. */
export function promoTarget(endsAt: number, loopHours: number | null | undefined, now: number): number | null {
  if (now < endsAt) return endsAt
  if (!loopHours) return null
  const cycle = loopHours * 3_600_000
  return endsAt + (Math.floor((now - endsAt) / cycle) + 1) * cycle
}

export interface Remaining { days: number, hours: number, minutes: number, seconds: number }

/** Quanto falta, em dias, horas, minutos e segundos (nunca negativo). */
export function splitRemaining(ms: number): Remaining {
  const total = Math.max(0, Math.floor(ms / 1000))
  return {
    days: Math.floor(total / 86_400),
    hours: Math.floor(total / 3600) % 24,
    minutes: Math.floor(total / 60) % 60,
    seconds: total % 60,
  }
}

/** Valor com o desconto do Pix (centavos), arredondado como o backend (desconto para baixo). */
export function pixPrice(price: number, percent: number): number {
  return price - Math.floor(price * percent / 100)
}
