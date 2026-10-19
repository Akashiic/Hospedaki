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
