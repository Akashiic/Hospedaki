// v-reveal: anima elementos quando entram na tela (só opacidade/transform, leve para o celular).
// Uso: v-reveal (sobe e aparece) ou v-reveal="'wipe'" (imagem se revela de cima para baixo).
export default defineNuxtPlugin((nuxtApp) => {
  let observer: IntersectionObserver | null = null

  if (import.meta.client && 'IntersectionObserver' in window) {
    document.documentElement.classList.add('has-reveal')
    observer = new IntersectionObserver((entries) => {
      for (const entry of entries) {
        if (entry.isIntersecting) {
          entry.target.classList.add('is-in')
          observer!.unobserve(entry.target)
        }
      }
    }, { rootMargin: '0px 0px -8% 0px', threshold: 0.12 })
  }

  nuxtApp.vueApp.directive<HTMLElement, string | undefined>('reveal', {
    getSSRProps: () => ({}),
    mounted(el, binding) {
      el.classList.add(binding.value === 'wipe' ? 'reveal-wipe' : 'reveal')
      if (!observer) return el.classList.add('is-in')
      observer.observe(el)
    },
    unmounted(el) {
      observer?.unobserve(el)
    },
  })
})
