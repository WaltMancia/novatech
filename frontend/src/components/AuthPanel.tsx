import { useState, type FormEvent } from 'react'
import { ApiError } from '../lib/api'
import { useAuth } from '../lib/auth'

const input = 'mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 focus:border-indigo-500 focus:outline-none'

export default function AuthPanel() {
  const { user, loading, login, register, logout } = useAuth()
  const [mode, setMode] = useState<'login' | 'register'>('login')
  const [error, setError] = useState<string | null>(null)
  const [fields, setFields] = useState<Record<string, string[]>>({})
  const [busy, setBusy] = useState(false)

  if (loading) return null

  if (user) {
    return (
      <section className="mt-8 rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
        <p className="font-medium">{user.name}</p>
        <p className="text-sm text-slate-600">
          {user.email} · {user.role === 'admin' ? 'Administrador' : 'Cliente'}
        </p>
        <button onClick={logout} className="mt-4 rounded-lg bg-slate-900 px-4 py-2 text-sm text-white hover:bg-slate-700">
          Cerrar sesión
        </button>
      </section>
    )
  }

  async function onSubmit(e: FormEvent<HTMLFormElement>) {
    e.preventDefault()
    const form = new FormData(e.currentTarget)
    const email = String(form.get('email'))
    const password = String(form.get('password'))
    setBusy(true)
    setError(null)
    setFields({})
    try {
      if (mode === 'login') await login(email, password)
      else await register(email, String(form.get('name')), password)
    } catch (err) {
      if (err instanceof ApiError) {
        setError(err.message)
        setFields(err.fields ?? {})
      } else setError('No se pudo conectar con el servidor.')
    } finally {
      setBusy(false)
    }
  }

  return (
    <section className="mt-8 rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
      <h2 className="text-lg font-semibold">{mode === 'login' ? 'Iniciar sesión' : 'Crear cuenta'}</h2>
      <form onSubmit={onSubmit} className="mt-4 space-y-4" noValidate>
        {mode === 'register' && (
          <label className="block text-sm font-medium">
            Nombre
            <input name="name" autoComplete="name" className={input} />
            <FieldError messages={fields.name} />
          </label>
        )}
        <label className="block text-sm font-medium">
          Correo
          <input name="email" type="email" autoComplete="email" className={input} />
          <FieldError messages={fields.email} />
        </label>
        <label className="block text-sm font-medium">
          Contraseña
          <input
            name="password"
            type="password"
            autoComplete={mode === 'login' ? 'current-password' : 'new-password'}
            className={input}
          />
          {mode === 'register' && <span className="text-xs font-normal text-slate-500">Mínimo 12 caracteres.</span>}
          <FieldError messages={fields.password} />
        </label>
        {error && <p role="alert" className="text-sm text-red-600">{error}</p>}
        <button disabled={busy} className="w-full rounded-lg bg-indigo-600 px-4 py-2 font-medium text-white hover:bg-indigo-500 disabled:opacity-50">
          {busy ? 'Enviando…' : mode === 'login' ? 'Entrar' : 'Registrarme'}
        </button>
      </form>
      <button
        onClick={() => { setMode(mode === 'login' ? 'register' : 'login'); setError(null); setFields({}) }}
        className="mt-4 text-sm text-indigo-600 hover:underline"
      >
        {mode === 'login' ? '¿No tienes cuenta? Regístrate' : '¿Ya tienes cuenta? Inicia sesión'}
      </button>
    </section>
  )
}

function FieldError({ messages }: { messages?: string[] }) {
  return messages?.length ? <span className="mt-1 block text-xs font-normal text-red-600">{messages[0]}</span> : null
}
