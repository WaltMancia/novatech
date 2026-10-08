/**
 * Cliente HTTP de la API. Responsabilidades:
 *  - Mismo origen + cookies de sesión (credentials: 'same-origin').
 *  - Token CSRF obtenido de /api/csrf y enviado en X-CSRF-Token en métodos que cambian estado.
 *  - Reintento único si el token expiró (sesión rotada).
 *  - Errores tipados (ApiError) con el formato uniforme { error: { code, message, fields? } }.
 */
export class ApiError extends Error {
  constructor(
    public readonly status: number,
    public readonly code: string,
    message: string,
    public readonly fields?: Record<string, string[]>,
  ) {
    super(message)
    this.name = 'ApiError'
  }
}

type Method = 'GET' | 'POST' | 'PUT' | 'PATCH' | 'DELETE'

let csrfToken: string | null = null

async function loadCsrf(force = false): Promise<string> {
  if (force || csrfToken === null) {
    const res = await fetch('/api/csrf', { credentials: 'same-origin' })
    if (!res.ok) throw new ApiError(res.status, 'csrf_unavailable', 'No se pudo obtener el token CSRF.')
    csrfToken = ((await res.json()) as { csrfToken: string }).csrfToken
  }
  return csrfToken
}

/** Debe llamarse tras login/logout: el servidor rota el token de sesión. */
export function resetCsrf(): void {
  csrfToken = null
}

async function request<T>(method: Method, path: string, body?: unknown, retry = true): Promise<T> {
  const headers: Record<string, string> = { Accept: 'application/json' }
  if (body !== undefined) headers['Content-Type'] = 'application/json'
  if (method !== 'GET') headers['X-CSRF-Token'] = await loadCsrf()

  const res = await fetch(`/api${path}`, {
    method,
    headers,
    credentials: 'same-origin',
    body: body === undefined ? undefined : JSON.stringify(body),
  })
  const data = res.status === 204 ? null : await res.json().catch(() => null)

  if (!res.ok) {
    const err = data?.error
    if (retry && res.status === 403 && err?.code === 'csrf_mismatch') {
      await loadCsrf(true)
      return request<T>(method, path, body, false)
    }
    throw new ApiError(res.status, err?.code ?? 'unknown', err?.message ?? 'Error inesperado.', err?.fields)
  }
  return data as T
}

export const api = {
  get: <T>(path: string) => request<T>('GET', path),
  post: <T>(path: string, body?: unknown) => request<T>('POST', path, body ?? {}),
  put: <T>(path: string, body?: unknown) => request<T>('PUT', path, body ?? {}),
  patch: <T>(path: string, body?: unknown) => request<T>('PATCH', path, body ?? {}),
  delete: <T>(path: string) => request<T>('DELETE', path),
}
