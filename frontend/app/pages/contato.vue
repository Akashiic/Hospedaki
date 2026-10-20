<script setup lang="ts">
const { property, whatsappLink, gallery } = await useProperty()

const image = computed(() => gallery.value[3]?.url ?? gallery.value[0]?.url)
const page = await useSitePage('contato', { image: () => image.value })

const mapsLink = computed(() => {
  const q = [property.value.address, property.value.city, property.value.state].filter(Boolean).join(', ')
  return q ? `https://www.google.com/maps/search/?api=1&query=${encodeURIComponent(q)}` : null
})

// Como funciona a reserva, na ordem em que acontece.
const steps = [
  { title: 'Você escolhe as datas', text: 'No calendário você vê os dias livres de cada acomodação (os ocupados aparecem riscados) e o valor na hora.' },
  { title: 'Envia o pedido', text: 'Sem pagar nada ainda. As datas ficam seguras para você enquanto a pousada responde.' },
  { title: 'A pousada confirma', text: 'Você recebe a confirmação pelo WhatsApp e combina a forma de pagamento.' },
]
</script>

<template>
  <div>
    <SitePageHeader compact :eyebrow="page.eyebrow" :title="page.title" :lead="page.lead" :image="image" />

    <section class="container contact">
      <div v-reveal class="card main">
        <p class="eyebrow">WhatsApp</p>
        <p class="big">{{ formatPhone(property.whatsapp) }}</p>
        <a v-if="whatsappLink()" :href="whatsappLink()!" class="btn" target="_blank" rel="noopener">Chamar no WhatsApp <span class="arrow">→</span></a>
      </div>
      <div v-if="property.phone" v-reveal class="card" style="--i: 1">
        <p class="eyebrow">Telefone</p>
        <p class="big">{{ formatPhone(property.phone) }}</p>
      </div>
      <div v-if="property.email" v-reveal class="card" style="--i: 2">
        <p class="eyebrow">E-mail</p>
        <p class="big small">{{ property.email }}</p>
      </div>
      <div v-if="property.address || property.city" v-reveal class="card" style="--i: 3">
        <p class="eyebrow">Endereço</p>
        <p class="big small">{{ property.address }}<template v-if="property.address && property.city"><br></template>{{ property.city }}<template v-if="property.state">, {{ property.state }}</template></p>
        <a v-if="mapsLink" :href="mapsLink" class="text-link" target="_blank" rel="noopener">Abrir no mapa <span class="arrow">→</span></a>
      </div>
    </section>

    <section class="container how">
      <p v-reveal class="eyebrow">Como funciona a reserva</p>
      <ol>
        <li v-for="(step, i) in steps" :key="step.title" v-reveal :style="{ '--i': i * 2 }">
          <span class="n">{{ i + 1 }}</span>
          <h2>{{ step.title }}</h2>
          <p>{{ step.text }}</p>
        </li>
      </ol>
      <NuxtLink v-reveal to="/reservar" class="btn">Ver datas e reservar <span class="arrow">→</span></NuxtLink>
    </section>
  </div>
</template>

<style scoped lang="scss">
.contact {
  @include section-pad(clamp(3.5rem, 8vw, 6rem), clamp(2rem, 5vw, 3rem));
  display: grid; gap: 1rem; grid-template-columns: repeat(auto-fit, minmax(min(100%, 250px), 1fr));
}
.card {
  background: var(--card); border-radius: var(--radius); padding: 1.5rem; display: grid; gap: .6rem; align-content: start;
  box-shadow: 0 1px 2px rgb(0 0 0 / .04), 0 18px 40px -30px rgb(20 40 30 / .35);
  &.main { --focus: var(--focus-dark); background: var(--night); color: var(--on-night); .eyebrow { @include eyebrow(var(--amber)); } }
  .btn, .text-link { justify-self: start; margin-top: .4rem; }
}
.big { @include display(clamp(1.8rem, 3vw, 2.4rem)); margin: 0; font-variant-numeric: tabular-nums; overflow-wrap: anywhere; &.small { font-size: clamp(1.4rem, 2.4vw, 1.8rem); line-height: 1.2; } }
.how {
  padding-bottom: clamp(5rem, 10vw, 8rem);
  ol { list-style: none; padding: 0; margin: 2rem 0 2.5rem; display: grid; gap: 2rem; grid-template-columns: repeat(auto-fit, minmax(min(100%, 260px), 1fr)); }
  li { border-top: 1px solid var(--line); padding-top: 1.2rem; }
  .n { font-family: var(--font-display); font-size: 3rem; line-height: 1; color: var(--brand); }
  h2 { @include display(1.7rem); margin: .4rem 0 .3rem; }
  p { margin: 0; color: var(--muted); }
}
</style>
