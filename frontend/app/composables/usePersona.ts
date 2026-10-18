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

/** Casal fica com a acomodação de menor capacidade; família, com a de maior. */
export function accommodationFor(property: Property, persona: Persona | null): Accommodation | undefined {
  if (!persona || !property.accommodations.length) return undefined
  const bySize = [...property.accommodations].sort((a, b) => a.max_guests - b.max_guests)
  return persona === 'casal' ? bySize[0] : bySize[bySize.length - 1]
}
