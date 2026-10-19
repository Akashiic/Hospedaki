export interface AdminBooking {
  id: number
  code: string
  status: 'pending' | 'confirmed' | 'declined' | 'cancelled'
  check_in: string
  check_out: string
  nights: number
  guests: number
  guest_name: string
  guest_email: string | null
  guest_phone: string
  notes: string | null
  total: number
  /** Pessoas a mais cujo acréscimo não entrou no total: combinar com o hóspede. */
  extra_guests_unpriced: number
  accommodations: { id: number, name: string, subtotal: number }[]
  created_at: string
}

export interface AdminBlock {
  id: number
  accommodation_id: number
  starts_on: string
  ends_on: string
  reason: string | null
}

export interface AdminMe {
  user: { name: string, email: string }
  property: { name: string, slug: string, primary_color: string, demo?: boolean, timezone?: string, accommodations: { id: number, name: string }[] }
}

/** Token do painel, guardado num cookie do navegador do dono. */
export function useAdminToken() {
  // Na prévia o login vale só enquanto a página estiver aberta.
  if (useRuntimeConfig().public.demo) return useState<string | null>('hospedaki_admin', () => null)
  // secure: o token só viaja em HTTPS (no `nuxt dev` local fica liberado).
  return useCookie<string | null>('hospedaki_admin', { maxAge: 60 * 60 * 24 * 30, sameSite: 'lax', secure: !import.meta.dev })
}

/** Cliente da API do painel do dono. Sem login válido, volta para a tela de entrar. */
export function useAdminApi() {
  const config = useRuntimeConfig().public
  const token = useAdminToken()

  if (config.demo) {
    // Carregado só na prévia, para não pesar no site de verdade.
    return (async <T>(path: string, options?: Record<string, any>) => {
      const { demoFetch } = await import('~/demo/demoApi')
      return demoFetch<T>(`/admin${path}`, options)
    }) as unknown as typeof $fetch
  }

  return $fetch.create({
    baseURL: `${config.apiBase}/admin`,
    headers: { Accept: 'application/json' },
    onRequest({ options }) {
      if (token.value) options.headers.set('Authorization', `Bearer ${token.value}`)
    },
    onResponseError({ response }) {
      if (response.status === 401) {
        token.value = null
        navigateTo('/painel/entrar')
      }
    },
  })
}
