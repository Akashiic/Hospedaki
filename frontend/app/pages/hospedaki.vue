<script setup lang="ts">
// Página do Hospedaki para donos de pousada. Existe só na pousada de demonstração:
// o site de uma pousada de verdade não mostra a venda do sistema.
definePageMeta({ layout: false })

const { property } = await useProperty()
if (!property.value.demo) throw createError({ status: 404, message: 'Página não encontrada', fatal: true })

const config = useRuntimeConfig().public
const contactWhatsapp = config.salesWhatsapp
  ? `https://wa.me/${config.salesWhatsapp}?text=${encodeURIComponent('Olá! Vi a demonstração do Hospedaki e quero um site de reservas para a minha pousada.')}`
  : null
const contactEmail = config.salesEmail ? `mailto:${config.salesEmail}?subject=${encodeURIComponent('Hospedaki para a minha pousada')}` : null

useSeoMeta({
  title: 'Hospedaki · Site de reservas diretas para pousadas',
  description: 'Seu próprio site de reservas, com calendário, preços e painel do dono. O hóspede reserva direto com você, sem a comissão do Airbnb e do Booking.',
})

const fees = [
  { name: 'Airbnb', value: '~15,5%', text: 'de cada reserva, na tarifa paga só pelo anfitrião.' },
  { name: 'Booking.com', value: '15% ou mais', text: 'de cada reserva, conforme o contrato e a visibilidade.' },
  { name: 'Hospedaki', value: '0%', text: 'de comissão por reserva. Você paga só uma taxa mínima pelo sistema.', ours: true },
]

const steps = [
  { title: 'Montamos o seu site', text: 'Com o nome, as cores, as fotos, as acomodações e os preços da sua pousada, inclusive fim de semana e temporada.' },
  { title: 'O hóspede reserva direto', text: 'Ele vê os dias livres no calendário, recebe o valor na hora e envia o pedido. As datas ficam seguras para ele.' },
  { title: 'Você confirma no painel', text: 'Chega um aviso por e-mail. No painel você confirma ou recusa e fala com o hóspede pelo WhatsApp.' },
]

const features = [
  'Site próprio com a cara da sua pousada, rápido no celular',
  'Calendário por acomodação, sem reserva duplicada',
  'Preço por diária, fim de semana e temporada (feriados, réveillon, férias)',
  'Painel do dono: pedidos, confirmações e datas bloqueadas',
  'Bloqueio das datas vendidas no Airbnb e no Booking',
  'Aviso por e-mail a cada pedido novo',
  'Google Analytics e Pixel do Meta para medir seus anúncios',
]

const soon = ['Pagamento por Pix', 'Calendário sincronizado com Airbnb e Booking', 'Aviso automático por WhatsApp']
</script>

<template>
  <div class="sales">
    <header class="bar">
      <div class="container bar-inner">
        <NuxtLink to="/hospedaki" class="logo">Hospedaki</NuxtLink>
        <nav aria-label="Demonstração">
          <NuxtLink to="/">Pousada de exemplo</NuxtLink>
          <NuxtLink to="/painel/entrar">Painel do dono</NuxtLink>
        </nav>
      </div>
    </header>

    <main id="conteudo">
      <section class="hero">
        <div class="container">
          <p class="eyebrow">Para pousadas e hospedarias</p>
          <h1>Seu próprio site de reservas. Sem comissão por reserva.</h1>
          <p class="lead">
            O hóspede encontra a sua pousada, escolhe as datas e reserva direto com você,
            sem deixar 15% de cada estadia com o Airbnb ou o Booking.
          </p>
          <div class="actions">
            <NuxtLink to="/" class="btn btn-amber">Ver a pousada de exemplo <span class="arrow">→</span></NuxtLink>
            <NuxtLink to="/painel/entrar" class="btn btn-outline-light">Testar o painel do dono</NuxtLink>
          </div>
          <p class="hint">A demonstração usa o {{ property.name }}. Faça um pedido de reserva no site e veja ele chegar no painel.</p>
        </div>
      </section>

      <section class="container block">
        <p class="eyebrow">O problema</p>
        <h2>Quanto fica com as plataformas a cada reserva</h2>
        <div class="fees">
          <div v-for="f in fees" :key="f.name" class="fee" :class="{ ours: f.ours }">
            <p class="fee-name">{{ f.name }}</p>
            <p class="fee-value">{{ f.value }}</p>
            <p>{{ f.text }}</p>
          </div>
        </div>
        <p class="fine">Comissões aproximadas das plataformas; a taxa exata depende do seu contrato.</p>
      </section>

      <section class="calc-section">
        <div class="container block">
          <p class="eyebrow">Calculadora</p>
          <h2>Quanto você deixaria de pagar</h2>
          <p class="sub">Não precisa sair das plataformas: elas continuam trazendo hóspedes novos. Quem volta, indica ou acha você no Instagram reserva pelo seu site.</p>
          <SalesSavings />
        </div>
      </section>

      <section class="container block">
        <p class="eyebrow">Como funciona</p>
        <h2>Do primeiro clique à reserva confirmada</h2>
        <ol class="steps">
          <li v-for="(s, i) in steps" :key="s.title">
            <span class="num" aria-hidden="true">{{ i + 1 }}</span>
            <h3>{{ s.title }}</h3>
            <p>{{ s.text }}</p>
          </li>
        </ol>
      </section>

      <section class="container block features">
        <div>
          <p class="eyebrow">O que já vem pronto</p>
          <h2>Tudo o que a sua pousada precisa para vender direto</h2>
        </div>
        <div>
          <ul class="list">
            <li v-for="f in features" :key="f">{{ f }}</li>
          </ul>
          <p class="soon-title">Em breve</p>
          <ul class="list soon">
            <li v-for="f in soon" :key="f">{{ f }}</li>
          </ul>
        </div>
      </section>

      <section class="cta">
        <div class="container">
          <h2>Quer um site assim para a sua pousada?</h2>
          <p>Mostre a demonstração para quem cuida das reservas e fale com a gente.</p>
          <div class="actions">
            <a v-if="contactWhatsapp" :href="contactWhatsapp" class="btn btn-amber" target="_blank" rel="noopener">Falar no WhatsApp <span class="arrow">→</span></a>
            <a v-if="contactEmail" :href="contactEmail" class="btn btn-outline-light">Mandar um e-mail</a>
            <NuxtLink v-if="!contactWhatsapp && !contactEmail" to="/" class="btn btn-amber">Ver a pousada de exemplo <span class="arrow">→</span></NuxtLink>
            <NuxtLink to="/painel/entrar" class="btn btn-outline-light">Testar o painel do dono</NuxtLink>
          </div>
        </div>
      </section>
    </main>
  </div>
