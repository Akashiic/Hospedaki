<script setup lang="ts">
// Painel e login: nunca no Google. O servidor já manda X-Robots-Tag (nuxt.config, routeRules);
// a meta tag repete o aviso para quem só lê o HTML.
useSeoMeta({ robots: 'noindex, nofollow, noarchive, nosnippet' })
</script>

<template>
  <div class="admin">
    <slot />
    <AdminToasts />
  </div>
</template>

<style lang="scss">
// Estilos do painel (cartões, esqueletos, transições), carregados só aqui.
@use '~/assets/scss/admin';
</style>
