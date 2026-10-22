// Recorre el flujo de compra contra una instancia en ejecución.
//   node scripts/smoke.mjs [urlBase]      (por defecto http://localhost:8080)
// Requiere haber ejecutado migraciones y semilla. Crea/usa dos cuentas de prueba.

const BASE = process.argv[2] ?? 'http://localhost:8080'
const PASSWORD = 'clave-de-prueba-123'
let failures = 0

class Client {
  cookies = new Map()
  csrf = null

  async call(method, path, body) {
    const headers = { Accept: 'application/json' }
    if (method !== 'GET') {
      this.csrf ??= (await this.call('GET', '/api/csrf')).body.csrfToken
      headers['X-CSRF-Token'] = this.csrf
    }
    if (body !== undefined) headers['Content-Type'] = 'application/json'
    if (this.cookies.size) headers.Cookie = [...this.cookies].map(([k, v]) => `${k}=${v}`).join('; ')

    const res = await fetch(BASE + path, { method, headers, body: body === undefined ? undefined : JSON.stringify(body) })
    for (const c of res.headers.getSetCookie()) {
      const [pair] = c.split(';')
      const i = pair.indexOf('=')
      const [name, value] = [pair.slice(0, i), pair.slice(i + 1)]
      value === '' || /expires=Thu, 01 Jan 1970/i.test(c) ? this.cookies.delete(name) : this.cookies.set(name, value)
    }
    const text = await res.text()
    return { status: res.status, body: text ? JSON.parse(text) : null }
  }

  async authenticate(email) {
    let r = await this.call('POST', '/api/auth/login', { email, password: PASSWORD })
    if (r.status === 401) r = await this.call('POST', '/api/auth/register', { email, name: 'Cuenta de prueba', password: PASSWORD })
    this.csrf = null // el servidor rota el token al autenticar
    if (![200, 201].includes(r.status)) throw new Error(`No se pudo autenticar ${email}: ${r.status}`)
  }
}

function check(label, condition, detail = '') {
  console.log(`${condition ? '  ✓' : '  ✗'} ${label}${condition ? '' : ' ' + detail}`)
  if (!condition) failures++
}

const a = new Client()
const b = new Client()
await a.authenticate('smoke-a@novatech.test')
await b.authenticate('smoke-b@novatech.test')
console.log(`\nFlujo de compra en ${BASE}\n`)

const catalog = await a.call('GET', '/api/products')
check('catálogo con 10 productos', catalog.body.products.length === 10, `(${catalog.body.products.length})`)
const [laptop, , audio] = catalog.body.products
const stockBefore = laptop.stock

check('carrito rechaza precio impuesto por el cliente', (await a.call('POST', '/api/cart/items', { productId: laptop.id, quantity: 1, priceCents: 1 })).status === 422)
check('carrito rechaza cantidad fuera de rango', (await a.call('POST', '/api/cart/items', { productId: laptop.id, quantity: 11 })).status === 422)
await a.call('POST', '/api/cart/items', { productId: laptop.id, quantity: 2 })
const cart = await a.call('POST', '/api/cart/items', { productId: audio.id, quantity: 1 })
check('total del carrito calculado por el servidor', cart.body.totalCents === laptop.priceCents * 2 + audio.priceCents)

const declined = await a.call('POST', '/api/orders', { paymentMethod: 'simulated_decline' })
check('pago rechazado devuelve 402', declined.status === 402)
const afterDecline = await a.call('GET', `/api/products/${laptop.id}`)
check('stock restaurado tras el rechazo', afterDecline.body.product.stock === stockBefore)
check('carrito conservado tras el rechazo', (await a.call('GET', '/api/cart')).body.items.length === 2)

const paid = await a.call('POST', '/api/orders', { paymentMethod: 'simulated_success' })
check('pago aprobado crea el pedido (201, paid)', paid.status === 201 && paid.body.order.status === 'paid')
const orderId = paid.body.order.id
check('stock descontado', (await a.call('GET', `/api/products/${laptop.id}`)).body.product.stock === stockBefore - 2)
check('carrito vacío tras la compra', (await a.call('GET', '/api/cart')).body.items.length === 0)
check('el dueño ve su pedido', (await a.call('GET', `/api/orders/${orderId}`)).status === 200)

const foreign = await b.call('GET', `/api/orders/${orderId}`)
check('BOLA: otro usuario recibe 404 en pedido ajeno', foreign.status === 404)
check('BOLA: el listado de otro usuario no incluye el pedido', !(await b.call('GET', '/api/orders')).body.orders.some((o) => o.id === orderId))
check('sin sesión no se accede a pedidos', (await new Client().call('GET', '/api/orders')).status === 401)

console.log(failures === 0 ? '\nTodo correcto.\n' : `\n${failures} verificación(es) fallaron.\n`)
process.exit(failures === 0 ? 0 : 1)
