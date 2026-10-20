<script setup lang="ts">
const { property } = await useProperty()

const image = computed(() => property.value.accommodations[0]?.photos[0]?.url)
const page = await useSitePage('acomodacoes', { image: () => image.value })
</script>

<template>
  <div>
    <SitePageHeader
      compact
      :eyebrow="page.eyebrow"
      :title="page.title"
      :lead="page.lead"
      :image="image"
    />
    <SiteCatalog :property="property" />
  </div>
</template>
