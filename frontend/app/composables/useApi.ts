import type { PageKey, PageSettings, SiteSeo } from '~/utils/sitePages'

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
  /** Por diária (estadia) ou por hora (day use, eventos). */
  booking_mode?: 'nightly' | 'hourly'
  /** Check-in e check-out desta acomodação ("15:00"); já vêm com os da pousada quando não tem próprios. */
  check_in_time?: string
  check_out_time?: string
  /** Por hora: preço da hora (centavos), mínimo de horas e horário de funcionamento. */
  hourly_price?: number | null
  min_hours?: number
  opens_at?: string
  closes_at?: string
  /** Pagamento online (vazio = pedido que o dono confirma) e a promoção do Pix valendo agora. */
  payment?: AccommodationPayment
  photos: Photo[]
}

export type PaymentMethod = 'pix' | 'card'

export interface AccommodationPayment {
  methods: PaymentMethod[]
  /** Desconto no Pix (%); 0 = sem desconto agora. */
  pix_discount: number
  /** Fim do ciclo atual da promoção (ISO); null = sem prazo. */
  promo_ends_at: string | null
  /** Em loop: a promoção recomeça a cada tantas horas. */
  promo_loop_hours: number | null
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
  /** Ícone da aba do navegador (quadrado, PNG). */
  icon_url?: string | null
  /** Imagem que aparece ao compartilhar o link (WhatsApp, redes), 1200 × 630. */
  share_image_url?: string | null
  /** Textos, SEO e presença no menu de cada página, escolhidos em "Seu site" no painel. */
  pages?: Record<PageKey, PageSettings>
  seo?: SiteSeo
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
  /** Reserva por hora: horas e preço da hora. */
  hours?: number
  hourly_price?: number
}

export interface Quote {
  check_in: string
  check_out: string
  nights: number
  total: number
  items: QuoteItem[]
  /** Pessoas a mais cujo acréscimo não está no total (combinado com a pousada). */
  extra_guests_unpriced: number
  /** Reserva por hora: início, fim e horas. */
  start_time?: string
  end_time?: string
  hours?: number
  /** Como vai ser pago: formas online (vazio = o dono confirma) e o total no Pix. */
  payment?: { methods: PaymentMethod[], pix_discount: number, pix_total: number, pix_percent: number }
}

/** Uma tentativa de pagamento, como o checkout mostra. */
export interface CheckoutPayment {
  method: PaymentMethod
  provider: 'mercadopago' | 'stripe' | 'simulated'
  status: 'pending' | 'paid' | 'failed' | 'expired' | 'refunded'
  amount: number
  discount: number
  expires_at: string | null
  /** Pix: copia e cola (vira o QR Code) e a página do gateway com as instruções. */
  qr_code: string | null
  ticket_url: string | null
  /** Cartão na Stripe: o navegador confirma com isto. */
  client_secret: string | null
  brand: string | null
  last4: string | null
  installments: number | null
  message: string | null
  simulated: boolean
}

/** Situação do checkout de uma reserva (GET /checkout/{token}). */
export interface Checkout {
  code: string
  status: 'awaiting_payment' | 'confirmed' | 'expired' | 'cancelled' | 'declined' | 'pending'
  due_at: string | null
  paid_at: string | null
  payment_method: PaymentMethod | null
  property: { name: string, slug: string, whatsapp: string | null }
  booking: {
    check_in: string
    check_out: string
    nights: number
    arrival_time: string | null
    start_time: string | null
    end_time: string | null
    hours: number
    guests: number
    guest_name: string
    guest_email: string | null
    total: number
    pix_discount: number
    accommodations: { name: string, subtotal: number, photo: string | null }[]
  }
  methods: {
    pix: { provider: CheckoutPayment['provider'], discount: number, total: number, percent: number, promo_ends_at: string | null } | null
    card: { provider: CheckoutPayment['provider'], total: number, installments: number, public_key: string | null } | null
  }
  payment: CheckoutPayment | null
}

/** Cliente do checkout (/checkout/{token}): fora da pousada, o link já diz qual é a reserva. */
export function useCheckoutApi(token: string) {
  const config = useRuntimeConfig().public
  return $fetch.create({ baseURL: `${config.apiBase}/checkout/${token}`, headers: { Accept: 'application/json' } })
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

/** Erros de validação por campo ({ name: ['mensagem'] }), para mostrar cada um embaixo do seu campo. */
export function apiFieldErrors(error: unknown): Record<string, string[]> {
  return (error as { data?: { errors?: Record<string, string[]> } }).data?.errors ?? {}
}

/** Telefone brasileiro legível: 5511950211636 → (11) 95021-1636. */
export function formatPhone(value: string | null | undefined) {
  const d = (value ?? '').replace(/\D/g, '').replace(/^55(?=\d{10,11}$)/, '')
  if (d.length === 11) return `(${d.slice(0, 2)}) ${d.slice(2, 7)}-${d.slice(7)}`
  if (d.length === 10) return `(${d.slice(0, 2)}) ${d.slice(2, 6)}-${d.slice(6)}`
  return value ?? ''
}
