<script setup lang="ts">
const { property, gallery, whatsappLink } = await useProperty()

const image = computed(() => gallery.value[1]?.url ?? gallery.value[0]?.url)
const page = await useSitePage('lugar', { image: () => image.value })
</script>

<template>
  <div>
    <SitePageHeader
      :eyebrow="page.eyebrow"
      :title="page.title"
      :lead="page.lead"
      :image="image"
    />
    <SiteIntro :property="property" />
    <LazySiteGallery v-if="gallery.length > 1" hydrate-on-visible :photos="gallery" />
    <SiteExtras v-if="property.extras?.length" :extras="property.extras" :whatsapp="whatsappLink('Olá! Quero saber dos serviços opcionais.')" />
  </div>
</template>
