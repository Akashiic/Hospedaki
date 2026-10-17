import { demoFetch } from '~/demo/demoApi'

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
  property: { name: string, slug: string, primary_color: string, accommodations: { id: number, name: string }[] }
}

/** Token do painel, guardado num cookie do navegador do dono. */
export function useAdminToken() {
  // Na prévia o login vale só enquanto a página estiver aberta.
  if (useRuntimeConfig().public.demo) return useState<string | null>('hospedaki_admin', () => null)
  return useCookie<string | null>('hospedaki_admin', { maxAge: 60 * 60 * 24 * 30, sameSite: 'lax' })
}

/** Cliente da API do painel do dono. Sem login válido, volta para a tela de entrar. */
export function useAdminApi() {
  const config = useRuntimeConfig().public
  const token = useAdminToken()

  if (config.demo) {
    return (<T>(path: string, options?: Parameters<typeof demoFetch>[1]) =>
      demoFetch<T>(`/admin${path}`, options)) as unknown as typeof $fetch
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
