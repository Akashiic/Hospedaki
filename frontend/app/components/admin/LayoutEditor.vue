<script setup lang="ts">
import type { Property } from '~/composables/useApi'
import type { HomeSection, SectionKey } from '~/utils/homeLayout'

// Editor da página inicial: o dono liga, desliga e reordena as seções, vê o resultado ao vivo
// na prévia (o site de verdade, com o layout ainda não salvo em ?layout=) e salva quando gostar.
const emit = defineEmits<{ saved: [message: string] }>()

const api = useAdminApi()
const site = useApi()

const saved = ref<HomeSection[]>([])
const draft = ref<HomeSection[]>([])
const property = ref<Property | null>(null)
const loadError = ref('')
const saving = ref(false)
const error = ref('')

async function load() {
  loadError.value = ''
  try {
    const [layout, prop] = await Promise.all([
      api<{ sections: HomeSection[] }>('/layout'),
      site<{ data: Property }>('/'),
    ])
    saved.value = normalizeLayout(layout.sections)
    draft.value = saved.value.map(s => ({ ...s }))
    property.value = prop.data
  }
  catch (e) {
    loadError.value = apiError(e)
  }
}
onMounted(load)

// Seção sem conteúdo cadastrado não aparece no site, mesmo ligada: o editor avisa em vez de esconder.
const available = computed(() => {
  const p = property.value
  if (!p) return availableSections({ story: 1, accommodations: 1, photos: 2, extras: 1 })
  const photos = new Set([...p.photos, ...p.accommodations.flatMap(a => a.photos)].map(ph => ph.url)).size
  return availableSections({ story: p.story.length, accommodations: p.accommodations.length, photos, extras: p.extras?.length ?? 0 })
})
const warnings = computed(() => layoutWarnings(draft.value, available.value))
const dirty = computed(() => !sameLayout(draft.value, saved.value))
const isDefault = computed(() => sameLayout(draft.value, DEFAULT_LAYOUT))
const activePreset = computed(() => LAYOUT_PRESETS.find(p => sameLayout(draft.value, presetLayout(p.keys)))?.id)

// ── Reordenar: setas (teclado e toque) e arrastar (mouse) ──
const list = ref<HTMLElement>()
async function move(from: number, to: number, focus?: 'up' | 'down') {
  const key = draft.value[from]?.key
  draft.value = moveSection(draft.value, from, to)
  if (!focus || !key) return
  // O foco acompanha a seção movida, para dar vários passos seguidos pelo teclado.
  await nextTick()
  const btn = list.value?.querySelector<HTMLButtonElement>(`[data-key="${key}"] .${focus}`)
  ;(btn && !btn.disabled ? btn : list.value?.querySelector<HTMLElement>(`[data-key="${key}"] .${focus === 'up' ? 'down' : 'up'}`))?.focus()
}

const dragging = ref<number | null>(null)
const over = ref<number | null>(null)
function onDragStart(i: number, e: DragEvent) {
  dragging.value = i
  e.dataTransfer?.setData('text/plain', String(i))
  if (e.dataTransfer) e.dataTransfer.effectAllowed = 'move'
}
function onDragOver(i: number) {
  if (dragging.value !== null && i > 0) over.value = i
}
function onDrop(i: number) {
  if (dragging.value !== null) move(dragging.value, Math.max(1, i))
  dragging.value = over.value = null
}
function onDragEnd() {
  dragging.value = over.value = null
}

function toggle(key: SectionKey) {
  draft.value = draft.value.map(s => s.key === key && key !== LOCKED_SECTION ? { ...s, visible: !s.visible } : s)
}
function applyPreset(keys: SectionKey[]) {
  draft.value = presetLayout(keys)
}
function undo() {
  draft.value = saved.value.map(s => ({ ...s }))
}
function restoreDefault() {
  draft.value = DEFAULT_LAYOUT.map(s => ({ ...s }))
}

async function save() {
  saving.value = true
  error.value = ''
  try {
    const res = await api<{ sections: HomeSection[] }>('/layout', { method: 'PUT', body: { sections: draft.value } })
    saved.value = normalizeLayout(res.sections)
    draft.value = saved.value.map(s => ({ ...s }))
    emit('saved', 'Página inicial atualizada. As mudanças já estão no site.')
  }
  catch (e) {
    error.value = apiError(e)
  }
  finally {
    saving.value = false
  }
}

