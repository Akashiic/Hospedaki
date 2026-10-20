import { AiBots } from '@nuxtjs/robots/util'

// Robôs que coletam páginas para treinar modelos de IA (a lista do @nuxtjs/robots para
// blockAiBots: 'training'). Os de busca com IA, como o do ChatGPT, continuam liberados.
const AI_TRAINING_BOTS = ['GPTBot', 'ClaudeBot', 'Google-Extended', 'meta-externalagent', 'Amazonbot', 'MistralAI-Training', 'Applebot-Extended', 'CCBot']

// "Robôs de IA" escolhido pelo dono em SEO: o robots.txt ganha um grupo bloqueando esses robôs.
export default defineNitroPlugin((nitroApp) => {
  nitroApp.hooks.hook('robots:config', async (ctx) => {
    if (ctx.context !== 'robots.txt' || !ctx.event) return
    const mode = (await siteProperty())?.seo?.ai_bots
    if (mode !== 'training' && mode !== 'all') return

    ctx.groups.push({
      userAgent: mode === 'all' ? AiBots : AI_TRAINING_BOTS,
      comment: [mode === 'all' ? 'Robôs de IA bloqueados (painel → SEO)' : 'Robôs que treinam IA bloqueados (painel → SEO)'],
      disallow: ['/'],
      allow: [],
    })
  })
})
