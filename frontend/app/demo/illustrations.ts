// Cenas ilustradas usadas só na prévia, no lugar das fotos reais da pousada.
// Cada cena é um SVG em camadas (céu, montanhas com perspectiva atmosférica, névoa, primeiro plano).

const W = 1600
const H = 1000

function rng(seed: number) {
  return () => {
    seed = (seed * 16807) % 2147483647
    return (seed - 1) / 2147483646
  }
}

function ridge(seed: number, base: number, amp: number, color: string, opacity = 1) {
  const r = rng(seed)
  let d = `M0 ${H} L0 ${base}`
  const steps = 16
  const offsets = Array.from({ length: 4 }, () => r() * Math.PI * 2)
  for (let i = 0; i <= steps; i++) {
    const x = (W / steps) * i
    const y = base
      - Math.sin(i / 2.3 + offsets[0]!) * amp * 0.6
      - Math.sin(i / 1.1 + offsets[1]!) * amp * 0.25
      - Math.sin(i / 4.7 + offsets[2]!) * amp * 0.5
    d += ` L${x.toFixed(0)} ${y.toFixed(0)}`
  }
  return `<path d="${d} L${W} ${H}Z" fill="${color}" opacity="${opacity}"/>`
}

function pines(seed: number, count: number, baseY: number, minH: number, maxH: number, color: string, xFrom = 0, xTo = W) {
  const r = rng(seed)
  let out = ''
  for (let i = 0; i < count; i++) {
    const x = xFrom + r() * (xTo - xFrom)
    const h = minH + r() * (maxH - minH)
    const w = h * 0.36
    const y = baseY + r() * 30
    out += `<path d="M${x} ${y - h} L${x + w / 2} ${y} L${x - w / 2} ${y}Z" fill="${color}"/>`
  }
  return out
}

function mist(y: number, height: number, color: string, opacity: number) {
  return `<rect x="0" y="${y}" width="${W}" height="${height}" fill="url(#mist-${color.slice(1)})" opacity="${opacity}"/>`
}

function mistDef(color: string) {
  return `<linearGradient id="mist-${color.slice(1)}" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="${color}" stop-opacity="0"/><stop offset=".5" stop-color="${color}" stop-opacity="1"/><stop offset="1" stop-color="${color}" stop-opacity="0"/></linearGradient>`
}

function stars(seed: number, count: number) {
  const r = rng(seed)
  let out = ''
  for (let i = 0; i < count; i++) {
    out += `<circle cx="${(r() * W).toFixed(0)}" cy="${(r() * H * 0.5).toFixed(0)}" r="${(r() * 1.8 + 0.4).toFixed(1)}" fill="#fff" opacity="${(r() * 0.7 + 0.2).toFixed(2)}"/>`
  }
  return out
}

function chalet(x: number, y: number, s: number, wall: string, roof: string, glow: string) {
  return `<g transform="translate(${x} ${y}) scale(${s})">
<path d="M-10 0 L130 -150 L270 0Z" fill="${roof}"/>
<rect x="20" y="-10" width="220" height="150" fill="${wall}"/>
<path d="M60 -10 L130 -85 L200 -10Z" fill="${glow}" opacity=".9"/>
<rect x="55" y="30" width="45" height="55" fill="${glow}"/><rect x="160" y="30" width="45" height="55" fill="${glow}"/>
<rect x="110" y="55" width="40" height="85" fill="${glow}" opacity=".85"/>
<rect x="0" y="135" width="260" height="14" fill="${roof}"/>
</g>`
}

function scene(defs: string, body: string) {
  const markup = `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 ${W} ${H}" preserveAspectRatio="xMidYMid slice"><defs>${defs}</defs>${body}</svg>`
  return `data:image/svg+xml;charset=utf-8,${encodeURIComponent(markup)}`
}

const sky = (id: string, stops: [number, string][]) =>
  `<linearGradient id="${id}" x1="0" y1="0" x2="0" y2="1">${stops.map(([o, c]) => `<stop offset="${o}" stop-color="${c}"/>`).join('')}</linearGradient>`

const sunGlow = (id: string, color: string) =>
  `<radialGradient id="${id}"><stop offset="0" stop-color="${color}" stop-opacity=".95"/><stop offset=".25" stop-color="${color}" stop-opacity=".45"/><stop offset="1" stop-color="${color}" stop-opacity="0"/></radialGradient>`

