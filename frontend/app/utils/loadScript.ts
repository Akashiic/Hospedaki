// Carrega um script de terceiros uma vez só (SDK do Mercado Pago, Stripe.js).
const loading = new Map<string, Promise<void>>()

export function loadScript(src: string): Promise<void> {
  let promise = loading.get(src)
  if (!promise) {
    promise = new Promise<void>((resolve, reject) => {
      const el = document.createElement('script')
      el.src = src
      el.async = true
      el.onload = () => resolve()
      el.onerror = () => {
        loading.delete(src)
        el.remove()
        reject(new Error(`Não carregou ${src}`))
      }
      document.head.appendChild(el)
    })
    loading.set(src, promise)
  }
  return promise
}

/** Bandeira pelo começo do número (só para mostrar o ícone e no pagamento simulado). */
export function cardBrand(number: string): string {
  const n = number.replace(/\D/g, '')
  // Elo e Hipercard antes: alguns começam como Visa e Master.
  if (/^(4011|4312|4389|4514|4576|5041|5066|5067|509|6277|6362|6363|650|6516|6550)/.test(n)) return 'elo'
  if (/^(606282|3841)/.test(n)) return 'hipercard'
  if (/^4/.test(n)) return 'visa'
  if (/^(5[1-5]|2[2-7])/.test(n)) return 'master'
  if (/^3[47]/.test(n)) return 'amex'
  return 'cartão'
}
