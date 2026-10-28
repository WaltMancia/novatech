import { useState } from 'react'
import { ErrorNotice, Pagination, Spinner, btnPrimary, btnSecondary, card } from '../../components/ui'
import { api } from '../../lib/api'
import { errorMessage } from '../../lib/errors'
import { formatMoney } from '../../lib/format'
import type { AdminProduct, Meta } from '../../lib/types'
import { useFetch } from '../../lib/useFetch'
import ProductForm from './ProductForm'

export default function AdminProducts() {
  const [page, setPage] = useState(1)
  const { data, error, loading, reload } = useFetch<{ products: AdminProduct[]; meta: Meta }>(`/admin/products?page=${page}&perPage=10`)
  const [editing, setEditing] = useState<AdminProduct | 'new' | null>(null)
  const [actionError, setActionError] = useState<string | null>(null)

  async function setActive(p: AdminProduct, active: boolean) {
    setActionError(null)
    try {
      await api.patch(`/admin/products/${p.id}`, { active })
      reload()
    } catch (e) {
      setActionError(errorMessage(e))
    }
  }

  const saved = () => {
    setEditing(null)
    reload()
  }

  return (
    <>
      <div className="mb-4 flex justify-end">
        {editing === null && <button onClick={() => setEditing('new')} className={btnPrimary}>Nuevo producto</button>}
      </div>
      {editing === 'new' && <ProductForm onSaved={saved} onCancel={() => setEditing(null)} />}
      {editing && editing !== 'new' && <ProductForm key={editing.id} product={editing} onSaved={saved} onCancel={() => setEditing(null)} />}
      {actionError && <div className="mb-4"><ErrorNotice message={actionError} /></div>}
      {loading && !data && <Spinner />}
      {error && <ErrorNotice message={error} />}
      {data && (
        <>
          <div className={`${card} overflow-x-auto`}>
            <table className="w-full text-left text-sm">
              <thead className="border-b border-slate-200 bg-slate-50 text-xs uppercase text-slate-500">
                <tr><th className="p-3">SKU</th><th className="p-3">Nombre</th><th className="p-3">Precio</th><th className="p-3">Stock</th><th className="p-3">Estado</th><th className="p-3" /></tr>
              </thead>
              <tbody className="divide-y divide-slate-100">
                {data.products.map((p) => (
                  <tr key={p.id} className={p.active ? '' : 'text-slate-400'}>
                    <td className="p-3 font-mono text-xs">{p.sku}</td>
                    <td className="p-3">{p.name}</td>
                    <td className="p-3">{formatMoney(p.priceCents)}</td>
                    <td className="p-3">{p.stock}</td>
                    <td className="p-3">{p.active ? 'Activo' : 'Inactivo'}</td>
                    <td className="flex justify-end gap-2 p-3">
                      <button onClick={() => setEditing(p)} className={btnSecondary}>Editar</button>
                      <button onClick={() => setActive(p, !p.active)} className={btnSecondary}>{p.active ? 'Desactivar' : 'Activar'}</button>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
          <Pagination meta={data.meta} onPage={setPage} />
        </>
      )}
    </>
  )
}
