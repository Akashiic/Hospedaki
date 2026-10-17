<script setup lang="ts">
const { property, gallery } = await useProperty()

useSeoMeta({ title: `Reservar · ${property.value.name}` })
</script>

<template>
  <div>
    <SitePageHeader
      compact
      eyebrow="Reserva"
      title="Escolha suas datas"
      lead="Nada é cobrado agora. Você envia o pedido, a pousada confirma e combina o pagamento com você."
      :image="gallery[2]?.url ?? gallery[0]?.url"
    />
    <SiteBooking :property="property" />
  </div>
</template>
