import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { api, ApiError, resetCsrf } from '../lib/api'

const json = (status: number, body: unknown) =>
  new Response(JSON.stringify(body), { status, headers: { 'Content-Type': 'application/json' } })

const fetchMock = vi.fn<typeof fetch>()
const calls = () => fetchMock.mock.calls.map(([url, init]) => ({ url: String(url), method: init?.method ?? 'GET', headers: (init?.headers ?? {}) as Record<string, string>, body: init?.body }))

beforeEach(() => {
  resetCsrf()
  fetchMock.mockReset()
  vi.stubGlobal('fetch', fetchMock)
})
afterEach(() => vi.unstubAllGlobals())

describe('cliente de API', () => {
  it('GET no pide ni envía token CSRF', async () => {
    fetchMock.mockResolvedValueOnce(json(200, { products: [] }))
    await api.get('/products')

    expect(calls()).toHaveLength(1)
    expect(calls()[0].url).toBe('/api/products')
    expect(calls()[0].headers['X-CSRF-Token']).toBeUndefined()
  })

  it('obtiene el token una sola vez y lo envía en cada petición que cambia estado', async () => {
    fetchMock
      .mockResolvedValueOnce(json(200, { csrfToken: 'tok-1' }))
      .mockResolvedValue(json(200, { ok: true }))
    await api.post('/cart/items', { productId: 1, quantity: 1 })
    await api.delete('/cart')

    const [csrf, post, del] = calls()
    expect(csrf.url).toBe('/api/csrf')
    expect(post.headers['X-CSRF-Token']).toBe('tok-1')
    expect(del.headers['X-CSRF-Token']).toBe('tok-1')
    expect(calls().filter((c) => c.url === '/api/csrf')).toHaveLength(1)
  })

  it('serializa el cuerpo como JSON', async () => {
    fetchMock.mockResolvedValueOnce(json(200, { csrfToken: 't' })).mockResolvedValueOnce(json(200, {}))
    await api.post('/orders', { paymentMethod: 'simulated_success' })

    const post = calls()[1]
    expect(post.headers['Content-Type']).toBe('application/json')
    expect(post.body).toBe('{"paymentMethod":"simulated_success"}')
  })

  it('reintenta una vez con un token nuevo si el servidor responde csrf_mismatch', async () => {
    fetchMock
      .mockResolvedValueOnce(json(200, { csrfToken: 'viejo' }))
      .mockResolvedValueOnce(json(403, { error: { code: 'csrf_mismatch', message: 'Token CSRF inválido o ausente.' } }))
      .mockResolvedValueOnce(json(200, { csrfToken: 'nuevo' }))
      .mockResolvedValueOnce(json(201, { done: true }))

    await expect(api.post('/orders', {})).resolves.toEqual({ done: true })
    expect(calls().at(-1)?.headers['X-CSRF-Token']).toBe('nuevo')
  })

  it('no reintenta indefinidamente: el segundo csrf_mismatch se propaga', async () => {
    const mismatch = () => json(403, { error: { code: 'csrf_mismatch', message: 'Token CSRF inválido o ausente.' } })
    fetchMock
      .mockResolvedValueOnce(json(200, { csrfToken: 'a' }))
      .mockResolvedValueOnce(mismatch())
      .mockResolvedValueOnce(json(200, { csrfToken: 'b' }))
      .mockResolvedValueOnce(mismatch())

    await expect(api.post('/orders', {})).rejects.toMatchObject({ status: 403, code: 'csrf_mismatch' })
    expect(fetchMock).toHaveBeenCalledTimes(4)
  })

  it('convierte errores del servidor en ApiError con código, mensaje y campos', async () => {
    fetchMock.mockResolvedValueOnce(json(200, { csrfToken: 't' })).mockResolvedValueOnce(
      json(422, { error: { code: 'validation_failed', message: 'Los datos enviados no son válidos.', fields: { email: ['Correo inválido.'] } } }),
    )

    const error = await api.post('/auth/register', {}).catch((e: unknown) => e)
    expect(error).toBeInstanceOf(ApiError)
    expect(error).toMatchObject({ status: 422, code: 'validation_failed', fields: { email: ['Correo inválido.'] } })
  })

  it('un 403 distinto de csrf_mismatch no se reintenta', async () => {
    fetchMock.mockResolvedValueOnce(json(403, { error: { code: 'forbidden', message: 'No tienes permiso para esta acción.' } }))
    await expect(api.get('/admin/users')).rejects.toMatchObject({ status: 403, code: 'forbidden' })
    expect(fetchMock).toHaveBeenCalledTimes(1)
  })

  it('maneja respuestas 204 sin cuerpo', async () => {
    fetchMock.mockResolvedValueOnce(json(200, { csrfToken: 't' })).mockResolvedValueOnce(new Response(null, { status: 204 }))
    await expect(api.post('/auth/logout')).resolves.toBeNull()
  })

  it('tolera respuestas de error que no son JSON', async () => {
    fetchMock.mockResolvedValueOnce(new Response('<html>Bad gateway</html>', { status: 502 }))
    await expect(api.get('/products')).rejects.toMatchObject({ status: 502, code: 'unknown' })
  })

  it('resetCsrf fuerza obtener un token nuevo (tras login/logout)', async () => {
    fetchMock
      .mockResolvedValueOnce(json(200, { csrfToken: 'uno' }))
      .mockResolvedValueOnce(json(200, {}))
      .mockResolvedValueOnce(json(200, { csrfToken: 'dos' }))
      .mockResolvedValueOnce(json(200, {}))
    await api.post('/auth/login', {})
    resetCsrf()
    await api.post('/orders', {})

    expect(calls().filter((c) => c.url === '/api/csrf')).toHaveLength(2)
    expect(calls().at(-1)?.headers['X-CSRF-Token']).toBe('dos')
  })
})
