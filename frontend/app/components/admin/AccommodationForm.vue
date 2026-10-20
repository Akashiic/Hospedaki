<script setup lang="ts">
import type { AccommodationInput, AdminAccommodation } from '~/composables/useAdminApi'

// Formulário de uma acomodação: dados, hóspedes, preços (com simulação do que o hóspede paga),
// temporadas e fotos. Nova acomodação: primeiro salva os dados, depois libera fotos e temporadas.
const props = defineProps<{ accommodation: AdminAccommodation | null, demo?: boolean }>()
const emit = defineEmits<{ saved: [acc: AdminAccommodation, created: boolean], deleted: [id: number], back: [] }>()

const api = useAdminApi()

const blank = (): AccommodationInput => ({
  name: '', description: '', amenities: [], max_guests: 2, included_guests: null,
  base_price: 0, weekend_price: null, extra_guest_price: null, cleaning_fee: null,
  weekly_discount: null, monthly_discount: null, min_nights: 1, best_for: null, active: true,
  booking_mode: 'nightly', check_in_time: null, check_out_time: null, hourly_price: null, min_hours: 1, opens_at: '08:00', closes_at: '22:00',
})
const fromAcc = (a: AdminAccommodation): AccommodationInput => ({
  name: a.name, description: a.description ?? '', amenities: [...a.amenities], max_guests: a.max_guests,
  included_guests: a.included_guests, base_price: a.base_price, weekend_price: a.weekend_price,
  extra_guest_price: a.extra_guest_price, cleaning_fee: a.cleaning_fee, weekly_discount: a.weekly_discount,
  monthly_discount: a.monthly_discount, min_nights: a.min_nights, best_for: a.best_for, active: a.active,
  booking_mode: a.booking_mode, check_in_time: a.check_in_time, check_out_time: a.check_out_time, hourly_price: a.hourly_price,
  min_hours: a.min_hours, opens_at: a.opens_at, closes_at: a.closes_at,
})

const current = ref<AdminAccommodation | null>(props.accommodation)
const form = reactive<AccommodationInput>(current.value ? fromAcc(current.value) : blank())

// Opções de preço ligadas por caixa de marcar. Desligar zera o campo (o site deixa de usar);
// religar devolve o último valor ou uma sugestão para começar.
const OFF = { included: ['included_guests', null], extra: ['extra_guest_price', null], weekend: ['weekend_price', null], cleaning: ['cleaning_fee', null], minNights: ['min_nights', 1], weekly: ['weekly_discount', null], monthly: ['monthly_discount', null] } as const
type Opt = keyof typeof OFF
const isOn = (o: Opt) => { const [key, off] = OFF[o]; return form[key] != null && form[key] !== off && form[key] !== 0 }
const opts = reactive(Object.fromEntries(Object.keys(OFF).map(o => [o, isOn(o as Opt)])) as Record<Opt, boolean>)
const syncOpts = () => (Object.keys(OFF) as Opt[]).forEach((o) => { opts[o] = isOn(o) })
const stash: Partial<Record<Opt, number | null>> = {}
const suggestion: Record<Opt, () => number> = {
  included: () => Math.min(2, form.max_guests), extra: () => 8000, weekend: () => form.base_price, cleaning: () => 10000,
  minNights: () => 2, weekly: () => 10, monthly: () => 20,
}
watch(() => ({ ...opts }), (now, before) => {
  for (const o of Object.keys(OFF) as Opt[]) {
    if (now[o] === before[o]) continue
    const [key, off] = OFF[o]
    // Ligar só preenche um campo vazio: ao desfazer ou salvar, o valor que voltou fica.
    if (now[o]) {
      if (form[key] == null || form[key] === off) (form as Record<string, unknown>)[key] = stash[o] ?? suggestion[o]()
    }
    else {
      stash[o] = form[key] as number | null
      ;(form as Record<string, unknown>)[key] = off
    }
  }
  // Sem pessoas incluídas, não existe "pessoa a mais".
  if (!now.included && opts.extra) opts.extra = false
})
// ── Horários ──
const hourly = computed(() => form.booking_mode === 'hourly')
// Check-in e check-out próprios desta acomodação; desligado, valem os da pousada.
const propertyTimes = computed(() => ({
  in: current.value?.property_check_in_time ?? '14:00',
  out: current.value?.property_check_out_time ?? '12:00',
}))
const ownTimes = ref(form.check_in_time != null || form.check_out_time != null)
watch(ownTimes, (on) => {
  if (on) {
    form.check_in_time ??= propertyTimes.value.in
    form.check_out_time ??= propertyTimes.value.out
  }
  else {
    form.check_in_time = null
    form.check_out_time = null
  }
})
// Por hora: começa com um preço de exemplo para a simulação fazer sentido.
watch(() => form.booking_mode, (mode) => {
  if (mode === 'hourly' && form.hourly_price == null) form.hourly_price = 10000
})
const hourSimulation = computed(() => [Math.max(form.min_hours, 4), Math.max(form.min_hours, 6), Math.max(form.min_hours, 8)]
  .filter((h, i, all) => all.indexOf(h) === i)
  .map(h => ({ hours: h, total: h * (form.hourly_price ?? 0) + (form.cleaning_fee ?? 0) })))

