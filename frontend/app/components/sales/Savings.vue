<script setup lang="ts">
import { PLATFORM_FEES, savings } from '~/utils/savings'

// Calculadora da página do Hospedaki: quanto de comissão a pousada deixa de pagar
// quando parte das reservas das plataformas passa a vir direto pelo site dela.
const revenue = ref(10000)
const fee = ref<number>(PLATFORM_FEES[0].fee)
const direct = ref(40)

const result = computed(() => savings(Number(revenue.value), Number(fee.value), Number(direct.value)))
const percent = (v: number) => v.toLocaleString('pt-BR', { maximumFractionDigits: 1 })
</script>

<template>
  <div class="calc">
    <form class="inputs" @submit.prevent>
      <label for="revenue">
        Quanto você fatura por mês com Airbnb e Booking
        <span class="money"><span aria-hidden="true">R$</span><input id="revenue" v-model.number="revenue" type="number" inputmode="numeric" min="0" step="100"></span>
      </label>

      <fieldset>
        <legend>Comissão da plataforma</legend>
        <div class="chips">
          <button v-for="p in PLATFORM_FEES" :key="p.label" type="button" :aria-pressed="fee === p.fee" @click="fee = p.fee">
            {{ p.label }} · {{ percent(p.fee) }}%
          </button>
          <label class="custom" for="fee">
            <span class="sr-only">Outra comissão, em porcentagem</span>
            <input id="fee" v-model.number="fee" type="number" inputmode="decimal" min="0" max="100" step="0.5">
            <span aria-hidden="true">%</span>
          </label>
        </div>
      </fieldset>

      <label for="direct">
        <span>Parte das reservas que passaria a vir direto: <strong>{{ direct }}%</strong></span>
        <input id="direct" v-model.number="direct" type="range" min="0" max="100" step="5">
      </label>
    </form>

    <div class="out" aria-live="polite">
      <p class="label">Hoje você paga de comissão</p>
      <p class="today">{{ formatMoney(result.commissionMonth) }} <small>por mês</small></p>
      <p class="label">Com o Hospedaki, deixaria de pagar</p>
      <p class="big">{{ formatMoney(result.savedYear) }} <small>por ano</small></p>
      <p class="month">{{ formatMoney(result.savedMonth) }} por mês, que ficam com você.</p>
      <p class="fine">Comissões aproximadas: a taxa exata depende do seu contrato com cada plataforma.</p>
    </div>
  </div>
</template>

<style scoped lang="scss">
.calc {
  display: grid; gap: 1.2rem;
  @include above(lg) { grid-template-columns: 1.1fr 1fr; align-items: stretch; }
}
.inputs, .out { border-radius: var(--radius); padding: clamp(1.2rem, 3vw, 1.8rem); }
.inputs { display: grid; gap: 1.2rem; background: var(--card); border: 1px solid var(--line); }
label, legend { display: grid; gap: .45rem; font-weight: 700; font-size: .92rem; }
fieldset { border: 0; margin: 0; padding: 0; display: grid; gap: .45rem; }
legend { padding: 0; margin-bottom: .45rem; }
.money { display: flex; align-items: center; gap: .5rem; font-size: 1.1rem; }
.money input { font-size: 1.1rem; font-weight: 700; }
.chips { display: flex; flex-wrap: wrap; gap: .5rem; }
.chips button {
  min-height: 44px; padding: 0 1rem; border-radius: 999px; border: 1px solid var(--line);
  background: var(--card); color: var(--text); font: inherit; font-size: .88rem; font-weight: 700; cursor: pointer;
  &[aria-pressed="true"] { background: var(--brand); color: var(--brand-ink); border-color: var(--brand); }
}
.custom { display: flex; align-items: center; gap: .35rem; font-weight: 700; }
.custom input { width: 5.5rem; min-height: 44px; }
input[type=range] { width: 100%; min-height: 44px; accent-color: var(--brand); }

.out { background: var(--night); color: var(--on-night); display: grid; align-content: center; gap: .2rem; }
.out p { margin: 0; }
.label { @include eyebrow(var(--amber-soft), sm); margin-top: .6rem !important; }
.today { font-size: 1.3rem; font-weight: 700; }
.big { @include display(clamp(2.6rem, 6vw, 3.6rem)); color: var(--amber); }
small { font-family: var(--font-body); font-size: .9rem; color: var(--on-night-muted); font-weight: 400; }
.month { color: var(--on-night); }
.fine { margin-top: .9rem !important; font-size: .8rem; color: var(--on-night-muted); }
</style>
