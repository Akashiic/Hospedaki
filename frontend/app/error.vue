<script setup lang="ts">
import type { NuxtError } from '#app'

// O Nuxt põe o error.vue no pacote inicial de todas as páginas; por isso ele só carrega a página
// de erro de verdade (components/site/ErrorPage.vue) quando um erro acontece.
defineProps<{ error: NuxtError }>()
</script>

<template>
  <LazySiteErrorPage :error="error" />
</template>
