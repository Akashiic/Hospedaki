/**
 * Páginas do site público e os textos padrão de cada uma. O dono troca qualquer texto em
 * "Seu site → Páginas"; campo vazio volta ao padrão daqui, montado com os dados da pousada.
 * O painel usa as mesmas funções para mostrar os padrões e a prévia do Google.
 */

export type PageKey = 'inicio' | 'lugar' | 'acomodacoes' | 'reservar' | 'contato'

/** O que o dono escolheu para uma página (null = usar o padrão). */
export interface PageSettings {
  menu: boolean
  /** Aparece no Google (sai do mapa do site e ganha noindex quando desligado). */
  index: boolean
  eyebrow: string | null
  title: string | null
  lead: string | null
  seo_title: string | null
  seo_description: string | null
  /** Palavras-chave (meta keywords), separadas por vírgula; vazio = as do site. */
  keywords: string | null
  /** Só em Acomodações: fotos de cada acomodação em grade ou em carrossel. */
  photos?: 'grid' | 'carousel'
}

/** SEO avançado da pousada. */
export interface SiteSeo {
  description: string | null
  /** Palavras-chave do site inteiro (meta keywords), separadas por vírgula. */
  keywords: string | null
  /** Meta author: quem assina o site. */
  author: string | null
  /** Posição da pousada no mapa (geo.position, ICBM e dados estruturados). */
  latitude: number | null
  longitude: number | null
  indexable: boolean
  /** Separador dos títulos: "O lugar · Pousada". */
  separator: string
  /** Robôs de IA no robots.txt: liberados, bloquear os que treinam modelos, ou bloquear todos. */
  ai_bots: 'allow' | 'training' | 'all'
  /** Dados estruturados (Schema.org) da pousada, lidos pelo Google. */
  structured_data: boolean
  schema_type: SchemaType
  /** Fotos das acomodações no mapa do site (Google Imagens). */
  sitemap_images: boolean
  google_verification: string | null
  ga_id: string | null
  meta_pixel_id: string | null
}

/** Tipos de hospedagem do Schema.org, com o nome que o dono entende. */
export const SCHEMA_TYPES = [
  { value: 'BedAndBreakfast', label: 'Pousada' },
  { value: 'LodgingBusiness', label: 'Hospedagem (geral)' },
  { value: 'Hotel', label: 'Hotel' },
  { value: 'Hostel', label: 'Hostel' },
  { value: 'Resort', label: 'Resort' },
  { value: 'Campground', label: 'Camping' },
  { value: 'Motel', label: 'Motel' },
] as const
export type SchemaType = typeof SCHEMA_TYPES[number]['value']

export const SEPARATORS = ['·', '|', '–', '—'] as const

export interface SitePageInfo {
  key: PageKey
  path: string
  /** Nome no menu do site e no painel. */
  label: string
  /** Para que serve a página, explicado ao dono. */
  description: string
  /** Pode sair do menu (Início e Reservar ficam sempre). */
  menuOptional: boolean
  /** Pode sair do Google (o Início segue a opção do site inteiro). */
  searchOptional: boolean
  /** Tem abertura com título sobre a foto (a do início é a capa, editada nas seções). */
  header: boolean
}

export const SITE_PAGES: SitePageInfo[] = [
  { key: 'inicio', path: '/', label: 'Início', description: 'A porta de entrada: capa, o dia na pousada, acomodações e o que mais você escolher.', menuOptional: false, searchOptional: false, header: false },
  { key: 'lugar', path: '/o-lugar', label: 'O lugar', description: 'A pousada, a região, a galeria de fotos e os serviços opcionais.', menuOptional: true, searchOptional: true, header: true },
  { key: 'acomodacoes', path: '/acomodacoes', label: 'Acomodações', description: 'Todas as acomodações com fotos, comodidades e diárias.', menuOptional: true, searchOptional: true, header: true },
  { key: 'reservar', path: '/reservar', label: 'Reservar', description: 'O calendário com os dias livres, o valor na hora e o pedido de reserva.', menuOptional: false, searchOptional: true, header: true },
  { key: 'contato', path: '/contato', label: 'Contato', description: 'WhatsApp, telefone, e-mail, endereço e como funciona a reserva.', menuOptional: true, searchOptional: true, header: true },
]

