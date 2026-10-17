// https://nuxt.com/docs/api/configuration/nuxt-config

// `npm run preview:demo` gera uma versão estática com dados de exemplo, sem backend.
const demo = process.env.HOSPEDAKI_DEMO === '1'

export default defineNuxtConfig({
  compatibilityDate: '2025-07-15',
  ssr: !demo,
  router: { options: { hashMode: demo } },
  devtools: { enabled: true },
  // O painel do dono roda só no navegador e não aparece no Google.
  routeRules: {
    '/painel/**': { ssr: false, headers: { 'X-Robots-Tag': 'noindex' } },
  },
  css: ['~/assets/main.css'],
  app: {
    head: {
      htmlAttrs: { lang: 'pt-BR' },
      meta: [{ name: 'viewport', content: 'width=device-width, initial-scale=1' }],
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
      propertySlug: 'pousada-exemplo',
      gaId: '',
      metaPixelId: '',
      demo,
    },
  },
})
