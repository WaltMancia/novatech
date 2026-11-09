// Genera, a partir de docs/risk/risks.json: la matriz de calor (SVG), el registro de riesgos (Markdown) y un CSV.
//   node scripts/risk-heatmap.mjs

import { mkdirSync, readFileSync, writeFileSync } from 'node:fs'
import { dirname, join, resolve } from 'node:path'
import { fileURLToPath } from 'node:url'

const ROOT = resolve(dirname(fileURLToPath(import.meta.url)), '..')
const DIR = join(ROOT, 'docs', 'risk')
const { scale, risks } = JSON.parse(readFileSync(join(DIR, 'risks.json'), 'utf8'))

const LEVELS = [
  { name: 'Bajo', max: 4, color: '#86efac' },
  { name: 'Medio', max: 9, color: '#fde047' },
  { name: 'Alto', max: 14, color: '#fdba74' },
  { name: 'Crítico', max: 25, color: '#fca5a5' },
]
const level = (score) => LEVELS.find((l) => score <= l.max)
const esc = (s) => String(s).replaceAll('&', '&amp;').replaceAll('<', '&lt;').replaceAll('>', '&gt;')

for (const r of risks) {
  for (const k of ['p0', 'i0', 'p1', 'i1']) if (!Number.isInteger(r[k]) || r[k] < 1 || r[k] > 5) throw new Error(`${r.id}: ${k} fuera de 1-5`)
  r.s0 = r.p0 * r.i0
  r.s1 = r.p1 * r.i1
  if (r.s1 > r.s0) throw new Error(`${r.id}: el riesgo residual no puede superar al inicial`)
}

// ---------- SVG ----------
const CELL = 84, GRID = CELL * 5, M = { left: 70, top: 70, gap: 90 }
const W = M.left * 2 + GRID * 2 + M.gap, H = M.top + GRID + 150

function grid(x0, title, pKey, iKey) {
  let out = `<text x="${x0 + GRID / 2}" y="${M.top - 38}" text-anchor="middle" class="t">${esc(title)}</text>`
  for (let p = 5; p >= 1; p--) {
    for (let i = 1; i <= 5; i++) {
      const x = x0 + (i - 1) * CELL, y = M.top + (5 - p) * CELL
      out += `<rect x="${x}" y="${y}" width="${CELL}" height="${CELL}" fill="${level(p * i).color}" stroke="#fff" stroke-width="2"/>`
      const ids = risks.filter((r) => r[pKey] === p && r[iKey] === i).map((r) => r.id)
      for (let k = 0; k < ids.length; k += 2) {
        out += `<text x="${x + CELL / 2}" y="${y + 24 + (k / 2) * 17}" text-anchor="middle" class="id">${ids.slice(k, k + 2).join(' ')}</text>`
      }
    }
    out += `<text x="${x0 - 12}" y="${M.top + (5 - p) * CELL + CELL / 2 + 4}" text-anchor="end" class="ax">${p}</text>`
  }
  for (let i = 1; i <= 5; i++) out += `<text x="${x0 + (i - 1) * CELL + CELL / 2}" y="${M.top + GRID + 20}" text-anchor="middle" class="ax">${i}</text>`
  out += `<text x="${x0 + GRID / 2}" y="${M.top + GRID + 44}" text-anchor="middle" class="lb">Impacto</text>`
  out += `<text transform="translate(${x0 - 44} ${M.top + GRID / 2}) rotate(-90)" text-anchor="middle" class="lb">Probabilidad</text>`
  return out
}

