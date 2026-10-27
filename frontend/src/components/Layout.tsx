import { Link, NavLink, Outlet, useNavigate } from 'react-router-dom'
import { useAuth } from '../lib/auth'
import { useCart } from '../lib/cart'
import { btnSecondary } from './ui'

const link = ({ isActive }: { isActive: boolean }) =>
  `rounded-md px-3 py-2 text-sm font-medium ${isActive ? 'bg-indigo-50 text-indigo-700' : 'text-slate-700 hover:bg-slate-100'}`

export default function Layout() {
  const { user, logout } = useAuth()
  const { count } = useCart()
  const navigate = useNavigate()

  async function onLogout() {
    await logout()
    navigate('/')
  }

  return (
    <div className="flex min-h-screen flex-col">
      <header className="sticky top-0 z-10 border-b border-slate-200 bg-white/90 backdrop-blur">
        <div className="mx-auto flex max-w-6xl flex-wrap items-center justify-between gap-2 px-4 py-3">
          <Link to="/" className="text-xl font-extrabold tracking-tight text-indigo-700">
            Nova<span className="text-slate-900">Tech</span>
          </Link>
          <nav aria-label="Principal" className="flex flex-wrap items-center gap-1">
            <NavLink to="/" end className={link}>Catálogo</NavLink>
            {user && <NavLink to="/orders" className={link}>Mis pedidos</NavLink>}
            {user?.role === 'admin' && <NavLink to="/admin" className={link}>Administración</NavLink>}
            {user && (
              <NavLink to="/cart" className={link}>
                Carrito
                {count > 0 && <span className="ml-1.5 rounded-full bg-indigo-600 px-2 py-0.5 text-xs text-white">{count}</span>}
              </NavLink>
            )}
          </nav>
          <div className="flex items-center gap-2">
            {user ? (
              <>
                <span className="hidden text-sm text-slate-600 sm:inline">{user.name}</span>
                <button onClick={onLogout} className={btnSecondary}>Salir</button>
              </>
            ) : (
              <>
                <Link to="/login" className={btnSecondary}>Ingresar</Link>
                <Link to="/register" className="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-500">Crear cuenta</Link>
              </>
            )}
          </div>
        </div>
      </header>
      <main className="mx-auto w-full max-w-6xl flex-1 px-4 py-8">
        <Outlet />
      </main>
      <footer className="border-t border-slate-200 bg-white py-6 text-center text-xs text-slate-500">
        NovaTech · Proyecto académico de Seguridad en Aplicaciones · Los pagos son simulados.
      </footer>
    </div>
  )
}
