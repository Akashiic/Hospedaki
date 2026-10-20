import type { Accommodation, Property } from '~/composables/useApi'

export type Persona = 'casal' | 'familia'

/**
 * Quem o visitante disse que vem junto (escolhido nas "duas portas" da capa).
 * Vale para a visita inteira: muda os textos de "Um dia aqui", a ordem das acomodações
 * e a acomodação que já vem marcada em Reservar.
 */
export function usePersona() {
  return useState<Persona | null>('persona', () => null)
}

/**
 * A acomodação que a pousada indicou para quem vem (best_for). Sem indicação, casal fica com a
 * de menor capacidade e família com a de maior. Só as de estadia: um salão de festas reservado
 * por hora não é onde a família dorme.
 */
export function accommodationFor(property: Property, persona: Persona | null): Accommodation | undefined {
  const stays = property.accommodations.filter(a => !isHourly(a))
  if (!persona || !stays.length) return undefined
  const chosen = stays.find(a => a.best_for === persona)
  if (chosen) return chosen
  const bySize = [...stays].sort((a, b) => a.max_guests - b.max_guests)
  return persona === 'casal' ? bySize[0] : bySize[bySize.length - 1]
}
