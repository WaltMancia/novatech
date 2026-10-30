import { render, screen } from '@testing-library/react'
import { MemoryRouter, Route, Routes } from 'react-router-dom'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { RequireAuth, RequireRole } from '../components/guards'
import type { Role } from '../lib/types'

const auth = vi.hoisted(() => ({ value: { user: null as null | { id: number; role: string }, loading: false } }))
vi.mock('../lib/auth', () => ({ useAuth: () => auth.value }))

function setUser(role: Role | null, loading = false) {
  auth.value = { user: role ? { id: 1, role } : null, loading }
}

function renderAt(path: string) {
  return render(
    <MemoryRouter initialEntries={[path]}>
      <Routes>
        <Route path="/login" element={<div>Pantalla de login</div>} />
        <Route element={<RequireAuth />}>
          <Route path="/orders" element={<div>Mis pedidos</div>} />
        </Route>
        <Route path="/admin" element={<RequireRole role="admin" />}>
          <Route index element={<div>Panel admin</div>} />
        </Route>
      </Routes>
    </MemoryRouter>,
  )
}

beforeEach(() => setUser(null))

describe('RequireAuth', () => {
  it('redirige a /login cuando no hay sesión', () => {
    renderAt('/orders')
    expect(screen.getByText('Pantalla de login')).toBeInTheDocument()
    expect(screen.queryByText('Mis pedidos')).not.toBeInTheDocument()
  })

  it('no muestra contenido ni redirige mientras se verifica la sesión', () => {
    setUser(null, true)
    renderAt('/orders')
    expect(screen.getByRole('status')).toBeInTheDocument()
    expect(screen.queryByText('Pantalla de login')).not.toBeInTheDocument()
    expect(screen.queryByText('Mis pedidos')).not.toBeInTheDocument()
  })

  it('permite el acceso con sesión iniciada', () => {
    setUser('customer')
    renderAt('/orders')
    expect(screen.getByText('Mis pedidos')).toBeInTheDocument()
  })
})

describe('RequireRole', () => {
  it('un anónimo es enviado a /login', () => {
    renderAt('/admin')
    expect(screen.getByText('Pantalla de login')).toBeInTheDocument()
  })

  it('un cliente ve 403 y nunca el contenido de administración', () => {
    setUser('customer')
    renderAt('/admin')
    expect(screen.getByText('403')).toBeInTheDocument()
    expect(screen.queryByText('Panel admin')).not.toBeInTheDocument()
  })

  it('un administrador accede al panel', () => {
    setUser('admin')
    renderAt('/admin')
    expect(screen.getByText('Panel admin')).toBeInTheDocument()
  })

  it('espera a conocer la sesión antes de decidir', () => {
    setUser('customer', true)
    renderAt('/admin')
    expect(screen.getByRole('status')).toBeInTheDocument()
    expect(screen.queryByText('403')).not.toBeInTheDocument()
  })
})
