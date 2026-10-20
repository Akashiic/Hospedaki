<script setup lang="ts">
const { property, gallery } = await useProperty()

const image = computed(() => gallery.value[2]?.url ?? gallery.value[0]?.url)
const page = await useSitePage('reservar', { image: () => image.value })
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
    <SiteBooking :property="property" />
  </div>
</template>