const savedSnapshot = ref(JSON.stringify(form))
const dirty = computed(() => JSON.stringify(form) !== savedSnapshot.value)
const isNew = computed(() => !current.value)

const saving = ref(false)
const errors = ref<Record<string, string[]>>({})
const message = ref('')
const fieldError = (name: string) => errors.value[name]?.[0]

async function save() {
  saving.value = true
  errors.value = {}
  message.value = ''
  try {
    const created = isNew.value
    const res = await api<{ data: AdminAccommodation }>(created ? '/accommodations' : `/accommodations/${current.value!.id}`, {
      method: created ? 'POST' : 'PUT',
      body: { ...form, description: form.description || null },
    })
    current.value = res.data
    Object.assign(form, fromAcc(res.data))
    syncOpts()
    ownTimes.value = form.check_in_time != null || form.check_out_time != null
    savedSnapshot.value = JSON.stringify(form)
    message.value = created ? 'Acomodação criada. Agora adicione as fotos.' : 'Alterações salvas. Já estão no site.'
    emit('saved', res.data, created)
  }
  catch (e) {
    const data = (e as { data?: { errors?: Record<string, string[]> } }).data
    errors.value = data?.errors ?? {}
    message.value = apiError(e)
  }
  finally {
    saving.value = false
  }
}

function undo() {
  Object.assign(form, current.value ? fromAcc(current.value) : blank())
  ownTimes.value = form.check_in_time != null || form.check_out_time != null
  nextTick(syncOpts)
  errors.value = {}
}

// Fotos e temporadas salvam na hora e devolvem a acomodação atualizada.
function updated(acc: AdminAccommodation) {
  current.value = acc
  emit('saved', acc, false)
}

// ── Comodidades ──
const newAmenity = ref('')
const SUGGESTIONS = ['Wi-Fi', 'Ar-condicionado', 'Estacionamento', 'Piscina', 'Café da manhã', 'Cozinha completa', 'TV com streamings', 'Churrasqueira', 'Hidromassagem', 'Lareira', 'Varanda', 'Aceita pets', 'Roupa de cama e banho', 'Vista para as montanhas']
const suggestions = computed(() => SUGGESTIONS.filter(s => !form.amenities.some(a => a.toLowerCase() === s.toLowerCase())).slice(0, 8))
function addAmenity(value = newAmenity.value) {
  const v = value.trim()
  if (v && !form.amenities.some(a => a.toLowerCase() === v.toLowerCase())) form.amenities.push(v)
  newAmenity.value = ''
}
function removeAmenity(i: number) {
  form.amenities.splice(i, 1)
}

