import type { Property } from '~/composables/useApi'

const iso = (d: Date) => `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`

export interface FreeWeekend { checkIn: string, checkOut: string, accommodationIds: number[] }

/**
 * Próximo fim de semana (noites de sexta e sábado) com pelo menos uma acomodação livre.
 * Busca só no navegador, depois que a página aparece, para não atrasar a capa.
 */
export function useNextWeekend(property: Ref<Property>) {
  const api = useApi()
  const free = useState<FreeWeekend | null>('next-weekend', () => null)

  onMounted(async () => {
    if (free.value) return
    const from = new Date()
    const to = new Date(from.getFullYear(), from.getMonth(), from.getDate() + 120)
    try {
      const res = await api<{ accommodations: { id: number, unavailable: string[] }[] }>('/availability', {
        query: { from: iso(from), to: iso(to) },
      })
      const friday = new Date(from.getFullYear(), from.getMonth(), from.getDate() + ((5 - from.getDay() + 7) % 7))
      for (; friday < to; friday.setDate(friday.getDate() + 7)) {
        const sat = new Date(friday.getFullYear(), friday.getMonth(), friday.getDate() + 1)
        const nights = [iso(friday), iso(sat)]
        const ids = res.accommodations.filter(a => nights.every(n => !a.unavailable.includes(n))).map(a => a.id)
        if (ids.length) {
          const sun = new Date(friday.getFullYear(), friday.getMonth(), friday.getDate() + 2)
          free.value = { checkIn: iso(friday), checkOut: iso(sun), accommodationIds: ids }
          return
        }
      }
    }
    catch {
      // Sem disponibilidade, a capa só não mostra o aviso.
    }
  })

  return { free, accommodations: computed(() => property.value.accommodations.filter(a => free.value?.accommodationIds.includes(a.id))) }
}

/** "24 a 26 de out" */
export function formatRange(checkIn: string, checkOut: string) {
  const [a, b] = [checkIn, checkOut].map((s) => { const [y, m, d] = s.split('-').map(Number); return new Date(y!, m! - 1, d!) })
  const month = (d: Date) => d.toLocaleDateString('pt-BR', { month: 'short' }).replace('.', '')
  return a!.getMonth() === b!.getMonth()
    ? `${a!.getDate()} a ${b!.getDate()} de ${month(b!)}`
    : `${a!.getDate()} de ${month(a!)} a ${b!.getDate()} de ${month(b!)}`
}
