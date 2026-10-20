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
// Os títulos já chegam completos ("O lugar · Pousada"): sem o modelo "%s | Site" do Nuxt SEO.
// Nome do site, og:site_name, canonical e "aparecer no Google" vêm do site config, que o
// servidor monta com os dados da pousada (server/plugins/site-config.ts).
useHead({ titleTemplate: '%s' })

// Dados estruturados: a pousada como hospedagem (tipo, endereço, preços, check-in...).
// Desligáveis em "Seu site → SEO".
if (property.value.seo?.structured_data !== false) {
  const origin = (useSiteConfig().url || useRequestURL().origin).replace(/\/$/, '')
  useSchemaOrg([defineLocalBusiness(lodgingSchema(property.value, property.value.seo?.schema_type ?? 'BedAndBreakfast', origin) as Parameters<typeof defineLocalBusiness>[0])])
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
