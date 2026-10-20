<script setup lang="ts">
import type { SiteSeo } from '~/utils/sitePages'
import { FileCode2, Map as MapIcon } from 'lucide-vue-next'

// "Seu site → SEO": imagem de compartilhamento, descrição padrão, aparecer ou não no Google,
// verificação do Search Console e IDs de Google Analytics e Pixel do Meta.
const props = defineProps<{ demo?: boolean, accommodations: { name: string }[] }>()
const emit = defineEmits<{ message: [text: string] }>()

const { site, loadError, load, send } = useAdminSite()
onMounted(load)

const copy = (): SiteSeo => ({ ...site.value!.seo })
const form = ref<SiteSeo | null>(null)
watch(() => site.value?.seo, (seo, before) => {
  if (seo && (!form.value || !before || !dirtyAgainst(before))) form.value = copy()
}, { immediate: true })
function dirtyAgainst(seo: SiteSeo) {
  return JSON.stringify(form.value) !== JSON.stringify(seo)
}
const dirty = computed(() => !!site.value && !!form.value && dirtyAgainst(site.value.seo))

// A prévia usa a página inicial com a descrição que está sendo escrita.
const home = computed(() => site.value && form.value
  ? pageContent('inicio', { ...site.value.identity, accommodations: props.accommodations, seo: form.value }, site.value.pages.inicio)
  : null)

// O Search Console entrega uma tag inteira; aceitamos colar a tag e guardamos só o código.
function cleanVerification(value: string | null) {
  const match = value?.match(/content=["']([^"']+)["']/)
  return (match?.[1] ?? value)?.trim() || null
}

const saving = ref(false)
const error = ref('')
const errors = ref<Record<string, string[]>>({})
async function save() {
  if (!form.value) return
  saving.value = true
  error.value = ''
  errors.value = {}
  form.value.google_verification = cleanVerification(form.value.google_verification)
  form.value.ga_id = form.value.ga_id?.trim().toUpperCase() || null
  form.value.meta_pixel_id = form.value.meta_pixel_id?.trim() || null
  try {
    await send('/site/seo', { method: 'PUT', body: { ...form.value } })
    form.value = copy()
    emit('message', 'SEO salvo. O Google lê as mudanças na próxima visita dele ao site.')
  }
  catch (e) {
    error.value = apiError(e)
    errors.value = apiFieldErrors(e)
  }
  finally {
    saving.value = false
  }
}
function undo() {
  form.value = copy()
  errors.value = {}
  error.value = ''
}
const err = (f: string) => errors.value[f]?.[0]
const origin = import.meta.client ? window.location.origin : ''

function beforeUnload(e: BeforeUnloadEvent) {
  if (dirty.value) e.preventDefault()
}
onMounted(() => window.addEventListener('beforeunload', beforeUnload))
onBeforeUnmount(() => window.removeEventListener('beforeunload', beforeUnload))
</script>

