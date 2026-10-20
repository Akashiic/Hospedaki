<script setup lang="ts">
// Como a página aparece numa busca do Google: endereço, título (azul) e descrição. O Google corta
// o título perto de 60 caracteres e a descrição perto de 160; a prévia corta do mesmo jeito.
const props = defineProps<{ title: string, description: string | null, path: string, name: string, iconUrl?: string | null }>()

const origin = import.meta.client ? window.location.origin : ''
const host = computed(() => origin.replace(/^https?:\/\//, ''))
const crumbs = computed(() => props.path.split('/').filter(Boolean))
const cut = (text: string, max: number) => text.length > max ? `${text.slice(0, max - 1).trimEnd()}…` : text
</script>

<template>
  <figure class="google" aria-label="Prévia no Google">
    <div class="site">
      <span class="fav">
        <img v-if="iconUrl" :src="iconUrl" alt="">
        <span v-else>{{ name.slice(0, 1) }}</span>
      </span>
      <span class="where">
        <span class="name">{{ name }}</span>
        <span class="url">{{ origin || 'https://seusite.com.br' }}<template v-for="c in crumbs" :key="c"> › {{ c }}</template></span>
      </span>
    </div>
    <p class="title">{{ cut(title, 60) }}</p>
    <p class="desc">{{ description ? cut(description, 160) : 'Sem descrição: o Google escolhe um trecho da página.' }}</p>
    <figcaption class="sr-only">Prévia no Google de {{ host }}{{ path }}</figcaption>
  </figure>
</template>

<style scoped lang="scss">
.google { margin: 0; padding: 1rem 1.1rem; background: #fff; border-radius: 14px; box-shadow: inset 0 0 0 1px var(--line); font-family: arial, sans-serif; display: grid; gap: .25rem; min-width: 0; }
.site { display: flex; align-items: center; gap: .6rem; min-width: 0; }
.fav { display: grid; place-items: center; flex: none; width: 28px; height: 28px; border-radius: 50%; background: #f1f3f4; overflow: hidden; font-size: .8rem; color: #4d5156; }
.fav img { width: 18px; height: 18px; object-fit: cover; border-radius: 4px; }
.where { display: grid; min-width: 0; line-height: 1.25; }
.name { font-size: .86rem; color: #202124; }
.url { font-size: .76rem; color: #4d5156; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.title { margin: .3rem 0 0; color: #1a0dab; font-size: 1.2rem; line-height: 1.3; overflow-wrap: anywhere; }
.desc { margin: 0; color: #4d5156; font-size: .86rem; line-height: 1.55; overflow-wrap: anywhere; }
</style>
