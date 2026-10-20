<script setup lang="ts">
import type { PageKey, PageSettings, PageSource } from '~/utils/sitePages'

// Uma página do site: textos da abertura, presença no menu e como aparece no Google e no
// WhatsApp. Campo vazio usa o texto padrão (mostrado como exemplo). A página inicial tem,
// além disso, o editor de seções.
const props = defineProps<{ pageKey: PageKey, source: PageSource, demo?: boolean }>()
const emit = defineEmits<{ back: [], message: [text: string] }>()

const { site, send } = useAdminSite()
const info = computed(() => SITE_PAGES.find(p => p.key === props.pageKey)!)

const copy = (): PageSettings => ({ ...site.value!.pages[props.pageKey] })
const draft = ref<PageSettings>(copy())
const dirty = computed(() => JSON.stringify(draft.value) !== JSON.stringify(site.value?.pages[props.pageKey]))
const defaults = computed(() => pageDefaults(props.pageKey, props.source))
const content = computed(() => pageContent(props.pageKey, props.source, draft.value))

// A página inicial começa nas seções, que é o que mais muda.
const mode = ref<'sections' | 'texts'>(props.pageKey === 'inicio' ? 'sections' : 'texts')

const saving = ref(false)
const error = ref('')
const errors = ref<Record<string, string[]>>({})
async function save() {
  saving.value = true
  error.value = ''
  errors.value = {}
  try {
    await send(`/site/pages/${props.pageKey}`, { method: 'PUT', body: { ...draft.value } })
    draft.value = copy()
    emit('message', `${info.value.label}: alterações publicadas no site.`)
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
  draft.value = copy()
  errors.value = {}
  error.value = ''
}

function beforeUnload(e: BeforeUnloadEvent) {
  if (dirty.value) e.preventDefault()
}
onMounted(() => window.addEventListener('beforeunload', beforeUnload))
onBeforeUnmount(() => window.removeEventListener('beforeunload', beforeUnload))
</script>

<template>
  <div class="page-editor">
    <header class="top a-rise">
      <button type="button" class="a-back" @click="emit('back')">← Páginas</button>
      <div class="title-row">
        <div class="a-head">
          <h1>{{ info.label }}</h1>
          <p>{{ info.description }}</p>
        </div>
        <a :href="info.path" target="_blank" rel="noopener" class="a-back">Ver no site ↗</a>
      </div>
      <div v-if="pageKey === 'inicio'" class="modes" role="tablist" aria-label="O que editar">
        <button type="button" role="tab" :aria-selected="mode === 'sections'" @click="mode = 'sections'">Seções da página</button>
        <button type="button" role="tab" :aria-selected="mode === 'texts'" @click="mode = 'texts'">Google e compartilhamento</button>
      </div>
    </header>

    <Transition name="a-tab" mode="out-in">
      <AdminLayoutEditor v-if="mode === 'sections'" key="sections" embedded @saved="emit('message', $event)" />

      <form v-else key="texts" class="a-form body" @submit.prevent="save">
        <div class="fields">
          <section v-if="info.menuOptional" class="a-section a-rise" style="--i: 1">
            <h2>Menu do site</h2>
            <AdminOption id="page-menu" v-model="draft.menu" label="Mostrar no menu" hint="Desligado, a página sai do menu e do rodapé, mas continua aberta para quem tiver o link." />
          </section>

          <section v-if="info.header" class="a-section a-rise" style="--i: 2">
            <h2>Abertura da página</h2>
            <p class="a-hint">O texto sobre a foto, no alto da página. Deixe em branco para usar o exemplo.</p>
            <AdminTextField id="page-eyebrow" v-model="draft.eyebrow" label="Chamada" :max="PAGE_LIMITS.eyebrow" :placeholder="defaults.eyebrow" :error="errors.eyebrow?.[0]" />
            <AdminTextField id="page-title" v-model="draft.title" label="Título" :max="PAGE_LIMITS.title" :placeholder="defaults.title" :error="errors.title?.[0]" />
            <AdminTextField id="page-lead" v-model="draft.lead" label="Texto de apoio" :max="PAGE_LIMITS.lead" :rows="3" :placeholder="defaults.lead ?? 'Opcional'" :error="errors.lead?.[0]" />
          </section>

          <section class="a-section a-rise" style="--i: 3">
            <h2>No Google</h2>
            <p class="a-hint">O título azul e o resumo que aparecem na busca. Bons textos dizem o que a página tem e onde fica a pousada.</p>
            <AdminOption
              v-if="info.searchOptional"
              id="page-index"
              v-model="draft.index"
              label="Mostrar esta página no Google"
              :hint="pageKey === 'acomodacoes' ? 'Desligado, esta página e as de cada acomodação recebem noindex e saem do mapa do site.' : 'Desligado, a página recebe noindex e sai do mapa do site. Continua aberta para quem tiver o link.'"
            />
            <AdminTextField id="page-seo-title" v-model="draft.seo_title" label="Título na busca" :max="PAGE_LIMITS.seo_title" :placeholder="defaults.seoTitle" hint="Até uns 60 caracteres aparecem inteiros." :error="errors.seo_title?.[0]" />
            <AdminTextField id="page-seo-desc" v-model="draft.seo_description" label="Descrição na busca" :max="PAGE_LIMITS.seo_description" :rows="3" :placeholder="defaults.seoDescription ?? 'Ex.: Pousada com piscina e vista para as montanhas. Reserve direto, sem taxa.'" hint="Até uns 160 caracteres aparecem inteiros." :error="errors.seo_description?.[0]" />
            <AdminTextField id="page-keywords" v-model="draft.keywords" label="Palavras-chave" :max="PAGE_LIMITS.keywords" :placeholder="site?.seo.keywords ?? 'Ex.: pousada em Mairiporã, chalé com hidromassagem'" hint="Separadas por vírgula. Vazio, a página usa as do site (em SEO)." :error="errors.keywords?.[0]" />
          </section>

          <section v-if="pageKey === 'acomodacoes'" class="a-section a-rise" style="--i: 4">
            <h2>Fotos de cada acomodação</h2>
            <p class="a-hint">Como as fotos aparecem na página de cada acomodação.</p>
            <div class="layouts" role="radiogroup" aria-label="Fotos de cada acomodação">
              <label class="layout" :class="{ on: (draft.photos ?? 'grid') === 'grid' }">
                <input v-model="draft.photos" type="radio" name="photos-layout" value="grid" class="native">
                <span class="mini grid" aria-hidden="true"><i /><i /><i /><i /><i /></span>
                <strong>Grade</strong>
                <small>Todas as fotos à vista, a primeira em destaque.</small>
              </label>
              <label class="layout" :class="{ on: draft.photos === 'carousel' }">
                <input v-model="draft.photos" type="radio" name="photos-layout" value="carousel" class="native">
                <span class="mini carousel" aria-hidden="true"><i /><b /><b /><b /><b /></span>
                <strong>Carrossel</strong>
                <small>Uma foto grande por vez, com miniaturas e setas; arrasta no celular.</small>
              </label>
            </div>
          </section>
        </div>

        <aside class="previews a-rise" style="--i: 2" aria-label="Prévias">
          <div v-if="info.header" class="preview-block">
            <p class="a-label">Abertura</p>
            <div class="hero">
              <p class="eyebrow">{{ content.eyebrow }}</p>
              <p class="h">{{ content.title }}</p>
              <p v-if="content.lead" class="lead">{{ content.lead }}</p>
            </div>
          </div>
          <div class="preview-block">
            <p class="a-label">Na busca do Google</p>
            <AdminGooglePreview :title="content.seoTitle" :description="content.seoDescription" :path="info.path" :name="source.name" :icon-url="site?.identity.icon_url" />
          </div>
          <div class="preview-block">
            <p class="a-label">Enviada no WhatsApp</p>
            <AdminSharePreview :title="content.seoTitle" :description="content.seoDescription" :image="site?.identity.share_image_url ?? null" />
            <p v-if="!site?.identity.share_image_url" class="a-hint">Sem imagem de compartilhamento, o site usa a foto da página. Escolha uma em <NuxtLink :to="{ query: { aba: 'identidade' } }">Identidade</NuxtLink>.</p>
          </div>
        </aside>

        <AdminSaveBar class="bar" :dirty="dirty" :saving="saving" :error="error" @undo="undo" />
      </form>
    </Transition>
  </div>
</template>

<style scoped lang="scss">
.page-editor { display: grid; gap: 1.2rem; }
.top { display: grid; gap: .6rem; }
.title-row { display: flex; flex-wrap: wrap; justify-content: space-between; align-items: end; gap: .4rem 1rem; }
.modes { justify-self: start; display: inline-flex; padding: 4px; border-radius: 14px; background: var(--a-card); box-shadow: var(--a-shadow); }
.modes button {
  min-height: 40px; padding: 0 1rem; border: 0; border-radius: 10px; background: none; font: inherit; font-size: .88rem; font-weight: 700; color: var(--muted); cursor: pointer;
  transition: opacity $dur-sm $ease-move;
  &[aria-selected="true"] { background: var(--night); color: var(--on-night); }
}
.body { grid-template-columns: minmax(0, 1fr); align-items: start; @include above(lg) { grid-template-columns: minmax(0, 1.1fr) minmax(0, 1fr); } }
.fields { display: grid; gap: 1rem; min-width: 0; }
.previews { display: grid; gap: 1rem; min-width: 0; @include above(lg) { position: sticky; top: 1rem; } }
.preview-block { display: grid; gap: .45rem; }
.bar { grid-column: 1 / -1; }
// Miniatura da abertura: o mesmo véu escuro com a cor da pousada.
.hero {
  display: grid; gap: .3rem; padding: 2.2rem 1.2rem 1.2rem; border-radius: 14px; color: #fff;
  background: linear-gradient(160deg, color-mix(in srgb, var(--brand) 55%, #0e1411), #0e1411 75%);
}
.hero p { margin: 0; }
.eyebrow { @include eyebrow(var(--amber-soft), sm); }
.h { font-family: var(--font-display); font-size: clamp(1.6rem, 3vw, 2.1rem); line-height: 1.05; overflow-wrap: anywhere; }
.lead { color: rgb(255 255 255 / .82); font-size: .88rem; }
// Grade ou carrossel: dois cartões com um desenho do layout.
.layouts { display: grid; gap: .6rem; grid-template-columns: repeat(auto-fit, minmax(min(100%, 200px), 1fr)); }
.layout {
  position: relative; display: grid; gap: .3rem; padding: .8rem; border-radius: 14px; cursor: pointer; box-shadow: inset 0 0 0 1.5px var(--line);
  strong { font-size: .95rem; }
  small { color: var(--muted); font-size: .82rem; }
  &.on { box-shadow: inset 0 0 0 2px var(--brand); background: color-mix(in srgb, var(--brand) 7%, white); }
  &:has(.native:focus-visible) { outline: 3px solid var(--focus); outline-offset: 2px; }
}
.native { position: absolute; opacity: 0; width: 1px; height: 1px; }
.mini { display: grid; gap: 3px; height: 64px; margin-bottom: .2rem; }
.mini i, .mini b { display: block; border-radius: 4px; background: color-mix(in srgb, var(--brand) 35%, var(--a-sunk)); }
.mini.grid { grid-template-columns: 1fr 1fr; grid-template-rows: 1.4fr 1fr 1fr; }
.mini.grid i:first-child { grid-column: 1 / -1; }
.mini.carousel { grid-template-columns: repeat(4, 1fr); grid-template-rows: 1fr 14px; }
.mini.carousel i { grid-column: 1 / -1; }
.mini.carousel b { opacity: .55; }
.mini.carousel b:first-of-type { opacity: 1; }
</style>
