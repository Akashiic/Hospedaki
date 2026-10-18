declare global {
  interface Window {
    gtag?: (...args: unknown[]) => void
    fbq?: (...args: unknown[]) => void
  }
}

/** Envia eventos de conversão para o Google Analytics e o Pixel do Meta, se configurados. */
export function useTracking() {
  function bookingRequested(value: number) {
    if (!import.meta.client) return
    window.gtag?.('event', 'generate_lead', { currency: 'BRL', value: value / 100 })
    window.fbq?.('track', 'Lead', { currency: 'BRL', value: value / 100 })
  }

  return { bookingRequested }
}