// Fechar a aba com mudanças não salvas pede confirmação ao navegador.
function beforeUnload(e: BeforeUnloadEvent) {
  if (dirty.value) e.preventDefault()
}
onMounted(() => window.addEventListener('beforeunload', beforeUnload))
onBeforeUnmount(() => window.removeEventListener('beforeunload', beforeUnload))

// ── Prévia ao vivo: o site de verdade, recarregado um instante depois de cada mudança ──
const device = ref<'mobile' | 'desktop'>('mobile')
// A prévia estática do site (npm run preview:demo) usa rotas com #.
const hash = useRuntimeConfig().public.demo ? '#/' : ''
const previewUrl = (layout: HomeSection[]) => `/${hash}?layout=${toLayoutParam(layout)}`
const frameSrc = ref('')
const frameLoading = ref(true)
let timer: ReturnType<typeof setTimeout> | undefined
watch(draft, (layout) => {
  clearTimeout(timer)
  timer = setTimeout(() => {
    const next = previewUrl(layout)
    if (next !== frameSrc.value) {
      frameLoading.value = true
      frameSrc.value = next
    }
  }, frameSrc.value ? 450 : 0)
}, { deep: true })
onBeforeUnmount(() => clearTimeout(timer))

// O computador é simulado em 1280 px e reduzido para caber na coluna.
const stage = ref<HTMLElement>()
const stageWidth = ref(0)
let observer: ResizeObserver | undefined
onMounted(() => {
  observer = new ResizeObserver(([entry]) => { stageWidth.value = entry?.contentRect.width ?? 0 })
  if (stage.value) observer.observe(stage.value)
})
onBeforeUnmount(() => observer?.disconnect())
const frameWidth = computed(() => device.value === 'desktop' ? 1280 : 390)
const scale = computed(() => stageWidth.value ? Math.min(1, stageWidth.value / frameWidth.value) : 1)
</script>

<template>
  <div class="editor">
    <div v-if="loadError" class="load-error" role="alert">
      <p>Não foi possível carregar a página inicial. {{ loadError }}</p>
      <button type="button" class="btn btn-sm" @click="load">Tentar de novo</button>
    </div>

    <template v-else>
      <div class="side">
        <header class="intro">
          <h2>Página inicial</h2>
          <p>Escolha o que aparece na página inicial do seu site e em que ordem. A prévia mostra o resultado antes de salvar.</p>
        </header>

        <section class="presets" aria-labelledby="presets-title">
          <h3 id="presets-title">Comece por uma sugestão</h3>
          <div class="chips">
            <button
              v-for="p in LAYOUT_PRESETS"
              :key="p.id"
              type="button"
              :aria-pressed="activePreset === p.id"
              :title="p.description"
              @click="applyPreset(p.keys)"
            >
              {{ p.label }}
            </button>
          </div>
          <p class="hint">{{ LAYOUT_PRESETS.find(p => p.id === activePreset)?.description ?? 'Layout personalizado.' }}</p>
        </section>

        <ol ref="list" class="sections" aria-label="Seções da página inicial, de cima para baixo">
          <li
            v-for="(s, i) in draft"
            :key="s.key"
            :data-key="s.key"
            :class="{ off: !s.visible, empty: !available[s.key], dragging: dragging === i, over: over === i && dragging !== i }"
            :draggable="i > 0"
            @dragstart="onDragStart(i, $event)"
            @dragover.prevent="onDragOver(i)"
            @drop.prevent="onDrop(i)"
            @dragend="onDragEnd"
          >
            <span class="grip" :class="{ locked: i === 0 }" aria-hidden="true">{{ i === 0 ? '🔒' : '⠿' }}</span>
            <div class="info">
              <strong>{{ SECTION_INFO[s.key].label }}</strong>
              <p>{{ SECTION_INFO[s.key].description }}</p>
              <p v-if="i === 0" class="note">Fica sempre no topo: é a primeira coisa que o hóspede vê.</p>
              <p v-else-if="!available[s.key]" class="note">Ainda sem conteúdo cadastrado. Fica fora do site até ter.</p>
            </div>
            <div v-if="i > 0" class="controls">
              <button type="button" class="arrow up" :disabled="i === 1" :aria-label="`Subir ${SECTION_INFO[s.key].label}`" @click="move(i, i - 1, 'up')">↑</button>
              <button type="button" class="arrow down" :disabled="i === draft.length - 1" :aria-label="`Descer ${SECTION_INFO[s.key].label}`" @click="move(i, i + 1, 'down')">↓</button>
              <button
                type="button"
                class="switch"
                role="switch"
                :aria-checked="s.visible"
                :aria-label="`Mostrar ${SECTION_INFO[s.key].label}`"
                @click="toggle(s.key)"
              >
                <span class="track"><span class="thumb" /></span>
                <span class="state">{{ s.visible ? 'Visível' : 'Oculta' }}</span>
              </button>
            </div>
          </li>
        </ol>

        <ul v-if="warnings.length" class="warnings" aria-live="polite">
          <li v-for="w in warnings" :key="w">{{ w }}</li>
        </ul>

        <div class="more">
          <button type="button" class="link" :disabled="isDefault || saving" @click="restoreDefault">Restaurar padrão</button>
          <a :href="previewUrl(draft)" target="_blank" rel="noopener" class="link">Abrir a prévia em outra aba ↗</a>
        </div>

        <div class="actions">
          <p v-if="error" class="error" role="alert">{{ error }}</p>
          <p class="status" :class="{ dirty }" aria-live="polite">{{ dirty ? 'Alterações não salvas' : 'Tudo salvo' }}</p>
          <button type="button" class="btn btn-ghost btn-sm" :disabled="!dirty || saving" @click="undo">Desfazer</button>
          <button type="button" class="btn btn-sm" :disabled="!dirty || saving" @click="save">{{ saving ? 'Salvando...' : 'Salvar e publicar' }}</button>
        </div>
      </div>

      <aside class="preview" aria-label="Prévia da página inicial">
        <div class="preview-bar">
          <span>Prévia ao vivo</span>
          <div class="devices" role="group" aria-label="Tamanho da prévia">
            <button type="button" :aria-pressed="device === 'mobile'" @click="device = 'mobile'">Celular</button>
            <button type="button" :aria-pressed="device === 'desktop'" @click="device = 'desktop'">Computador</button>
          </div>
        </div>
        <div ref="stage" class="stage" :class="device">
          <div class="frame-box" :style="{ width: `${frameWidth * scale}px`, height: `${(device === 'desktop' ? 800 : 760) * scale}px` }">
            <iframe
              v-if="frameSrc"
              :src="frameSrc"
              title="Prévia da página inicial"
              :style="{ width: `${frameWidth}px`, height: `${device === 'desktop' ? 800 : 760}px`, transform: `scale(${scale})` }"
              @load="frameLoading = false"
            />
            <p v-if="frameLoading" class="loading">Atualizando a prévia...</p>
          </div>
        </div>
      </aside>
    </template>
  </div>
