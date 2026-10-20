<script setup lang="ts">
import type { AdminSiteIdentity } from '~/composables/useAdminApi'

// "Seu site → Configurações": logo e ícone (salvam na hora), nome, frase, cor e contato
// (salvam juntos pela barra do pé).
defineProps<{ demo?: boolean }>()
const emit = defineEmits<{ message: [text: string], changed: [] }>()

const { site, loadError, load, send } = useAdminSite()
onMounted(load)

const FIELDS = ['name', 'tagline', 'description', 'primary_color', 'email', 'phone', 'whatsapp', 'address', 'city', 'state'] as const
type IdentityForm = Record<typeof FIELDS[number], string | null>
const pick = (): IdentityForm => Object.fromEntries(FIELDS.map(f => [f, site.value?.identity[f] ?? null])) as IdentityForm

const form = ref<IdentityForm>(pick())
watch(() => site.value?.identity, (now, before) => {
  // Primeira carga, ou depois de salvar: o formulário segue o que está no servidor.
  if (!before || !dirtyAgainst(before)) form.value = pick()
}, { immediate: true })
function dirtyAgainst(identity: AdminSiteIdentity) {
  return FIELDS.some(f => (form.value[f] ?? null) !== (identity[f] ?? null))
}
const dirty = computed(() => !!site.value && dirtyAgainst(site.value.identity))

// Cores prontas, todas com bom contraste para o texto branco dos botões.
const COLORS = ['#2f6f4f', '#1f5f7a', '#3d4f8f', '#7a3e6b', '#a1452d', '#8a6a1f', '#4a5a3a', '#2b2b2b']

