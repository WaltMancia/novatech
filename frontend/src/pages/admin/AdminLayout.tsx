import { NavLink, Outlet } from 'react-router-dom'
import { PageHeader } from '../../components/ui'

const tab = ({ isActive }: { isActive: boolean }) =>
  `border-b-2 px-4 py-2 text-sm font-medium ${isActive ? 'border-indigo-600 text-indigo-700' : 'border-transparent text-slate-600 hover:text-slate-900'}`

export default function AdminLayout() {
  return (
    <>
      <PageHeader title="Administración" />
      <nav aria-label="Administración" className="mb-6 flex flex-wrap border-b border-slate-200">
        <NavLink to="/admin/products" className={tab}>Productos</NavLink>
        <NavLink to="/admin/orders" className={tab}>Pedidos</NavLink>
        <NavLink to="/admin/users" className={tab}>Usuarios</NavLink>
        <NavLink to="/admin/audit" className={tab}>Auditoría</NavLink>
      </nav>
      <Outlet />
    </>
  )
}
