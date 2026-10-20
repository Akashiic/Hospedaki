<script setup lang="ts">
import type { Component } from 'vue'
import type { AdminTab } from '~/composables/useAdminApi'
import { BedDouble, CalendarCheck, CalendarX2, ExternalLink, House, Inbox, LogOut, Menu, X } from 'lucide-vue-next'

// Moldura do painel: barra lateral com ícones no computador; no celular, barra no alto com o
// nome da pousada e navegação embaixo (as quatro abas do dia a dia e "Mais" com o resto).
const props = defineProps<{ tab: AdminTab, propertyName: string | null, userName: string | null, pending: number, demo?: boolean, iconUrl?: string | null }>()
const emit = defineEmits<{ go: [tab: AdminTab], logout: [] }>()

const items: { tab: AdminTab, label: string, icon: Component, mobile?: boolean }[] = [
  { tab: 'inicio', label: 'Início', icon: House, mobile: true },
  { tab: 'pedidos', label: 'Pedidos', icon: Inbox, mobile: true },
  { tab: 'reservas', label: 'Reservas', icon: CalendarCheck, mobile: true },
  { tab: 'acomodacoes', label: 'Acomodações', icon: BedDouble, mobile: true },
  { tab: 'bloqueios', label: 'Datas bloqueadas', icon: CalendarX2 },
]

// Barra fina no topo enquanto alguma requisição do painel está em andamento. Só aparece
// depois de 150 ms, para não piscar nas respostas rápidas.
const inflight = useAdminBusy()
const busy = ref(false)
let busyTimer: ReturnType<typeof setTimeout> | undefined
watch(inflight, (n) => {
  clearTimeout(busyTimer)
  if (n > 0) busyTimer = setTimeout(() => { busy.value = true }, 150)
  else busy.value = false
})
onBeforeUnmount(() => clearTimeout(busyTimer))

const more = ref(false)
const inMore = computed(() => !items.find(i => i.tab === props.tab)?.mobile)
function go(tab: AdminTab) {
  more.value = false
  emit('go', tab)
}
const initials = computed(() => initialsOf(props.userName ?? 'P'))
</script>

<template>
  <div class="shell">
    <Transition name="progress">
      <div v-if="busy" class="progress" role="progressbar" aria-label="Carregando"><span /></div>
    </Transition>
    <aside class="side" aria-label="Menu do painel">
      <div class="brand">
        <span class="mark" :class="{ img: iconUrl }" aria-hidden="true"><img v-if="iconUrl" :src="iconUrl" alt=""><template v-else>{{ (propertyName ?? 'P').slice(0, 1) }}</template></span>
        <div>
          <p class="eyebrow">Painel</p>
          <strong>{{ propertyName ?? 'Sua pousada' }}</strong>
        </div>
      </div>
      <nav class="nav">
        <button v-for="item in items" :key="item.tab" type="button" :aria-current="tab === item.tab ? 'page' : undefined" @click="go(item.tab)">
          <component :is="item.icon" :size="20" aria-hidden="true" />
          <span>{{ item.label }}</span>
          <span v-if="item.tab === 'pedidos' && pending" class="badge">{{ pending }}</span>
        </button>
        <!-- "Seu site" é um dropdown, com Configurações como outro dropdown dentro dele. -->
        <AdminSiteMenu class="site-menu" :tab="tab" variant="side" @go="go" />
      </nav>
      <div class="foot">
        <a href="/" target="_blank" rel="noopener" class="ghost"><ExternalLink :size="18" aria-hidden="true" /> Ver o site</a>
        <div class="me">
          <span class="avatar" aria-hidden="true">{{ initials }}</span>
          <span class="who">{{ userName ?? '' }}</span>
          <button type="button" class="icon" aria-label="Sair do painel" title="Sair" @click="emit('logout')"><LogOut :size="18" /></button>
        </div>
      </div>
    </aside>

    <header class="top">
      <span class="mark" :class="{ img: iconUrl }" aria-hidden="true"><img v-if="iconUrl" :src="iconUrl" alt=""><template v-else>{{ (propertyName ?? 'P').slice(0, 1) }}</template></span>
      <strong>{{ propertyName ?? 'Painel' }}</strong>
      <a href="/" target="_blank" rel="noopener" class="icon" aria-label="Ver o site"><ExternalLink :size="20" /></a>
    </header>

    <main class="main">
      <slot />
    </main>

    <nav class="bottom" aria-label="Menu do painel">
      <button v-for="item in items.filter(i => i.mobile)" :key="item.tab" type="button" :aria-current="tab === item.tab ? 'page' : undefined" @click="go(item.tab)">
        <span class="ico">
          <component :is="item.icon" :size="22" aria-hidden="true" />
          <span v-if="item.tab === 'pedidos' && pending" class="badge">{{ pending }}</span>
        </span>
        <span>{{ item.label }}</span>
      </button>
      <button type="button" :aria-current="inMore ? 'page' : undefined" :aria-expanded="more" @click="more = !more">
        <span class="ico"><Menu :size="22" aria-hidden="true" /></span>
        <span>Mais</span>
      </button>
    </nav>

    <Transition name="sheet">
      <div v-if="more" class="sheet-wrap" @click.self="more = false">
        <div class="sheet" role="dialog" aria-label="Mais opções">
          <button type="button" class="close icon" aria-label="Fechar" @click="more = false"><X :size="20" /></button>
          <button v-for="item in items.filter(i => !i.mobile)" :key="item.tab" type="button" class="row" :aria-current="tab === item.tab ? 'page' : undefined" @click="go(item.tab)">
            <component :is="item.icon" :size="20" aria-hidden="true" /> {{ item.label }}
          </button>
          <AdminSiteMenu :tab="tab" variant="sheet" @go="go" />
          <a href="/" target="_blank" rel="noopener" class="row"><ExternalLink :size="20" aria-hidden="true" /> Ver o site</a>
          <button type="button" class="row danger" @click="emit('logout')"><LogOut :size="20" aria-hidden="true" /> Sair</button>
        </div>
      </div>
    </Transition>
  </div>
