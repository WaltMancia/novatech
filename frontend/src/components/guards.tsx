import type { ReactNode } from 'react'
import { Navigate, Outlet, useLocation } from 'react-router-dom'
import { useAuth } from '../lib/auth'
import type { Role } from '../lib/types'
import { Spinner } from './ui'

/**
 * Guardas de navegación: mejoran la experiencia, no son una barrera de seguridad.
 * La autorización real la aplica siempre el servidor (Authenticate + role:admin).
 */
export function RequireAuth({ children }: { children?: ReactNode }) {
  const { user, loading } = useAuth()
  const location = useLocation()

  if (loading) return <Spinner />
  if (!user) return <Navigate to="/login" replace state={{ from: location.pathname }} />
  return <>{children ?? <Outlet />}</>
}

export function RequireRole({ role, children }: { role: Role; children?: ReactNode }) {
  const { user, loading } = useAuth()
  const location = useLocation()

  if (loading) return <Spinner />
  if (!user) return <Navigate to="/login" replace state={{ from: location.pathname }} />
  if (user.role !== role) return <Forbidden />
  return <>{children ?? <Outlet />}</>
}

export function Forbidden() {
  return (
    <div className="py-20 text-center">
      <h1 className="text-3xl font-bold">403</h1>
      <p className="mt-2 text-slate-600">No tienes permiso para ver esta página.</p>
    </div>
  )
}
