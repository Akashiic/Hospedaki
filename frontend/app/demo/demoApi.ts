// API de mentira para a prévia visual: roda no navegador, sem servidor.
// Imita as respostas do backend Laravel com dados de exemplo.
import type { Accommodation, Property, Quote } from '~/composables/useApi'
import { chaletPhoto, interiorPhoto, spacePhoto } from './illustrations'

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
  accommodations: { id: number, name: string, subtotal: number }[]
  created_at: string
}

const iso = (d: Date) => `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`
const parse = (s: string) => { const [y, m, d] = s.split('-').map(Number); return new Date(y!, m! - 1, d!) }
const addDays = (s: string, n: number) => { const d = parse(s); d.setDate(d.getDate() + n); return iso(d) }
const today = iso(new Date())

const accommodations: Accommodation[] = [
  {
    id: 1, name: 'Chalé', slug: 'chale', max_guests: 2, base_price: 35000, weekend_price: 45000, min_nights: 1,
    description: 'Chalé para casal com cama queen, cozinha equipada, lareira e varanda com rede de frente para a mata.',
    photos: [{ url: chaletPhoto, caption: 'Chalé' }, { url: interiorPhoto, caption: 'Interior do chalé' }],
  },
  {
    id: 2, name: 'Espaço', slug: 'espaco', max_guests: 6, base_price: 50000, weekend_price: 65000, min_nights: 1,
    description: 'Espaço amplo para famílias e grupos, com dois quartos, sala integrada, churrasqueira e quintal gramado.',
    photos: [{ url: spacePhoto, caption: 'Espaço' }],
  },
]

const property: Property = {
  name: 'Pousada Exemplo', slug: 'pousada-exemplo',
  description: 'Um chalé aconchegante e um espaço amplo, cercados de natureza. Reserve direto com a gente, sem taxas de aplicativo.',
  email: 'contato@exemplo.com.br', phone: null, whatsapp: '5500000000000', address: null,
  city: 'Cidade', state: 'SP', logo_url: null, primary_color: '#2f6f4f',
  check_in_time: '14:00', check_out_time: '12:00', accommodations,
}

const seasons: DemoSeason[] = accommodations.map(a => ({
  accommodation_id: a.id,
  starts_on: `${new Date().getFullYear()}-12-26`,
  ends_on: `${new Date().getFullYear() + 1}-01-02`,
  price: Math.round(a.base_price * 1.5),
}))

const blocks: DemoBlock[] = [
  { id: 1, accommodation_id: 2, starts_on: addDays(today, 20), ends_on: addDays(today, 22), reason: 'Reserva pelo Airbnb' },
]

let nextId = 4
const bookings: DemoBooking[] = [
  booking(1, 'confirmed', 1, 5, 7, 'Ana Souza', 2, 'Aniversário de casamento.'),
  booking(2, 'pending', 1, 12, 14, 'Carlos Lima', 2, null),
  booking(3, 'pending', 2, 9, 12, 'Família Oliveira', 5, 'Vamos com um cachorro pequeno, pode?'),
]

function booking(id: number, status: DemoBooking['status'], accId: number, inDays: number, outDays: number, name: string, guests: number, notes: string | null): DemoBooking {
  const check_in = addDays(today, inDays)
  const check_out = addDays(today, outDays)
  const acc = accommodations.find(a => a.id === accId)!
  const subtotal = priceStay(acc, check_in, check_out)
  return {
    id, code: `DEMO${id}${name.slice(0, 2).toUpperCase()}`, status, check_in, check_out, guests,
    guest_name: name, guest_email: null, guest_phone: '(11) 99999-0000', notes, total: subtotal,
    accommodations: [{ id: acc.id, name: acc.name, subtotal }], created_at: new Date().toISOString(),
  }
}

function priceStay(acc: Accommodation, checkIn: string, checkOut: string) {
  let total = 0
  for (let night = checkIn; night < checkOut; night = addDays(night, 1)) {
    const season = seasons.find(s => s.accommodation_id === acc.id && night >= s.starts_on && night <= s.ends_on)
    const weekend = [5, 6].includes(parse(night).getDay())
    total += season ? season.price : (weekend && acc.weekend_price ? acc.weekend_price : acc.base_price)
  }
  return total
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

function fail(field: string, message: string): never {
  throw { data: { message, errors: { [field]: [message] } } }
}

function quote(body: { accommodation_ids: number[], check_in: string, check_out: string, guests: number }): Quote {
  const accs = accommodations.filter(a => body.accommodation_ids.includes(a.id))
  if (body.guests > accs.reduce((n, a) => n + a.max_guests, 0)) fail('guests', 'Número de hóspedes acima da capacidade.')
  for (const acc of accs) {
    if (unavailable(acc.id, body.check_in, body.check_out).length) fail('check_in', `${acc.name} não está disponível nessas datas.`)
  }
  const items = accs.map(a => ({ accommodation_id: a.id, name: a.name, subtotal: priceStay(a, body.check_in, body.check_out) }))
  const nights = Math.round((parse(body.check_out).getTime() - parse(body.check_in).getTime()) / 86400000)
  return { check_in: body.check_in, check_out: body.check_out, nights, total: items.reduce((n, i) => n + i.subtotal, 0), items }
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
        accommodations: q.items.map(i => ({ id: i.accommodation_id, name: i.name, subtotal: i.subtotal })),
        created_at: new Date().toISOString(),
      }
      bookings.push(created)
      return { data: created }
    },
  }

  const handler = handlers[`${method} ${route}`]
  if (!handler) throw { data: { message: `Rota de demonstração não encontrada: ${method} ${route}` } }
  return handler() as T
}

export const demoState = { property, accommodations, bookings, blocks }
