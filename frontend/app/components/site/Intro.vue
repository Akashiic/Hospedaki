<script setup lang="ts">
import type { Property } from '~/composables/useApi'

defineProps<{ property: Property }>()
</script>

<template>
  <section id="o-lugar" class="intro">
    <div class="container grid">
      <div class="lead">
        <h2 v-reveal class="eyebrow">O lugar</h2>
        <p v-reveal class="statement" style="--d: .1s">{{ property.description }}</p>
      </div>
      <ul class="highlights">
        <li v-for="(item, i) in property.highlights" :key="item.title" v-reveal :style="{ '--d': `${0.15 + i * 0.12}s` }">
          <h3>{{ item.title }}</h3>
          <p>{{ item.text }}</p>
        </li>
      </ul>
    </div>
    <div v-reveal class="container facts">
      <div><span>Check-in</span><strong>a partir das {{ property.check_in_time }}</strong></div>
      <div><span>Check-out</span><strong>até {{ property.check_out_time }}</strong></div>
      <div><span>Hóspedes</span><strong>até {{ property.accommodations.reduce((n, a) => n + a.max_guests, 0) }} pessoas</strong></div>
      <div v-if="property.city"><span>Onde</span><strong>{{ property.city }}<template v-if="property.state">, {{ property.state }}</template></strong></div>
    </div>
  </section>
</template>

<style scoped>
.intro { padding-block: clamp(5rem, 12vw, 9rem) clamp(3rem, 7vw, 5rem); }
.grid { display: grid; grid-template-columns: minmax(0, 1.25fr) minmax(0, 1fr); gap: clamp(2.5rem, 6vw, 6rem); align-items: end; }
.eyebrow { margin: 0; font-family: var(--font-body); line-height: 1.4; }
.statement { font-family: var(--font-display); font-size: clamp(2rem, 4.2vw, 3.4rem); line-height: 1.12; margin: 1.2rem 0 0; text-wrap: pretty; }
.highlights { list-style: none; margin: 0; padding: 0; display: grid; gap: 1.6rem; }
.highlights li { border-top: 1px solid var(--line); padding-top: 1.1rem; }
.highlights h3 { margin: 0 0 .3rem; font-size: 1.55rem; }
.highlights p { margin: 0; color: var(--muted); }
.facts { display: grid; grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); gap: 1.5rem 2rem; margin-top: clamp(3.5rem, 8vw, 6rem); }
.facts div { display: grid; gap: .2rem; border-left: 1px solid var(--line); padding-left: 1rem; }
.facts span { text-transform: uppercase; letter-spacing: .16em; font-size: .7rem; font-weight: 700; color: var(--muted); }
.facts strong { font-weight: 500; font-size: 1.05rem; }
@media (max-width: 860px) { .grid { grid-template-columns: minmax(0, 1fr); } }
</style>
