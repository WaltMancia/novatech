import { useState } from 'react'
import { ErrorNotice, Pagination, Spinner, card } from '../../components/ui'
import type { AdminUser, Meta } from '../../lib/types'
import { useFetch } from '../../lib/useFetch'

export default function AdminUsers() {
  const [page, setPage] = useState(1)
  const { data, error, loading } = useFetch<{ users: AdminUser[]; meta: Meta }>(`/admin/users?page=${page}&perPage=10`)

  if (loading && !data) return <Spinner />
  if (error) return <ErrorNotice message={error} />
  if (!data) return null

  return (
    <>
      <div className={`${card} overflow-x-auto`}>
        <table className="w-full text-left text-sm">
          <thead className="border-b border-slate-200 bg-slate-50 text-xs uppercase text-slate-500">
            <tr><th className="p-3">ID</th><th className="p-3">Nombre</th><th className="p-3">Correo</th><th className="p-3">Rol</th><th className="p-3">Cuenta</th></tr>
          </thead>
          <tbody className="divide-y divide-slate-100">
            {data.users.map((u) => (
              <tr key={u.id}>
                <td className="p-3">{u.id}</td>
                <td className="p-3">{u.name}</td>
                <td className="p-3">{u.email}</td>
                <td className="p-3">{u.role === 'admin' ? 'Administrador' : 'Cliente'}</td>
                <td className="p-3">{u.locked ? <span className="text-red-600">Bloqueada</span> : 'Activa'}</td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>
      <Pagination meta={data.meta} onPage={setPage} />
    </>
  )
}
