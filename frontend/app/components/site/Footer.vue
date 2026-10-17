<script setup lang="ts">
import type { Property } from '~/composables/useApi'

const props = defineProps<{ property: Property }>()
const emit = defineEmits<{ book: [] }>()
const scrollTo = useScrollTo()
const year = new Date().getFullYear()

const whatsappLink = computed(() => props.property.whatsapp
  ? `https://wa.me/${props.property.whatsapp}?text=${encodeURIComponent('Olá! Gostaria de informações sobre hospedagem.')}`
  : null)
const phoneLabel = computed(() => {
  const d = props.property.whatsapp?.replace(/\D/g, '').replace(/^55/, '') ?? ''
  return d.length >= 10 ? `(${d.slice(0, 2)}) ${d.slice(2, -4)}-${d.slice(-4)}` : props.property.whatsapp
})
</script>

<template>
  <footer id="contato" class="footer">
    <div class="container call">
      <p v-reveal class="eyebrow">Reserve direto</p>
      <h2 v-reveal style="--d: .1s">Sua estadia começa aqui.</h2>
      <div v-reveal class="actions" style="--d: .2s">
        <button type="button" class="btn btn-light" @click="emit('book')">Ver datas e reservar <span class="arrow">→</span></button>
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
        <p v-if="property.email">{{ property.email }}</p>
      </div>
      <div>
        <p class="label">Onde estamos</p>
        <p v-if="property.address">{{ property.address }}</p>
        <p v-if="property.city">{{ property.city }}<template v-if="property.state">, {{ property.state }}</template></p>
      </div>
      <div>
        <p class="label">Navegue</p>
        <a href="#o-lugar" @click.prevent="scrollTo('o-lugar')">O lugar</a>
        <a href="#acomodacoes" @click.prevent="scrollTo('acomodacoes')">Acomodações</a>
        <a href="#galeria" @click.prevent="scrollTo('galeria')">Galeria</a>
        <a href="#reservar" @click.prevent="scrollTo('reservar')">Reservar</a>
      </div>
    </div>

    <div class="container legal">
      <p>© {{ year }} {{ property.name }}</p>
      <p>Reservas por Hospedaki</p>
    </div>
  </footer>
</template>

<style scoped>
.footer { background: var(--night); color: var(--on-night); padding-top: clamp(5rem, 11vw, 9rem); padding-bottom: env(safe-area-inset-bottom, 0px); }
.eyebrow { color: var(--amber); }
.call { padding-bottom: clamp(4rem, 9vw, 7rem); border-bottom: 1px solid rgb(255 255 255 / .1); }
h2 { font-size: clamp(3rem, 8.5vw, 7rem); margin: .5rem 0 2.2rem; max-width: 14ch; }
.actions { display: flex; flex-wrap: wrap; gap: .8rem; }
.cols { display: grid; grid-template-columns: 1.4fr repeat(3, 1fr); gap: 2rem; padding-block: 3rem; }
.cols p { margin: 0 0 .35rem; }
.cols a { display: block; color: var(--on-night); text-decoration: none; margin-bottom: .35rem; opacity: .85; transition: opacity .2s; }
.cols a:hover { opacity: 1; }
.name { font-family: var(--font-display); font-size: 2rem; line-height: 1.1; }
.muted { color: var(--on-night-muted); }
.label { text-transform: uppercase; letter-spacing: .16em; font-size: .7rem; font-weight: 700; color: var(--on-night-muted); margin-bottom: .8rem !important; }
.legal { display: flex; justify-content: space-between; flex-wrap: wrap; gap: .5rem; padding-block: 1.4rem 2rem; border-top: 1px solid rgb(255 255 255 / .1); color: var(--on-night-muted); font-size: .85rem; }
.legal p { margin: 0; }
@media (max-width: 860px) { .cols { grid-template-columns: 1fr 1fr; } .cols > :first-child { grid-column: 1 / -1; } }
</style>