</template>

<style scoped lang="scss">
.shell { --side: 248px; min-height: 100vh; }
// Barra de progresso indeterminada: um trecho de cor que corre da esquerda para a direita.
.progress { position: fixed; z-index: 90; top: 0; left: 0; right: 0; height: 3px; overflow: hidden; background: color-mix(in srgb, var(--brand) 18%, transparent); }
.progress span { position: absolute; inset: 0; width: 40%; background: var(--brand); border-radius: 0 3px 3px 0; animation: run 1.1s $ease-move infinite; }
@keyframes run { from { transform: translateX(-100%); } to { transform: translateX(250%); } }
.progress-enter-active { transition: opacity $dur-sm $ease-enter; }
.progress-leave-active { transition: opacity $dur-md $ease-exit; }
.progress-enter-from, .progress-leave-to { opacity: 0; }
@include reduced-motion { .progress span { animation-duration: 2.2s; } }
.mark {
  display: grid; place-items: center; flex: none; width: 40px; height: 40px; border-radius: 12px;
  background: var(--brand); color: var(--brand-ink); font-family: var(--font-display); font-size: 1.4rem;
}
.eyebrow { @include eyebrow(var(--muted), sm); }
.badge { min-width: 1.35rem; height: 1.35rem; padding: 0 .4rem; border-radius: 999px; background: var(--amber); color: var(--night); font-size: .72rem; font-weight: 800; display: inline-grid; place-items: center; }
.icon { display: grid; place-items: center; width: 40px; height: 40px; border: 0; border-radius: 10px; background: none; color: var(--muted); cursor: pointer; }

// ── Computador: barra lateral fixa ──
.side {
  position: fixed; inset: 0 auto 0 0; z-index: 20; width: var(--side);
  display: flex; flex-direction: column; gap: 1.6rem; padding: 1.4rem 1rem 1rem;
  background: var(--a-card); box-shadow: 1px 0 0 rgb(20 32 26 / .06);
  @include below(lg) { display: none; }
}
.brand { display: flex; align-items: center; gap: .75rem; padding: 0 .4rem; }
.brand strong { display: block; font-family: var(--font-display); font-weight: 400; font-size: 1.25rem; line-height: 1.1; }
.nav { display: grid; gap: .2rem; }
.nav button {
  position: relative; display: flex; align-items: center; gap: .75rem; min-height: 44px; padding: 0 .8rem;
  border: 0; border-radius: 12px; background: none; font: inherit; font-weight: 600; color: var(--muted); cursor: pointer; text-align: left;
  // Fundo da aba ativa entra por opacidade (o fundo em si não anima).
  &::before { content: ""; position: absolute; inset: 0; border-radius: inherit; background: var(--a-sunk); opacity: 0; transition: opacity $dur-sm $ease-move; z-index: -1; }
  & { isolation: isolate; }
  &:hover::before { opacity: .7; }
  &[aria-current="page"] { color: var(--text); }
  &[aria-current="page"]::before { opacity: 1; }
  &[aria-current="page"] svg { color: var(--brand-text); }
  .badge { margin-left: auto; }
}
.site-menu { margin-top: .2rem; }
// A barra lateral rola quando o menu aberto não cabe na altura da tela.
.side { overflow-y: auto; }
.mark.img { background: var(--a-card); box-shadow: inset 0 0 0 1px var(--line); overflow: hidden; }
.mark img { width: 100%; height: 100%; object-fit: cover; }
.row[aria-current="page"] { background: var(--a-sunk); }
.foot { margin-top: auto; display: grid; gap: .6rem; }
.ghost { display: flex; align-items: center; gap: .6rem; min-height: 40px; padding: 0 .8rem; border-radius: 10px; color: var(--muted); font-weight: 600; text-decoration: none; font-size: .9rem; }
.me { display: flex; align-items: center; gap: .6rem; padding: .6rem .5rem .2rem; border-top: 1px solid var(--line); }
.avatar { display: grid; place-items: center; width: 34px; height: 34px; border-radius: 50%; background: var(--a-sunk); color: var(--brand-text); font-size: .8rem; font-weight: 800; flex: none; }
.who { flex: 1; min-width: 0; font-size: .86rem; font-weight: 600; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }

