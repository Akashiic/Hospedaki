// API de mentira para a prévia visual: roda no navegador, sem servidor.
// Imita as respostas do backend Laravel com dados de exemplo.
import type { Accommodation, Property, Quote, QuoteItem } from '~/composables/useApi'
import { addDaysIso, addMonthsIso, nightsBetween, todayIn, weekdayOf } from '~/utils/dates'
import { normalizeLayout } from '~/utils/homeLayout'
import { plural } from '~/utils/pricing'
import { dawnScene, goldenScene, interiorScene, nightScene, porchScene, trailScene } from './illustrations'

interface DemoSeason { accommodation_id: number, starts_on: string, ends_on: string, price: number }
interface DemoBlock { id: number, accommodation_id: number, starts_on: string, ends_on: string, reason: string | null }
export interface DemoBooking {
  id: number
  code: string
  status: 'pending' | 'confirmed' | 'declined' | 'cancelled'
  check_in: string
  check_out: string
  guests: number
  guest_name: string
  guest_email: string | null
  guest_phone: string
  notes: string | null
  total: number
  extra_guests_unpriced: number
  accommodations: { id: number, name: string, subtotal: number }[]
  created_at: string
}

// "Hoje" no fuso da pousada, como o backend (Property::today()).
const TIMEZONE = 'America/Sao_Paulo'
const today = () => todayIn(TIMEZONE)
const addDays = addDaysIso

// Conteúdo real de ivoforti.com (Espaço Bela Vista e Chalé Master). Capacidade é estimada pelas camas:
// o site deles não informa. Diárias para 2 pessoas, como no seed; o acréscimo por pessoa a mais
// ainda não foi informado pela pousada (extra_guest_price null). No Chalé, as 2 incluídas são provisórias. As ilustrações ficam no lugar das fotos até recebermos as fotos reais.
const accommodations: Accommodation[] = [
  {
    id: 1, name: 'Espaço Bela Vista', slug: 'espaco-bela-vista', max_guests: 6, base_price: 49700, weekend_price: 99700, min_nights: 1, included_guests: 2, extra_guest_price: null,
    description: 'Apartamento de 30 m² com sala (sofá e TV 32" com Fire TV e streamings), quarto de casal, quarto com dois beliches e banheiro. Do lado de fora, pátio com palmeiras imperiais, deck com LED à noite, piscina com escadaria larga para as crianças, área de fogueira e salão de festas com cozinha, churrasqueira e sinuca. Valores para o casal; pessoas a mais têm acréscimo.',
    amenities: ['Piscina familiar com escadaria larga', 'Salão de festas com cozinha e churrasqueira', 'Mesa de sinuca', 'Área de fogueira', 'Deck com iluminação de LED', 'TV 32" com Fire TV e streamings', 'Rampa de acesso', 'Café da manhã opcional'],
    photos: [{ url: goldenScene, caption: 'Pátio com palmeiras imperiais' }, { url: nightScene, caption: 'Deck iluminado à noite' }, { url: trailScene, caption: 'Área de fogueira' }],
  },
  {
    id: 2, name: 'Chalé Master', slug: 'chale-master', max_guests: 4, base_price: 49700, weekend_price: 99700, min_nights: 1, included_guests: 2, extra_guest_price: null,
    description: 'Chalé de 98 m² com vista para as montanhas. Suíte master com cama king, closet e TV com streamings; banheiro em porcelanato com banheira de hidromassagem extra grande para o casal. Sala com livros, cozinha completa compacta, sala de jantar, segundo quarto com duas camas de solteiro e varanda com cadeiras. Acesso por escada e ponte de madeira, perto do centro e dos espaços de casamento.',
    amenities: ['Vista para as montanhas', 'Hidromassagem para casal', 'Cama king com closet', 'Cozinha completa', 'TV com streamings', 'Varanda', 'Perto do centro'],
    photos: [{ url: dawnScene, caption: 'O chalé ao amanhecer' }, { url: interiorScene, caption: 'Suíte master' }, { url: porchScene, caption: 'Varanda com vista' }],
  },
]

