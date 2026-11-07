// Recorre el panel de administración contra una instancia en ejecución.
//   $env:ADMIN_EMAIL="admin@novatech.local"; $env:ADMIN_PASSWORD="..."; node scripts/smoke-admin.mjs [urlBase]
// Requiere un administrador (bin/create-admin.php) y haber ejecutado antes scripts/smoke.mjs.

import { BASE, Client, Report } from './lib/client.mjs'

const { ADMIN_EMAIL, ADMIN_PASSWORD } = process.env
if (!ADMIN_EMAIL || !ADMIN_PASSWORD) {
  console.error('Define ADMIN_EMAIL y ADMIN_PASSWORD en el entorno.')
  process.exit(2)
}

const report = new Report()
const check = (label, condition, detail) => report.check(label, condition, detail)

const customer = new Client()
const admin = new Client()
await customer.authenticate('smoke-a@novatech.test')
const login = await admin.login(ADMIN_EMAIL, ADMIN_PASSWORD)
if (login.status !== 200 || login.body.user.role !== 'admin') throw new Error('Las credenciales no corresponden a un administrador.')
console.log(`\nPanel de administración en ${BASE}\n`)

const draft = { sku: `NV-SMK-${Date.now().toString(36).toUpperCase()}`, name: 'Producto de humo', description: 'Creado por el script', priceCents: 12345, stock: 5 }

check('cliente: GET /admin/products -> 403', (await customer.call('GET', '/api/admin/products')).status === 403)
check('cliente: POST /admin/products -> 403', (await customer.call('POST', '/api/admin/products', draft)).status === 403)
check('anónimo: /admin/users -> 401', (await new Client().call('GET', '/api/admin/users')).status === 401)

const created = await admin.call('POST', '/api/admin/products', draft)
check('admin crea producto (201)', created.status === 201)
const id = created.body?.product?.id
check('SKU duplicado -> 422', (await admin.call('POST', '/api/admin/products', draft)).status === 422)
check('imagePath con esquema javascript: rechazado (422 de la API o 403 del WAF)', [403, 422].includes((await admin.call('POST', '/api/admin/products', { ...draft, sku: draft.sku + '-X', imagePath: 'javascript:alert(1)' })).status))
check('campo extra (id) -> 422', (await admin.call('POST', '/api/admin/products', { ...draft, sku: draft.sku + '-Y', id: 1 })).status === 422)

check('el producto aparece en el catálogo público', (await customer.call('GET', '/api/products')).body.products.some((p) => p.id === id))
const patched = await admin.call('PATCH', `/api/admin/products/${id}`, { priceCents: 9999, stock: 8 })
check('PATCH actualiza precio y stock', patched.body?.product?.priceCents === 9999 && patched.body?.product?.stock === 8)
check('PATCH vacío -> 422', (await admin.call('PATCH', `/api/admin/products/${id}`, {})).status === 422)
check('DELETE desactiva (204)', (await admin.call('DELETE', `/api/admin/products/${id}`)).status === 204)
check('el producto desactivado sale del catálogo', !(await customer.call('GET', '/api/products')).body.products.some((p) => p.id === id))
check('el admin aún lo ve (inactivo)', (await admin.call('GET', `/api/admin/products/${id}`)).body?.product?.active === false)

const target = (await customer.call('GET', '/api/products')).body.products[2]
const stockBefore = target.stock
await customer.call('DELETE', '/api/cart')
await customer.call('POST', '/api/cart/items', { productId: target.id, quantity: 1 })
const order = await customer.call('POST', '/api/orders', { paymentMethod: 'simulated_success' })
check('cliente compra para la prueba (201)', order.status === 201)
const orderId = order.body.order.id
check('admin ve pedidos de todos', (await admin.call('GET', '/api/admin/orders?status=paid')).body.orders.some((o) => o.id === orderId))
check('cliente: cancelar pedido -> 403', (await customer.call('POST', `/api/admin/orders/${orderId}/cancel`, {})).status === 403)
const cancelled = await admin.call('POST', `/api/admin/orders/${orderId}/cancel`, {})
check('admin cancela el pedido pagado', cancelled.body?.order?.status === 'cancelled')
check('el stock vuelve al inventario', (await customer.call('GET', `/api/products/${target.id}`)).body.product.stock === stockBefore)
check('cancelar de nuevo -> 409', (await admin.call('POST', `/api/admin/orders/${orderId}/cancel`, {})).status === 409)

const users = await admin.call('GET', '/api/admin/users')
check('listado de usuarios sin hashes', users.status === 200 && !JSON.stringify(users.body).includes('argon2'))
const audit = await admin.call('GET', '/api/admin/audit-logs?action=admin.order_cancelled&perPage=5')
check('auditoría registra la cancelación', audit.body?.events?.some((e) => e.metadata?.order_id === orderId))
check('filtro de auditoría con inyección rechazado (422 o 403 del WAF)', [403, 422].includes((await admin.call('GET', `/api/admin/audit-logs?action=${encodeURIComponent("x' OR 1=1 --")}`)).status))

report.finish()
