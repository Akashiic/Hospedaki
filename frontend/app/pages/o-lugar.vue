<script setup lang="ts">
const { property, gallery } = await useProperty()

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
  </div>
</template>
