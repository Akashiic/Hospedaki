import type { PageKey, PageSettings, SiteSeo } from '~/utils/sitePages'
import type { SocialNetwork } from '~/utils/social'

export type BookingStatus = 'pending' | 'awaiting_payment' | 'confirmed' | 'declined' | 'cancelled' | 'expired'

export interface AdminBooking {
  id: number
  code: string
  status: BookingStatus
  check_in: string
  check_out: string
  nights: number
  /** Estadia: horário previsto de chegada. Por hora: início, fim e horas. */
  arrival_time?: string | null
  start_time?: string | null
  end_time?: string | null
  hours?: number
  guests: number
  guest_name: string
  guest_email: string | null
  guest_phone: string
  notes: string | null
  total: number
  /** Pessoas a mais cujo acréscimo não entrou no total: combinar com o hóspede. */
  extra_guests_unpriced: number
  accommodations: { id: number, name: string, subtotal: number }[]
  /** Pagamento online: prazo para pagar, quando e como foi pago e o desconto do Pix (centavos). */
  payment_due_at?: string | null
  paid_at?: string | null
  payment_method?: 'pix' | 'card' | null
  pix_discount?: number
  created_at: string
}

/**
 * Abas do painel (o nome vai na URL: /painel?aba=reservas). "Seu site" reúne Páginas e
 * Configurações (identidade, contato, SEO e integrações).
 */
export type AdminTab = 'inicio' | 'pedidos' | 'reservas' | 'estatisticas' | 'bloqueios' | 'acomodacoes' | 'paginas' | 'identidade' | 'contato' | 'seo' | 'integracoes' | 'pagamentos' | 'redes' | 'dominio' | 'temas'

export interface AdminBlock {
  id: number
  accommodation_id: number
  starts_on: string
  ends_on: string
  reason: string | null
}

export interface AdminSeason {
  id: number
  name: string
  starts_on: string
  ends_on: string
  price: number
  weekend_price: number | null
  min_nights: number | null
}

/** Acomodação como o painel edita (valores em centavos). */
export interface AdminAccommodation {
  id: number
  name: string
  slug: string
  description: string | null
  amenities: string[]
  max_guests: number
  included_guests: number | null
  base_price: number
  weekend_price: number | null
  extra_guest_price: number | null
  cleaning_fee: number | null
  weekly_discount: number | null
  monthly_discount: number | null
  min_nights: number
  best_for: 'casal' | 'familia' | null
  shared_unit: string | null
  /** Por diária (estadia) ou por hora (day use, eventos). */
  booking_mode: 'nightly' | 'hourly'
  /** Horários próprios ("15:00"); null = os da pousada (property_check_*_time). */
  check_in_time: string | null
  check_out_time: string | null
  property_check_in_time?: string
  property_check_out_time?: string
  /** Por hora: preço da hora (centavos), mínimo de horas e funcionamento. */
  hourly_price: number | null
  min_hours: number
  opens_at: string
  closes_at: string
  /** Pagamento online: formas aceitas, desconto no Pix (%) e prazo da promoção (ISO; null = sem prazo). */
  accepts_pix: boolean
  accepts_card: boolean
  pix_discount: number
  promo_ends_at: string | null
  promo_loop: boolean
  promo_loop_hours: number
  active: boolean
  sort_order: number
  photos: { id: number, url: string, caption: string | null }[]
  seasons: AdminSeason[]
  bookings_count: number
  upcoming_bookings_count: number
}

/** Campos que o formulário envia ao criar ou salvar. */
export type AccommodationInput = Pick<AdminAccommodation,
  'name' | 'description' | 'amenities' | 'max_guests' | 'included_guests' | 'base_price' | 'weekend_price'
  | 'extra_guest_price' | 'cleaning_fee' | 'weekly_discount' | 'monthly_discount' | 'min_nights' | 'best_for' | 'active'
  | 'booking_mode' | 'check_in_time' | 'check_out_time' | 'hourly_price' | 'min_hours' | 'opens_at' | 'closes_at'
  | 'accepts_pix' | 'accepts_card' | 'pix_discount' | 'promo_ends_at' | 'promo_loop' | 'promo_loop_hours'>

