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
      htmlAttrs: { lang: 'pt-BR' },
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
