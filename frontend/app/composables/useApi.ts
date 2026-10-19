export interface Photo {
  url: string
  caption: string | null
}

export interface Accommodation {
  id: number
  name: string
  slug: string
  description: string | null
  max_guests: number
  base_price: number
  weekend_price: number | null
  min_nights: number
  /** Taxa de limpeza por estadia, em centavos; null = sem taxa. */
  cleaning_fee?: number | null
  /** Desconto (%) sobre as diárias a partir de 7 noites e a partir de 28. */
  weekly_discount?: number | null
  monthly_discount?: number | null
  /** Pessoas incluídas no valor da diária; null = a diária vale para a lotação toda. */
  included_guests: number | null
  /** Acréscimo por pessoa a mais, por diária, em centavos; null = ainda não informado. */
  extra_guest_price: number | null
  amenities: string[]
  /** Indicação da pousada para a escolha da capa; sem ela, vale a capacidade. */
  best_for?: 'casal' | 'familia' | null
  /** Acomodações com o mesmo valor dividem o imóvel (o Espaço inclui o apartamento) e não são reservadas juntas. */
  shared_unit?: string | null
  photos: Photo[]
}

export interface PropertyPhoto extends Photo {
  featured: boolean
}

/** Um momento do dia contado na home; `couple` e `family` trocam o texto conforme quem vem. */
export interface StoryMoment {
  time: string
  period: string
  title: string
  text: string
  couple?: string
  family?: string
  image?: string
}

export interface Property {
  name: string
  tagline: string | null
  highlights: { title: string, text: string }[]
  story: StoryMoment[]
  photos: PropertyPhoto[]
  slug: string
  description: string | null
  email: string | null
  phone: string | null
  whatsapp: string | null
  address: string | null
  city: string | null
  state: string | null
  logo_url: string | null
  primary_color: string
  /** Fuso da pousada (IANA): define o "hoje" do calendário, igual ao do backend. */
  timezone: string
  /** Serviços opcionais (café da manhã, decoração...), mostrados em "O lugar". */
  extras?: { title: string, text: string }[]
  /** Seções da página inicial na ordem escolhida pelo dono no painel (a capa sempre primeiro). */
  home_sections?: { key: string, visible: boolean }[]
  /** Título da seção "Um dia aqui"; sem ele, um título genérico. */
  story_title: string | null
  check_in_time: string
  check_out_time: string
  /** Pousada de demonstração do Hospedaki: aviso de demo, login aberto no painel e página para donos. */
  demo?: boolean
  accommodations: Accommodation[]
}

export interface QuoteItem {
  accommodation_id: number
  name: string
  /** Total desta acomodação: diárias (com as pessoas a mais), menos o desconto, mais a limpeza. */
  subtotal: number
  /** Soma das diárias, já com o acréscimo das pessoas a mais quando o valor é conhecido. */
  nights_total: number
  /** Desconto de estadia longa, em centavos, e o percentual aplicado. */
  discount: number
  discount_percent: number
  /** Taxa de limpeza da estadia. */
  cleaning_fee: number
  /** Pessoas além das incluídas, atribuídas a esta acomodação. */
  extra_guests: number
  /** Valor delas na estadia (0 quando o acréscimo ainda não foi informado). */
  extra_guests_subtotal: number
}

export interface Quote {
  check_in: string
  check_out: string
  nights: number
  total: number
  items: QuoteItem[]
  /** Pessoas a mais cujo acréscimo não está no total (combinado com a pousada). */
  extra_guests_unpriced: number
}

/** Cliente da API já apontado para a pousada configurada neste site. */
export function useApi() {
  const config = useRuntimeConfig().public

  // Prévia visual: responde com dados de exemplo, sem backend.
  if (config.demo) {
    const prefix = `/properties/${config.propertySlug}`
    // Carregado só na prévia, para não pesar no site de verdade.
    return (async <T>(path: string, options?: Record<string, any>) => {
      const { demoFetch } = await import('~/demo/demoApi')
      return demoFetch<T>(`${prefix}${path}`, options)
    }) as unknown as typeof $fetch
  }

  return $fetch.create({
    baseURL: `${config.apiBase}/properties/${config.propertySlug}`,
    headers: { Accept: 'application/json' },
  })
}

/** Primeira mensagem de erro de validação devolvida pela API. */
export function apiError(error: unknown) {
  const data = (error as { data?: { message?: string, errors?: Record<string, string[]> } }).data
  const first = data?.errors ? Object.values(data.errors)[0]?.[0] : undefined
  return first ?? data?.message ?? 'Não foi possível concluir. Tente novamente.'
}

/** Telefone brasileiro legível: 5511950211636 → (11) 95021-1636. */
export function formatPhone(value: string | null | undefined) {
  const d = (value ?? '').replace(/\D/g, '').replace(/^55(?=\d{10,11}$)/, '')
  if (d.length === 11) return `(${d.slice(0, 2)}) ${d.slice(2, 7)}-${d.slice(7)}`
  if (d.length === 10) return `(${d.slice(0, 2)}) ${d.slice(2, 6)}-${d.slice(6)}`
  return value ?? ''
}
