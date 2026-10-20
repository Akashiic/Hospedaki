<script setup lang="ts">
import type { SiteImageKind } from '~/composables/useAdminApi'
import { ImagePlus, Trash2 } from 'lucide-vue-next'

// Uma imagem do site (logo, ícone ou imagem de compartilhamento): mostra a atual, envia outra
// ou remove. Salva na hora; o servidor recorta e reduz para a medida certa de cada uma.
const props = defineProps<{ kind: SiteImageKind, url: string | null, label: string, hint: string, demo?: boolean, hideLabel?: boolean }>()
const emit = defineEmits<{ message: [text: string] }>()

const { send } = useAdminSite()
const busy = ref(false)
const error = ref('')
const input = ref<HTMLInputElement>()
const MESSAGES: Record<SiteImageKind, [sent: string, removed: string]> = {
  logo: ['Logo atualizado no site.', 'Logo removido: o menu volta a mostrar o nome da pousada.'],
  icon: ['Ícone atualizado no site.', 'Ícone removido.'],
  share: ['Imagem de compartilhamento atualizada.', 'Imagem de compartilhamento removida.'],
}

/** Foto enorme do celular: reduz no navegador antes de enviar (PNG mantém o fundo transparente). */
async function shrink(file: File): Promise<Blob> {
  try {
    const bitmap = await createImageBitmap(file, { imageOrientation: 'from-image' })
    const scale = Math.min(1, 2000 / Math.max(bitmap.width, bitmap.height))
    if (scale === 1 && file.size < 4_000_000) {
      bitmap.close()
      return file
    }
    const canvas = document.createElement('canvas')
    canvas.width = Math.round(bitmap.width * scale)
    canvas.height = Math.round(bitmap.height * scale)
    canvas.getContext('2d')!.drawImage(bitmap, 0, 0, canvas.width, canvas.height)
    bitmap.close()
    const type = props.kind === 'share' ? 'image/jpeg' : 'image/png'
    return await new Promise<Blob>((resolve, reject) => canvas.toBlob(b => (b ? resolve(b) : reject(new Error('toBlob'))), type, 0.88))
  }
  catch {
    return file
  }
}

async function upload(files: FileList | null) {
  const file = files?.[0]
  if (!file) return
  busy.value = true
  error.value = ''
  try {
    const body = new FormData()
    const blob = await shrink(file)
    body.append('photo', blob, file.name.replace(/\.\w+$/, '') + (blob.type === 'image/jpeg' ? '.jpg' : '.png'))
    await send(`/site/images/${props.kind}`, { method: 'POST', body })
    emit('message', MESSAGES[props.kind][0])
  }
  catch (e) {
    error.value = apiError(e)
  }
  finally {
    busy.value = false
    if (input.value) input.value.value = ''
  }
}

async function remove() {
  busy.value = true
  error.value = ''
  try {
    await send(`/site/images/${props.kind}`, { method: 'DELETE' })
    emit('message', MESSAGES[props.kind][1])
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
  <div class="slot" :class="[kind, { busy }]">
    <div class="frame">
      <Transition name="swap" mode="out-in">
        <img v-if="url" :key="url" :src="url" :alt="`${label} atual`">
        <span v-else class="empty"><ImagePlus :size="26" aria-hidden="true" /></span>
      </Transition>
    </div>
    <div class="info">
      <strong v-if="!hideLabel">{{ label }}</strong>
      <p class="a-hint">{{ demo ? 'Na demonstração o envio de imagens fica desligado.' : hint }}</p>
      <div class="tools">
        <input :id="`img-${kind}`" ref="input" type="file" accept="image/png,image/jpeg,image/webp" class="file" :disabled="busy || demo" @change="upload(($event.target as HTMLInputElement).files)">
        <label :for="`img-${kind}`" class="btn btn-sm" :class="{ disabled: busy || demo }">{{ busy ? 'Enviando...' : url ? 'Trocar' : 'Enviar imagem' }}</label>
        <button v-if="url" type="button" class="btn btn-ghost btn-sm" :disabled="busy" @click="remove"><Trash2 :size="16" aria-hidden="true" /> Remover</button>
      </div>
      <p v-if="error" class="a-err" role="alert">{{ error }}</p>
    </div>
  </div>
</template>

<style scoped lang="scss">
.slot { display: grid; grid-template-columns: auto minmax(0, 1fr); gap: 1rem; align-items: center; transition: opacity $dur-sm $ease-move; @include below(sm) { grid-template-columns: minmax(0, 1fr); } }
.slot.busy { opacity: .6; }
// Fundo xadrez discreto: mostra se o logo ou o ícone têm transparência.
.frame {
  position: relative; display: grid; place-items: center; overflow: hidden; border-radius: 14px;
  background: repeating-conic-gradient(#efeee8 0% 25%, #fff 0% 50%) 50% / 16px 16px;
  box-shadow: inset 0 0 0 1px var(--line);
}
.logo .frame { width: 220px; height: 96px; padding: .8rem; }
.icon .frame { width: 96px; height: 96px; }
.share .frame { width: 220px; aspect-ratio: 1200 / 630; }
.frame img { max-width: 100%; max-height: 100%; object-fit: contain; display: block; }
.share .frame img, .icon .frame img { width: 100%; height: 100%; object-fit: cover; }
.empty { color: var(--muted); }
.info { display: grid; gap: .35rem; justify-items: start; }
.tools { display: flex; flex-wrap: wrap; gap: .4rem; margin-top: .2rem; }
.tools .btn { gap: .35rem; }
.file { position: absolute; width: 1px; height: 1px; padding: 0; margin: -1px; border: 0; overflow: hidden; clip: rect(0 0 0 0); opacity: 0; }
label.btn { cursor: pointer; }
label.disabled { opacity: .45; cursor: default; pointer-events: none; }
.file:focus-visible + label { outline: 3px solid var(--focus); outline-offset: 2px; }
.swap-enter-active { transition: opacity $dur-md $ease-enter, transform $dur-lg $ease-enter; }
.swap-leave-active { transition: opacity $dur-xs $ease-exit; }
.swap-enter-from { opacity: 0; transform: scale($zoom-sm); }
.swap-leave-to { opacity: 0; }
@include reduced-motion { .swap-enter-from { transform: none; } }
</style>