export const dawnScene = scene(
  sky('sk', [[0, '#2c3e5c'], [0.45, '#c98a6b'], [0.7, '#f1c48f'], [1, '#f6dcb4']]) + sunGlow('sun', '#ffe2a8') + mistDef('#f5dcc0'),
  `<rect width="${W}" height="${H}" fill="url(#sk)"/>
<circle cx="1050" cy="560" r="420" fill="url(#sun)"/><circle cx="1050" cy="560" r="46" fill="#fff1d2"/>
${ridge(3, 560, 90, '#9a7d86', 0.75)}${mist(500, 160, '#f5dcc0', 0.8)}
${ridge(7, 640, 110, '#6b6074')}${mist(600, 140, '#f5dcc0', 0.55)}
${ridge(11, 720, 80, '#3f4a4f')}${pines(5, 40, 760, 60, 130, '#2c3638')}
${ridge(17, 820, 60, '#1f2a28')}${chalet(980, 640, 0.95, '#2a221d', '#16120f', '#f7c26b')}
${pines(9, 18, 930, 140, 260, '#121a18', 0, 700)}${pines(13, 10, 950, 160, 300, '#0d1312', 1300, 1600)}`,
)

export const nightScene = scene(
  sky('sk', [[0, '#05070f'], [0.55, '#14213d'], [1, '#2b3a55']]) + sunGlow('moon', '#cfd8ff') + sunGlow('win', '#ffb85c') + mistDef('#3a4a66'),
  `<rect width="${W}" height="${H}" fill="url(#sk)"/>${stars(21, 220)}
<circle cx="320" cy="200" r="220" fill="url(#moon)" opacity=".6"/><circle cx="320" cy="200" r="38" fill="#eef1ff"/>
${ridge(23, 600, 100, '#1d2840')}${mist(560, 140, '#3a4a66', 0.6)}
${ridge(29, 700, 90, '#141d2e')}${pines(31, 36, 740, 70, 150, '#0e1522')}
<circle cx="820" cy="760" r="260" fill="url(#win)" opacity=".55"/>
${ridge(37, 840, 50, '#0a0f19')}${chalet(690, 660, 1, '#1a1512', '#0c0907', '#ffb85c')}
${pines(41, 16, 950, 160, 300, '#05080e', 0, 560)}${pines(43, 12, 960, 180, 320, '#05080e', 1150, 1600)}
${Array.from({ length: 26 }, (_, i) => { const r = rng(50 + i); return `<circle cx="${(r() * W).toFixed(0)}" cy="${(780 + r() * 200).toFixed(0)}" r="2.6" fill="#ffe28a" opacity="${(0.4 + r() * 0.6).toFixed(2)}"/>` }).join('')}`,
)

export const goldenScene = scene(
  sky('sk', [[0, '#6f8fb0'], [0.5, '#e8b98a'], [1, '#f7dcb0']]) + sunGlow('sun', '#fff0c4') + mistDef('#f3d6a8') + `<linearGradient id="lawn" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#7e8f4e"/><stop offset="1" stop-color="#3f5630"/></linearGradient>`,
  `<rect width="${W}" height="${H}" fill="url(#sk)"/>
<circle cx="320" cy="470" r="520" fill="url(#sun)"/><circle cx="320" cy="470" r="52" fill="#fff6dc"/>
${ridge(61, 560, 70, '#a3967e', 0.8)}${mist(510, 140, '#f3d6a8', 0.7)}
${ridge(67, 640, 60, '#6d7457')}
<path d="M0 700 Q500 640 900 690 T1600 680 V1000 H0Z" fill="url(#lawn)"/>
<g transform="translate(760 520)"><path d="M-40 70 L300 -10 L640 70Z" fill="#5a3f2c"/><rect x="0" y="60" width="600" height="190" fill="#e9dcc5"/>
<rect x="50" y="110" width="150" height="90" fill="#f6c87a"/><rect x="230" y="110" width="150" height="90" fill="#f6c87a"/><rect x="420" y="100" width="130" height="150" fill="#6b4c36"/>
<rect x="-40" y="245" width="680" height="18" fill="#a68c6c"/></g>
${pines(71, 6, 760, 200, 320, '#33452a', 0, 600)}<circle cx="1480" cy="700" r="90" fill="#3b5030"/><circle cx="1400" cy="740" r="70" fill="#455c37"/>
<path d="M0 1000 L0 900 Q400 860 800 900 T1600 880 V1000Z" fill="#2f4226"/>`,
)

