import type { AdminSite } from '~/composables/useAdminApi'

/**
 * Configuração de "Seu site", carregada uma vez e dividida entre Páginas, Configurações e SEO.
 * Toda gravação devolve a configuração inteira, que substitui a guardada.
 */
export function useAdminSite() {
  const api = useAdminApi()
  const site = useState<AdminSite | null>('admin-site', () => null)
  const loadError = ref('')

  async function load() {
    loadError.value = ''
    try {
      site.value = await api<AdminSite>('/site')
    }
    catch (e) {
      loadError.value = apiError(e)
    }
  }

  async function send(path: string, options: { method: 'PUT' | 'POST' | 'DELETE', body?: Record<string, unknown> | FormData }) {
    site.value = await api<AdminSite>(path, options)
    return site.value
  }

  return { site, loadError, load, send }
}
