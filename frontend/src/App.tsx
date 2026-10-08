import { useEffect, useState } from 'react'
import { api, ApiError } from './lib/api'

type Check = { label: string; state: 'loading' | 'ok' | 'fail'; detail?: string }

export default function App() {
  const [checks, setChecks] = useState<Check[]>([
    { label: 'API (liveness)', state: 'loading' },
    { label: 'Base de datos (readiness)', state: 'loading' },
    { label: 'Token CSRF', state: 'loading' },
  ])

  useEffect(() => {
    const set = (i: number, c: Partial<Check>) =>
      setChecks((prev) => prev.map((p, idx) => (idx === i ? { ...p, ...c } : p)))
    const run = async (i: number, fn: () => Promise<unknown>) => {
      try {
        await fn()
        set(i, { state: 'ok' })
      } catch (e) {
        set(i, { state: 'fail', detail: e instanceof ApiError ? e.code : 'network_error' })
      }
    }
    void run(0, () => api.get('/health'))
    void run(1, () => api.get('/health/ready'))
    void run(2, () => api.post('/health-csrf-probe').catch((e) => {
      // 404 significa que el token CSRF fue aceptado y solo falta la ruta (esperado en Fase 0).
      if (!(e instanceof ApiError && e.status === 404)) throw e
    }))
  }, [])

  const color = { loading: 'bg-slate-300', ok: 'bg-emerald-500', fail: 'bg-red-500' }

  return (
    <main className="mx-auto max-w-xl px-6 py-16">
      <h1 className="text-3xl font-bold tracking-tight">NovaTech</h1>
      <p className="mt-2 text-slate-600">Fase 0 · Estado de la plataforma</p>
      <ul className="mt-8 divide-y divide-slate-200 rounded-xl border border-slate-200 bg-white shadow-sm">
        {checks.map((c) => (
          <li key={c.label} className="flex items-center justify-between px-5 py-4">
            <span className="font-medium">{c.label}</span>
            <span className="flex items-center gap-2 text-sm text-slate-600">
              {c.detail}
              <span className={`h-3 w-3 rounded-full ${color[c.state]}`} aria-label={c.state} />
            </span>
          </li>
        ))}
      </ul>
    </main>
  )
}
