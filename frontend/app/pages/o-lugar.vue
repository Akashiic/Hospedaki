<script setup lang="ts">
const { property, gallery, whatsappLink } = await useProperty()

useSeoMeta({ title: `O lugar · ${property.value.name}`, description: property.value.description ?? undefined })
</script>

<template>
  <div>
    <SitePageHeader
      eyebrow="O lugar"
      :title="property.tagline ?? property.name"
      :lead="[property.city, property.state].filter(Boolean).join(', ') || null"
      :image="gallery[1]?.url ?? gallery[0]?.url"
    />
    <SiteIntro :property="property" />
    <LazySiteGallery v-if="gallery.length > 1" hydrate-on-visible :photos="gallery" />
    <SiteExtras v-if="property.extras?.length" :extras="property.extras" :whatsapp="whatsappLink('Olá! Quero saber dos serviços opcionais.')" />
  </div>
</template>
