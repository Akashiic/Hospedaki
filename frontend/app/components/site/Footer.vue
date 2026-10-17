<script setup lang="ts">
import type { Property } from '~/composables/useApi'

const props = defineProps<{ property: Property, showCall?: boolean }>()
const year = new Date().getFullYear()

const whatsappLink = computed(() => props.property.whatsapp
  ? `https://wa.me/${props.property.whatsapp}?text=${encodeURIComponent('Olá! Gostaria de informações sobre hospedagem.')}`
  : null)
const phoneLabel = computed(() => formatPhone(props.property.whatsapp))
</script>

<template>
  <footer class="footer">
    <div v-if="showCall" class="container call">
      <p v-reveal class="eyebrow">Reserve direto</p>
      <h2 v-reveal style="--i: 1">Sua estadia começa aqui.</h2>
      <div v-reveal class="actions" style="--i: 3">
        <NuxtLink to="/reservar" class="btn btn-light">Ver datas e reservar <span class="arrow">→</span></NuxtLink>
        <a v-if="whatsappLink" :href="whatsappLink" class="btn btn-outline-light" target="_blank" rel="noopener">Falar no WhatsApp</a>
      </div>
    </div>

    <div class="container cols">
      <div>
        <p class="name">{{ property.name }}</p>
        <p v-if="property.tagline" class="muted">{{ property.tagline }}</p>
      </div>
      <div>
        <p class="label">Contato</p>
        <p v-if="property.whatsapp">WhatsApp {{ phoneLabel }}</p>
        <p v-if="property.phone">Telefone {{ formatPhone(property.phone) }}</p>
        <p v-if="property.email">{{ property.email }}</p>
      </div>
      <div>
        <p class="label">Onde estamos</p>
        <p v-if="property.address">{{ property.address }}</p>
        <p v-if="property.city">{{ property.city }}<template v-if="property.state">, {{ property.state }}</template></p>
      </div>
      <nav aria-label="Rodapé">
        <p class="label">Navegue</p>
        <NuxtLink to="/o-lugar">O lugar</NuxtLink>
        <NuxtLink to="/acomodacoes">Acomodações</NuxtLink>
        <NuxtLink to="/reservar">Reservar</NuxtLink>
        <NuxtLink to="/contato">Contato</NuxtLink>
      </nav>
    </div>

    <div class="container legal">
      <p>© {{ year }} {{ property.name }}</p>
      <p>Reservas por Hospedaki</p>
    </div>
  </footer>
</template>

<style scoped lang="scss">
.footer { background: var(--night); color: var(--on-night); padding-bottom: env(safe-area-inset-bottom, 0px); }
.eyebrow { @include eyebrow(var(--amber)); }
.call { @include section-pad(clamp(5rem, 11vw, 9rem), clamp(4rem, 9vw, 7rem)); border-bottom: 1px solid rgb(255 255 255 / .1); }
h2 { @include display(clamp(3rem, 8.5vw, 7rem)); margin: .5rem 0 2.2rem; max-width: 14ch; }
.actions { display: flex; flex-wrap: wrap; gap: .8rem; }
.cols {
  display: grid; grid-template-columns: 1.4fr repeat(3, 1fr); gap: 2rem; padding-block: 3.5rem 3rem;
  p { margin: 0 0 .35rem; }
  a { display: block; color: var(--on-night); text-decoration: none; margin-bottom: .35rem; opacity: .85; transition: opacity $dur-sm $ease-move; &:hover { opacity: 1; } }
  @include below(lg) { grid-template-columns: 1fr 1fr; > :first-child { grid-column: 1 / -1; } }
}
.name { @include display(2rem); }
.muted { color: var(--on-night-muted); }
.label { @include eyebrow(var(--on-night-muted)); letter-spacing: .16em; font-size: .7rem; margin-bottom: .8rem !important; }
.legal {
  display: flex; justify-content: space-between; flex-wrap: wrap; gap: .5rem; padding-block: 1.4rem 2rem;
  border-top: 1px solid rgb(255 255 255 / .1); color: var(--on-night-muted); font-size: .85rem;
  p { margin: 0; }
}
</style>
