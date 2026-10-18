// Gera a prévia visual (dados de exemplo, sem backend) em .output/public,
// com caminhos relativos para funcionar dentro de qualquer pasta.
import { execSync } from 'node:child_process'
import { readdirSync, readFileSync, writeFileSync } from 'node:fs'

execSync('npx nuxt generate', { stdio: 'inherit', env: { ...process.env, HOSPEDAKI_DEMO: '1' } })

for (const file of ['index.html', '200.html', '404.html']) {
  const path = `.output/public/${file}`
  const html = readFileSync(path, 'utf8')
    .replaceAll('"/_nuxt/', '"./_nuxt/')
    .replaceAll('"/fonts/', '"./fonts/')
    .replace('cdnURL:""', 'cdnURL:new URL(".",location.href).href.replace(/\\/$/,"")')
  writeFileSync(path, html)
}

// As folhas de estilo ficam em _nuxt/, então as fontes ficam uma pasta acima.
for (const file of readdirSync('.output/public/_nuxt').filter(f => f.endsWith('.css'))) {
  const path = `.output/public/_nuxt/${file}`
  writeFileSync(path, readFileSync(path, 'utf8').replaceAll('url(/fonts/', 'url(../fonts/'))
}

console.log('Prévia pronta em .output/public')