const legend = LEVELS.map((l, k) => `<rect x="${M.left + k * 150}" y="${H - 58}" width="22" height="22" fill="${l.color}"/><text x="${M.left + k * 150 + 30}" y="${H - 42}" class="ax">${l.name}</text>`).join('')
const svg = `<svg xmlns="http://www.w3.org/2000/svg" width="${W}" height="${H}" viewBox="0 0 ${W} ${H}" font-family="Arial, Helvetica, sans-serif">
<style>.t{font-size:20px;font-weight:700;fill:#0f172a}.id{font-size:13px;font-weight:700;fill:#0f172a}.ax{font-size:14px;fill:#334155}.lb{font-size:15px;font-weight:700;fill:#334155}</style>
<rect width="${W}" height="${H}" fill="#fff"/>
${grid(M.left, 'Riesgo inicial (antes de los controles)', 'p0', 'i0')}
${grid(M.left + GRID + M.gap, 'Riesgo residual (después de los controles)', 'p1', 'i1')}
${legend}
<text x="${M.left}" y="${H - 14}" class="ax">Escala: P e I de 1 a 5; puntaje = P x I (Bajo 1-4, Medio 5-9, Alto 10-14, Crítico 15-25). NovaTech, ${new Date().getFullYear()}.</text>
</svg>
`
mkdirSync(DIR, { recursive: true })
writeFileSync(join(DIR, 'heatmap.svg'), svg)

// ---------- Markdown ----------
const count = (key) => LEVELS.map((l) => `${l.name}: ${risks.filter((r) => level(r[key]).name === l.name).length}`).join(' · ')
const bySeverity = [...risks].sort((a, b) => b.s0 - a.s0)
const row = (r) => `| ${r.id} | ${r.name} | ${r.type} | ${r.impact} | ${r.p0}×${r.i0} = **${r.s0}** (${level(r.s0).name}) | ${r.treatment} | ${r.controls} | ${r.p1}×${r.i1} = **${r.s1}** (${level(r.s1).name}) |`
const high = risks.filter((r) => r.s1 >= 10)

const md = `# Registro de riesgos y matriz de calor

> Generado con \`node scripts/risk-heatmap.mjs\` a partir de [risk/risks.json](risk/risks.json). No editar a mano: modificar el JSON y regenerar.

${scale}

## Matriz de calor (antes y después)

![Matriz de calor](risk/heatmap.svg)

| | Distribución |
|---|---|
| **Inicial** | ${count('s0')} |
| **Residual** | ${count('s1')} |

## Registro y tratamiento

Decisión por riesgo: **Aceptar, Eliminar, Evitar, Mitigar o Transferir**. Tipo: Software, Seguridad, Técnico, Cloud, Legal. Impacto: Reputacional, Operacional, Financiero, Legal.

| ID | Riesgo | Tipo | Impacto | Inicial (P×I) | Tratamiento | Controles implementados | Residual (P×I) |
|---|---|---|---|---|---|---|---|
${bySeverity.map(row).join('\n')}

## Riesgos que permanecen en nivel Alto o superior

${high.length ? high.map((r) => `- **${r.id} ${r.name}** (${r.s1}, ${level(r.s1).name}): ${r.controls}`).join('\n') : 'Ninguno.'}

## Revisión

El registro se revisa al cerrar cada fase y cuando un escaneo (SAST, SCA, DAST o Trivy) o el SIEM revele un riesgo nuevo. Cada cambio de puntaje debe citar la evidencia (prueba, informe o commit).
`
writeFileSync(join(ROOT, 'docs', 'RISK_REGISTER.md'), md)

// ---------- CSV ----------
const csv = ['ID,Riesgo,Tipo,Impacto,P inicial,I inicial,Puntaje inicial,Nivel inicial,Tratamiento,Controles,P residual,I residual,Puntaje residual,Nivel residual']
for (const r of bySeverity) {
  const cells = [r.id, r.name, r.type, r.impact, r.p0, r.i0, r.s0, level(r.s0).name, r.treatment, r.controls, r.p1, r.i1, r.s1, level(r.s1).name]
  csv.push(cells.map((c) => `"${String(c).replaceAll('"', '""')}"`).join(','))
}
writeFileSync(join(DIR, 'risk-register.csv'), '\ufeff' + csv.join('\n') + '\n')

console.log(`Riesgos: ${risks.length}. Inicial -> ${count('s0')}. Residual -> ${count('s1')}.`)
console.log('Generados: docs/risk/heatmap.svg, docs/RISK_REGISTER.md, docs/risk/risk-register.csv')
