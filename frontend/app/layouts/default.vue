<script setup lang="ts">
// Moldura do site público: menu, conteúdo da página (com transição) e rodapé.
const { property, whatsappLink } = await useProperty()
const route = useRoute()
const config = useRuntimeConfig().public

// Identidade e SEO escolhidos em "Seu site": ícone da aba, nome do site nas redes,
// verificação do Google Search Console e "não aparecer no Google".
useHead(() => ({
  link: property.value.icon_url
    ? [
        { rel: 'icon', type: 'image/png', href: property.value.icon_url },
        { rel: 'apple-touch-icon', href: property.value.icon_url },
      ]
    : [],
  meta: [
    { name: 'theme-color', content: property.value.primary_color },
    ...(property.value.seo?.google_verification ? [{ name: 'google-site-verification', content: property.value.seo.google_verification }] : []),
  ],
}))
// Metas clássicas de SEO (painel → SEO): palavras-chave do site (cada página pode trocar pelas
// suas), autor, localização para buscas locais e o perfil no X para os cartões de link.
const seo = computed(() => property.value.seo)
useSeoMeta({
  author: () => seo.value?.author || property.value.name,
  twitterCard: 'summary_large_image',
  twitterSite: () => twitterHandle(property.value.social?.x) ?? undefined,
})
useHead(() => {
  const { city, state } = property.value
  const lat = seo.value?.latitude
  const lng = seo.value?.longitude
  const hasGeo = typeof lat === 'number' && typeof lng === 'number'
  return {
    meta: [
      // Mesmo nome (keywords) da meta das páginas: a da página, registrada depois, substitui esta.
      ...(seo.value?.keywords ? [{ name: 'keywords', content: seo.value.keywords }] : []),
      ...(state && /^[A-Z]{2}$/.test(state) ? [{ name: 'geo.region', content: `BR-${state}` }] : []),
      ...(city ? [{ name: 'geo.placename', content: [city, state].filter(Boolean).join(', ') }] : []),
      ...(hasGeo ? [{ name: 'geo.position', content: `${lat};${lng}` }, { name: 'ICBM', content: `${lat}, ${lng}` }] : []),
    ],
  }
})

// Os títulos já chegam completos ("O lugar · Pousada"): sem o modelo "%s | Site" do Nuxt SEO.
// Nome do site, og:site_name, canonical e "aparecer no Google" vêm do site config, que o
// servidor monta com os dados da pousada (server/plugins/site-config.ts).
useHead({ titleTemplate: '%s' })

// Dados estruturados: o site e a pousada como hospedagem, com tudo aninhado (endereço, mapa,
// fotos, diferenciais, contato, ação de reservar, acomodações). Desenho em utils/lodgingSchema.ts.
// Desligáveis em "Seu site → SEO".
if (structuredDataOn(property.value)) {
  const origin = schemaOrigin()
  useSchemaOrg([
    defineWebSite({ inLanguage: 'pt-BR' }),
    defineLocalBusiness(lodgingSchema(property.value, property.value.seo?.schema_type ?? 'BedAndBreakfast', origin) as Parameters<typeof defineLocalBusiness>[0]),
  ])
}

// Google Analytics e Pixel do Meta: os IDs da instalação (variáveis de ambiente) ou os do painel.
onMounted(() => loadAnalytics({
  gaId: config.gaId || property.value.seo?.ga_id,
  metaPixelId: config.metaPixelId || property.value.seo?.meta_pixel_id,
}))

// O link funciona sem JavaScript (âncora comum). Com JavaScript, o foco vai direto para o conteúdo
// sem mexer no endereço: na prévia (roteador por hash) "#conteudo" viraria uma troca de página.
function skipToContent() {
  const main = document.getElementById('conteudo')
  main?.focus({ preventScroll: true })
  main?.scrollIntoView()
}
</script>

<template>
  <div class="site" :style="{ '--brand': property.primary_color }">
    <!-- Primeiro Tab da página: pula o menu. Fica inerte com o menu do celular aberto, como o resto. -->
    <a href="#conteudo" class="skip-link" @click.prevent="skipToContent">Pular para o conteúdo</a>
    <SiteNav :name="property.name" :logo-url="property.logo_url" :pages="property.pages" />
    <main id="conteudo" tabindex="-1">
      <slot />
    </main>
    <SiteFooter :property="property" :show-call="route.path !== '/reservar' && !route.path.startsWith('/pagamento')" />
    <!-- Dentro da prévia do editor do painel (?layout=), o aviso de demonstração atrapalharia. -->
    <SiteDemoChip v-if="property.demo && !route.query.layout" />
    <SiteMobileBar v-if="route.path !== '/reservar'" :property="property" :whatsapp="whatsappLink()" />
  </div>
</template>
