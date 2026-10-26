// Recorre el flujo de compra contra una instancia en ejecución.
//   node scripts/smoke.mjs [urlBase]      (por defecto http://localhost:8080)
// Requiere haber ejecutado migraciones y semilla. Crea/usa dos cuentas de prueba.

import { BASE, Client, Report } from './lib/client.mjs'

const report = new Report()
const check = (label, condition, detail) => report.check(label, condition, detail)

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

report.finish()
