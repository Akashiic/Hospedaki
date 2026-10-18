import type { Property } from '~/composables/useApi'
import { addDaysIso, formatIso, todayIn, weekdayOf } from '~/utils/dates'

export interface FreeWeekend { checkIn: string, checkOut: string, accommodationIds: number[] }

/**
 * Próximo fim de semana (noites de sexta e sábado) com pelo menos uma acomodação livre
 * que aceite estadias de duas diárias (mínimo de diárias ≤ 2).
 * Busca só no navegador, depois que a página aparece, para não atrasar a capa.
 */
export function useNextWeekend(property: Ref<Property>) {
  const api = useApi()
  const free = useState<FreeWeekend | null>('next-weekend', () => null)

  onMounted(async () => {
    if (free.value) return
    // "Hoje" no fuso da pousada, o mesmo que o backend usa para recusar datas passadas.
    const from = todayIn(property.value.timezone)
    const to = addDaysIso(from, 120)
    try {
      const res = await api<{ accommodations: { id: number, unavailable: string[] }[] }>('/availability', {
        query: { from, to },
      })
      // Acomodação que exige mais de 2 diárias daria erro logo ao abrir o link de reserva.
      const fits = new Set(property.value.accommodations.filter(a => a.min_nights <= 2).map(a => a.id))
      for (let friday = addDaysIso(from, (5 - weekdayOf(from) + 7) % 7); friday < to; friday = addDaysIso(friday, 7)) {
        const nights = [friday, addDaysIso(friday, 1)]
        const ids = res.accommodations.filter(a => fits.has(a.id) && nights.every(n => !a.unavailable.includes(n))).map(a => a.id)
        if (ids.length) {
          free.value = { checkIn: friday, checkOut: addDaysIso(friday, 2), accommodationIds: ids }
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
  const month = (iso: string) => formatIso(iso, { month: 'short' }).replace('.', '')
  const day = (iso: string) => Number(iso.slice(8))
  return checkIn.slice(0, 7) === checkOut.slice(0, 7)
    ? `${day(checkIn)} a ${day(checkOut)} de ${month(checkOut)}`
    : `${day(checkIn)} de ${month(checkIn)} a ${day(checkOut)} de ${month(checkOut)}`
}
