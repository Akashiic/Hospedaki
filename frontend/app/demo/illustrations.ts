// Ilustrações usadas só na prévia, no lugar das fotos reais da pousada.
function svg(body: string, sky: [string, string]) {
  const markup = `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 800 600" preserveAspectRatio="xMidYMid slice">
<defs><linearGradient id="s" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="${sky[0]}"/><stop offset="1" stop-color="${sky[1]}"/></linearGradient></defs>
<rect width="800" height="600" fill="url(#s)"/>${body}</svg>`
  return `data:image/svg+xml;charset=utf-8,${encodeURIComponent(markup)}`
}

const hills = `<circle cx="620" cy="140" r="46" fill="#ffe7b0" opacity=".9"/>
<path d="M0 380 Q160 290 330 360 T800 330 V600 H0Z" fill="#7fa58a"/>
<path d="M0 450 Q220 370 430 430 T800 420 V600 H0Z" fill="#4f7d61"/>`

export const chaletPhoto = svg(`${hills}
<g transform="translate(250 250)"><path d="M0 170 L150 40 L300 170Z" fill="#7a4b33"/><rect x="35" y="160" width="230" height="140" fill="#c99a6b"/>
<rect x="125" y="210" width="50" height="90" fill="#5d3826"/><rect x="60" y="195" width="45" height="40" fill="#f7e4b8"/><rect x="195" y="195" width="45" height="40" fill="#f7e4b8"/>
<rect x="20" y="295" width="260" height="14" fill="#5d3826"/></g>
<path d="M0 520 Q300 470 800 520 V600 H0Z" fill="#3d6650"/>`, ['#a9cbe0', '#e9d9c4'])

export const spacePhoto = svg(`${hills}
<g transform="translate(150 280)"><rect x="0" y="40" width="500" height="150" fill="#e8e0d2"/><path d="M-20 50 L250 -10 L520 50Z" fill="#8a6a50"/>
<rect x="40" y="80" width="130" height="80" fill="#9fc2cf"/><rect x="190" y="80" width="130" height="80" fill="#9fc2cf"/><rect x="340" y="80" width="110" height="110" fill="#6e5440"/>
<rect x="-30" y="185" width="560" height="16" fill="#b9a68f"/></g>
<circle cx="90" cy="470" r="38" fill="#3d6650"/><circle cx="720" cy="480" r="44" fill="#3d6650"/>`, ['#f2c9a0', '#f6e6d4'])

export const interiorPhoto = svg(`<rect y="380" width="800" height="220" fill="#b48a65"/>
<rect x="90" y="120" width="260" height="190" fill="#cfe3ea" stroke="#f3ead9" stroke-width="14"/>
<rect x="440" y="300" width="300" height="110" rx="10" fill="#f3ead9"/><rect x="440" y="270" width="300" height="50" rx="10" fill="#8aa596"/>
<rect x="470" y="330" width="90" height="40" rx="8" fill="#e8c9a0"/><rect x="620" y="330" width="90" height="40" rx="8" fill="#e8c9a0"/>`, ['#efe4d3', '#e5d6c0'])
