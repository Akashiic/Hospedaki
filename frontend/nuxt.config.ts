// https://nuxt.com/docs/api/configuration/nuxt-config
export default defineNuxtConfig({
  compatibilityDate: '2025-07-15',
  devtools: { enabled: true },
  css: ['~/assets/main.css'],
  app: {
    head: {
      htmlAttrs: { lang: 'pt-BR' },
      meta: [{ name: 'viewport', content: 'width=device-width, initial-scale=1' }],
    },
  },
  // Valores trocados por variáveis de ambiente NUXT_PUBLIC_* em cada instalação.
  runtimeConfig: {
    public: {
      apiBase: 'http://localhost:8000/api',
      propertySlug: 'pousada-exemplo',
      gaId: '',
      metaPixelId: '',
    },
  },
})
