import { useState, type FormEvent } from 'react'
import { Link, useNavigate } from 'react-router-dom'
import { ErrorNotice, PageHeader, btnPrimary, card } from '../components/ui'
import { api } from '../lib/api'
import { useCart } from '../lib/cart'
import { errorMessage } from '../lib/errors'
import { formatMoney } from '../lib/format'
import type { Order } from '../lib/types'

const METHODS = [
  { value: 'simulated_success', label: 'Pago simulado: aprobado', hint: 'El cobro se aprueba.' },
  { value: 'simulated_decline', label: 'Pago simulado: rechazado', hint: 'Para probar el rechazo; el carrito se conserva.' },
]

export default function Checkout() {
  const { cart, refresh } = useCart()
  const navigate = useNavigate()
  const [method, setMethod] = useState(METHODS[0].value)
  const [error, setError] = useState<string | null>(null)
  const [busy, setBusy] = useState(false)

  if (cart.items.length === 0) {
    return (
      <div className="py-16 text-center">
        <h1 className="text-2xl font-bold">No hay nada que pagar</h1>
        <Link to="/" className={`${btnPrimary} mt-6`}>Volver al catálogo</Link>
      </div>
    )
  }

  async function onSubmit(e: FormEvent) {
    e.preventDefault()
    setBusy(true)
    setError(null)
    try {
      const { order } = await api.post<{ order: Order }>('/orders', { paymentMethod: method })
      await refresh()
      navigate(`/orders/${order.id}`, { replace: true })
    } catch (err) {
      setError(errorMessage(err))
      await refresh().catch(() => undefined)
    } finally {
      setBusy(false)
    }
  }

  return (
    <>
      <PageHeader title="Confirmar pedido" />
      <div className="grid gap-6 lg:grid-cols-3">
        <form onSubmit={onSubmit} className={`${card} space-y-4 p-5 lg:col-span-2`}>
          <fieldset>
            <legend className="font-semibold">Método de pago</legend>
            <p className="mt-1 text-sm text-slate-600">Es un entorno de práctica: no se solicitan ni se almacenan datos de tarjeta.</p>
            <div className="mt-3 space-y-2">
              {METHODS.map((m) => (
                <label key={m.value} className="flex cursor-pointer items-start gap-3 rounded-lg border border-slate-200 p-3 has-[:checked]:border-indigo-500 has-[:checked]:bg-indigo-50">
                  <input type="radio" name="paymentMethod" value={m.value} checked={method === m.value} onChange={() => setMethod(m.value)} className="mt-1" />
                  <span>
                    <span className="block text-sm font-medium">{m.label}</span>
                    <span className="block text-xs text-slate-600">{m.hint}</span>
                  </span>
                </label>
              ))}
            </div>
          </fieldset>
          {error && <ErrorNotice message={error} />}
          <button disabled={busy} className={btnPrimary}>{busy ? 'Procesando…' : `Pagar ${formatMoney(cart.totalCents)}`}</button>
        </form>
        <aside className={`${card} h-fit p-5`}>
          <h2 className="font-semibold">Tu pedido</h2>
          <ul className="mt-3 space-y-2 text-sm">
            {cart.items.map((i) => (
              <li key={i.productId} className="flex justify-between gap-3">
                <span>{i.quantity} × {i.name}</span>
                <span className="font-medium">{formatMoney(i.subtotalCents)}</span>
              </li>
            ))}
          </ul>
          <p className="mt-4 flex justify-between border-t border-slate-200 pt-4 text-lg font-bold">
            <span>Total</span>
            <span>{formatMoney(cart.totalCents)}</span>
          </p>
        </aside>
      </div>
    </>
  )
}
