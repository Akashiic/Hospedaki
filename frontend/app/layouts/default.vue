<script setup lang="ts">
// Moldura do site público: menu, conteúdo da página (com transição) e rodapé.
const { property } = await useProperty()
const route = useRoute()
</script>

<template>
  <div class="site" :style="{ '--brand': property.primary_color }">
    <SiteNav :name="property.name" :logo-url="property.logo_url" />
    <main>
      <slot />
    </main>
    <SiteFooter :property="property" :show-call="route.path !== '/reservar'" />
  </div>
</template>
