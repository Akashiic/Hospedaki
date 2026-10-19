// Situação de cada reserva no painel e os filtros da aba Reservas.
// Mesmas regras do BookingController::scoped do backend (testadas em tests/bookings.test.ts).

export type BookingScope = 'requests' | 'upcoming' | 'ongoing' | 'completed' | 'past' | 'cancelled' | 'all'

/** Onde a reserva está hoje: o status salvo cruzado com as datas. */
export type BookingPhase = 'request' | 'expired' | 'upcoming' | 'ongoing' | 'completed' | 'declined' | 'cancelled'

interface Dated {
  status: 'pending' | 'confirmed' | 'declined' | 'cancelled'
  check_in: string
  check_out: string
}

export function bookingPhase(b: Dated, today: string): BookingPhase {
  if (b.status === 'declined' || b.status === 'cancelled') return b.status
  if (b.status === 'pending') return b.check_in < today ? 'expired' : 'request'
  if (b.check_out <= today) return 'completed'
  return b.check_in <= today ? 'ongoing' : 'upcoming'
}

export function inScope(b: Dated, scope: BookingScope, today: string): boolean {
  const phase = bookingPhase(b, today)
  switch (scope) {
    case 'requests': return phase === 'request'
    case 'upcoming': return phase === 'upcoming'
    case 'ongoing': return phase === 'ongoing'
    case 'completed': return phase === 'completed'
    case 'past': return b.check_out <= today || phase === 'expired'
    case 'cancelled': return phase === 'declined' || phase === 'cancelled'
    default: return true
  }
}

/** Filtros da aba Reservas, na ordem dos botões. */
export const BOOKING_SCOPES: { scope: BookingScope, label: string, empty: string }[] = [
  { scope: 'upcoming', label: 'Próximas', empty: 'Nenhuma reserva confirmada pela frente.' },
  { scope: 'ongoing', label: 'Em andamento', empty: 'Nenhum hóspede na pousada agora.' },
  { scope: 'completed', label: 'Concluídas', empty: 'Nenhuma estadia concluída ainda.' },
  { scope: 'past', label: 'Passadas', empty: 'Nada no passado ainda.' },
  { scope: 'cancelled', label: 'Canceladas', empty: 'Nenhuma reserva cancelada ou recusada.' },
  { scope: 'all', label: 'Todas', empty: 'Nenhuma reserva ainda.' },
]

export const PHASE_LABEL: Record<BookingPhase, string> = {
  request: 'Aguardando você',
  expired: 'Venceu sem resposta',
  upcoming: 'Confirmada',
  ongoing: 'Hospedado agora',
  completed: 'Concluída',
  declined: 'Recusada',
  cancelled: 'Cancelada',
}

/** Busca por nome, código, telefone ou acomodação, sem diferenciar acentos e maiúsculas. */
export function matchesSearch(b: { guest_name: string, code: string, guest_phone: string, accommodations: { name: string }[] }, term: string): boolean {
  const norm = (v: string) => v.normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase()
  const t = norm(term.trim())
  if (!t) return true
  const digits = t.replace(/\D/g, '')
  return [b.guest_name, b.code, ...b.accommodations.map(a => a.name)].some(v => norm(v).includes(t))
    || (digits.length >= 3 && b.guest_phone.replace(/\D/g, '').includes(digits))
}
