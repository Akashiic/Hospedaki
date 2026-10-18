// Carrega Google Analytics 4 e Pixel do Meta só quando os IDs estão configurados.
export default defineNuxtPlugin(() => {
  const { gaId, metaPixelId } = useRuntimeConfig().public

  if (gaId) {
    useHead({ script: [{ src: `https://www.googletagmanager.com/gtag/js?id=${gaId}`, async: true }] })
    const w = window as unknown as { dataLayer: unknown[] }
    w.dataLayer = w.dataLayer || []
    window.gtag = function gtag() {
      // eslint-disable-next-line prefer-rest-params
      w.dataLayer.push(arguments)
    }
    window.gtag('js', new Date())
    window.gtag('config', gaId)
  }

  if (metaPixelId) {
    /* eslint-disable */
    ;(function (f: any, b: Document, e: string, v: string) {
      if (f.fbq) return
      const n: any = (f.fbq = function () {
        n.callMethod ? n.callMethod.apply(n, arguments) : n.queue.push(arguments)
      })
      if (!f._fbq) f._fbq = n
      n.push = n
      n.loaded = true
      n.version = '2.0'
      n.queue = []
      const t = b.createElement(e) as HTMLScriptElement
      t.async = true
      t.src = v
      b.head.appendChild(t)
    })(window, document, 'script', 'https://connect.facebook.net/en_US/fbevents.js')
    /* eslint-enable */
    window.fbq!('init', metaPixelId)
    window.fbq!('track', 'PageView')
  }
})