</template>

<style scoped lang="scss">
.editor { display: grid; gap: 1.2rem; align-items: start; grid-template-columns: minmax(0, 1fr); @include above(lg) { grid-template-columns: minmax(0, 1fr) minmax(0, 1.05fr); } }
.side { display: grid; gap: 1rem; min-width: 0; }
.intro h2 { margin: 0; font-size: 1.6rem; }
.intro p { margin: .3rem 0 0; color: var(--muted); }
h3 { margin: 0 0 .5rem; font-family: var(--font-body); font-size: .88rem; font-weight: 700; }
.presets { background: var(--card); border: 1px solid var(--line); border-radius: var(--radius); padding: 1rem; }
.chips { display: flex; flex-wrap: wrap; gap: .45rem; }
.chips button {
  min-height: 40px; padding: 0 .9rem; border-radius: 999px; border: 1px solid var(--line); background: var(--card);
  font: inherit; font-size: .86rem; font-weight: 700; color: var(--text); cursor: pointer;
  &[aria-pressed="true"] { background: var(--brand); border-color: var(--brand); color: var(--brand-ink); }
}
.hint { margin: .6rem 0 0; color: var(--muted); font-size: .86rem; }

.sections { list-style: none; margin: 0; padding: 0; display: grid; gap: .5rem; }
.sections li {
  display: grid; grid-template-columns: auto minmax(0, 1fr) auto; gap: .8rem; align-items: center;
  background: var(--card); border: 1px solid var(--line); border-radius: var(--radius); padding: .8rem .9rem;
  transition: opacity $dur-sm $ease-move, transform $dur-md $ease-enter;
  &[draggable="true"] { cursor: grab; }
  &.dragging { opacity: .45; }
  &.over { box-shadow: inset 0 3px 0 var(--brand); }
  &.off .info { opacity: .55; }
  @include below(sm) { grid-template-columns: auto minmax(0, 1fr); .controls { grid-column: 1 / -1; justify-content: flex-end; } }
}
.grip { color: var(--muted); font-size: 1.1rem; width: 1.4rem; text-align: center; user-select: none; }
.grip.locked { font-size: .95rem; }
.info strong { display: block; }
.info p { margin: .15rem 0 0; color: var(--muted); font-size: .86rem; line-height: 1.4; }
.info .note { color: var(--warn-ink); font-weight: 500; }
.controls { display: flex; align-items: center; gap: .3rem; }
.arrow {
  width: 40px; height: 40px; border-radius: 10px; border: 1px solid var(--line); background: var(--card);
  font: inherit; font-size: 1.05rem; color: var(--text); cursor: pointer;
  &:disabled { opacity: .35; cursor: default; }
}
.switch { display: inline-flex; align-items: center; gap: .45rem; min-height: 44px; padding: 0 .3rem 0 .5rem; background: none; border: 0; font: inherit; cursor: pointer; }
.track { position: relative; width: 40px; height: 24px; border-radius: 999px; background: var(--line); }
.thumb { position: absolute; top: 3px; left: 3px; width: 18px; height: 18px; border-radius: 50%; background: #fff; box-shadow: 0 1px 3px rgb(0 0 0 / .25); transition: transform $dur-sm $ease-move; }
.switch[aria-checked="true"] .track { background: var(--brand); }
.switch[aria-checked="true"] .thumb { transform: translateX(16px); }
.state { font-size: .82rem; font-weight: 700; color: var(--muted); min-width: 3.6rem; text-align: left; }

.warnings { list-style: none; margin: 0; padding: .8rem 1rem; display: grid; gap: .4rem; background: var(--warn-soft); color: var(--warn-ink); border-radius: 10px; font-size: .9rem; }
.more { display: flex; flex-wrap: wrap; justify-content: space-between; gap: .4rem 1rem; }
.link {
  display: inline-flex; align-items: center; min-height: 40px; padding: 0; background: none; border: 0;
  font: inherit; font-size: .88rem; font-weight: 700; color: var(--brand-text); cursor: pointer; text-decoration: none;
  &:disabled { opacity: .45; cursor: default; }
}
// Barra fixa no pé da tela enquanto se mexe na lista: o estado e os dois botões numa linha só.
.actions {
  position: sticky; bottom: 0; z-index: 2; display: flex; flex-wrap: wrap; align-items: center; gap: .5rem;
  background: var(--card); border: 1px solid var(--line); border-radius: var(--radius); padding: .7rem .8rem calc(.7rem + env(safe-area-inset-bottom, 0px));
  box-shadow: 0 -8px 24px rgb(0 0 0 / .06);
}
.status { margin: 0 auto 0 0; font-size: .84rem; color: var(--muted); }
.status.dirty { color: var(--warn-ink); font-weight: 700; }
.error { flex-basis: 100%; margin: 0; color: var(--danger); font-weight: 700; }

.preview { position: sticky; top: 1rem; display: grid; gap: .6rem; min-width: 0; @include below(lg) { position: static; } }
.preview-bar { display: flex; justify-content: space-between; align-items: center; gap: .6rem; font-weight: 700; font-size: .88rem; }
.devices { display: inline-flex; background: var(--card); border: 1px solid var(--line); border-radius: 999px; padding: 3px; }
.devices button {
  min-height: 34px; padding: 0 .8rem; border: 0; border-radius: 999px; background: none; font: inherit; font-size: .82rem; font-weight: 700; color: var(--muted); cursor: pointer;
  &[aria-pressed="true"] { background: var(--night); color: var(--on-night); }
}
.stage { min-width: 0; display: grid; justify-items: center; background: var(--card); border: 1px solid var(--line); border-radius: var(--radius); padding: .8rem; }
.frame-box { position: relative; overflow: hidden; border-radius: 12px; box-shadow: 0 10px 30px rgb(0 0 0 / .12); background: var(--night); }
.stage.mobile .frame-box { border-radius: 22px; outline: 6px solid var(--night); }
iframe { display: block; border: 0; transform-origin: 0 0; background: var(--paper); }
.loading { position: absolute; left: 50%; bottom: .8rem; transform: translateX(-50%); margin: 0; padding: .35rem .8rem; border-radius: 999px; background: rgb(14 20 18 / .85); color: var(--on-night); font-size: .8rem; white-space: nowrap; }
.load-error { background: var(--card); border: 1px solid var(--line); border-left: 4px solid var(--danger); border-radius: var(--radius); padding: 1rem 1.1rem; display: grid; gap: .8rem; justify-items: start; }
.load-error p { margin: 0; }
</style>
