<script setup lang="ts">
// Campo de valor em reais: o dono digita "450" ou "1.250,50" e o v-model guarda centavos (ou null).
const model = defineModel<number | null>({ required: true })
defineProps<{ id: string, placeholder?: string, required?: boolean }>()

const text = ref(inputFromCents(model.value))
// Valor trocado por fora (outra acomodação aberta, desfazer): o campo acompanha.
watch(model, (cents) => {
  if (cents !== centsFromInput(text.value)) text.value = inputFromCents(cents)
})
function onInput() {
  model.value = centsFromInput(text.value)
}
function onBlur() {
  text.value = inputFromCents(model.value)
}
</script>

<template>
  <span class="money">
    <span class="prefix" aria-hidden="true">R$</span>
    <input :id="id" v-model="text" inputmode="decimal" autocomplete="off" :placeholder="placeholder ?? '0,00'" :required="required" @input="onInput" @blur="onBlur">
  </span>
</template>

<style scoped lang="scss">
.money { position: relative; display: block; }
.prefix { position: absolute; left: .85rem; top: 50%; transform: translateY(-50%); color: var(--muted); font-weight: 500; pointer-events: none; }
input { padding-left: 2.6rem; font-variant-numeric: tabular-nums; }
</style>
