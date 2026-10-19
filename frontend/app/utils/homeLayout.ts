// Seções da página inicial: catálogo, ordem escolhida pelo dono e as regras do editor do painel.
// Espelha o App\Support\HomeLayout do backend. Funções puras (testadas em tests/homeLayout.test.ts).

export type SectionKey = 'hero' | 'story' | 'stays' | 'intro' | 'gallery' | 'extras'

export interface HomeSection {
  key: SectionKey
  visible: boolean
}

export const SECTION_KEYS: SectionKey[] = ['hero', 'story', 'stays', 'intro', 'gallery', 'extras']

/** A capa abre o site: fica sempre no topo e não pode ser escondida. */
export const LOCKED_SECTION: SectionKey = 'hero'

const DEFAULT_VISIBLE: SectionKey[] = ['hero', 'story', 'stays']

/** Nome e explicação de cada seção, como aparecem no painel. */
export const SECTION_INFO: Record<SectionKey, { label: string, description: string }> = {
  hero: { label: 'Capa', description: 'Fotos em destaque, a frase da pousada e a escolha "a dois / com a família".' },
  story: { label: 'Um dia aqui', description: 'Manhã, tarde e noite na pousada, com o céu mudando enquanto o hóspede rola a página.' },
  stays: { label: 'Acomodações', description: 'Cartões com foto e preço de cada acomodação.' },
  intro: { label: 'Sobre o lugar', description: 'Apresentação da pousada, diferenciais, horários e capacidade.' },
  gallery: { label: 'Galeria de fotos', description: 'Fotos da pousada e das acomodações para passar uma a uma.' },
  extras: { label: 'Serviços opcionais', description: 'Café da manhã, decoração e outros serviços à parte.' },
}

export const DEFAULT_LAYOUT: HomeSection[] = SECTION_KEYS.map(key => ({ key, visible: DEFAULT_VISIBLE.includes(key) }))

/**
 * Lista completa e válida: ignora chaves desconhecidas e repetidas, põe a capa no topo e
 * acrescenta no fim, escondidas, as seções que faltarem.
 */
export function normalizeLayout(saved: readonly { key: string, visible?: boolean }[] | null | undefined): HomeSection[] {
  if (!saved) return DEFAULT_LAYOUT.map(s => ({ ...s }))
  const seen = new Map<SectionKey, HomeSection>()
  for (const item of saved) {
    const key = item.key as SectionKey
    if (SECTION_KEYS.includes(key) && !seen.has(key)) seen.set(key, { key, visible: !!item.visible })
  }
  for (const key of SECTION_KEYS) if (!seen.has(key)) seen.set(key, { key, visible: false })
  seen.delete(LOCKED_SECTION)
  return [{ key: LOCKED_SECTION, visible: true }, ...seen.values()]
}

/** Layout em texto curto para a prévia: as seções visíveis na ordem, separadas por vírgula. */
export function toLayoutParam(sections: HomeSection[]): string {
  return sections.filter(s => s.visible).map(s => s.key).join(',')
}

/** Lê o layout da prévia (?layout=hero,stays,story); null quando não há prévia válida. */
export function fromLayoutParam(param: unknown): HomeSection[] | null {
  if (typeof param !== 'string' || !param) return null
  const keys = param.split(',').filter((k): k is SectionKey => SECTION_KEYS.includes(k as SectionKey))
  if (!keys.length) return null
  return normalizeLayout(keys.map(key => ({ key, visible: true })))
}

/** O que a pousada tem cadastrado para cada seção aparecer (uma seção vazia não é mostrada). */
export interface LayoutContent {
  story: number
  accommodations: number
  photos: number
  extras: number
}

export function availableSections(c: LayoutContent): Record<SectionKey, boolean> {
  return { hero: true, story: c.story > 0, stays: c.accommodations > 0, intro: true, gallery: c.photos > 1, extras: c.extras > 0 }
}

/** Seções que o site mostra, na ordem: visíveis e com conteúdo. */
export function shownSections(sections: HomeSection[], available: Record<SectionKey, boolean>): SectionKey[] {
  return sections.filter(s => s.visible && available[s.key]).map(s => s.key)
}

/** Avisos do editor: escolhas que funcionam, mas costumam custar reservas. */
export function layoutWarnings(sections: HomeSection[], available: Record<SectionKey, boolean>): string[] {
  const shown = shownSections(sections, available)
  const warnings: string[] = []
  if (available.stays && !shown.includes('stays')) {
    warnings.push('As acomodações não aparecem na página inicial. O hóspede ainda chega a elas pelo menu, mas muitos desistem antes.')
  }
  if (shown.length === 1) {
    warnings.push('Só a capa está visível: a página inicial fica muito curta para convencer o hóspede.')
  }
  const staysAt = shown.indexOf('stays')
  if (staysAt > 3) {
    warnings.push('As acomodações estão bem no fim da página. Subir a seção ajuda quem já chega querendo reservar.')
  }
  return warnings
}

/** Layouts prontos para o dono começar: as seções visíveis, na ordem. */
export const LAYOUT_PRESETS: { id: string, label: string, description: string, keys: SectionKey[] }[] = [
  { id: 'experiencia', label: 'Experiência primeiro', description: 'Conta o dia na pousada e depois mostra onde ficar. É o padrão.', keys: ['hero', 'story', 'stays'] },
  { id: 'acomodacoes', label: 'Acomodações primeiro', description: 'Para quem já chega querendo comparar e reservar.', keys: ['hero', 'stays', 'story', 'extras'] },
  { id: 'fotos', label: 'Fotos em destaque', description: 'A galeria logo depois da capa, para encantar pelas imagens.', keys: ['hero', 'gallery', 'stays', 'intro'] },
  { id: 'completa', label: 'Página completa', description: 'Todas as seções, da apresentação aos serviços.', keys: ['hero', 'intro', 'story', 'stays', 'gallery', 'extras'] },
]

export function presetLayout(keys: SectionKey[]): HomeSection[] {
  return normalizeLayout(keys.map(key => ({ key, visible: true })))
}

/** Move a seção de `from` para `to`, sem nunca tirar a capa do topo. */
export function moveSection(sections: HomeSection[], from: number, to: number): HomeSection[] {
  if (from === to || from < 1 || to < 1 || from >= sections.length || to >= sections.length) return sections
  const next = [...sections]
  const [item] = next.splice(from, 1)
  next.splice(to, 0, item!)
  return next
}

export function sameLayout(a: HomeSection[], b: HomeSection[]): boolean {
  return a.length === b.length && a.every((s, i) => s.key === b[i]!.key && s.visible === b[i]!.visible)
}