export const PAGE_LIMITS = { eyebrow: 40, title: 80, lead: 240, seo_title: 70, seo_description: 170, keywords: 255 } as const

/** Dados da pousada usados nos textos padrão. */
export interface PageSource {
  name: string
  tagline: string | null
  description: string | null
  city: string | null
  state: string | null
  whatsapp: string | null
  phone: string | null
  email: string | null
  address: string | null
  accommodations: { name: string }[]
  seo?: Partial<Pick<SiteSeo, 'description' | 'separator' | 'keywords'>> | null
}

export interface PageContent {
  eyebrow: string
  title: string
  lead: string | null
  seoTitle: string
  seoDescription: string | null
  /** Palavras-chave da página ou, sem elas, as do site. */
  keywords?: string | null
}

/** "A, B e C" (sem artigo: "o"/"a" depende do nome de cada pousada). */
function joinList(items: string[]) {
  return items.length > 1 ? `${items.slice(0, -1).join(', ')} e ${items.at(-1)}` : (items[0] ?? '')
}

/** Textos padrão de uma página, montados com os dados da pousada. */
export function pageDefaults(key: PageKey, p: PageSource): PageContent {
  const place = [p.city, p.state].filter(Boolean).join(', ') || null
  const sep = p.seo?.separator || '·'
  // A descrição escolhida em SEO vale para o Google; sem ela, a apresentação da pousada.
  const description = p.seo?.description || p.description || null

  switch (key) {
    case 'inicio':
      return { eyebrow: '', title: p.tagline ?? p.name, lead: null, seoTitle: p.tagline ? `${p.name} ${sep} ${p.tagline}` : p.name, seoDescription: description }
    case 'lugar':
      return { eyebrow: 'O lugar', title: p.tagline ?? p.name, lead: place, seoTitle: `O lugar ${sep} ${p.name}`, seoDescription: description }
    case 'acomodacoes': {
      const list = joinList(p.accommodations.map(a => a.name))
      return {
        eyebrow: 'Onde você vai ficar',
        title: 'Acomodações',
        lead: 'Reserve uma ou mais juntas. Os calendários são separados, e você vê o valor na hora.',
        seoTitle: `Acomodações ${sep} ${p.name}`,
        seoDescription: list ? `${list}: fotos, comodidades e diárias de cada acomodação.` : description,
      }
    }
    case 'reservar':
      return {
        eyebrow: 'Reserva',
        title: 'Escolha suas datas',
        lead: 'Nada é cobrado agora. Você envia o pedido, a pousada confirma e combina o pagamento com você.',
        seoTitle: `Reservar ${sep} ${p.name}`,
        seoDescription: 'Escolha as datas no calendário, veja o valor na hora e envie o pedido. Nada é cobrado agora.',
      }
    case 'contato': {
      // Só os canais que a pousada cadastrou.
      const channels = [
        p.whatsapp && 'WhatsApp',
        p.phone && 'telefone',
        p.email && 'e-mail',
        (p.address || p.city) && 'endereço',
      ].filter(Boolean) as string[]
      return {
        eyebrow: 'Contato',
        title: 'Fale com a gente',
        lead: null,
        seoTitle: `Contato ${sep} ${p.name}`,
        seoDescription: channels.length ? `${p.name}: ${joinList(channels)}. Fale com a gente e reserve direto.` : description,
      }
    }
  }
}

/** Textos finais de uma página: o que o dono escreveu ou, no que ficou vazio, o padrão. */
export function pageContent(key: PageKey, p: PageSource, saved?: Partial<PageSettings> | null): PageContent {
  const base = pageDefaults(key, p)
  return {
    eyebrow: saved?.eyebrow || base.eyebrow,
    title: saved?.title || base.title,
    lead: saved?.lead || base.lead,
    seoTitle: saved?.seo_title || base.seoTitle,
    seoDescription: saved?.seo_description || base.seoDescription || p.seo?.description || null,
    keywords: saved?.keywords || p.seo?.keywords || null,
  }
}

/** Páginas que aparecem no menu do site, na ordem. */
export function menuPages(pages?: Partial<Record<PageKey, Pick<PageSettings, 'menu'>>> | null) {
  return SITE_PAGES.filter(page => !page.menuOptional || (pages?.[page.key]?.menu ?? true))
}