// ── Simulação: quanto o hóspede paga com os preços do formulário (2 pessoas) ──
const simulation = computed(() => {
  const week = form.base_price
  const end = form.weekend_price ?? form.base_price
  return [
    { label: 'Fim de semana', detail: '2 diárias (sex. e sáb.)', nights: 2, nightsTotal: 2 * end },
    { label: 'Uma semana', detail: '7 diárias', nights: 7, nightsTotal: 5 * week + 2 * end },
    { label: 'Um mês', detail: '28 diárias', nights: 28, nightsTotal: 20 * week + 8 * end },
  ].map(s => ({ ...s, ...stayCharges(s.nightsTotal, s.nights, form) }))
})

// ── Excluir ──
const askingDelete = ref(false)
const deleting = ref(false)
async function remove() {
  if (!current.value) return
  deleting.value = true
  try {
    await api(`/accommodations/${current.value.id}`, { method: 'DELETE' })
    emit('deleted', current.value.id)
  }
  catch (e) {
    message.value = apiError(e)
    askingDelete.value = false
  }
  finally {
    deleting.value = false
  }
}

function beforeUnload(e: BeforeUnloadEvent) {
  if (dirty.value) e.preventDefault()
}
onMounted(() => window.addEventListener('beforeunload', beforeUnload))
onBeforeUnmount(() => window.removeEventListener('beforeunload', beforeUnload))

function back() {
  if (dirty.value && !window.confirm('Sair sem salvar as alterações?')) return
  emit('back')
}
</script>

