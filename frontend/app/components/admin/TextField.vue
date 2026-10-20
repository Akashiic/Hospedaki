<script setup lang="ts">
// Campo de texto do painel com rótulo, contador de caracteres e o texto padrão como exemplo:
// vazio, o site usa o padrão (mostrado no placeholder).
const model = defineModel<string | null>({ required: true })
const props = defineProps<{ id: string, label: string, max?: number, placeholder?: string | null, hint?: string, error?: string, rows?: number, count?: boolean, type?: string, inputmode?: 'text' | 'tel' | 'email' | 'numeric' | 'url' }>()

const text = computed({
  get: () => model.value ?? '',
  set: (value: string) => { model.value = value === '' ? null : value },
})
// O contador avisa perto do limite que o Google costuma mostrar, sem impedir de digitar até o máximo.
const near = computed(() => props.max && text.value.length > props.max * 0.9)
</script>

<template>
  <div class="a-field">
    <div class="a-field-head">
      <label :for="id">{{ label }}</label>
      <span v-if="max && count !== false" class="a-count" :class="{ near }" aria-hidden="true">{{ text.length }}/{{ max }}</span>
    </div>
    <textarea v-if="rows" :id="id" v-model="text" :rows="rows" :maxlength="max" :placeholder="placeholder ?? undefined" :aria-invalid="!!error || undefined" />
    <input v-else :id="id" v-model="text" :type="type ?? 'text'" :inputmode="inputmode" :maxlength="max" :placeholder="placeholder ?? undefined" :aria-invalid="!!error || undefined">
    <small v-if="error" class="a-err">{{ error }}</small>
    <small v-else-if="hint" class="a-hint">{{ hint }}</small>
  </div>
</template>