<template>
  <section class="seo">
    <header class="a-head a-rise">
      <h1>SEO</h1>
      <p>Como o seu site aparece no Google e quando alguém compartilha o link. O texto de cada página fica em <NuxtLink :to="{ query: { aba: 'paginas' } }">Páginas</NuxtLink>.</p>
    </header>

    <div v-if="loadError" class="a-card load-error" role="alert">
      <p>Não foi possível carregar o SEO. {{ loadError }}</p>
      <button type="button" class="btn btn-sm" @click="load">Tentar de novo</button>
    </div>
    <AdminLoading v-else-if="!site || !form" text="Carregando SEO..." :cards="2" height="180px" />

    <div v-else class="layout">
      <div class="col">
        <section class="a-section a-rise" style="--i: 1">
          <h2>Imagem de compartilhamento</h2>
          <p class="a-hint">A foto que aparece quando o link do site é enviado no WhatsApp, Instagram ou Facebook. Muda na hora.</p>
          <AdminImageSlot kind="share" :url="site.identity.share_image_url" label="Imagem de compartilhamento" hide-label hint="Uma foto bonita na horizontal. Ela é recortada em 1200 × 630." :demo="demo" @message="emit('message', $event)" />
        </section>

        <form class="a-form" @submit.prevent="save">
          <section class="a-section a-rise" style="--i: 2">
            <h2>No Google</h2>
            <AdminTextField id="seo-desc" v-model="form.description" label="Descrição padrão" :max="PAGE_LIMITS.seo_description" :rows="3" placeholder="Ex.: Pousada em Mairiporã com piscina, fogueira e chalé com hidromassagem. Reserve direto, sem taxa." hint="Resumo da pousada para as páginas que não têm descrição própria." :error="err('description')" />
            <AdminOption id="seo-index" v-model="form.indexable" label="Aparecer no Google" hint="Desligue só enquanto o site estiver em montagem. Desligado, o Google tira o site da busca." />
            <p v-if="!form.indexable" class="warn" role="status">O site vai sumir da busca do Google. Quem tem o link continua entrando normalmente.</p>
          </section>

          <section class="a-section a-rise" style="--i: 3">
            <h2>Avançado</h2>
            <p class="a-hint">Para quem usa as ferramentas do Google e do Meta. Pode deixar em branco.</p>
            <AdminTextField id="seo-google" v-model="form.google_verification" label="Verificação do Google Search Console" placeholder="Cole a tag ou só o código" hint="No Search Console, escolha &quot;Tag HTML&quot; e cole aqui. Serve para acompanhar buscas e cliques." :error="err('google_verification')" />
            <div class="a-row">
              <AdminTextField id="seo-ga" v-model="form.ga_id" label="Google Analytics (GA4)" placeholder="G-XXXXXXXXXX" hint="Conta as visitas e os pedidos de reserva." :error="err('ga_id')" />
              <AdminTextField id="seo-pixel" v-model="form.meta_pixel_id" label="Pixel do Meta" inputmode="numeric" placeholder="1234567890" hint="Mede os anúncios do Instagram e do Facebook." :error="err('meta_pixel_id')" />
            </div>
            <ul class="files">
              <li><MapIcon :size="18" aria-hidden="true" /><span><strong>Mapa do site</strong> para o Google, atualizado sozinho com as acomodações.</span><a :href="`${origin}/sitemap.xml`" target="_blank" rel="noopener">sitemap.xml ↗</a></li>
              <li><FileCode2 :size="18" aria-hidden="true" /><span><strong>Regras para buscadores</strong>, seguindo a opção "Aparecer no Google".</span><a :href="`${origin}/robots.txt`" target="_blank" rel="noopener">robots.txt ↗</a></li>
            </ul>
          </section>

          <AdminSaveBar :dirty="dirty" :saving="saving" :error="error" @undo="undo" />
        </form>
      </div>

      <aside v-if="home" class="previews a-rise" style="--i: 2" aria-label="Prévias da página inicial">
        <div class="preview-block">
          <p class="a-label">Página inicial no Google</p>
          <AdminGooglePreview :title="home.seoTitle" :description="home.seoDescription" path="/" :name="site.identity.name" :icon-url="site.identity.icon_url" />
        </div>
        <div class="preview-block">
          <p class="a-label">Link enviado no WhatsApp</p>
          <AdminSharePreview :title="home.seoTitle" :description="home.seoDescription" :image="site.identity.share_image_url" />
        </div>
      </aside>
    </div>
  </section>
</template>

<style scoped lang="scss">
.seo { display: grid; gap: 1.2rem; max-width: 1240px; }
.layout { display: grid; gap: 1rem; align-items: start; grid-template-columns: minmax(0, 1fr); @include above(lg) { grid-template-columns: minmax(0, 1.15fr) minmax(0, 1fr); } }
.col { display: grid; gap: 1rem; min-width: 0; }
.previews { display: grid; gap: 1rem; min-width: 0; @include above(lg) { position: sticky; top: 1rem; } }
.preview-block { display: grid; gap: .45rem; }
.warn { margin: 0; padding: .7rem .9rem; border-radius: 12px; background: var(--warn-soft); color: var(--warn-ink); font-size: .88rem; font-weight: 600; }
.files { list-style: none; margin: 0; padding: 0; display: grid; gap: .5rem; }
.files li { display: grid; grid-template-columns: auto minmax(0, 1fr) auto; gap: .7rem; align-items: center; padding: .7rem .8rem; border-radius: 12px; background: var(--a-sunk); font-size: .86rem; color: var(--muted); }
.files svg { color: var(--brand-text); }
.files strong { color: var(--text); }
.files a { font-weight: 700; color: var(--brand-text); text-decoration: none; white-space: nowrap; }
@include below(sm) { .files li { grid-template-columns: auto minmax(0, 1fr); a { grid-column: 2; } } }
.load-error { padding: 1rem 1.2rem; display: grid; gap: .8rem; justify-items: start; border-left: 4px solid var(--danger); }
.load-error p { margin: 0; }
</style>
