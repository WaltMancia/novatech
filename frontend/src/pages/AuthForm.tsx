import { useState, type FormEvent } from 'react'
import { Link, Navigate, useLocation, useNavigate } from 'react-router-dom'
import { Field, ErrorNotice, btnPrimary, card, inputCls } from '../components/ui'
import { useAuth } from '../lib/auth'
import { errorMessage, fieldErrors } from '../lib/errors'

export default function AuthForm({ mode }: { mode: 'login' | 'register' }) {
  const { user, login, register } = useAuth()
  const navigate = useNavigate()
  const location = useLocation()
  const from = (location.state as { from?: string } | null)?.from ?? '/'
  const [error, setError] = useState<string | null>(null)
  const [fields, setFields] = useState<Record<string, string[]>>({})
  const [busy, setBusy] = useState(false)

  if (user) return <Navigate to={from} replace />

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
      navigate(from, { replace: true })
    } catch (err) {
      setError(errorMessage(err))
      setFields(fieldErrors(err))
    } finally {
      setBusy(false)
    }
  }

  const isLogin = mode === 'login'
  return (
    <div className={`${card} mx-auto max-w-md p-6`}>
      <h1 className="text-2xl font-bold">{isLogin ? 'Iniciar sesión' : 'Crear cuenta'}</h1>
      <form onSubmit={onSubmit} className="mt-5 space-y-4" noValidate>
        {!isLogin && (
          <Field label="Nombre" error={fields.name?.[0]}>
            <input name="name" autoComplete="name" className={inputCls} />
          </Field>
        )}
        <Field label="Correo" error={fields.email?.[0]}>
          <input name="email" type="email" autoComplete="email" className={inputCls} />
        </Field>
        <Field label="Contraseña" error={fields.password?.[0]}>
          <input name="password" type="password" autoComplete={isLogin ? 'current-password' : 'new-password'} className={inputCls} />
          {!isLogin && <span className="text-xs font-normal text-slate-500">Mínimo 12 caracteres.</span>}
        </Field>
        {error && <ErrorNotice message={error} />}
        <button disabled={busy} className={`${btnPrimary} w-full`}>{busy ? 'Enviando…' : isLogin ? 'Entrar' : 'Registrarme'}</button>
      </form>
      <p className="mt-5 text-sm text-slate-600">
        {isLogin ? (
          <>¿No tienes cuenta? <Link to="/register" state={{ from }} className="font-medium text-indigo-600 hover:underline">Regístrate</Link></>
        ) : (
          <>¿Ya tienes cuenta? <Link to="/login" state={{ from }} className="font-medium text-indigo-600 hover:underline">Inicia sesión</Link></>
        )}
      </p>
    </div>
  )
}