const property: Property = {
  name: 'Espaço Bela Vista', slug: 'espaco-bela-vista', tagline: 'Piscina, fogueira e vista para as montanhas em Mairiporã',
  description: 'Um espaço com piscina e salão de festas para a família e um chalé de 98 m² com hidromassagem para o casal em Mairiporã. Reserve direto com a gente, sem taxa de aplicativo.',
  highlights: [
    { title: 'Para a família', text: 'Piscina com escadaria larga para as crianças, fogueira e salão com sinuca e churrasqueira.' },
    { title: 'Para o casal', text: 'Chalé com cama king, banheira de hidromassagem e vista para as montanhas.' },
    { title: 'Reserva direta', text: 'Você fala com a gente pelo WhatsApp, sem intermediário e sem taxa de aplicativo.' },
  ],
  story: [
    {
      time: '8h', period: 'Manhã', title: 'Acordar sem pressa', image: dawnScene,
      text: 'Vista para as montanhas no chalé, pátio de palmeiras imperiais no espaço e café da manhã opcional.',
      couple: 'Acordar na cama king da suíte master, com vista para as montanhas. O café da manhã é opcional.',
      family: 'As crianças acordam com o pátio de palmeiras imperiais lá fora. O café da manhã é opcional.',
    },
    {
      time: '15h', period: 'Tarde', title: 'A tarde inteira pela frente', image: goldenScene,
      text: 'Piscina com escadaria larga, churrasqueira no salão de festas ou um livro na varanda do chalé.',
      couple: 'Um livro da sala do chalé e a varanda com cadeiras para ver o dia passar.',
      family: 'Piscina com escadaria larga para os pequenos entrarem com calma e churrasqueira pronta no salão de festas.',
    },
    {
      time: '21h', period: 'Noite', title: 'Quando o deck acende', image: nightScene,
      text: 'Fogueira, deck com LED, sinuca no salão ou a banheira de hidromassagem do chalé.',
      couple: 'Jantar na cozinha do chalé e, para fechar, a banheira de hidromassagem extra grande.',
      family: 'Roda de fogueira, deck iluminado e sinuca no salão para quem não quer dormir cedo.',
    },
  ],
  photos: [
    { url: goldenScene, caption: 'Pátio com palmeiras imperiais', featured: true },
    { url: dawnScene, caption: 'Montanhas ao amanhecer', featured: true },
    { url: nightScene, caption: 'Deck iluminado à noite', featured: true },
    { url: interiorScene, caption: 'Suíte do Chalé Master', featured: false },
    { url: trailScene, caption: 'Área de fogueira', featured: false },
    { url: porchScene, caption: 'Varanda do chalé', featured: false },
  ],
  email: 'contato@ivoforti.com', phone: '1146043867', whatsapp: '5511950211636', address: 'R. Custódio, 180',
  city: 'Mairiporã', state: 'SP', logo_url: null, primary_color: '#2f6f4f', timezone: TIMEZONE, demo: true,
  story_title: 'Do café da manhã à fogueira',
  check_in_time: '14:00', check_out_time: '12:00', accommodations,
}

// Feriado do Chalé Master: R$ 1.297 por diária. Datas de exemplo (réveillon) até o dono informar as dele.
const seasons: DemoSeason[] = [{
  accommodation_id: 2,
  starts_on: `${today().slice(0, 4)}-12-26`,
  ends_on: `${Number(today().slice(0, 4)) + 1}-01-02`,
  price: 129700,
}]

const blocks: DemoBlock[] = [
  { id: 1, accommodation_id: 1, starts_on: addDays(today(), 20), ends_on: addDays(today(), 22), reason: 'Reserva pelo Airbnb' },
]

let nextId = 4
const bookings: DemoBooking[] = [
  booking(1, 'confirmed', 2, 5, 7, 'Ana Souza', 2, 'Aniversário de casamento.'),
  booking(2, 'pending', 2, 12, 14, 'Carlos Lima', 2, null),
  booking(3, 'pending', 1, 9, 12, 'Família Oliveira', 5, 'Vamos com um cachorro pequeno, pode?'),
]

