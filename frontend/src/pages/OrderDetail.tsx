import { Link, useParams } from 'react-router-dom'
import { PageHeader, Spinner, StatusBadge, card } from '../components/ui'
import { formatDate, formatMoney } from '../lib/format'
import type { Order } from '../lib/types'
import { useFetch } from '../lib/useFetch'
import NotFound from './NotFound'

export default function OrderDetail() {
  const { id } = useParams()
  const { data, error, loading } = useFetch<{ order: Order }>(`/orders/${Number(id)}`)

  if (loading) return <Spinner />
  if (!data) return error ? <NotFound message="Pedido no encontrado." /> : null
  const o = data.order

  return (
    <>
      <PageHeader title={`Pedido #${o.id}`} subtitle={formatDate(o.createdAt)} actions={<StatusBadge status={o.status} />} />
      <div className={`${card} divide-y divide-slate-200`}>
        {o.items.map((i) => (
          <div key={i.productId} className="flex justify-between gap-4 p-4 text-sm">
            <span>{i.quantity} × {i.name} <span className="text-slate-500">({formatMoney(i.unitPriceCents)} c/u)</span></span>
            <span className="font-medium">{formatMoney(i.subtotalCents)}</span>
          </div>
        ))}
        <div className="flex justify-between p-4 text-lg font-bold">
          <span>Total</span>
          <span>{formatMoney(o.totalCents)}</span>
        </div>
      </div>
      {o.paymentRef && <p className="mt-3 text-xs text-slate-500">Referencia de pago (simulada): {o.paymentRef}</p>}
      <Link to="/orders" className="mt-6 inline-block text-sm text-indigo-600 hover:underline">← Mis pedidos</Link>
    </>
  )
}
