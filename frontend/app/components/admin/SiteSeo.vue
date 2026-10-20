<script setup lang="ts">
import type { Property } from '~/composables/useApi'
import type { SiteSeo } from '~/utils/sitePages'
import { Bot, FileCode2, LockKeyhole, Map as MapIcon, MapPin, RefreshCw, Sparkles } from 'lucide-vue-next'

// "Seu site → Configurações → SEO": como o site aparece no Google e o que os robôs podem ler.
// Controla o Nuxt SEO do site público: robots.txt (robôs de busca e de IA), sitemap.xml, dados
// estruturados (Schema.org) e separador dos títulos. "Integrações" usa o mesmo formulário para
// Search Console, Analytics e Pixel; cada tela mostra só os seus campos.
const props = defineProps<{ section: 'seo' | 'integracoes', accommodations: { name: string }[] }>()
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

// A prévia usa a página inicial com a descrição e o separador que estão sendo escolhidos.
const home = computed(() => site.value && form.value
  ? pageContent('inicio', { ...site.value.identity, accommodations: props.accommodations, seo: form.value }, site.value.pages.inicio)
  : null)
const exampleTitle = computed(() => site.value && form.value ? `O lugar ${form.value.separator} ${site.value.identity.name}` : '')

const AI_OPTIONS = [
  { value: 'allow', label: 'Liberar', hint: 'ChatGPT, Gemini, Claude e outros podem ler o site e citar a pousada nas respostas.' },
  { value: 'training', label: 'Bloquear o treino de IA', hint: 'O site não vira material de treino de IA, mas continua aparecendo nas buscas com IA.' },
  { value: 'all', label: 'Bloquear todos', hint: 'Nenhum robô de IA lê o site. A pousada some também das respostas do ChatGPT e parecidos.' },
] as const

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
  form.value.author = form.value.author?.trim() || null
  try {
    await send('/site/seo', { method: 'PUT', body: { ...form.value } })
    form.value = copy()
    emit('message', props.section === 'seo'
      ? 'SEO salvo. Robots e mapa do site se atualizam em alguns minutos; o Google lê na próxima visita dele.'
      : 'Integrações salvas. Já valem para as próximas visitas ao site.')
    // O site guarda os dados da pousada por um minuto: a prévia ao vivo espera um pouco.
    if (props.section === 'seo') setTimeout(refreshLive, 65_000)
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

// Coordenadas: aceita "-23,318" (vírgula) e colar "lat, long" de uma vez (o que o Google Maps copia).
function setCoord(field: 'latitude' | 'longitude', raw: string) {
  if (!form.value) return
  const both = raw.match(/^\s*(-?\d+(?:[.,]\d+)?)\s*[,;\s]\s*(-?\d+(?:[.,]\d+)?)\s*$/)
  if (field === 'latitude' && both && raw.includes(' ')) {
    form.value.latitude = Number(both[1]!.replace(',', '.'))
    form.value.longitude = Number(both[2]!.replace(',', '.'))
    return
  }
  const n = Number(raw.trim().replace(',', '.'))
  form.value[field] = raw.trim() === '' || Number.isNaN(n) ? null : n
}
const origin = import.meta.client ? window.location.origin : ''

// ── Ao vivo: o robots.txt e o sitemap.xml que o site está servindo agora ──
const robotsTxt = ref<string | null>(null)
const sitemapUrls = ref<string[] | null>(null)
const liveError = ref(false)
const liveLoading = ref(false)
async function refreshLive() {
  liveLoading.value = true
  liveError.value = false
  try {
    const [robots, sitemap] = await Promise.all([
      $fetch<string>('/robots.txt', { responseType: 'text', query: { t: Date.now() } }),
      $fetch<string>('/sitemap.xml', { responseType: 'text', query: { t: Date.now() } }),
    ])
    robotsTxt.value = robots.replace(/^# (START|END) nuxt-robots.*\n?/gm, '').trim()
    // Só as páginas (<url><loc>), sem as fotos (<image:loc>).
    sitemapUrls.value = [...sitemap.matchAll(/<url>\s*<loc>([^<]+)<\/loc>/g)].map(m => m[1]!.replace(origin, '') || '/')
  }
  catch {
    liveError.value = true
  }
  finally {
    liveLoading.value = false
  }
}
onMounted(() => { if (props.section === 'seo') refreshLive() })

// Dados que o Google lê da pousada (Schema.org), montados como o site monta.
const siteApi = useApi()
const publicProperty = ref<Property | null>(null)
onMounted(async () => {
  if (props.section !== 'seo') return
  publicProperty.value = await siteApi<{ data: Property }>('/').then(r => r.data).catch(() => null)
})
const schema = computed(() => {
  const p = publicProperty.value
  if (!p || !form.value) return null
  const s = lodgingSchema(p, form.value.schema_type, origin)
  return [
    { label: 'Tipo', value: SCHEMA_TYPES.find(t => t.value === form.value!.schema_type)?.label },
    { label: 'Nome', value: s.name },
    { label: 'Endereço', value: [s.address?.streetAddress, s.address?.addressLocality, s.address?.addressRegion].filter(Boolean).join(', ') || null },
    { label: 'Telefone', value: s.telephone ? formatPhone(s.telephone) : null },
    { label: 'Diárias', value: s.priceRange ?? null },
    { label: 'Check-in e check-out', value: s.checkinTime ? `${s.checkinTime} e ${s.checkoutTime}` : null },
    { label: 'Fotos', value: s.image?.length ? `${s.image.length}` : null },
  ]
})

function beforeUnload(e: BeforeUnloadEvent) {
  if (dirty.value) e.preventDefault()
}
onMounted(() => window.addEventListener('beforeunload', beforeUnload))
onBeforeUnmount(() => window.removeEventListener('beforeunload', beforeUnload))
</script>

<template>
  <section class="seo">
    <header v-if="section === 'seo'" class="a-head a-rise">
      <h1>SEO</h1>
      <p>Como o seu site aparece no Google e o que os robôs podem ler. O texto de cada página e quais páginas aparecem no Google ficam em <NuxtLink :to="{ query: { aba: 'paginas' } }">Páginas</NuxtLink>.</p>
    </header>
    <header v-else class="a-head a-rise">
      <h1>Integrações</h1>
      <p>Ferramentas do Google e do Meta ligadas ao seu site. Pode deixar em branco o que não usa.</p>
    </header>

    <div v-if="loadError" class="a-card load-error" role="alert">
      <p>Não foi possível carregar o SEO. {{ loadError }}</p>
      <button type="button" class="btn btn-sm" @click="load">Tentar de novo</button>
    </div>
    <AdminLoading v-else-if="!site || !form" text="Carregando SEO..." :cards="2" height="180px" />

    <div v-else class="layout" :class="section">
      <div class="col">
        <form class="a-form" @submit.prevent="save">
          <section v-if="section === 'seo'" class="a-section a-rise" style="--i: 1">
            <h2>No Google</h2>
            <AdminOption id="seo-index" v-model="form.indexable" label="Aparecer no Google" hint="Desligue só enquanto o site estiver em montagem. Desligado, o robots.txt fecha o site e toda página recebe noindex." />
            <p v-if="!form.indexable" class="warn" role="status">O site vai sumir da busca do Google. Quem tem o link continua entrando normalmente.</p>
            <AdminTextField id="seo-desc" v-model="form.description" label="Descrição padrão" :max="PAGE_LIMITS.seo_description" :rows="3" placeholder="Ex.: Pousada em Mairiporã com piscina, fogueira e chalé com hidromassagem. Reserve direto, sem taxa." hint="Resumo da pousada para as páginas que não têm descrição própria." :error="err('description')" />
            <AdminTextField id="seo-keywords" v-model="form.keywords" label="Palavras-chave" :max="PAGE_LIMITS.keywords" :rows="2" placeholder="Ex.: pousada em Mairiporã, chalé com hidromassagem, pousada com piscina, day use" hint="Separadas por vírgula. O Google dá pouco peso a elas hoje, mas outros buscadores e diretórios ainda leem. Cada página pode ter as suas em Páginas." :error="err('keywords')" />
            <div class="a-field">
              <span id="sep-label" class="a-label">Separador dos títulos</span>
              <div class="chips" role="radiogroup" aria-labelledby="sep-label">
                <button v-for="s in SEPARATORS" :key="s" type="button" role="radio" :aria-checked="form.separator === s" @click="form.separator = s">{{ s }}</button>
              </div>
              <small class="a-hint">Na aba do navegador e no Google: <strong>{{ exampleTitle }}</strong></small>
            </div>
          </section>

          <section v-if="section === 'seo'" class="a-section a-rise" style="--i: 2">
            <h2><MapPin :size="20" aria-hidden="true" /> Autor e localização</h2>
            <p class="a-hint">Metas clássicas que buscadores locais, mapas e diretórios ainda usam. A região e a cidade vêm de <NuxtLink :to="{ query: { aba: 'contato' } }">Contato</NuxtLink>.</p>
            <AdminTextField id="seo-author" v-model="form.author" label="Autor do site" :max="80" :placeholder="site.identity.name" hint="Vazio, usa o nome da pousada." :error="err('author')" />
            <div class="a-row">
              <div class="a-field">
                <div class="a-field-head"><label for="seo-lat">Latitude</label></div>
                <input id="seo-lat" :value="form.latitude ?? ''" inputmode="decimal" placeholder="-23.3184" :aria-invalid="!!err('latitude') || undefined" @input="setCoord('latitude', ($event.target as HTMLInputElement).value)">
                <small v-if="err('latitude')" class="a-err">{{ err('latitude') }}</small>
              </div>
              <div class="a-field">
                <div class="a-field-head"><label for="seo-lng">Longitude</label></div>
                <input id="seo-lng" :value="form.longitude ?? ''" inputmode="decimal" placeholder="-46.5871" :aria-invalid="!!err('longitude') || undefined" @input="setCoord('longitude', ($event.target as HTMLInputElement).value)">
                <small v-if="err('longitude')" class="a-err">{{ err('longitude') }}</small>
              </div>
            </div>
            <p class="a-hint">No Google Maps, clique com o botão direito na pousada e copie os números do topo do menu. Pode colar os dois juntos no campo da latitude.</p>
          </section>

          <section v-if="section === 'seo'" class="a-section a-rise" style="--i: 2">
            <h2><Bot :size="20" aria-hidden="true" /> Robôs de IA</h2>
            <p class="a-hint">ChatGPT, Gemini, Claude e outros têm robôs que leem sites. Você escolhe o que eles podem fazer com o seu.</p>
            <div class="choices" role="radiogroup" aria-label="Robôs de IA">
              <label v-for="o in AI_OPTIONS" :key="o.value" class="choice" :class="{ on: form.ai_bots === o.value }">
                <input v-model="form.ai_bots" type="radio" name="ai-bots" :value="o.value" class="native">
                <span class="dot" aria-hidden="true" />
                <span><strong>{{ o.label }}</strong><small>{{ o.hint }}</small></span>
              </label>
            </div>
          </section>

          <section v-if="section === 'seo'" class="a-section a-rise" style="--i: 3">
            <h2><Sparkles :size="20" aria-hidden="true" /> Dados estruturados</h2>
            <p class="a-hint">Informações em formato que o Google entende (Schema.org): ajudam a mostrar endereço, telefone e preço da pousada direto na busca e no Maps.</p>
            <AdminOption id="seo-schema" v-model="form.structured_data" label="Enviar os dados da pousada ao Google">
              <label for="seo-schema-type" class="a-field narrow"><span class="a-label">Tipo de hospedagem</span>
                <select id="seo-schema-type" v-model="form.schema_type">
                  <option v-for="t in SCHEMA_TYPES" :key="t.value" :value="t.value">{{ t.label }}</option>
                </select>
              </label>
            </AdminOption>
            <dl v-if="form.structured_data && schema" class="facts">
              <div v-for="f in schema" :key="f.label" :class="{ missing: !f.value }">
                <dt>{{ f.label }}</dt>
                <dd>{{ f.value ?? 'Não cadastrado' }}</dd>
              </div>
            </dl>
            <p v-if="form.structured_data" class="a-hint">Os dados vêm de <NuxtLink :to="{ query: { aba: 'identidade' } }">Identidade</NuxtLink>, <NuxtLink :to="{ query: { aba: 'contato' } }">Contato</NuxtLink> e das acomodações. <a :href="`https://search.google.com/test/rich-results?url=${encodeURIComponent(origin + '/')}`" target="_blank" rel="noopener">Testar no Google ↗</a></p>
          </section>

          <section v-if="section === 'seo'" class="a-section a-rise" style="--i: 4">
            <h2><MapIcon :size="20" aria-hidden="true" /> Mapa do site</h2>
            <p class="a-hint">A lista de páginas que o Google deve visitar, montada sozinha com as páginas e as acomodações publicadas.</p>
            <AdminOption id="seo-sitemap-images" v-model="form.sitemap_images" label="Incluir as fotos" hint="As fotos das acomodações entram no mapa e podem aparecer no Google Imagens." />
          </section>

          <section v-if="section === 'integracoes'" class="a-section a-rise" style="--i: 1">
            <h2>Google e Meta</h2>
            <AdminTextField id="seo-google" v-model="form.google_verification" label="Verificação do Google Search Console" placeholder="Cole a tag ou só o código" hint="No Search Console, escolha &quot;Tag HTML&quot; e cole aqui. Depois envie o mapa do site (sitemap.xml) por lá." :error="err('google_verification')" />
            <div class="a-row">
              <AdminTextField id="seo-ga" v-model="form.ga_id" label="Google Analytics (GA4)" placeholder="G-XXXXXXXXXX" hint="Conta as visitas e os pedidos de reserva." :error="err('ga_id')" />
              <AdminTextField id="seo-pixel" v-model="form.meta_pixel_id" label="Pixel do Meta" inputmode="numeric" placeholder="1234567890" hint="Mede os anúncios do Instagram e do Facebook." :error="err('meta_pixel_id')" />
            </div>
          </section>

          <AdminSaveBar :dirty="dirty" :saving="saving" :error="error" @undo="undo" />
        </form>
      </div>

      <aside v-if="section === 'seo'" class="previews a-rise" style="--i: 2" aria-label="Prévias e o que está no ar">
        <div v-if="home" class="preview-block">
          <p class="a-label">Página inicial no Google</p>
          <AdminGooglePreview :title="home.seoTitle" :description="home.seoDescription" path="/" :name="site.identity.name" :icon-url="site.identity.icon_url" />
        </div>

        <div class="live a-section">
          <div class="live-head">
            <h2>No ar agora</h2>
            <button type="button" class="icon-btn" :class="{ spin: liveLoading }" aria-label="Atualizar" title="Atualizar" @click="refreshLive"><RefreshCw :size="18" /></button>
          </div>
          <p v-if="liveError" class="a-hint">Não foi possível ler o robots.txt e o mapa do site agora. Tente atualizar em instantes.</p>
          <template v-else>
            <div class="file">
              <div class="file-head"><FileCode2 :size="16" aria-hidden="true" /> <strong>robots.txt</strong> <a :href="`${origin}/robots.txt`" target="_blank" rel="noopener">abrir ↗</a></div>
              <pre v-if="robotsTxt !== null">{{ robotsTxt }}</pre>
              <div v-else class="a-skeleton" style="height: 90px" />
            </div>
            <div class="file">
              <div class="file-head"><MapIcon :size="16" aria-hidden="true" /> <strong>sitemap.xml</strong> <span v-if="sitemapUrls" class="count">{{ sitemapUrls.length }} {{ sitemapUrls.length === 1 ? 'página' : 'páginas' }}</span> <a :href="`${origin}/sitemap.xml`" target="_blank" rel="noopener">abrir ↗</a></div>
              <ul v-if="sitemapUrls" class="urls">
                <li v-for="u in sitemapUrls" :key="u"><code>{{ u }}</code></li>
                <li v-if="!sitemapUrls.length" class="a-hint">Vazio: o site está fora do Google.</li>
              </ul>
              <div v-else class="a-skeleton" style="height: 120px" />
            </div>
            <p class="a-hint">Depois de salvar, o robots.txt muda em até 1 minuto e o mapa do site em até 5.</p>
          </template>
        </div>

        <div class="lock a-section">
          <h2><LockKeyhole :size="20" aria-hidden="true" /> Painel protegido</h2>
          <p class="a-hint">O painel e a tela de entrar nunca aparecem no Google:</p>
          <ul>
            <li>Cada resposta do servidor leva <code>noindex, nofollow</code> (X-Robots-Tag) e a página repete na meta tag.</li>
            <li>Ficam fora do mapa do site e não podem ser abertos dentro de outro site.</li>
            <li>Não ficam guardados em cache por navegadores ou proxies.</li>
            <li>A API também pede para não ser indexada.</li>
          </ul>
          <p class="a-hint">De propósito, o robots.txt não bloqueia o painel: bloqueado lá, o Google não leria o noindex e poderia listar o endereço mesmo assim.</p>
        </div>
      </aside>
    </div>
  </section>
</template>

<style scoped lang="scss">
.seo { display: grid; gap: 1.2rem; max-width: 1240px; }
.layout { display: grid; gap: 1rem; align-items: start; grid-template-columns: minmax(0, 1fr); @include above(lg) { grid-template-columns: minmax(0, 1.15fr) minmax(0, 1fr); } }
.layout.integracoes { max-width: 820px; @include above(lg) { grid-template-columns: minmax(0, 1fr); } }
.col { display: grid; gap: 1rem; min-width: 0; }
.previews { display: grid; gap: 1rem; min-width: 0; }
.preview-block { display: grid; gap: .45rem; }
h2 { display: flex; align-items: center; gap: .5rem; }
h2 svg { color: var(--brand-text); }
.warn { margin: 0; padding: .7rem .9rem; border-radius: 12px; background: var(--warn-soft); color: var(--warn-ink); font-size: .88rem; font-weight: 600; }
.chips { display: flex; flex-wrap: wrap; gap: .4rem; }
.chips button {
  min-width: 48px; min-height: 44px; padding: 0 .9rem; border-radius: 12px; border: 1px solid var(--line); background: var(--card);
  font: inherit; font-size: 1.1rem; font-weight: 700; color: var(--text); cursor: pointer;
  &[aria-checked="true"] { background: var(--brand); border-color: var(--brand); color: var(--brand-ink); }
}
// Opções de robôs de IA: cartões com bolinha, a escolhida com fundo da marca.
.choices { display: grid; gap: .5rem; }
.choice {
  position: relative; display: flex; gap: .8rem; align-items: flex-start; padding: .8rem .9rem; border-radius: 14px; cursor: pointer; isolation: isolate;
  box-shadow: inset 0 0 0 1px var(--a-sunk);
  &::before { content: ""; position: absolute; inset: 0; z-index: -1; border-radius: inherit; background: var(--a-sunk); opacity: 0; transition: opacity $dur-sm $ease-move; }
  &.on::before { opacity: 1; }
  strong { display: block; font-size: .92rem; }
  small { display: block; margin-top: .15rem; color: var(--muted); font-size: .84rem; line-height: 1.4; }
}
.native { position: absolute; opacity: 0; width: 1px; height: 1px; }
.dot { flex: none; position: relative; width: 22px; height: 22px; margin-top: .1rem; border-radius: 50%; border: 2px solid color-mix(in srgb, var(--line) 60%, var(--muted)); }
.dot::after { content: ""; position: absolute; inset: 3px; border-radius: 50%; background: var(--brand); opacity: 0; transform: scale(.4); transition: opacity $dur-sm $ease-move, transform $dur-md $ease-enter; }
.on .dot { border-color: var(--brand); }
.on .dot::after { opacity: 1; transform: none; }
.choice:has(.native:focus-visible) { outline: 3px solid var(--focus); outline-offset: 2px; }
.narrow { max-width: 280px; }
.facts { margin: 0; display: grid; gap: .4rem; grid-template-columns: repeat(auto-fill, minmax(min(100%, 180px), 1fr)); }
.facts div { padding: .55rem .7rem; border-radius: 10px; background: var(--a-sunk); min-width: 0; }
.facts dt { @include eyebrow(var(--muted), sm); }
.facts dd { margin: .15rem 0 0; font-weight: 600; font-size: .88rem; overflow-wrap: anywhere; }
.facts .missing dd { color: var(--warn-ink); font-weight: 500; }
.live-head { display: flex; justify-content: space-between; align-items: center; }
.live-head h2 { margin: 0; font-family: var(--font-body); font-size: 1.15rem; font-weight: 700; }
.icon-btn { display: grid; place-items: center; width: 40px; height: 40px; border: 0; border-radius: 10px; background: var(--a-sunk); color: var(--muted); cursor: pointer; }
.icon-btn.spin svg { animation: spin 0.9s linear infinite; }
@keyframes spin { to { transform: rotate(360deg); } }
.file { display: grid; gap: .4rem; }
.file-head { display: flex; align-items: center; gap: .4rem; font-size: .88rem; }
.file-head svg { color: var(--brand-text); }
.file-head a { margin-left: auto; font-weight: 700; color: var(--brand-text); text-decoration: none; font-size: .82rem; }
.count { color: var(--muted); font-size: .8rem; }
pre { margin: 0; padding: .8rem .9rem; border-radius: 12px; background: var(--night); color: var(--on-night); font-size: .78rem; line-height: 1.5; overflow-x: auto; max-height: 260px; white-space: pre; }
.urls { list-style: none; margin: 0; padding: .5rem; border-radius: 12px; background: var(--a-sunk); display: grid; gap: .15rem; max-height: 220px; overflow-y: auto; }
.urls code { font-size: .8rem; }
.lock ul { margin: 0; padding-left: 1.1rem; display: grid; gap: .35rem; font-size: .86rem; }
.lock code { font-size: .78rem; background: var(--a-sunk); border-radius: 6px; padding: .05rem .3rem; }
.load-error { padding: 1rem 1.2rem; display: grid; gap: .8rem; justify-items: start; border-left: 4px solid var(--danger); }
.load-error p { margin: 0; }
@include reduced-motion { .icon-btn.spin svg { animation-duration: 2.5s; } }
</style>