const saving = ref(false)
const error = ref('')
const errors = ref<Record<string, string[]>>({})
async function save() {
  saving.value = true
  error.value = ''
  errors.value = {}
  try {
    await send('/site/identity', { method: 'PUT', body: { ...form.value } })
    form.value = pick()
    emit('message', 'Configurações salvas. O site já está atualizado.')
    emit('changed')
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
  form.value = pick()
  errors.value = {}
  error.value = ''
}
const err = (f: string) => errors.value[f]?.[0]

function beforeUnload(e: BeforeUnloadEvent) {
  if (dirty.value) e.preventDefault()
}
onMounted(() => window.addEventListener('beforeunload', beforeUnload))
onBeforeUnmount(() => window.removeEventListener('beforeunload', beforeUnload))
</script>

<template>
  <section class="settings">
    <header class="a-head a-rise">
      <h1>Configurações</h1>
      <p>A cara do seu site: logo, ícone, nome, cor e os contatos que aparecem para o hóspede.</p>
    </header>

    <div v-if="loadError" class="a-card load-error" role="alert">
      <p>Não foi possível carregar as configurações. {{ loadError }}</p>
      <button type="button" class="btn btn-sm" @click="load">Tentar de novo</button>
    </div>
    <AdminLoading v-else-if="!site" text="Carregando configurações..." :cards="2" height="180px" />

    <template v-else>
      <section class="a-section a-rise" style="--i: 1">
        <h2>Logo e ícone</h2>
        <p class="a-hint">Mudam na hora, sem precisar salvar.</p>
        <AdminImageSlot kind="logo" :url="site.identity.logo_url" label="Logo" hint="Aparece no menu do site, no lugar do nome. PNG com fundo transparente fica melhor." :demo="demo" @message="emit('message', $event)" />
        <div class="logo-preview" aria-hidden="true">
          <div class="bar dark">
            <img v-if="site.identity.logo_url" :src="site.identity.logo_url" alt="">
            <span v-else class="word">{{ form.name || site.identity.name }}</span>
            <span class="links"><i /><i /><i /></span>
          </div>
        </div>
        <hr>
        <AdminImageSlot kind="icon" :url="site.identity.icon_url" label="Ícone" hint="O símbolo pequeno na aba do navegador, nos favoritos e na tela do celular. Use uma imagem quadrada e simples." :demo="demo" @message="emit('message', $event); emit('changed')" />
        <div class="tab-preview" aria-hidden="true">
          <div class="tab">
            <span class="fav">
              <img v-if="site.identity.icon_url" :src="site.identity.icon_url" alt="">
              <span v-else>{{ (form.name || site.identity.name).slice(0, 1) }}</span>
            </span>
            <span class="tab-title">{{ form.name || site.identity.name }}</span>
            <span class="x">×</span>
          </div>
        </div>
      </section>

      <form class="a-form" @submit.prevent="save">
        <section class="a-section a-rise" style="--i: 2">
          <h2>Nome e cor</h2>
          <AdminTextField id="site-name" v-model="form.name" label="Nome da pousada" :max="80" :error="err('name')" />
          <AdminTextField id="site-tagline" v-model="form.tagline" label="Frase de destaque" :max="140" placeholder="Ex.: Piscina, fogueira e vista para as montanhas" hint="O título grande da capa do site." :error="err('tagline')" />
          <div class="a-field">
            <span class="a-label" id="color-label">Cor principal</span>
            <div class="colors" role="radiogroup" aria-labelledby="color-label">
              <button
                v-for="c in COLORS"
                :key="c"
                type="button"
                role="radio"
                class="swatch"
                :aria-checked="form.primary_color === c"
                :aria-label="`Cor ${c}`"
                :style="{ '--c': c }"
                @click="form.primary_color = c"
              />
              <label class="custom" :style="{ '--c': form.primary_color ?? undefined }">
                <input :value="form.primary_color ?? '#2f6f4f'" type="color" aria-label="Escolher outra cor" @input="form.primary_color = ($event.target as HTMLInputElement).value">
                <span>Outra</span>
              </label>
            </div>
            <div class="color-preview" :style="{ '--brand': form.primary_color ?? undefined }">
              <span class="btn btn-sm">Ver datas e reservar</span>
              <span class="pill">Chalé Master</span>
            </div>
            <small v-if="err('primary_color')" class="a-err">{{ err('primary_color') }}</small>
          </div>
        </section>

        <section class="a-section a-rise" style="--i: 3">
          <h2>Sobre a pousada</h2>
          <AdminTextField id="site-desc" v-model="form.description" label="Apresentação" :max="1200" :rows="5" placeholder="O que a pousada tem de especial, onde fica, para quem é..." hint="Aparece em &quot;O lugar&quot; e é o resumo padrão para o Google." :error="err('description')" />
        </section>

        <section class="a-section a-rise" style="--i: 4">
          <h2>Contato</h2>
          <p class="a-hint">Aparecem no rodapé, na página de contato e nos botões de WhatsApp. Deixe em branco o que não usa.</p>
          <div class="a-row">
            <AdminTextField id="site-whatsapp" v-model="form.whatsapp" label="WhatsApp" type="tel" inputmode="numeric" placeholder="5511999990000" hint="Só números, com 55 e o DDD." :error="err('whatsapp')" />
            <AdminTextField id="site-phone" v-model="form.phone" label="Telefone" type="tel" inputmode="tel" placeholder="1140001234" :error="err('phone')" />
            <AdminTextField id="site-email" v-model="form.email" label="E-mail" type="email" inputmode="email" placeholder="contato@suapousada.com.br" :error="err('email')" />
          </div>
          <AdminTextField id="site-address" v-model="form.address" label="Endereço" :max="160" placeholder="Rua, número" :error="err('address')" />
          <div class="a-row">
            <AdminTextField id="site-city" v-model="form.city" label="Cidade" :max="80" :error="err('city')" />
            <AdminTextField id="site-state" v-model="form.state" label="Estado (UF)" :max="2" :count="false" placeholder="SP" :error="err('state')" />
          </div>
        </section>

        <AdminSaveBar :dirty="dirty" :saving="saving" :error="error" @undo="undo" />
      </form>
    </template>
  </section>
</template>

<style scoped lang="scss">
.settings { display: grid; gap: 1.2rem; max-width: 900px; }
hr { border: 0; border-top: 1px solid var(--a-sunk); margin: .2rem 0; width: 100%; }
// Prévia do logo no menu do site (sobre a foto escura da capa).
.logo-preview .bar { display: flex; align-items: center; justify-content: space-between; gap: 1rem; min-height: 64px; padding: .7rem 1rem; border-radius: 14px; }
.bar.dark { background: linear-gradient(120deg, #1b2a22, #0e1411); color: #fff; }
.bar img { height: 40px; max-width: 200px; object-fit: contain; }
.word { font-family: var(--font-display); font-size: 1.4rem; }
.links { display: flex; gap: .5rem; }
.links i { display: block; width: 38px; height: 6px; border-radius: 3px; background: rgb(255 255 255 / .3); }
// Prévia do ícone numa aba do navegador.
.tab-preview { padding: .6rem .6rem 0; border-radius: 14px 14px 0 0; background: #dfe3e8; max-width: 360px; }
.tab { display: flex; align-items: center; gap: .55rem; padding: .55rem .8rem; border-radius: 10px 10px 0 0; background: #fff; font-family: system-ui, sans-serif; font-size: .8rem; }
.fav { display: grid; place-items: center; flex: none; width: 18px; height: 18px; border-radius: 4px; overflow: hidden; background: var(--brand); color: #fff; font-size: .7rem; font-weight: 700; }
.fav img { width: 100%; height: 100%; object-fit: cover; }
.tab-title { flex: 1; min-width: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.x { color: #5f6368; }
.colors { display: flex; flex-wrap: wrap; align-items: center; gap: .5rem; }
.swatch {
  position: relative; width: 40px; height: 40px; border-radius: 50%; border: 0; background: var(--c); cursor: pointer;
  &::after { content: ""; position: absolute; inset: -4px; border-radius: 50%; box-shadow: 0 0 0 2px var(--c); opacity: 0; transform: scale(.85); transition: opacity $dur-sm $ease-move, transform $dur-md $ease-enter; }
  &[aria-checked="true"]::after { opacity: 1; transform: none; }
}
.custom { position: relative; display: inline-flex; align-items: center; gap: .45rem; min-height: 40px; padding: 0 .8rem 0 .3rem; border-radius: 999px; background: var(--a-sunk); font-size: .84rem; font-weight: 700; cursor: pointer; }
.custom input { width: 32px; height: 32px; min-height: 0 !important; padding: 0; border: 0; border-radius: 50%; background: none; cursor: pointer; }
.color-preview { display: flex; flex-wrap: wrap; align-items: center; gap: .6rem; margin-top: .4rem; padding: .8rem; border-radius: 14px; background: var(--paper); }
.color-preview .btn { pointer-events: none; }
.pill { padding: .3rem .8rem; border-radius: 999px; background: color-mix(in srgb, var(--brand) 14%, white); color: var(--brand); font-size: .8rem; font-weight: 700; }
.load-error { padding: 1rem 1.2rem; display: grid; gap: .8rem; justify-items: start; border-left: 4px solid var(--danger); }
.load-error p { margin: 0; }
</style>
