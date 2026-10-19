<script setup lang="ts">
import type { AdminAccommodation } from '~/composables/useAdminApi'

// Fotos de uma acomodação: enviar (várias de uma vez), legendar, reordenar e excluir.
// A primeira é a capa da acomodação no site. Envio, ordem e exclusão salvam na hora.
const props = defineProps<{ accommodation: AdminAccommodation, demo?: boolean }>()
const emit = defineEmits<{ updated: [acc: AdminAccommodation] }>()

const api = useAdminApi()
const photos = ref(props.accommodation.photos.map(p => ({ ...p })))
watch(() => props.accommodation.photos, (list) => { photos.value = list.map(p => ({ ...p })) })

const busy = ref(false)
const progress = ref('')
const error = ref('')
const input = ref<HTMLInputElement>()

/** Reduz no navegador antes de enviar: foto de celular (8 MB) vira uns 400 KB, e o servidor ainda refaz. */
async function shrink(file: File): Promise<Blob> {
  try {
    const bitmap = await createImageBitmap(file, { imageOrientation: 'from-image' })
    const scale = Math.min(1, 2000 / Math.max(bitmap.width, bitmap.height))
    const canvas = document.createElement('canvas')
    canvas.width = Math.round(bitmap.width * scale)
    canvas.height = Math.round(bitmap.height * scale)
    canvas.getContext('2d')!.drawImage(bitmap, 0, 0, canvas.width, canvas.height)
    bitmap.close()
    return await new Promise<Blob>((resolve, reject) => canvas.toBlob(b => (b ? resolve(b) : reject(new Error('toBlob'))), 'image/jpeg', 0.86))
  }
  catch {
    return file
  }
}

async function upload(files: FileList | null) {
  if (!files?.length) return
  busy.value = true
  error.value = ''
  const list = [...files]
  let latest: AdminAccommodation | null = null
  try {
    for (const [i, file] of list.entries()) {
      progress.value = list.length > 1 ? `Enviando ${i + 1} de ${list.length}...` : 'Enviando...'
      const body = new FormData()
      body.append('photo', await shrink(file), file.name.replace(/\.\w+$/, '') + '.jpg')
      const res = await api<{ data: AdminAccommodation }>(`/accommodations/${props.accommodation.id}/photos`, { method: 'POST', body })
      latest = res.data
    }
  }
  catch (e) {
    error.value = apiError(e)
  }
  finally {
    if (latest) emit('updated', latest)
    busy.value = false
    progress.value = ''
    if (input.value) input.value.value = ''
  }
}

async function saveOrder(list = photos.value) {
  busy.value = true
  error.value = ''
  try {
    const res = await api<{ data: AdminAccommodation }>(`/accommodations/${props.accommodation.id}/photos`, {
      method: 'PUT',
      body: { photos: list.map(p => ({ id: p.id, caption: p.caption || null })) },
    })
    emit('updated', res.data)
  }
  catch (e) {
    error.value = apiError(e)
  }
  finally {
    busy.value = false
  }
}

function move(from: number, to: number) {
  if (to < 0 || to >= photos.value.length) return
  const list = [...photos.value]
  const [item] = list.splice(from, 1)
  list.splice(to, 0, item!)
  photos.value = list
  saveOrder(list)
}

// Legenda salva ao sair do campo, se mudou.
function saveCaption(i: number) {
  const original = props.accommodation.photos.find(p => p.id === photos.value[i]!.id)
  if ((original?.caption ?? '') !== (photos.value[i]!.caption ?? '')) saveOrder()
}

async function remove(id: number) {
  if (!window.confirm('Excluir esta foto?')) return
  busy.value = true
  error.value = ''
  try {
    const res = await api<{ data: AdminAccommodation }>(`/accommodations/${props.accommodation.id}/photos/${id}`, { method: 'DELETE' })
    emit('updated', res.data)
  }
  catch (e) {
    error.value = apiError(e)
  }
  finally {
    busy.value = false
  }
}
</script>

