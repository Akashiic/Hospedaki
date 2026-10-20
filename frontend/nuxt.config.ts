// https://nuxt.com/docs/api/configuration/nuxt-config

// `npm run preview:demo` gera uma versão estática com dados de exemplo, sem backend.
const demo = process.env.HOSPEDAKI_DEMO === '1'

const PANEL_HEADERS = {
  'X-Robots-Tag': 'noindex, nofollow, noarchive, nosnippet',
  'X-Frame-Options': 'DENY',
  'Content-Security-Policy': "frame-ancestors 'none'",
  'Referrer-Policy': 'no-referrer',
  'X-Content-Type-Options': 'nosniff',
  'Cache-Control': 'no-store',
}

export default defineNuxtConfig({
  compatibilityDate: '2025-07-15',
  ssr: !demo,
  router: { options: { hashMode: demo } },
  devtools: { enabled: true },
  // Nuxt SEO: robots.txt, sitemap.xml, dados estruturados (Schema.org) e tags padrão.
  // O que muda por pousada (nome, "aparecer no Google", robôs de IA...) vem da API a cada
  // requisição (server/plugins) e é escolhido pelo dono em "Seu site → SEO".
  modules: ['@nuxtjs/seo'],
  site: {
    // Endereço público: NUXT_SITE_URL em cada instalação; sem ela, o domínio da requisição.
    name: 'Hospedaki',
    defaultLocale: 'pt-BR',
    // Na prévia estática não há servidor para decidir: fica fora do Google.
    ...(demo ? { indexable: false } : {}),
  },
  robots: {
    // O painel não é bloqueado aqui de propósito: bloqueado no robots.txt, o Google não lê o
    // "noindex" e pode listar o endereço mesmo assim. Ele chega, lê o noindex e não indexa.
    robotsDisabledValue: 'noindex, nofollow, noarchive, nosnippet',
    cacheControl: 'max-age=600, must-revalidate',
  },
  sitemap: {
    // As URLs vêm todas da fonte dinâmica: ela sabe quais páginas o dono tirou do Google
    // e quais acomodações existem hoje.
    excludeAppSources: true,
    sources: ['/api/__sitemap__/urls'],
    exclude: ['/painel/**'],
    cacheMaxAgeSeconds: 300,
    xsl: false,
    // A prévia estática não tem endereço nem servidor: sem mapa do site (e já fica fora do Google).
    enabled: !demo,
  },
  schemaOrg: {
    // A pousada (LodgingBusiness) entra pela página, com os dados da API (layouts/default.vue).
    defaults: true,
  },
  seo: {
    // Os títulos já vêm completos ("O lugar · Pousada"), com o separador escolhido no painel.
    fallbackTitle: false,
    redirectToCanonicalSiteUrl: false,
  },
  // Imagem de compartilhamento é enviada pelo dono; gerar imagens exigiria um renderizador
  // e um segredo em produção. Markdown para IA e proteção de versão também ficam desligados.
  ogImage: false,
  aiReady: false,
  skewProtection: false,
  // O painel do dono e a tela de login: só no navegador, nunca no Google, nunca dentro de
  // outro site (clickjacking) e sem cache em proxies.
  routeRules: {
    '/painel': { ssr: false, robots: false, sitemap: false, headers: PANEL_HEADERS },
    '/painel/**': { ssr: false, robots: false, sitemap: false, headers: PANEL_HEADERS },
  },
  css: ['~/assets/scss/main.scss'],
  vite: {
    css: {
      preprocessorOptions: {
        scss: { additionalData: '@use "~/assets/scss/mixins" as *;\n' },
      },
    },
  },
  app: {
    // Transição de cinema entre as páginas do site (estilos em assets/scss/_motion.scss).
    pageTransition: { name: 'page', mode: 'out-in' },
    head: {
      meta: [{ name: 'viewport', content: 'width=device-width, initial-scale=1, viewport-fit=cover' }],
      // Fontes servidas pelo próprio site (public/fonts, licença OFL), carregadas logo no início.
      link: [
        { rel: 'preload', as: 'font', type: 'font/woff2', href: '/fonts/instrument-serif-latin-400-normal.woff2', crossorigin: '' },
        { rel: 'preload', as: 'font', type: 'font/woff2', href: '/fonts/figtree-latin-wght-normal.woff2', crossorigin: '' },
      ],
    },
  },
  // Valores trocados por variáveis de ambiente NUXT_PUBLIC_* em cada instalação.
  runtimeConfig: {
    public: {
      apiBase: 'http://localhost:8000/api',
      propertySlug: 'espaco-bela-vista',
      gaId: '',
      metaPixelId: '',
      // Contato da página do Hospedaki para pousadas (/hospedaki, só na demonstração).
      salesWhatsapp: '',
      salesEmail: '',
      demo,
    },
  },
})
