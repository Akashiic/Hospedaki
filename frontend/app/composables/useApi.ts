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
  photos: Photo[]
}

export interface Property {
  name: string
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
  check_in_time: string
  check_out_time: string
  accommodations: Accommodation[]
}

export interface Quote {
  check_in: string
  check_out: string
  nights: number
  total: number
  items: { accommodation_id: number, name: string, subtotal: number }[]
}

/** Cliente da API já apontado para a pousada configurada neste site. */
export function useApi() {
  const config = useRuntimeConfig().public

  return $fetch.create({
    baseURL: `${config.apiBase}/properties/${config.propertySlug}`,
    headers: { Accept: 'application/json' },
  })
}

export function formatMoney(cents: number) {
  return (cents / 100).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' })
}

/** Primeira mensagem de erro de validação devolvida pela API. */
export function apiError(error: unknown) {
  const data = (error as { data?: { message?: string, errors?: Record<string, string[]> } }).data
  const first = data?.errors ? Object.values(data.errors)[0]?.[0] : undefined
  return first ?? data?.message ?? 'Não foi possível concluir. Tente novamente.'
}
