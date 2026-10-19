<script setup lang="ts">
// Moldura do site público: menu, conteúdo da página (com transição) e rodapé.
const { property, whatsappLink } = await useProperty()
const route = useRoute()

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
    <SiteNav :name="property.name" :logo-url="property.logo_url" />
    <main id="conteudo" tabindex="-1">
      <slot />
    </main>
    <SiteFooter :property="property" :show-call="route.path !== '/reservar'" />
    <SiteDemoChip v-if="property.demo" />
    <SiteMobileBar v-if="route.path !== '/reservar'" :property="property" :whatsapp="whatsappLink()" />
  </div>
</template>
