<script setup lang="ts">
import type { NuxtError } from '#app'
import type { Property } from '~/composables/useApi'

// Página de erro do site (montada por app/error.vue), em português e com as cores da casa. Fica
// fora do layout de propósito: o layout busca a pousada de novo e, com a API fora do ar (503),
// quebraria outra vez.
// Os dados da pousada entram só quando já estão em cache (um 404 dentro do site); sem eles, a
// página continua de pé, sem nome nem WhatsApp.
const props = defineProps<{ error: NuxtError }>()
const { data: property } = useNuxtData<Property>('property')

// A cópia sai do status, não da mensagem (que pode vir técnica ou em inglês).
const status = computed(() => props.error.status ?? props.error.statusCode ?? 500)
const notFound = computed(() => status.value === 404)
const copy = computed(() => notFound.value
  ? { eyebrow: 'Erro 404', title: 'Página não encontrada', text: 'O endereço pode ter mudado. Comece pelo início ou veja as acomodações.' }
  : { eyebrow: `Erro ${status.value}`, title: 'Estamos fora do ar por um instante', text: 'Tente de novo em alguns segundos.' })

const whatsapp = computed(() => property.value?.whatsapp
  ? `https://wa.me/${property.value.whatsapp}?text=${encodeURIComponent('Olá! Tentei acessar o site e não consegui.')}`
  : null)

useHead({
  title: computed(() => [copy.value.title, property.value?.name].filter(Boolean).join(' · ')),
  meta: [{ name: 'robots', content: 'noindex' }],
})

const go = (path: string) => clearError({ redirect: path })
// Recarrega o mesmo endereço do zero: só limpar o erro não refaria a busca que falhou no servidor.
const retry = () => reloadNuxtApp({ persistState: false })
</script>

<template>
  <div class="error-page" :style="property ? { '--brand': property.primary_color } : undefined">
    <main id="conteudo" class="container inner" tabindex="-1">
      <p v-if="property" class="brand">{{ property.name }}</p>
      <p class="eyebrow">{{ copy.eyebrow }}</p>
      <h1>{{ copy.title }}</h1>
      <p class="text">{{ copy.text }}</p>
      <div class="actions">
        <template v-if="notFound">
          <button type="button" class="btn btn-light" @click="go('/')">Início</button>
          <button type="button" class="btn btn-outline-light" @click="go('/acomodacoes')">Acomodações</button>
        </template>
        <button v-else type="button" class="btn btn-light" @click="retry">Tentar de novo</button>
        <a v-if="whatsapp" :href="whatsapp" class="btn btn-outline-light" target="_blank" rel="noopener">Falar no WhatsApp</a>
      </div>
    </main>
  </div>
</template>

<style scoped lang="scss">
// Mesma abertura das páginas internas: eyebrow (0) → título (1, só sobe) → texto (2) → ações (3).
.error-page {
  --focus: var(--focus-dark);
  min-height: 100vh; min-height: 100svh; display: flex; align-items: center;
  background: var(--night); color: var(--on-night);
}
.inner { width: 100%; padding-block: clamp(4rem, 12vw, 8rem); }
.inner:focus { outline: none; }
.brand { @include display(1.6rem); margin: 0 0 2.5rem; @include motion-entrance(0); }
.eyebrow { @include eyebrow(var(--amber-soft)); @include motion-entrance(0); }
h1 { @include display(clamp(2.8rem, 8vw, 5.5rem)); margin: .5rem 0 0; max-width: 14ch; @include motion-entrance(1, rise); }
.text { max-width: 34rem; margin: 1.1rem 0 0; color: var(--on-night-muted); font-size: clamp(1rem, 1.5vw, 1.12rem); @include motion-entrance(2); }
.actions { display: flex; flex-wrap: wrap; gap: .8rem; margin-top: 2rem; @include motion-entrance(3); }

@include reduced-motion {
  .brand, .eyebrow, h1, .text, .actions { animation: none; }
}
</style>
