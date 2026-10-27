import { Link } from 'react-router-dom'
import { ErrorNotice, PageHeader, Spinner, StatusBadge, btnPrimary, card } from '../components/ui'
import { formatDate, formatMoney } from '../lib/format'
import type { Order } from '../lib/types'
import { useFetch } from '../lib/useFetch'

export default function Orders() {
  const { data, error, loading } = useFetch<{ orders: Order[] }>('/orders')

  if (loading) return <Spinner />
  if (error) return <ErrorNotice message={error} />
  if (!data || data.orders.length === 0) {
    return (
      <div className="py-16 text-center">
        <h1 className="text-2xl font-bold">Aún no tienes pedidos</h1>
        <Link to="/" className={`${btnPrimary} mt-6`}>Ir al catálogo</Link>
      </div>
    )
  }

  return (
    <>
      <PageHeader title="Mis pedidos" />
      <ul className="space-y-3">
        {data.orders.map((o) => (
          <li key={o.id}>
            <Link to={`/orders/${o.id}`} className={`${card} flex flex-wrap items-center justify-between gap-3 p-4 hover:border-indigo-300`}>
              <div>
                <p className="font-semibold">Pedido #{o.id}</p>
                <p className="text-sm text-slate-600">{formatDate(o.createdAt)} · {o.items.length} {o.items.length === 1 ? 'artículo' : 'artículos'}</p>
              </div>
              <div className="flex items-center gap-4">
                <StatusBadge status={o.status} />
                <span className="font-bold">{formatMoney(o.totalCents)}</span>
              </div>
            </Link>
          </li>
        ))}
      </ul>
    </>
  )
}