<template>
  <section class="card">
    <div class="title">
      <h3>Fotos</h3>
      <p class="hint">A primeira é a capa. Fotos na horizontal, bem iluminadas, vendem mais.</p>
    </div>

    <TransitionGroup v-if="photos.length" tag="ul" name="a-list" class="grid" :class="{ busy }">
      <li v-for="(p, i) in photos" :key="p.id">
        <figure>
          <img :src="p.url" :alt="p.caption ?? ''" loading="lazy" decoding="async">
          <span v-if="i === 0" class="cover">Capa</span>
        </figure>
        <label :for="`cap-${p.id}`" class="sr-only">Legenda da foto {{ i + 1 }}</label>
        <input :id="`cap-${p.id}`" v-model="p.caption" maxlength="120" placeholder="Legenda (opcional)" @blur="saveCaption(i)" @keydown.enter.prevent="($event.target as HTMLInputElement).blur()">
        <div class="tools">
          <button type="button" :disabled="busy || i === 0" :aria-label="`Mover foto ${i + 1} para antes`" @click="move(i, i - 1)">←</button>
          <button type="button" :disabled="busy || i === photos.length - 1" :aria-label="`Mover foto ${i + 1} para depois`" @click="move(i, i + 1)">→</button>
          <button v-if="i !== 0" type="button" class="text" :disabled="busy" @click="move(i, 0)">Usar como capa</button>
          <button type="button" class="text del" :disabled="busy" :aria-label="`Excluir foto ${i + 1}`" @click="remove(p.id)">Excluir</button>
        </div>
      </li>
    </TransitionGroup>
    <p v-else class="empty">Nenhuma foto ainda. Sem foto, a acomodação aparece no site com um fundo escuro.</p>

    <div class="upload">
      <input id="photo-upload" ref="input" type="file" accept="image/jpeg,image/png,image/webp" multiple class="file" :disabled="busy || demo" @change="upload(($event.target as HTMLInputElement).files)">
      <label for="photo-upload" class="btn btn-sm" :class="{ disabled: busy || demo }">{{ busy && progress ? progress : 'Adicionar fotos' }}</label>
      <span class="hint">{{ demo ? 'Na demonstração o envio de fotos fica desligado.' : 'JPG, PNG ou WebP. Pode escolher várias de uma vez.' }}</span>
    </div>
    <p v-if="error" class="err" role="alert">{{ error }}</p>
  </section>
</template>

<style scoped lang="scss">
.card { background: var(--a-card); border-radius: var(--a-radius); box-shadow: var(--a-shadow); padding: 1.2rem; display: grid; gap: .9rem; min-width: 0; }
.title { display: grid; gap: .2rem; }
h3 { margin: 0; font-size: 1.25rem; }
.hint { margin: 0; color: var(--muted); font-size: .86rem; }
.grid { position: relative; list-style: none; margin: 0; padding: 0; display: grid; gap: .9rem; grid-template-columns: repeat(auto-fill, minmax(min(100%, 140px), 1fr)); transition: opacity $dur-sm $ease-move; }
.grid.busy { opacity: .6; }
.grid li { display: grid; gap: .4rem; align-content: start; }
figure { position: relative; margin: 0; aspect-ratio: 4 / 3; border-radius: 14px; overflow: hidden; background: var(--night); }
img { width: 100%; height: 100%; object-fit: cover; display: block; }
.cover { position: absolute; left: .5rem; top: .5rem; background: var(--amber); color: var(--night); font-size: .72rem; font-weight: 700; padding: .15rem .5rem; border-radius: 999px; }
.grid input { font-size: .86rem; padding: .5rem .65rem; }
.tools { display: flex; flex-wrap: wrap; align-items: center; gap: .3rem; }
.tools button { min-width: 36px; height: 36px; border: 1px solid var(--line); background: var(--card); border-radius: 8px; font: inherit; cursor: pointer; }
.tools button:disabled { opacity: .4; cursor: default; }
.tools .text { border: 0; padding: 0 .3rem; font-size: .82rem; font-weight: 700; color: var(--brand-text); }
.tools .del { margin-left: auto; color: var(--danger); }
.empty { margin: 0; padding: 1.4rem; border: 1px dashed var(--line); border-radius: 10px; text-align: center; color: var(--muted); }
// Escondido, mas focável: o botão visível é o label. A regra global de input (width: 100%) venceria o .sr-only.
.upload input.file { position: absolute; width: 1px; height: 1px; padding: 0; margin: -1px; border: 0; overflow: hidden; clip: rect(0 0 0 0); opacity: 0; }
.upload { position: relative; display: flex; flex-wrap: wrap; align-items: center; gap: .6rem 1rem; }
.upload label { cursor: pointer; }
.upload label.disabled { opacity: .45; cursor: default; }
input[type=file]:focus-visible + label { outline: 3px solid var(--focus); outline-offset: 2px; }
.err { margin: 0; color: var(--danger); font-weight: 700; }
</style>
