<script setup lang="ts">
import type { SocialNetwork } from '~/utils/social'
import { ExternalLink } from 'lucide-vue-next'

// "Seu site → Configurações → Redes sociais": os perfis que aparecem no rodapé do site (e entram
// nos dados estruturados como "sameAs", o que ajuda o Google a ligar a pousada aos perfis).
// Cada campo aceita o link inteiro ou só o @ (nas redes que usam @); o servidor confere a rede.
const emit = defineEmits<{ message: [text: string] }>()
const { site, loadError, load, send } = useAdminSite()
onMounted(() => { if (!site.value) load() })

type Form = Record<SocialNetwork, string>
const blank = (): Form => Object.fromEntries(SOCIAL_NETWORKS.map(n => [n.key, ''])) as Form
const fromSite = (): Form => ({ ...blank(), ...(site.value?.social ?? {}) }) as Form
const form = ref<Form>(blank())
watch(() => site.value?.social, () => { form.value = fromSite() }, { immediate: true })
const dirty = computed(() => !!site.value && JSON.stringify(form.value) !== JSON.stringify(fromSite()))

const saving = ref(false)
const error = ref('')
const errors = ref<Record<string, string[]>>({})
async function save() {
  saving.value = true
  error.value = ''
  errors.value = {}
  try {
    const body = Object.fromEntries(Object.entries(form.value).map(([k, v]) => [k, v.trim() || null]))
    await send('/site/social', { method: 'PUT', body })
    form.value = fromSite()
    const n = Object.keys(site.value?.social ?? {}).length
    emit('message', n ? `Redes salvas: ${plural(n, 'perfil aparece', 'perfis aparecem')} no rodapé do site.` : 'Redes removidas do rodapé.')
  }
  catch (e) {
    error.value = apiError(e)
    errors.value = apiFieldErrors(e)
  }
  finally {
    saving.value = false
  }
}
const preview = computed(() => socialLinks(Object.fromEntries(Object.entries(form.value).filter(([, v]) => v.trim()).map(([k]) => [k, '#'])) as Partial<Record<SocialNetwork, string>>))
</script>

<template>
  <section class="social">
    <header class="a-head a-rise">
      <h1>Redes sociais</h1>
      <p>Os perfis da pousada aparecem no rodapé de todas as páginas, com o ícone de cada rede. Preencha só as que você usa.</p>
    </header>

    <div v-if="loadError" class="a-card load-error" role="alert">
      <p>Não foi possível carregar. {{ loadError }}</p>
      <button type="button" class="btn btn-sm" @click="load">Tentar de novo</button>
    </div>
    <AdminLoading v-else-if="!site" text="Carregando redes..." :cards="1" height="320px" />

    <div v-else class="layout">
      <form class="a-form" @submit.prevent="save">
        <section class="a-section a-rise" style="--i: 1">
          <h2>Perfis</h2>
          <p class="a-hint">Cole o link do perfil (o do navegador ou o de "Compartilhar"). No Instagram, TikTok, YouTube e X, basta o @.</p>
          <div class="nets">
            <div v-for="net in SOCIAL_NETWORKS" :key="net.key" class="net" :class="{ on: form[net.key].trim() }">
              <span class="ico" aria-hidden="true"><SocialIcon :network="net.key" /></span>
              <div class="a-field">
                <div class="a-field-head">
                  <label :for="`social-${net.key}`">{{ net.name }}</label>
                  <a v-if="site.social[net.key]" :href="site.social[net.key]" target="_blank" rel="noopener" class="open">abrir <ExternalLink :size="12" aria-hidden="true" /></a>
                </div>
                <input :id="`social-${net.key}`" v-model="form[net.key]" type="text" inputmode="url" autocomplete="off" spellcheck="false" :placeholder="net.placeholder" :aria-invalid="!!errors[net.key] || undefined">
                <small v-if="errors[net.key]" class="a-err">{{ errors[net.key]![0] }}</small>
              </div>
            </div>
          </div>
        </section>
        <AdminSaveBar :dirty="dirty" :saving="saving" :error="error" @undo="form = fromSite()" />
      </form>

      <aside class="side a-rise" style="--i: 2" aria-label="Prévia do rodapé">
        <p class="a-label">No rodapé do site</p>
        <div class="footer-preview">
          <p class="name">{{ site.identity.name }}</p>
          <ul v-if="preview.length" class="icons">
            <li v-for="n in preview" :key="n.key" :title="n.name"><SocialIcon :network="n.key" :size="18" /></li>
          </ul>
          <p v-else class="empty">Nenhuma rede ainda.</p>
        </div>
        <p class="a-hint">Os links também vão para os dados estruturados do Google (sameAs), que ligam o site aos perfis da pousada.</p>
      </aside>
    </div>
  </section>
</template>

<style scoped lang="scss">
.social { display: grid; gap: 1.2rem; max-width: 1240px; }
.layout { display: grid; gap: 1rem; align-items: start; grid-template-columns: minmax(0, 1fr); @include above(lg) { grid-template-columns: minmax(0, 1.6fr) minmax(260px, 1fr); } }
.nets { display: grid; gap: .7rem; grid-template-columns: repeat(auto-fill, minmax(min(100%, 300px), 1fr)); }
.net { display: flex; gap: .7rem; align-items: flex-start; padding: .7rem; border-radius: 14px; background: var(--a-sunk); }
.net .a-field { flex: 1; min-width: 0; }
.net input { width: 100%; }
.ico { display: grid; place-items: center; flex: none; width: 40px; height: 40px; margin-top: 1.4rem; border-radius: 12px; background: var(--a-card); color: var(--muted); }
.net.on .ico { background: var(--night); color: #fff; }
.open { display: inline-flex; align-items: center; gap: .2rem; font-size: .78rem; font-weight: 700; color: var(--brand-text); }
.side { display: grid; gap: .6rem; @include above(lg) { position: sticky; top: 1.5rem; } }
.footer-preview { display: grid; gap: .8rem; padding: 1.2rem; border-radius: 16px; background: var(--night); color: var(--on-night); }
.footer-preview .name { margin: 0; font-family: var(--font-display); font-size: 1.5rem; }
.icons { list-style: none; margin: 0; padding: 0; display: flex; flex-wrap: wrap; gap: .5rem; }
.icons li { display: grid; place-items: center; width: 40px; height: 40px; border-radius: 50%; border: 1px solid rgb(255 255 255 / .25); }
.empty { margin: 0; color: var(--on-night-muted); font-size: .88rem; }
.load-error { padding: 1rem 1.2rem; display: grid; gap: .8rem; justify-items: start; border-left: 4px solid var(--danger); }
.load-error p { margin: 0; }
</style>
