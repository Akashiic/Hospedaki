// v-reveal: anima elementos quando entram na tela (só opacidade/transform, leve para o celular).
// Uso: v-reveal (sobe e aparece) ou v-reveal="'wipe'" (imagem se revela de cima para baixo).
// Atraso na cena: style="--i: 2" (índice × passo de 75ms) ou --d em segundos. Tempos em assets/scss/_motion.scss.
//
// Sem JavaScript (ou para robôs de busca) nada é escondido: o CSS só oculta dentro de .has-reveal.
// Na hidratação, o que já está visível na tela fica como veio do servidor (sem sumir e voltar);
// só o que está abaixo da dobra, ou chega por navegação, ganha a entrada.
export default defineNuxtPlugin((nuxtApp) => {
  let observer: IntersectionObserver | null = null
  let pending: HTMLElement[] = []

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

  // Lê todas as posições de uma vez e só depois escreve as classes (um único cálculo de layout),
  // antes do próximo quadro, então o conteúdo que veio do servidor nunca pisca.
  function flushHydrated() {
    const items = pending
    pending = []
    const fold = window.innerHeight
    const visible = items.map(el => el.getBoundingClientRect().top < fold)
    items.forEach((el, i) => {
      if (visible[i]) return
      // Fora da tela: volta ao estado escondido (invisível para quem lê) e espera a rolagem.
      el.classList.remove('is-in')
      observer!.observe(el)
    })
  }

  nuxtApp.vueApp.directive<HTMLElement, string | undefined>('reveal', {
    getSSRProps: () => ({}),
    mounted(el, binding) {
      el.classList.add(binding.value === 'wipe' ? 'reveal-wipe' : 'reveal')
      if (!observer) return el.classList.add('is-in')
      if (nuxtApp.isHydrating) {
        // A classe e o is-in entram no mesmo quadro para o que já está na tela: nada se move.
        if (!pending.length) requestAnimationFrame(flushHydrated)
        pending.push(el)
        el.classList.add('is-in')
        return
      }
      observer.observe(el)
    },
    unmounted(el) {
      observer?.unobserve(el)
    },
  })
})