export const interiorScene = scene(
  `<radialGradient id="fire" cx=".3" cy=".75" r=".6"><stop offset="0" stop-color="#ffb35c" stop-opacity=".9"/><stop offset=".4" stop-color="#b8612e" stop-opacity=".35"/><stop offset="1" stop-color="#2a1a12" stop-opacity="0"/></radialGradient>`
  + sky('wall', [[0, '#3a2a20'], [1, '#22170f']]) + sky('win', [[0, '#2e4560'], [1, '#c48f6a']]),
  `<rect width="${W}" height="${H}" fill="url(#wall)"/>
${Array.from({ length: 12 }, (_, i) => `<rect x="0" y="${i * 60}" width="${W}" height="2" fill="#000" opacity=".18"/>`).join('')}
<rect x="900" y="160" width="520" height="400" fill="url(#win)"/>
<path d="M900 470 Q1000 420 1100 455 T1300 440 T1420 450 V560 H900Z" fill="#3f4f4a"/><path d="M900 510 Q1050 480 1200 505 T1420 495 V560 H900Z" fill="#2c3a35"/><rect x="900" y="160" width="520" height="400" fill="none" stroke="#1a110b" stroke-width="22"/><rect x="1150" y="160" width="20" height="400" fill="#1a110b"/>
<rect x="200" y="420" width="380" height="380" fill="#15100c"/><rect x="250" y="520" width="280" height="240" fill="#2b1810"/>
<path d="M330 760 Q360 640 390 700 Q410 600 440 690 Q470 650 480 760Z" fill="#ffb35c"/><path d="M360 760 Q385 690 400 720 Q420 670 440 760Z" fill="#ffe1a0"/>
<rect x="0" y="780" width="${W}" height="220" fill="#1b120c"/>
<rect x="700" y="660" width="560" height="150" rx="24" fill="#6e5643"/><rect x="700" y="610" width="560" height="80" rx="24" fill="#8a6d55"/>
<rect x="740" y="640" width="140" height="70" rx="16" fill="#c99b6d"/><rect x="1080" y="640" width="140" height="70" rx="16" fill="#9fb4a6"/>
<rect width="${W}" height="${H}" fill="url(#fire)"/>`,
)

export const trailScene = scene(
  sky('sk', [[0, '#cfe0d4'], [1, '#7f9c82']])
  + `<linearGradient id="ray" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="#fff8dc" stop-opacity=".55"/><stop offset="1" stop-color="#fff8dc" stop-opacity="0"/></linearGradient>`,
  `<rect width="${W}" height="${H}" fill="url(#sk)"/>
${pines(91, 30, 640, 260, 480, '#86a28a')}${pines(93, 26, 760, 320, 560, '#56735c')}
<path d="M200 0 L520 0 L1100 1000 L700 1000Z" fill="url(#ray)"/><path d="M700 0 L860 0 L1400 1000 L1200 1000Z" fill="url(#ray)" opacity=".7"/>
<path d="M560 1000 Q760 780 780 640 Q790 600 820 600 Q850 600 860 640 Q900 780 1120 1000Z" fill="#c9b48c"/>
${pines(97, 14, 960, 420, 700, '#2c4232', 0, 560)}${pines(99, 14, 980, 420, 720, '#2c4232', 1180, 1600)}
<path d="M0 1000 L0 930 Q300 900 560 960 L540 1000Z" fill="#35502f"/><path d="M1600 1000 L1600 920 Q1300 900 1120 980 L1140 1000Z" fill="#35502f"/>`,
)

export const porchScene = scene(
  sky('sk', [[0, '#7aa0bf'], [0.6, '#d9c6a6'], [1, '#e9d6b5']]) + mistDef('#e6d3b4'),
  `<rect width="${W}" height="${H}" fill="url(#sk)"/>
${ridge(101, 520, 80, '#8f9aa0', 0.8)}${mist(470, 140, '#e6d3b4', 0.7)}${ridge(103, 600, 90, '#6b7d72')}${ridge(107, 680, 60, '#4c6253')}
<rect x="0" y="0" width="90" height="${H}" fill="#3b2a1e"/><rect x="1510" y="0" width="90" height="${H}" fill="#3b2a1e"/><rect x="0" y="0" width="${W}" height="70" fill="#3b2a1e"/>
<rect x="0" y="760" width="${W}" height="20" fill="#5a4030"/>${Array.from({ length: 22 }, (_, i) => `<rect x="${60 + i * 70}" y="780" width="16" height="140" fill="#5a4030"/>`).join('')}
<rect x="0" y="910" width="${W}" height="90" fill="#4a3426"/>
<path d="M260 380 Q800 760 1340 380" fill="none" stroke="#e8dcc2" stroke-width="6"/><path d="M330 430 Q800 820 1270 430 L1240 470 Q800 860 360 470Z" fill="#b5553c"/><path d="M380 480 Q800 840 1220 480" fill="none" stroke="#e7b452" stroke-width="10" stroke-dasharray="30 18"/>`,
)

export const chaletPhoto = dawnScene
export const spacePhoto = goldenScene
export const interiorPhoto = interiorScene
