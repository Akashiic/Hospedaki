<script setup lang="ts">
// Barra fixa no pé do formulário: diz se há mudanças e oferece desfazer e salvar.
defineProps<{ dirty: boolean, saving: boolean, error?: string, label?: string }>()
defineEmits<{ undo: [] }>()
</script>

<template>
  <div class="a-savebar">
    <p class="a-status" :class="{ dirty, error: !!error }" aria-live="polite">{{ error || (dirty ? 'Alterações não salvas' : 'Tudo salvo') }}</p>
    <button type="button" class="btn btn-ghost btn-sm" :disabled="!dirty || saving" @click="$emit('undo')">Desfazer</button>
    <button type="submit" class="btn btn-sm" :disabled="!dirty || saving">{{ saving ? 'Salvando...' : (label ?? 'Salvar e publicar') }}</button>
  </div>
</template>
