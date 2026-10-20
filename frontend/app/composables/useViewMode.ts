/**
 * Lista ou grade numa tela do painel (Pedidos, Reservas, Acomodações). A escolha fica guardada
 * neste aparelho; sem armazenamento (navegação privada), vale a lista.
 */
export type ViewMode = 'list' | 'grid'

export function useViewMode(screen: string) {
  const key = `hospedaki-view-${screen}`
  const mode = useState<ViewMode>(key, () => 'list')
  onMounted(() => {
    try {
      const saved = localStorage.getItem(key)
      if (saved === 'list' || saved === 'grid') mode.value = saved
    }
    catch { /* sem armazenamento: fica a lista */ }
  })
  watch(mode, (value) => {
    try { localStorage.setItem(key, value) }
    catch { /* idem */ }
  })
  return mode
}
