// Redes sociais da pousada: a ordem, o nome e o ícone de cada uma, os mesmos no painel e no
// rodapé. Ícones em SVG próprios (as marcas não vêm no lucide), num traço só, em currentColor.

export type SocialNetwork = 'instagram' | 'facebook' | 'tiktok' | 'youtube' | 'x' | 'tripadvisor' | 'google' | 'airbnb' | 'booking'

export interface SocialInfo {
  key: SocialNetwork
  name: string
  /** Exemplo no campo do painel. */
  placeholder: string
  /** Aceita só o @ (as outras pedem o link). */
  handle: boolean
  /** Desenho do ícone (viewBox 0 0 24 24). */
  path: string
}

export const SOCIAL_NETWORKS: SocialInfo[] = [
  { key: 'instagram', name: 'Instagram', placeholder: '@pousada ou instagram.com/pousada', handle: true, path: 'M7 2h10a5 5 0 0 1 5 5v10a5 5 0 0 1-5 5H7a5 5 0 0 1-5-5V7a5 5 0 0 1 5-5Zm0 2a3 3 0 0 0-3 3v10a3 3 0 0 0 3 3h10a3 3 0 0 0 3-3V7a3 3 0 0 0-3-3H7Zm5 3.5a4.5 4.5 0 1 1 0 9 4.5 4.5 0 0 1 0-9Zm0 2a2.5 2.5 0 1 0 0 5 2.5 2.5 0 0 0 0-5ZM17.5 5.5a1 1 0 1 1 0 2 1 1 0 0 1 0-2Z' },
  { key: 'facebook', name: 'Facebook', placeholder: 'facebook.com/pousada', handle: false, path: 'M13.5 22v-8h2.7l.4-3.2h-3.1V8.8c0-.9.3-1.6 1.6-1.6h1.7V4.4c-.3 0-1.3-.1-2.5-.1-2.5 0-4.2 1.5-4.2 4.3v2.3H7.4V14h2.7v8h3.4Z' },
  { key: 'tiktok', name: 'TikTok', placeholder: '@pousada ou tiktok.com/@pousada', handle: true, path: 'M16.6 2c.3 2.3 1.6 3.8 3.9 4v3.1a7.2 7.2 0 0 1-3.8-1.2v6.6c0 3.6-2.5 6.5-6.2 6.5A6 6 0 0 1 4.4 15c0-3.9 3.3-6.6 7.3-6v3.3c-1.9-.4-3.7.6-3.7 2.7 0 1.7 1.3 2.9 2.8 2.9 1.7 0 2.7-1.1 2.7-3V2h3.1Z' },
  { key: 'youtube', name: 'YouTube', placeholder: '@pousada ou youtube.com/@pousada', handle: true, path: 'M21.6 7.2a2.6 2.6 0 0 0-1.8-1.8C18.2 5 12 5 12 5s-6.2 0-7.8.4A2.6 2.6 0 0 0 2.4 7.2C2 8.8 2 12 2 12s0 3.2.4 4.8a2.6 2.6 0 0 0 1.8 1.8C5.8 19 12 19 12 19s6.2 0 7.8-.4a2.6 2.6 0 0 0 1.8-1.8c.4-1.6.4-4.8.4-4.8s0-3.2-.4-4.8ZM10 15V9l5.2 3L10 15Z' },
  { key: 'x', name: 'X (Twitter)', placeholder: '@pousada ou x.com/pousada', handle: true, path: 'M17.8 3h3.1l-6.8 7.8L22 21h-6.2l-4.9-6.4L5.3 21H2.2l7.3-8.3L2 3h6.4l4.4 5.8L17.8 3Zm-1.1 16.2h1.7L7.4 4.7H5.6l11.1 14.5Z' },
  { key: 'tripadvisor', name: 'Tripadvisor', placeholder: 'tripadvisor.com.br/Hotel_Review-...', handle: false, path: 'M12 6.5c2.4 0 4.6.6 6.4 1.7H22l-1.7 1.9a4.6 4.6 0 1 1-6.8 5.6L12 17.4l-1.5-1.7a4.6 4.6 0 1 1-6.8-5.6L2 8.2h3.6C7.4 7.1 9.6 6.5 12 6.5Zm-5.4 4a2.7 2.7 0 1 0 0 5.4 2.7 2.7 0 0 0 0-5.4Zm10.8 0a2.7 2.7 0 1 0 0 5.4 2.7 2.7 0 0 0 0-5.4Zm-10.8 1.6a1.1 1.1 0 1 1 0 2.2 1.1 1.1 0 0 1 0-2.2Zm10.8 0a1.1 1.1 0 1 1 0 2.2 1.1 1.1 0 0 1 0-2.2ZM12 8.4c-1.4 0-2.7.3-3.8.8a4.6 4.6 0 0 1 3.8 4.4 4.6 4.6 0 0 1 3.8-4.4c-1.1-.5-2.4-.8-3.8-.8Z' },
  { key: 'google', name: 'Google Maps', placeholder: 'maps.app.goo.gl/... (botão Compartilhar)', handle: false, path: 'M12 2a7 7 0 0 1 7 7c0 5.2-7 13-7 13S5 14.2 5 9a7 7 0 0 1 7-7Zm0 4.5a2.5 2.5 0 1 0 0 5 2.5 2.5 0 0 0 0-5Z' },
  { key: 'airbnb', name: 'Airbnb', placeholder: 'airbnb.com.br/rooms/...', handle: false, path: 'M12 2.5c1 0 1.8.6 2.4 1.7 1.9 3.6 4.2 8.2 5.4 11a3.9 3.9 0 0 1-3.6 5.3c-1.5 0-2.9-.9-4.2-2.4-1.3 1.5-2.7 2.4-4.2 2.4a3.9 3.9 0 0 1-3.6-5.3c1.2-2.8 3.5-7.4 5.4-11 .6-1.1 1.4-1.7 2.4-1.7Zm0 2c-.3 0-.5.2-.7.6-1.8 3.5-4.1 8-5.3 10.8a1.9 1.9 0 0 0 1.8 2.6c.9 0 2-.7 3.1-2-1.4-1.9-2.3-3.8-2.3-5.2a3.4 3.4 0 0 1 6.8 0c0 1.4-.9 3.3-2.3 5.2 1.1 1.3 2.2 2 3.1 2a1.9 1.9 0 0 0 1.8-2.6c-1.2-2.8-3.5-7.3-5.3-10.8-.2-.4-.4-.6-.7-.6Zm0 6.8c-.8 0-1.4.6-1.4 1.4 0 .9.5 2.2 1.4 3.6.9-1.4 1.4-2.7 1.4-3.6 0-.8-.6-1.4-1.4-1.4Z' },
  { key: 'booking', name: 'Booking.com', placeholder: 'booking.com/hotel/br/...', handle: false, path: 'M4 3h7.2c3.2 0 5.1 1.6 5.1 4.2 0 1.6-.9 2.8-2.2 3.4 1.9.5 3 1.9 3 3.9 0 2.9-2.1 4.5-5.6 4.5H4V3Zm3.3 2.7v4.1h3.4c1.4 0 2.2-.7 2.2-2.1 0-1.3-.8-2-2.2-2H7.3Zm0 6.7v4.5h3.7c1.6 0 2.5-.8 2.5-2.3 0-1.4-.9-2.2-2.5-2.2H7.3ZM19 15.8a2.2 2.2 0 1 1 0 4.4 2.2 2.2 0 0 1 0-4.4Z' },
]

/** Perfis preenchidos, na ordem do rodapé. */
export function socialLinks(social: Partial<Record<SocialNetwork, string>> | null | undefined): (SocialInfo & { url: string })[] {
  return SOCIAL_NETWORKS.filter(n => social?.[n.key]).map(n => ({ ...n, url: social![n.key]! }))
}

/** "@usuario" do X para a meta twitter:site, a partir do endereço do perfil. */
export function twitterHandle(url: string | null | undefined): string | null {
  const m = url?.match(/^https:\/\/(?:www\.)?(?:x|twitter)\.com\/([A-Za-z0-9_]{1,15})/)
  return m ? `@${m[1]}` : null
}