function booking(id: number, status: DemoBooking['status'], accId: number, inDays: number, outDays: number, name: string, guests: number, notes: string | null): DemoBooking {
  const check_in = addDays(today(), inDays)
  const check_out = addDays(today(), outDays)
  const acc = accommodations.find(a => a.id === accId)!
  const [item] = priceItems([acc], check_in, check_out, guests)
  return {
    id, code: `DEMO${id}${name.slice(0, 2).toUpperCase()}`, status, check_in, check_out, guests,
    guest_name: name, guest_email: null, guest_phone: '(11) 99999-0000', notes, total: item!.subtotal,
    extra_guests_unpriced: unpricedOf([item!], [acc]),
    accommodations: [{ id: acc.id, name: acc.name, subtotal: item!.subtotal }], created_at: new Date().toISOString(),
  }
}

// Mesma divisão do BookingService::allocateExtraGuests: cada acomodação inclui `included_guests`
// pessoas (ou a capacidade toda, sem esse dado); as pessoas a mais vão para as acomodações na
// ordem do cadastro, até a capacidade de cada uma. A divisão serve só para o orçamento: um pedido
// de várias acomodações não diz quem dorme onde.
function allocateExtraGuests(accs: Accommodation[], guests: number): Map<number, number> {
  const included = (a: Accommodation) => Math.min(a.included_guests ?? a.max_guests, a.max_guests)
  let extra = Math.max(0, guests - accs.reduce((n, a) => n + included(a), 0))
  const result = new Map<number, number>()
  for (const a of accs) {
    const take = Math.min(extra, a.max_guests - included(a))
    result.set(a.id, take)
    extra -= take
  }
  return result
}

// Diária de cada noite (temporada > fim de semana > base) mais o acréscimo das pessoas a mais,
// quando a pousada informou o valor dele; como o PricingService.
function priceItems(accs: Accommodation[], checkIn: string, checkOut: string, guests: number): QuoteItem[] {
  const extras = allocateExtraGuests(accs, guests)
  return accs.map((acc) => {
    const extra = extras.get(acc.id) ?? 0
    const perNight = extra && acc.extra_guest_price ? extra * acc.extra_guest_price : 0
    let subtotal = 0
    let extraTotal = 0
    for (let night = checkIn; night < checkOut; night = addDays(night, 1)) {
      const season = seasons.find(s => s.accommodation_id === acc.id && night >= s.starts_on && night <= s.ends_on)
      const weekend = [5, 6].includes(weekdayOf(night))
      subtotal += (season ? season.price : (weekend && acc.weekend_price ? acc.weekend_price : acc.base_price)) + perNight
      extraTotal += perNight
    }
    return { accommodation_id: acc.id, name: acc.name, subtotal, extra_guests: extra, extra_guests_subtotal: extraTotal }
  })
}

// Pessoas a mais sem valor informado (ficam fora do total, a combinar com a pousada).
function unpricedOf(items: QuoteItem[], accs: Accommodation[]) {
  return items.reduce((n, i) => n + (accs.find(a => a.id === i.accommodation_id)?.extra_guest_price == null ? i.extra_guests : 0), 0)
}

function unavailable(accId: number, from: string, to: string) {
  const taken = new Set<string>()
  const mark = (start: string, endInclusive: string) => {
    for (let d = start; d <= endInclusive; d = addDays(d, 1)) if (d >= from && d < to) taken.add(d)
  }
  bookings.filter(b => ['pending', 'confirmed'].includes(b.status) && b.accommodations.some(a => a.id === accId))
    .forEach(b => mark(b.check_in, addDays(b.check_out, -1)))
  blocks.filter(b => b.accommodation_id === accId).forEach(b => mark(b.starts_on, b.ends_on))
  return [...taken].sort()
}

const withNights = (b: DemoBooking) => ({ ...b, nights: nightsBetween(b.check_in, b.check_out) })

