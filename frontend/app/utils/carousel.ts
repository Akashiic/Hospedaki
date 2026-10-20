// Ajustes comuns dos carrosséis do site (Swiper): textos de acessibilidade em português e
// a preferência de movimento reduzido.

/** Mensagens do módulo A11y do Swiper. `item` nomeia cada slide para o leitor de tela ("foto"). */
export function carouselA11y(label: string, item = 'foto') {
  return {
    containerMessage: label,
    containerRoleDescriptionMessage: 'carrossel',
    itemRoleDescriptionMessage: item,
    slideLabelMessage: '{{index}} de {{slidesLength}}',
    prevSlideMessage: 'Foto anterior',
    nextSlideMessage: 'Próxima foto',
    firstSlideMessage: 'Esta é a primeira foto',
    lastSlideMessage: 'Esta é a última foto',
    paginationBulletMessage: 'Ir para a foto {{index}}',
  }
}

/** Visitante pediu menos movimento ao sistema (só no navegador; no servidor, false). */
export function prefersReducedMotion(): boolean {
  return typeof window !== 'undefined' && window.matchMedia('(prefers-reduced-motion: reduce)').matches
}
