/** Avisos curtos do painel ("Reserva confirmada"), que sobem no canto e somem sozinhos. */
export interface Toast {
  id: number
  text: string
  tone: 'success' | 'error' | 'info'
}

let nextId = 1

export function useToast() {
  const toasts = useState<Toast[]>('admin-toasts', () => [])

  function dismiss(id: number) {
    toasts.value = toasts.value.filter(t => t.id !== id)
  }

  function show(text: string, tone: Toast['tone'] = 'success') {
    const id = nextId++
    // No máximo três de uma vez: o mais antigo sai.
    toasts.value = [...toasts.value.slice(-2), { id, text, tone }]
    setTimeout(() => dismiss(id), tone === 'error' ? 7000 : 4200)
  }

  return { toasts, show, dismiss }
}
