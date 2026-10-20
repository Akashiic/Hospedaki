<script setup lang="ts">
import type { Component } from 'vue'
import type { AdminMe } from '~/composables/useAdminApi'
import type { PageKey, PageSource } from '~/utils/sitePages'
import { BedDouble, CalendarCheck, ExternalLink, House, MessageCircle, Mountain, PencilLine } from 'lucide-vue-next'

// "Seu site → Páginas": as páginas do site, o que cada uma mostra no Google e se está no menu.
// Editar abre a página no próprio painel (?aba=paginas&pagina=contato).
const props = defineProps<{ me: AdminMe | null, demo?: boolean }>()
const emit = defineEmits<{ message: [text: string] }>()

const route = useRoute()
const router = useRouter()
const { site, loadError, load } = useAdminSite()
onMounted(load)

const view = useViewMode('paginas')
const ICONS: Record<PageKey, Component> = { inicio: House, lugar: Mountain, acomodacoes: BedDouble, reservar: CalendarCheck, contato: MessageCircle }

const editing = computed(() => SITE_PAGES.find(p => p.key === route.query.pagina)?.key ?? null)
function open(key: PageKey | null) {
  router.push({ query: { aba: 'paginas', ...(key ? { pagina: key } : {}) } })
  if (import.meta.client) window.scrollTo({ top: 0 })
}

const source = computed<PageSource | null>(() => site.value
  ? { ...site.value.identity, accommodations: props.me?.property.accommodations ?? [], seo: site.value.seo }
  : null)
const cards = computed(() => SITE_PAGES.map((info) => {
  const saved = site.value?.pages[info.key]
  const custom = !!saved && (['eyebrow', 'title', 'lead', 'seo_title', 'seo_description'] as const).some(f => saved[f])
  return { info, saved, custom, content: source.value ? pageContent(info.key, source.value, saved) : null }
}))
</script>

<template>
  <div>
    <Transition name="a-tab" mode="out-in">
      <AdminPageEditor
        v-if="editing && site && source"
        :key="editing"
        :page-key="editing"
        :source="source"
        :demo="demo"
        @back="open(null)"
        @message="emit('message', $event)"
      />

      <section v-else class="pages">
        <header class="a-head with-tools a-rise">
          <div class="a-head">
            <h1>Páginas</h1>
            <p>As páginas do seu site. Em cada uma você muda os textos da abertura, como ela aparece no Google e se fica no menu.</p>
          </div>
          <AdminViewToggle v-model="view" />
        </header>

        <div v-if="loadError" class="a-card load-error" role="alert">
          <p>Não foi possível carregar as páginas. {{ loadError }}</p>
          <button type="button" class="btn btn-sm" @click="load">Tentar de novo</button>
        </div>
        <AdminLoading v-else-if="!site" text="Carregando páginas..." :cards="3" height="150px" />

        <TransitionGroup v-else tag="ul" name="a-list" class="items" :class="view">
          <li v-for="(card, i) in cards" :key="card.info.key" class="item a-card a-lift a-rise" :style="{ '--i': i + 1 }">
            <span class="ico" aria-hidden="true"><component :is="ICONS[card.info.key]" :size="22" /></span>
            <div class="body">
              <div class="title-row">
                <h2>{{ card.info.label }}</h2>
                <code>{{ card.info.path }}</code>
              </div>
              <p class="desc">{{ card.info.description }}</p>
              <p v-if="card.content" class="seo"><span>No Google:</span> {{ card.content.seoTitle }}</p>
              <div class="chips">
                <span class="chip" :class="{ off: !(card.saved?.menu ?? true) }"><span class="a-dot" />{{ (card.saved?.menu ?? true) ? 'No menu' : 'Fora do menu' }}</span>
                <span v-if="card.saved?.index === false" class="chip off"><span class="a-dot" />Fora do Google</span>
                <span v-if="card.custom" class="chip brand">Textos personalizados</span>
                <span v-if="card.info.key === 'inicio'" class="chip">Seções e ordem</span>
              </div>
            </div>
            <div class="tools">
              <button type="button" class="btn btn-sm" @click="open(card.info.key)"><PencilLine :size="16" aria-hidden="true" /> Editar</button>
              <a :href="card.info.path" target="_blank" rel="noopener" class="btn btn-ghost btn-sm" :aria-label="`Ver ${card.info.label} no site`"><ExternalLink :size="16" aria-hidden="true" /> Ver</a>
            </div>
          </li>
        </TransitionGroup>
      </section>
    </Transition>
  </div>
</template>

<style scoped lang="scss">
.pages { display: grid; gap: 1.2rem; max-width: 1240px; }
.with-tools { display: flex; flex-wrap: wrap; justify-content: space-between; align-items: end; gap: .8rem; }
.items { position: relative; list-style: none; margin: 0; padding: 0; display: grid; gap: .8rem; }
.items.list { max-width: 980px; }
.items.grid { grid-template-columns: repeat(auto-fill, minmax(min(100%, 300px), 1fr)); align-items: stretch; }
.item { display: grid; grid-template-columns: auto minmax(0, 1fr) auto; gap: 1rem; align-items: center; padding: 1rem 1.1rem; }
.grid .item { grid-template-columns: minmax(0, 1fr); align-content: start; }
.grid .tools { justify-content: flex-start; }
@include below(sm) { .item { grid-template-columns: auto minmax(0, 1fr); .tools { grid-column: 1 / -1; } } }
.ico { display: grid; place-items: center; width: 48px; height: 48px; border-radius: 14px; background: color-mix(in srgb, var(--brand) 12%, white); color: var(--brand-text); }
.body { display: grid; gap: .35rem; min-width: 0; }
.title-row { display: flex; flex-wrap: wrap; align-items: baseline; gap: .2rem .6rem; }
h2 { margin: 0; font-size: 1.2rem; }
code { font-size: .78rem; color: var(--muted); background: var(--a-sunk); border-radius: 6px; padding: .1rem .4rem; }
.desc { margin: 0; color: var(--muted); font-size: .9rem; }
.seo { margin: 0; font-size: .84rem; color: #1a0dab; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.seo span { color: var(--muted); }
.chips { display: flex; flex-wrap: wrap; gap: .35rem; margin-top: .15rem; }
.chip { display: inline-flex; align-items: center; gap: .35rem; padding: .2rem .6rem; border-radius: 999px; background: var(--a-sunk); font-size: .76rem; font-weight: 700; color: var(--text); }
.chip .a-dot { color: var(--brand); }
.chip.off { color: var(--muted); .a-dot { color: var(--muted); } }
.chip.brand { background: color-mix(in srgb, var(--brand) 14%, white); color: var(--brand-text); }
.tools { display: flex; flex-wrap: wrap; gap: .4rem; justify-content: flex-end; }
.tools .btn { gap: .35rem; }
.load-error { padding: 1rem 1.2rem; display: grid; gap: .8rem; justify-items: start; border-left: 4px solid var(--danger); }
.load-error p { margin: 0; }
</style>