</template>

<style scoped lang="scss">
.sales { min-height: 100vh; background: var(--paper); }
.bar {
  position: sticky; top: 0; z-index: 30;
  padding-top: env(safe-area-inset-top, 0px);
  background: rgb(14 20 17 / .94); backdrop-filter: blur(10px); -webkit-backdrop-filter: blur(10px);
  --focus: var(--focus-dark);
}
.bar-inner { display: flex; align-items: center; justify-content: space-between; gap: 1rem; min-height: 60px; }
.logo { @include display(1.7rem); color: var(--on-night); text-decoration: none; }
.bar nav { display: flex; gap: .2rem; }
.bar nav a {
  display: inline-flex; align-items: center; min-height: 44px; padding: 0 .7rem;
  color: var(--on-night); font-weight: 700; font-size: .88rem; text-decoration: none; border-radius: 999px;
  &:hover { text-decoration: underline; }
}

.hero {
  --focus: var(--focus-dark);
  background: radial-gradient(120% 90% at 80% 0%, #24342b 0%, var(--night) 60%);
  color: var(--on-night);
  @include section-pad(clamp(4rem, 10vw, 7rem), clamp(4rem, 9vw, 6rem));
}
.hero .eyebrow { @include eyebrow(var(--amber-soft)); }
.hero h1 { @include display(clamp(2.6rem, 7vw, 5rem)); max-width: 15ch; margin: .8rem 0 1.2rem; }
.lead { font-size: clamp(1.05rem, 2vw, 1.25rem); max-width: 40rem; color: var(--on-night-muted); margin: 0; }
.actions { display: flex; flex-wrap: wrap; gap: .7rem; margin-top: 2rem; }
.hint { margin: 1.2rem 0 0; font-size: .9rem; color: var(--on-night-muted); }

.block { @include section-pad(clamp(3.5rem, 8vw, 6rem)); }
.block h2 { @include display(clamp(2rem, 4.5vw, 3.2rem)); margin: .6rem 0 1.5rem; max-width: 22ch; }
.sub { margin: -0.6rem 0 1.8rem; max-width: 42rem; color: var(--muted); }
.fine { margin: 1rem 0 0; font-size: .82rem; color: var(--muted); }

.fees { display: grid; gap: 1rem; @include above(md) { grid-template-columns: repeat(3, 1fr); } }
.fee { background: var(--card); border: 1px solid var(--line); border-radius: var(--radius); padding: 1.4rem; }
.fee p { margin: 0; }
.fee-name { font-weight: 700; }
.fee-value { @include display(clamp(2.4rem, 5vw, 3.2rem)); margin: .3rem 0 .4rem !important; color: var(--danger); }
.fee.ours { background: var(--night); color: var(--on-night); border-color: var(--night); }
.fee.ours .fee-value { color: var(--amber); }

.calc-section { background: var(--soft); }

.steps { list-style: none; padding: 0; margin: 0; display: grid; gap: 1rem; @include above(md) { grid-template-columns: repeat(3, 1fr); } }
.steps li { background: var(--card); border: 1px solid var(--line); border-radius: var(--radius); padding: 1.4rem; }
.steps h3 { @include display(1.6rem); margin: .7rem 0 .4rem; }
.steps p { margin: 0; color: var(--muted); }
.num { display: grid; place-items: center; width: 2.2rem; height: 2.2rem; border-radius: 50%; background: var(--brand); color: var(--brand-ink); font-weight: 700; }

.features { display: grid; gap: 1rem; @include above(lg) { grid-template-columns: 1fr 1.1fr; } }
.list { list-style: none; padding: 0; margin: 0; display: grid; gap: .55rem; }
.list li { position: relative; padding-left: 1.7rem; }
.list li::before { content: "✓"; position: absolute; left: 0; color: var(--brand-text); font-weight: 700; }
.soon-title { @include eyebrow(var(--muted), sm); margin-top: 1.6rem !important; margin-bottom: .55rem !important; }
.soon li { color: var(--muted); }
.soon li::before { content: "·"; color: var(--muted); }

.cta {
  --focus: var(--focus-dark);
  background: var(--night); color: var(--on-night); text-align: left;
  @include section-pad(clamp(3.5rem, 8vw, 6rem));
}
.cta h2 { @include display(clamp(2rem, 5vw, 3.4rem)); margin: 0 0 .8rem; max-width: 20ch; }
.cta p { margin: 0; color: var(--on-night-muted); }
</style>