function fail(field: string, message: string): never {
  throw { statusCode: 422, data: { message, errors: { [field]: [message] } } }
}

function notFound(): never {
  throw { statusCode: 404, data: { message: 'Não encontrado.' } }
}

const isoDate = (v: unknown): v is string => typeof v === 'string' && /^\d{4}-\d{2}-\d{2}$/.test(v)

const isInt = (v: unknown): v is number => typeof v === 'number' && Number.isInteger(v)
const missing = (v: unknown) => v === undefined || v === null || v === '' || (Array.isArray(v) && !v.length)

// Mesmas regras e mensagens do backend: primeiro o QuoteRequest (na ordem dos campos, com os
// textos de lang/pt_BR/validation.php), depois o BookingService.
function quote(body: { accommodation_ids: number[], check_in: string, check_out: string, guests: number }): Quote {
  const now = today()
  if (missing(body.accommodation_ids)) fail('accommodation_ids', 'O campo acomodações é obrigatório.')
  if (missing(body.check_in)) fail('check_in', 'O campo data de entrada é obrigatório.')
  if (!isoDate(body.check_in)) fail('check_in', 'O campo data de entrada deve estar no formato Y-m-d.')
  if (body.check_in < now) fail('check_in', 'A data de entrada não pode ser no passado.')
  if (missing(body.check_out)) fail('check_out', 'O campo data de saída é obrigatório.')
  if (!isoDate(body.check_out)) fail('check_out', 'O campo data de saída deve estar no formato Y-m-d.')
  if (body.check_out <= body.check_in) fail('check_out', 'A data de saída deve ser depois da data de entrada.')
  if (body.check_out >= addMonthsIso(now, 24)) fail('check_out', 'Só aceitamos reservas para os próximos dois anos.')
  if (missing(body.guests)) fail('guests', 'O campo hóspedes é obrigatório.')
  if (!isInt(body.guests)) fail('guests', 'O campo hóspedes deve ser um número inteiro.')
  if (body.guests < 1) fail('guests', 'O campo hóspedes deve ser pelo menos 1.')
  if (body.guests > 100) fail('guests', 'O campo hóspedes deve ser no máximo 100.')

  const ids = [...new Set(body.accommodation_ids)]
  const accs = accommodations.filter(a => ids.includes(a.id))
  if (accs.length !== ids.length) fail('accommodation_ids', 'Acomodação não encontrada.')
  const nights = nightsBetween(body.check_in, body.check_out)
  if (body.guests > accs.reduce((n, a) => n + a.max_guests, 0)) fail('guests', 'Número de hóspedes acima da capacidade.')
  for (const acc of accs) {
    if (nights < acc.min_nights) fail('check_out', `${acc.name} exige no mínimo ${plural(acc.min_nights, 'diária', 'diárias')}.`)
    if (unavailable(acc.id, body.check_in, body.check_out).length) fail('check_in', `${acc.name} não está disponível nessas datas.`)
  }
  const items = priceItems(accs, body.check_in, body.check_out, body.guests)
  return {
    check_in: body.check_in, check_out: body.check_out, nights,
    total: items.reduce((n, i) => n + i.subtotal, 0), items,
    extra_guests_unpriced: unpricedOf(items, accs),
  }
}

type Body = Record<string, any>