<template>
  <div class="editor a-rise">
    <header class="head">
      <button type="button" class="back" @click="back">← Acomodações</button>
      <h2>{{ isNew ? 'Nova acomodação' : current!.name }}</h2>
      <a v-if="current && current.active" :href="`/acomodacoes/${current.slug}`" target="_blank" rel="noopener" class="view">Ver no site ↗</a>
    </header>

    <form class="sections" @submit.prevent="save">
      <section class="card">
        <h3>Dados</h3>
        <label for="acc-name">Nome
          <input id="acc-name" v-model="form.name" required maxlength="80" placeholder="Ex.: Chalé do Lago">
          <small v-if="fieldError('name')" class="err">{{ fieldError('name') }}</small>
        </label>
        <label for="acc-desc"><span>Descrição <span class="opt">o que o hóspede encontra</span></span>
          <textarea id="acc-desc" v-model="form.description" rows="5" maxlength="3000" placeholder="Tamanho, quartos e camas, banheiro, vista, o que tem de especial..." />
        </label>

        <div class="field">
          <span class="label">Comodidades</span>
          <ul v-if="form.amenities.length" class="tags">
            <li v-for="(a, i) in form.amenities" :key="a">
              {{ a }}
              <button type="button" :aria-label="`Remover ${a}`" @click="removeAmenity(i)">×</button>
            </li>
          </ul>
          <div class="add">
            <label for="acc-amenity" class="sr-only">Nova comodidade</label>
            <input id="acc-amenity" v-model="newAmenity" maxlength="80" placeholder="Ex.: Banheira de hidromassagem" @keydown.enter.prevent="addAmenity()">
            <button type="button" class="btn btn-ghost btn-sm" :disabled="!newAmenity.trim()" @click="addAmenity()">Adicionar</button>
          </div>
          <div v-if="suggestions.length" class="suggest">
            <span>Sugestões:</span>
            <button v-for="s in suggestions" :key="s" type="button" @click="addAmenity(s)">+ {{ s }}</button>
          </div>
        </div>

        <div class="row">
          <label for="acc-best">Destaque na capa
            <select id="acc-best" v-model="form.best_for">
              <option :value="null">Nenhum</option>
              <option value="casal">Para quem vem a dois</option>
              <option value="familia">Para quem vem com a família</option>
            </select>
          </label>
          <div class="field">
            <span class="label">No site</span>
            <button type="button" class="switch" role="switch" :aria-checked="form.active" @click="form.active = !form.active">
              <span class="track"><span class="thumb" /></span>
              {{ form.active ? 'Publicada' : 'Oculta (não aparece nem recebe reservas)' }}
            </button>
          </div>
        </div>
      </section>

      <section class="card">
        <h3>Como é reservada</h3>
        <div class="modes" role="radiogroup" aria-label="Como é reservada">
          <label class="mode" :class="{ on: !hourly }">
            <input v-model="form.booking_mode" type="radio" name="booking-mode" value="nightly" class="native">
            <strong>Por diária</strong>
            <small>Estadias, com entrada e saída em dias diferentes.</small>
          </label>
          <label class="mode" :class="{ on: hourly }">
            <input v-model="form.booking_mode" type="radio" name="booking-mode" value="hourly" class="native">
            <strong>Por hora</strong>
            <small>Eventos e day use: o cliente escolhe o dia e o horário.</small>
          </label>
        </div>
        <small v-if="fieldError('booking_mode')" class="err">{{ fieldError('booking_mode') }}</small>
      </section>

      <section v-if="hourly" class="card">
        <h3>Preço e horários</h3>
        <div class="row">
          <label for="acc-hourly">Preço da hora
            <AdminMoneyInput id="acc-hourly" v-model="form.hourly_price" required />
            <small v-if="fieldError('hourly_price')" class="err">{{ fieldError('hourly_price') }}</small>
          </label>
          <label for="acc-min-hours">Mínimo de horas
            <input id="acc-min-hours" v-model.number="form.min_hours" type="number" min="1" max="24" required>
          </label>
          <label for="acc-max-h">Capacidade (pessoas)
            <input id="acc-max-h" v-model.number="form.max_guests" type="number" min="1" max="500" required>
          </label>
        </div>
        <div class="row">
          <label for="acc-opens">Abre às
            <input id="acc-opens" v-model="form.opens_at" type="time" step="3600" required>
          </label>
          <label for="acc-closes">Fecha às
            <input id="acc-closes" v-model="form.closes_at" type="time" step="3600" required>
            <small v-if="fieldError('closes_at')" class="err">{{ fieldError('closes_at') }}</small>
          </label>
        </div>
        <div class="options">
          <AdminOption id="opt-clean-h" v-model="opts.cleaning" label="Taxa de limpeza" hint="Cobrada uma vez por reserva.">
            <label for="acc-clean-h">Valor da taxa <AdminMoneyInput id="acc-clean-h" v-model="form.cleaning_fee" /></label>
          </AdminOption>
        </div>
        <div class="sim" aria-live="polite">
          <p class="sim-title">Quanto o cliente paga</p>
          <div class="sim-grid">
            <div v-for="s in hourSimulation" :key="s.hours">
              <span class="sim-label">{{ s.hours }} horas</span>
              <strong>{{ formatMoney(s.total) }}</strong>
              <small>{{ s.hours }} × {{ formatMoney(form.hourly_price ?? 0) }}<template v-if="form.cleaning_fee"> · com limpeza</template></small>
            </div>
          </div>
          <p class="hint">Um evento ocupa o dia inteiro das acomodações do mesmo imóvel, e uma estadia nelas fecha o espaço naquele dia.</p>
        </div>
      </section>

      <template v-if="!hourly">
      <section class="card">
        <h3>Hóspedes</h3>
        <label for="acc-max" class="narrow">Capacidade máxima
          <input id="acc-max" v-model.number="form.max_guests" type="number" min="1" max="50" required>
        </label>
        <div class="options">
          <AdminOption id="opt-incl" v-model="opts.included" label="A diária vale para menos pessoas" hint="Ex.: diária para 2, e quem passar disso paga à parte. Desligado, a diária vale para a lotação toda.">
            <label for="acc-incl">Pessoas incluídas na diária
              <input id="acc-incl" v-model.number="form.included_guests" type="number" min="1" :max="form.max_guests">
              <small v-if="fieldError('included_guests')" class="err">{{ fieldError('included_guests') }}</small>
            </label>
          </AdminOption>
          <AdminOption v-if="opts.included" id="opt-extra" v-model="opts.extra" label="Cobrar por pessoa a mais" hint="Desligado, o site avisa que o acréscimo é combinado na confirmação.">
            <label for="acc-extra">Valor por pessoa a mais, por diária
              <AdminMoneyInput id="acc-extra" v-model="form.extra_guest_price" />
            </label>
          </AdminOption>
        </div>
      </section>

      <section class="card">
        <h3>Preços</h3>
        <label for="acc-base" class="narrow">Diária
          <AdminMoneyInput id="acc-base" v-model="form.base_price" required />
          <small v-if="fieldError('base_price')" class="err">{{ fieldError('base_price') }}</small>
        </label>
        <p class="sub">Ligue só o que a sua pousada usa:</p>
        <div class="options">
          <AdminOption id="opt-weekend" v-model="opts.weekend" label="Preço diferente para sexta e sábado" hint="As noites de sexta e sábado passam a ter a diária daqui.">
            <label for="acc-weekend">Diária de sexta e sábado <AdminMoneyInput id="acc-weekend" v-model="form.weekend_price" /></label>
          </AdminOption>
          <AdminOption id="opt-clean" v-model="opts.cleaning" label="Taxa de limpeza" hint="Cobrada uma vez por estadia, sem desconto.">
            <label for="acc-clean">Valor da taxa <AdminMoneyInput id="acc-clean" v-model="form.cleaning_fee" /></label>
          </AdminOption>
          <AdminOption id="opt-min" v-model="opts.minNights" label="Mínimo de diárias" hint="Ex.: só aceitar reservas de 2 diárias ou mais.">
            <label for="acc-min">Mínimo
              <input id="acc-min" v-model.number="form.min_nights" type="number" min="2" max="90">
            </label>
          </AdminOption>
          <AdminOption id="opt-week" v-model="opts.weekly" label="Desconto para uma semana ou mais" hint="Sobre as diárias, a partir de 7.">
            <label for="acc-week">Desconto
              <span class="pct"><input id="acc-week" v-model.number="form.weekly_discount" type="number" min="1" max="90"><span aria-hidden="true">%</span></span>
            </label>
          </AdminOption>
          <AdminOption id="opt-month" v-model="opts.monthly" label="Desconto para um mês ou mais" hint="Sobre as diárias, a partir de 28. Vale no lugar do semanal.">
            <label for="acc-month">Desconto
              <span class="pct"><input id="acc-month" v-model.number="form.monthly_discount" type="number" min="1" max="90"><span aria-hidden="true">%</span></span>
              <small v-if="fieldError('monthly_discount')" class="err">{{ fieldError('monthly_discount') }}</small>
            </label>
          </AdminOption>
        </div>

        <div class="sim" aria-live="polite">
          <p class="sim-title">Quanto o hóspede paga (2 pessoas)</p>
          <div class="sim-grid">
            <div v-for="s in simulation" :key="s.label">
              <span class="sim-label">{{ s.label }}</span>
              <strong>{{ formatMoney(s.total) }}</strong>
              <small>{{ s.detail }}<template v-if="s.discount"> · −{{ s.discount_percent }}%</template><template v-if="s.cleaning_fee"> · com limpeza</template></small>
            </div>
          </div>
          <p class="hint">Feriados e alta temporada têm preço próprio: cadastre em "Temporadas e feriados".</p>
        </div>
      </section>

      <section class="card">
        <h3>Horários</h3>
        <AdminOption id="opt-times" v-model="ownTimes" label="Check-in e check-out próprios" :hint="`Desligado, valem os da pousada: entrada às ${formatHour(propertyTimes.in)} e saída até ${formatHour(propertyTimes.out)}.`">
          <div class="row">
            <label for="acc-in">Check-in a partir das
              <input id="acc-in" v-model="form.check_in_time" type="time" step="1800">
            </label>
            <label for="acc-out">Check-out até as
              <input id="acc-out" v-model="form.check_out_time" type="time" step="1800">
            </label>
          </div>
        </AdminOption>
        <p class="hint">O hóspede informa o horário previsto de chegada ao reservar, e ele aparece no pedido e na agenda do dia.</p>
      </section>
      </template>

      <div class="savebar">
        <p class="status" :class="{ dirty, error: Object.keys(errors).length }" role="status">
          {{ message || (dirty ? 'Alterações não salvas' : isNew ? 'Preencha e salve para criar' : 'Tudo salvo') }}
        </p>
        <button v-if="!isNew" type="button" class="btn btn-ghost btn-sm" :disabled="!dirty || saving" @click="undo">Desfazer</button>
        <button class="btn btn-sm" :disabled="saving || (!dirty && !isNew)">{{ saving ? 'Salvando...' : isNew ? 'Criar acomodação' : 'Salvar alterações' }}</button>
      </div>
    </form>

    <template v-if="current">
      <AdminAccommodationPhotos :accommodation="current" :demo="demo" @updated="updated" />
      <AdminAccommodationSeasons v-if="!hourly" :accommodation="current" @updated="updated" />

      <section class="card danger-zone">
        <h3>Excluir acomodação</h3>
        <template v-if="current.bookings_count">
          <p class="hint">Ela tem {{ plural(current.bookings_count, 'reserva', 'reservas') }} no histórico, então não pode ser excluída. Para tirar do site, deixe como <strong>Oculta</strong> em Dados e salve.</p>
        </template>
        <template v-else>
          <p class="hint">Some do site e do painel, com fotos e temporadas. Não dá para desfazer.</p>
          <div v-if="askingDelete" class="confirm">
            <span>Excluir {{ current.name }} de vez?</span>
            <button type="button" class="btn btn-sm danger" :disabled="deleting" @click="remove">Sim, excluir</button>
            <button type="button" class="btn btn-ghost btn-sm" @click="askingDelete = false">Voltar</button>
          </div>
          <button v-else type="button" class="btn btn-ghost btn-sm" @click="askingDelete = true">Excluir acomodação</button>
        </template>
      </section>
    </template>
    <p v-else class="card hint later">Fotos e temporadas ficam disponíveis assim que a acomodação for criada.</p>
  </div>
