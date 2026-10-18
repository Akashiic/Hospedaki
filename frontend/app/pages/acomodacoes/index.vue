<script setup lang="ts">
const { property } = await useProperty()

// Descrição montada só com os nomes cadastrados, sem artigo ("o"/"a" depende do nome de cada pousada).
const names = property.value.accommodations.map(a => a.name)
const list = names.length > 1 ? `${names.slice(0, -1).join(', ')} e ${names.at(-1)}` : (names[0] ?? '')
useSeoMeta({
  title: `Acomodações · ${property.value.name}`,
  description: list ? `${list}: fotos, comodidades e diárias de cada acomodação.` : undefined,
})
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
