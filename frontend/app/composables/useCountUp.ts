/**
 * Número que conta do valor anterior até o novo (os totais da tela Início), em t(4) = 820ms
 * desacelerando, como as entradas do site. Com movimento reduzido, troca na hora.
 */
export function useCountUp(target: Ref<number> | (() => number)) {
  const source = typeof target === 'function' ? computed(target) : target
  const shown = ref(0)
  let frame = 0

  watch(source, (to) => {
    cancelAnimationFrame(frame)
    const from = shown.value
    if (!import.meta.client || window.matchMedia('(prefers-reduced-motion: reduce)').matches || from === to) {
      shown.value = to
      return
    }
    const start = performance.now()
    const tick = (now: number) => {
      const p = Math.min(1, (now - start) / 820)
      // Desacelerando (quártica), parecida com a curva de entrada.
      shown.value = Math.round(from + (to - from) * (1 - (1 - p) ** 4))
      if (p < 1) frame = requestAnimationFrame(tick)
    }
    frame = requestAnimationFrame(tick)
  }, { immediate: true })

  onBeforeUnmount(() => cancelAnimationFrame(frame))
  return shown
}