</template>

<style scoped lang="scss">
.editor { display: grid; gap: 1rem; }
.head { display: flex; flex-wrap: wrap; align-items: center; gap: .3rem 1rem; }
.head h2 { @include display(clamp(2rem, 4vw, 2.6rem)); margin: 0; flex: 1 1 auto; }
.back, .view { background: none; border: 0; padding: .5rem 0; font: inherit; font-weight: 700; color: var(--brand-text); cursor: pointer; text-decoration: none; }
.back { flex-basis: 100%; text-align: left; }
.sections { display: grid; gap: 1rem; }
.card { background: var(--a-card); border-radius: var(--a-radius); box-shadow: var(--a-shadow); padding: 1.2rem; display: grid; gap: .9rem; min-width: 0; margin: 0; }
h3 { margin: 0; font-size: 1.25rem; }
label, .field { display: grid; gap: .3rem; font-weight: 700; font-size: .88rem; min-width: 0; }
.label { font-weight: 700; font-size: .88rem; }
.opt { font-weight: 400; color: var(--muted); }
.err { color: var(--danger); font-weight: 700; }
.hint { margin: 0; color: var(--muted); font-size: .88rem; font-weight: 400; }
.options { display: grid; gap: .5rem; }
.options :deep(.option) { box-shadow: inset 0 0 0 1px var(--a-sunk); }
.narrow { max-width: 260px; }
.sub { margin: .2rem 0 -.3rem; color: var(--muted); font-size: .88rem; font-weight: 600; }
.row { display: grid; gap: .9rem; grid-template-columns: repeat(auto-fit, minmax(min(100%, 220px), 1fr)); }
.pct { position: relative; display: block; }
.pct span { position: absolute; right: .85rem; top: 50%; transform: translateY(-50%); color: var(--muted); }
.tags { list-style: none; margin: 0; padding: 0; display: flex; flex-wrap: wrap; gap: .4rem; }
.tags li { display: inline-flex; align-items: center; gap: .2rem; background: var(--soft); border-radius: 999px; padding: .15rem .2rem .15rem .75rem; font-weight: 500; }
.tags button { width: 30px; height: 30px; border: 0; background: none; border-radius: 50%; font-size: 1.1rem; cursor: pointer; color: var(--muted); }
.add { display: flex; gap: .5rem; }
.suggest { display: flex; flex-wrap: wrap; align-items: center; gap: .35rem; font-weight: 400; color: var(--muted); font-size: .84rem; }
.suggest button { border: 1px dashed var(--line); background: none; border-radius: 999px; padding: .3rem .7rem; font: inherit; font-size: .82rem; color: var(--text); cursor: pointer; min-height: 32px; }
.switch { display: inline-flex; align-items: center; gap: .6rem; min-height: 44px; background: none; border: 0; padding: 0; font: inherit; font-weight: 500; cursor: pointer; text-align: left; }
.track { flex: none; position: relative; width: 40px; height: 24px; border-radius: 999px; background: var(--line); }
.thumb { position: absolute; top: 3px; left: 3px; width: 18px; height: 18px; border-radius: 50%; background: #fff; box-shadow: 0 1px 3px rgb(0 0 0 / .25); transition: transform $dur-sm $ease-move; }
.switch[aria-checked="true"] .track { background: var(--brand); }
.switch[aria-checked="true"] .thumb { transform: translateX(16px); }
.sim { background: var(--a-sunk); border-radius: 14px; padding: 1rem; display: grid; gap: .7rem; }
.sim-title { margin: 0; font-weight: 700; font-size: .88rem; }
.sim-grid { display: grid; gap: .7rem; grid-template-columns: repeat(auto-fit, minmax(min(100%, 160px), 1fr)); }
.sim-grid div { display: grid; gap: .1rem; background: var(--card); border-radius: 10px; padding: .7rem .8rem; }
.sim-label { @include eyebrow(var(--muted), sm); }
.sim-grid strong { font-family: var(--font-display); font-size: 1.5rem; font-weight: 400; font-variant-numeric: tabular-nums; }
.sim-grid small { color: var(--muted); }
.savebar {
  position: sticky; bottom: 0; z-index: 3;
  // No celular fica acima da navegação de baixo do painel.
  @include below(lg) { bottom: calc(4.9rem + env(safe-area-inset-bottom, 0px)); } display: flex; flex-wrap: wrap; align-items: center; gap: .5rem;
  background: var(--a-card); border-radius: var(--a-radius); box-shadow: var(--a-shadow); padding: .7rem .8rem calc(.7rem + env(safe-area-inset-bottom, 0px));
  box-shadow: 0 -8px 24px rgb(0 0 0 / .06);
}
.status { margin: 0 auto 0 0; font-size: .86rem; color: var(--muted); }
.status.dirty { color: var(--warn-ink); font-weight: 700; }
.status.error { color: var(--danger); font-weight: 700; }
.danger-zone { border-color: color-mix(in srgb, var(--danger) 30%, var(--line)); }
.danger-zone > .btn { justify-self: start; }
.confirm { display: flex; flex-wrap: wrap; align-items: center; gap: .5rem; font-weight: 700; }
.danger { background: var(--danger); color: var(--danger-ink); }
.later { text-align: center; }
// Por diária / por hora: dois cartões, o escolhido com fundo da marca (cor troca na hora).
.modes { display: grid; gap: .6rem; grid-template-columns: repeat(auto-fit, minmax(min(100%, 220px), 1fr)); }
.mode {
  position: relative; display: grid; gap: .2rem; padding: .9rem 1rem; border-radius: 14px; cursor: pointer;
  box-shadow: inset 0 0 0 1.5px var(--line); font-weight: 400;
  strong { font-size: 1rem; }
  small { color: var(--muted); font-size: .86rem; }
  &.on { box-shadow: inset 0 0 0 2px var(--brand); background: color-mix(in srgb, var(--brand) 8%, white); }
  &:has(.native:focus-visible) { outline: 3px solid var(--focus); outline-offset: 2px; }
}
.native { position: absolute; opacity: 0; width: 1px; height: 1px; }
</style>
