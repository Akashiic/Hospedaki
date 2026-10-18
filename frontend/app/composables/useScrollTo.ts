/** Rola suavemente até uma seção pelo id (funciona também na prévia, que usa # nas rotas). */
export function useScrollTo() {
  return (id: string) => {
    const el = document.getElementById(id)
    if (!el) return
    const reduce = window.matchMedia('(prefers-reduced-motion: reduce)').matches
    const top = el.getBoundingClientRect().top + window.scrollY - 64
    window.scrollTo({ top, behavior: reduce ? 'auto' : 'smooth' })
  }
}
