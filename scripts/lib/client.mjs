// Cliente HTTP mínimo para los scripts de verificación: mantiene cookies y token CSRF por sesión.

export const BASE = process.argv[2] ?? process.env.BASE_URL ?? 'http://localhost:8080'
export const PASSWORD = 'clave-de-prueba-123'

export class Client {
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
    let parsed = null
    try {
      parsed = text ? JSON.parse(text) : null
    } catch {
      parsed = { raw: text.slice(0, 200) } // p. ej. la página de bloqueo (HTML) del WAF
    }
    return { status: res.status, body: parsed }
  }

  async login(email, password) {
    const r = await this.call('POST', '/api/auth/login', { email, password })
    this.csrf = null // el servidor rota el token al autenticar
    return r
  }

  /** Inicia sesión o, si la cuenta no existe, la registra (solo cuentas de prueba). */
  async authenticate(email) {
    let r = await this.login(email, PASSWORD)
    if (r.status === 401) {
      r = await this.call('POST', '/api/auth/register', { email, name: 'Cuenta de prueba', password: PASSWORD })
      this.csrf = null
    }
    if (![200, 201].includes(r.status)) throw new Error(`No se pudo autenticar ${email}: ${r.status}`)
  }
}

export class Report {
  failures = 0

  check(label, condition, detail = '') {
    console.log(`${condition ? '  ✓' : '  ✗'} ${label}${condition ? '' : ' ' + detail}`)
    if (!condition) this.failures++
  }

  finish() {
    console.log(this.failures === 0 ? '\nTodo correcto.\n' : `\n${this.failures} verificación(es) fallaron.\n`)
    process.exit(this.failures === 0 ? 0 : 1)
  }
}
