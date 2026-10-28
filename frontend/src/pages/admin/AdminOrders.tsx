import { useState } from 'react'
import { ErrorNotice, Pagination, Spinner, StatusBadge, btnDanger, card, inputCls } from '../../components/ui'
import { api } from '../../lib/api'
import { errorMessage } from '../../lib/errors'
import { formatDate, formatMoney } from '../../lib/format'
import type { AdminOrder, Meta, OrderStatus } from '../../lib/types'
import { useFetch } from '../../lib/useFetch'

const STATUSES: { value: '' | OrderStatus; label: string }[] = [
  { value: '', label: 'Todos' },
  { value: 'paid', label: 'Pagados' },
  { value: 'pending', label: 'Pendientes' },
  { value: 'failed', label: 'Fallidos' },
  { value: 'cancelled', label: 'Cancelados' },
]

export default function AdminOrders() {
  const [page, setPage] = useState(1)
  const [status, setStatus] = useState<'' | OrderStatus>('')
  const query = `/admin/orders?page=${page}&perPage=10${status ? `&status=${status}` : ''}`
  const { data, error, loading, reload } = useFetch<{ orders: AdminOrder[]; meta: Meta }>(query)
  const [actionError, setActionError] = useState<string | null>(null)

  async function cancel(o: AdminOrder) {
    if (!window.confirm(`¿Cancelar el pedido #${o.id}? El stock volverá al inventario.`)) return
    setActionError(null)
    try {
      await api.post(`/admin/orders/${o.id}/cancel`)
      reload()
    } catch (e) {
      setActionError(errorMessage(e))
    }
  }

  return (
    <>
      <label className="mb-4 flex items-center gap-2 text-sm font-medium text-slate-700">
        Estado
        <select value={status} onChange={(e) => { setStatus(e.target.value as '' | OrderStatus); setPage(1) }} className={`${inputCls} mt-0 w-44`}>
          {STATUSES.map((s) => <option key={s.value} value={s.value}>{s.label}</option>)}
        </select>
      </label>
      {actionError && <div className="mb-4"><ErrorNotice message={actionError} /></div>}
      {loading && !data && <Spinner />}
      {error && <ErrorNotice message={error} />}
      {data && (
        <>
          <div className={`${card} overflow-x-auto`}>
            <table className="w-full text-left text-sm">
              <thead className="border-b border-slate-200 bg-slate-50 text-xs uppercase text-slate-500">
                <tr><th className="p-3">Pedido</th><th className="p-3">Cliente</th><th className="p-3">Fecha</th><th className="p-3">Estado</th><th className="p-3">Total</th><th className="p-3" /></tr>
              </thead>
              <tbody className="divide-y divide-slate-100">
                {data.orders.map((o) => (
                  <tr key={o.id}>
                    <td className="p-3 font-medium">#{o.id}</td>
                    <td className="p-3">Usuario {o.userId}</td>
                    <td className="p-3">{formatDate(o.createdAt)}</td>
                    <td className="p-3"><StatusBadge status={o.status} /></td>
                    <td className="p-3">{formatMoney(o.totalCents)}</td>
                    <td className="p-3 text-right">{o.status === 'paid' && <button onClick={() => cancel(o)} className={btnDanger}>Cancelar</button>}</td>
                  </tr>
                ))}
                {data.orders.length === 0 && <tr><td colSpan={6} className="p-6 text-center text-slate-500">Sin pedidos.</td></tr>}
              </tbody>
            </table>
          </div>
          <Pagination meta={data.meta} onPage={setPage} />
        </>
      )}
    </>
  )
}