/** Responde como o backend, a partir do caminho depois de /api. */
export async function demoFetch<T>(path: string, options: { method?: string, body?: Body, query?: Record<string, any> } = {}): Promise<T> {
  await new Promise(r => setTimeout(r, 150))
  const method = (options.method ?? 'GET').toUpperCase()
  const body = options.body ?? {}
  const route = path.replace(/^\/properties\/[^/]+/, '/site').replace(/\/$/, '') || '/'

  const handlers: Record<string, () => unknown> = {
    'GET /site': () => ({ data: property }),
    'GET /site/availability': () => ({
      from: options.query?.from, to: options.query?.to,
      accommodations: accommodations.map(a => ({ id: a.id, unavailable: unavailable(a.id, options.query!.from, options.query!.to) })),
    }),
    'POST /site/quote': () => quote(body as any),
    'POST /site/booking-requests': () => {
      const q = quote(body as any)
      const created: DemoBooking = {
        id: nextId++, code: Math.random().toString(36).slice(2, 10).toUpperCase(), status: 'pending',
        check_in: q.check_in, check_out: q.check_out, guests: body.guests, guest_name: body.guest_name,
        guest_email: body.guest_email, guest_phone: body.guest_phone, notes: body.notes || null, total: q.total,
        extra_guests_unpriced: q.extra_guests_unpriced,
        accommodations: q.items.map(i => ({ id: i.accommodation_id, name: i.name, subtotal: i.subtotal })),
        created_at: new Date().toISOString(),
      }
      bookings.push(created)
      return { data: created }
    },
  }

  // Painel do dono (na prévia, qualquer e-mail e senha entram).
  const bookingAction = route.match(/^\/admin\/bookings\/(\d+)\/(confirm|decline)$/)
  if (method === 'POST' && bookingAction) {
    const found = bookings.find(b => b.id === Number(bookingAction[1])) ?? notFound()
    if (bookingAction[2] === 'confirm') {
      if (found.status !== 'pending') fail('status', 'Só é possível confirmar pedidos pendentes.')
      found.status = 'confirmed'
    }
    else {
      if (!['pending', 'confirmed'].includes(found.status)) fail('status', 'Este pedido já foi encerrado.')
      found.status = found.status === 'pending' ? 'declined' : 'cancelled'
    }
    return { data: withNights(found) } as T
  }
  const blockDelete = route.match(/^\/admin\/blocked-dates\/(\d+)$/)
  if (method === 'DELETE' && blockDelete) {
    const index = blocks.findIndex(b => b.id === Number(blockDelete[1]))
    if (index === -1) notFound()
    blocks.splice(index, 1)
    return null as T
  }

  Object.assign(handlers, {
    'POST /admin/login': () => ({ token: 'demo', user: { name: 'Dono da Pousada', email: body.email } }),
    'POST /admin/demo-login': () => ({ token: 'demo', user: { name: 'Dono (demonstração)', email: 'demo@exemplo.com.br' } }),
    'POST /admin/logout': () => null,
    'GET /admin/me': () => ({
      user: { name: 'Dono da Pousada', email: 'dono@exemplo.com.br' },
      property: { name: property.name, slug: property.slug, primary_color: property.primary_color, demo: true, accommodations: accommodations.map(a => ({ id: a.id, name: a.name })) },
    }),
    'GET /admin/bookings': () => ({
      data: bookings
        .filter(b => !options.query?.status || b.status === options.query.status)
        .filter(b => !options.query?.upcoming || b.check_out >= today())
        .sort((a, b) => a.check_in.localeCompare(b.check_in))
        .map(withNights),
    }),
    'GET /admin/layout': () => ({ sections: normalizeLayout(property.home_sections) }),
    'PUT /admin/layout': () => {
      property.home_sections = normalizeLayout(body.sections)
      return { sections: property.home_sections }
    },
    'GET /admin/blocked-dates': () => ({ data: [...blocks].sort((a, b) => a.starts_on.localeCompare(b.starts_on)) }),
    'POST /admin/blocked-dates': () => {
      if (!accommodations.some(a => a.id === body.accommodation_id)) fail('accommodation_id', 'Acomodação não encontrada.')
      if (!body.starts_on || !body.ends_on || body.ends_on < body.starts_on) fail('ends_on', 'A última noite precisa ser depois da primeira.')
      const created = { id: nextId++, accommodation_id: body.accommodation_id, starts_on: body.starts_on, ends_on: body.ends_on, reason: body.reason }
      blocks.push(created)
      return { data: created }
    },
  })

  const handler = handlers[`${method} ${route}`]
  if (!handler) throw { data: { message: `Rota de demonstração não encontrada: ${method} ${route}` } }
  return handler() as T
}

export const demoState = { property, accommodations, bookings, blocks }
