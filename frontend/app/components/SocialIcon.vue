<script setup lang="ts">
import type { SocialNetwork } from '~/utils/social'

// Ícone de uma rede social (desenhos em utils/social.ts), na cor do texto.
const props = defineProps<{ network: SocialNetwork, size?: number }>()
const path = computed(() => SOCIAL_NETWORKS.find(n => n.key === props.network)?.path ?? '')
</script>

<template>
  <svg :width="size ?? 20" :height="size ?? 20" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path :d="path" fill="currentColor" fill-rule="evenodd" /></svg>
</template>