export interface AdminMe {
  user: { name: string, email: string }
  property: { name: string, slug: string, primary_color: string, icon_url?: string | null, demo?: boolean, timezone?: string, accommodations: { id: number, name: string }[] }
}

/** Nome, cores, contato e imagens do site ("Seu site → Configurações"). */
export interface AdminSiteIdentity {
  name: string
  tagline: string | null
  description: string | null
  primary_color: string
  email: string | null
  phone: string | null
  whatsapp: string | null
  address: string | null
  city: string | null
  state: string | null
  logo_url: string | null
  icon_url: string | null
  share_image_url: string | null
}

/** Tudo de "Seu site", como a API devolve em cada leitura e em cada gravação. */
export interface AdminSite {
  identity: AdminSiteIdentity
  pages: Record<PageKey, PageSettings>
  seo: SiteSeo
  /** Perfis nas redes sociais (rede → endereço), na ordem do rodapé. */
  social: Partial<Record<SocialNetwork, string>>
  /** O dono pediu para ser avisado quando os temas chegarem. */
  themes_waitlist: boolean
}

/** Domínio próprio do site ("Seu site → Domínio"). */
export interface AdminDomain {
  domain: string | null
  status: 'pending' | 'active' | 'misconfigured' | null
  checked_at: string | null
  records: { type: string, name: string, value: string }[]
  /** Registros extras que a Vercel pede quando o domínio já foi usado em outra conta. */
  verification: { type: string, domain: string, value: string }[]
  /** Ligação automática na Vercel (com as chaves no servidor). */
  automatic: boolean
  default_url: string
}

export type PaymentProvider = 'mercadopago' | 'stripe' | 'simulated'

/** Chaves de um modo (teste ou produção) de um gateway; a secreta volta só com o final (hint). */
export interface GatewayKeys {
  configured: boolean
  public_key: string | null
  secret_hint: string | null
  webhook_secret: boolean
}

export type GatewayMode = 'test' | 'live'

/** Um gateway: ligado ou não, o modo em uso e as chaves de cada modo. */
export interface AdminGateway {
  enabled: boolean
  mode: GatewayMode
  /** Ligado e com as chaves do modo em uso: é o que pode cobrar. */
  active: boolean
  test: GatewayKeys
  live: GatewayKeys
  webhook_url: string
}

/** "Seu site → Configurações → Pagamentos". */
export interface AdminPayments {
  demo: boolean
  configured: PaymentProvider[]
  pix: PaymentProvider | null
  card: PaymentProvider | null
  installments: number
  gateways: Record<'mercadopago' | 'stripe', AdminGateway>
}

export type SiteImageKind = 'logo' | 'icon' | 'share'

/** Token do painel, guardado num cookie do navegador do dono. */
export function useAdminToken() {
  // Na prévia o login vale só enquanto a página estiver aberta.
  if (useRuntimeConfig().public.demo) return useState<string | null>('hospedaki_admin', () => null)
  // secure: o token só viaja em HTTPS (no `nuxt dev` local fica liberado).
  return useCookie<string | null>('hospedaki_admin', { maxAge: 60 * 60 * 24 * 30, sameSite: 'lax', secure: !import.meta.dev })
}

/** Quantas requisições do painel estão em andamento (a barra de progresso no topo lê daqui). */
export function useAdminBusy() {
  return useState('admin-inflight', () => 0)
}

/** Cliente da API do painel do dono. Sem login válido, volta para a tela de entrar. */
export function useAdminApi() {
  const config = useRuntimeConfig().public
  const token = useAdminToken()
  const inflight = useAdminBusy()
  // Toda requisição conta na barra de progresso enquanto não volta.
  const track = <T>(request: Promise<T>) => {
    inflight.value++
    return request.finally(() => { inflight.value = Math.max(0, inflight.value - 1) })
  }

  if (config.demo) {
    // Carregado só na prévia, para não pesar no site de verdade.
    return (async <T>(path: string, options?: Record<string, any>) => {
      const { demoFetch } = await import('~/demo/demoApi')
      return track(demoFetch<T>(`/admin${path}`, options))
    }) as unknown as typeof $fetch
  }

  const fetcher = $fetch.create({
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
  return ((url: string, options?: Record<string, any>) => track(fetcher(url, options))) as typeof $fetch
}
