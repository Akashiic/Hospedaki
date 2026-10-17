<script setup lang="ts">
const { property } = await useProperty()

useSeoMeta({ title: `Acomodações · ${property.value.name}` })
</script>

<template>
  <div>
    <SitePageHeader
      eyebrow="Onde você vai ficar"
      title="Acomodações"
      lead="Reserve uma ou mais juntas. Os calendários são separados, e você vê o valor na hora."
      :image="property.accommodations[0]?.photos[0]?.url"
    />
    <SiteShowcase :accommodations="property.accommodations" />
  </div>
</template>
