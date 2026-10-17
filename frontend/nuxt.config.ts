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
      link: [
        { rel: 'preconnect', href: 'https://fonts.googleapis.com' },
        { rel: 'preconnect', href: 'https://fonts.gstatic.com', crossorigin: '' },
        { rel: 'stylesheet', href: 'https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:opsz,wght@12..96,600;12..96,700&family=Figtree:wght@400;500;700&display=swap' },
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
