<script setup lang="ts">
// Como o link aparece ao ser enviado no WhatsApp: imagem, título, descrição e endereço.
defineProps<{ title: string, description: string | null, image: string | null }>()
const host = import.meta.client ? window.location.host : 'seusite.com.br'
</script>

<template>
  <figure class="share" aria-label="Prévia ao compartilhar no WhatsApp">
    <div class="bubble">
      <div class="card">
        <img v-if="image" :src="image" alt="" class="img">
        <div v-else class="img none">Sem imagem</div>
        <div class="text">
          <strong>{{ title }}</strong>
          <span v-if="description" class="desc">{{ description }}</span>
          <span class="host">{{ host }}</span>
        </div>
      </div>
      <span class="link">https://{{ host }}</span>
    </div>
  </figure>
</template>

<style scoped lang="scss">
.share { margin: 0; padding: 1rem; border-radius: 14px; background: #efe7dd; display: grid; justify-items: end; min-width: 0; }
.bubble { width: min(100%, 330px); background: #d9fdd3; border-radius: 10px 0 10px 10px; padding: 4px 4px .5rem; box-shadow: 0 1px 1px rgb(0 0 0 / .08); display: grid; gap: .35rem; }
.card { background: rgb(0 0 0 / .05); border-radius: 7px; overflow: hidden; display: grid; }
.img { width: 100%; aspect-ratio: 1200 / 630; object-fit: cover; display: block; }
.img.none { display: grid; place-items: center; color: #667781; font-size: .8rem; background: rgb(0 0 0 / .04); }
.text { display: grid; gap: .15rem; padding: .5rem .6rem .55rem; font-family: system-ui, sans-serif; }
.text strong { font-size: .86rem; color: #111b21; line-height: 1.3; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }
.desc { font-size: .78rem; color: #667781; line-height: 1.35; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }
.host { font-size: .72rem; color: #667781; }
.link { padding: 0 .4rem; font-size: .84rem; color: #027eb5; font-family: system-ui, sans-serif; overflow-wrap: anywhere; }
</style>