.main {
  padding: clamp(1.2rem, 3vw, 2.4rem) clamp(1rem, 3vw, 2.6rem) 3rem calc(var(--side) + clamp(1rem, 3vw, 2.6rem));
  max-width: calc(var(--side) + 1240px);
  @include below(lg) { padding: 1rem var(--gutter-r) calc(6.5rem + env(safe-area-inset-bottom, 0px)) var(--gutter); }
}

// ── Celular: barra no alto e navegação embaixo ──
.top {
  display: none; position: sticky; top: 0; z-index: 20; align-items: center; gap: .7rem;
  padding: calc(.6rem + env(safe-area-inset-top, 0px)) var(--gutter-r) .6rem var(--gutter);
  background: color-mix(in srgb, var(--a-bg) 85%, transparent); backdrop-filter: blur(12px); -webkit-backdrop-filter: blur(12px);
  @include below(lg) { display: flex; }
  .mark { width: 34px; height: 34px; font-size: 1.15rem; border-radius: 10px; }
  strong { flex: 1; font-family: var(--font-display); font-weight: 400; font-size: 1.25rem; }
}
.bottom {
  display: none; position: fixed; z-index: 30; left: 0; right: 0; bottom: 0;
  padding: .35rem var(--gutter-r) calc(.35rem + env(safe-area-inset-bottom, 0px)) var(--gutter);
  background: color-mix(in srgb, var(--a-card) 92%, transparent); backdrop-filter: blur(14px); -webkit-backdrop-filter: blur(14px);
  box-shadow: 0 -1px 0 rgb(20 32 26 / .06), 0 -10px 30px -20px rgb(20 32 26 / .3);
  @include below(lg) { display: grid; grid-template-columns: repeat(5, minmax(0, 1fr)); }
  button {
    display: grid; justify-items: center; gap: .15rem; min-height: 56px; padding: .3rem 0; border: 0; background: none;
    font: inherit; font-size: .68rem; font-weight: 700; color: var(--muted); cursor: pointer;
  }
  .ico { position: relative; display: grid; place-items: center; width: 52px; height: 30px; border-radius: 999px; isolation: isolate; }
  // Pílula atrás do ícone ativo: entra crescendo (transform) e por opacidade.
  .ico::before { content: ""; position: absolute; inset: 0; border-radius: inherit; background: color-mix(in srgb, var(--brand) 16%, transparent); opacity: 0; transform: scaleX(.6); transition: opacity $dur-sm $ease-move, transform $dur-md $ease-enter; z-index: -1; }
  [aria-current="page"] { color: var(--text); }
  [aria-current="page"] .ico::before { opacity: 1; transform: none; }
  [aria-current="page"] svg { color: var(--brand-text); }
  .badge { position: absolute; top: -4px; right: 4px; }
}

.sheet-wrap { position: fixed; inset: 0; z-index: 60; background: rgb(10 14 12 / .35); display: grid; align-items: end; }
.sheet {
  position: relative; display: grid; gap: .2rem; padding: 1rem var(--gutter-r) calc(1rem + env(safe-area-inset-bottom, 0px)) var(--gutter);
  background: var(--a-card); border-radius: 22px 22px 0 0; box-shadow: 0 -20px 40px -20px rgb(0 0 0 / .3);
  max-height: 85vh; overflow-y: auto;
}
.close { position: absolute; top: .6rem; right: .6rem; }
.row { display: flex; align-items: center; gap: .8rem; min-height: 52px; padding: 0 .4rem; border: 0; background: none; font: inherit; font-weight: 600; color: var(--text); text-decoration: none; cursor: pointer; border-radius: 12px; }
.row svg { color: var(--brand-text); }
.row.danger, .row.danger svg { color: var(--danger); }
.sheet-enter-active { transition: opacity $dur-md $ease-enter; .sheet { transition: transform $dur-lg $ease-enter; } }
.sheet-leave-active { transition: opacity $dur-sm $ease-exit; .sheet { transition: transform $dur-sm $ease-exit; } }
.sheet-enter-from, .sheet-leave-to { opacity: 0; .sheet { transform: translateY(100%); } }
@include reduced-motion { .sheet-enter-from .sheet, .sheet-leave-to .sheet { transform: none; } }
</style>
