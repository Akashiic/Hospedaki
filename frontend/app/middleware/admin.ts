// Páginas do painel exigem login do dono.
export default defineNuxtRouteMiddleware(() => {
  if (!useAdminToken().value) return navigateTo('/painel/entrar')
})
