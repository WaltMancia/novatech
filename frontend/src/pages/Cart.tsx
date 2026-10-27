import { useState } from 'react'
import { Link } from 'react-router-dom'
import { ErrorNotice, PageHeader, btnPrimary, btnSecondary, card, inputCls } from '../components/ui'
import { useCart } from '../lib/cart'
import { errorMessage } from '../lib/errors'
import { formatMoney } from '../lib/format'

export default function CartPage() {
  const { cart, setQuantity, remove } = useCart()
  const [error, setError] = useState<string | null>(null)

  async function run(action: () => Promise<void>) {
    setError(null)
    try {
      await action()
    } catch (e) {
      setError(errorMessage(e))
    }
  }

  if (cart.items.length === 0) {
    return (
      <div className="py-16 text-center">
        <h1 className="text-2xl font-bold">Tu carrito está vacío</h1>
        <Link to="/" className={`${btnPrimary} mt-6`}>Explorar el catálogo</Link>
      </div>
    )
  }

  return (
    <>
      <PageHeader title="Tu carrito" />
      {error && <div className="mb-4"><ErrorNotice message={error} /></div>}
      <div className="grid gap-6 lg:grid-cols-3">
        <ul className={`${card} divide-y divide-slate-200 lg:col-span-2`}>
          {cart.items.map((item) => (
            <li key={item.productId} className="flex flex-wrap items-center justify-between gap-4 p-4">
              <div>
                <Link to={`/products/${item.productId}`} className="font-medium hover:underline">{item.name}</Link>
                <p className="text-sm text-slate-600">{formatMoney(item.unitPriceCents)} c/u</p>
              </div>
              <div className="flex items-center gap-4">
                <select
                  aria-label={`Cantidad de ${item.name}`}
                  value={item.quantity}
                  onChange={(e) => run(() => setQuantity(item.productId, Number(e.target.value)))}
                  className={`${inputCls} mt-0 w-20`}
                >
                  {Array.from({ length: Math.max(item.quantity, Math.min(10, item.stock)) }, (_, i) => i + 1).map((n) => (
                    <option key={n} value={n}>{n}</option>
                  ))}
                </select>
                <span className="w-28 text-right font-semibold">{formatMoney(item.subtotalCents)}</span>
                <button onClick={() => run(() => remove(item.productId))} className="text-sm text-red-600 hover:underline">Quitar</button>
              </div>
            </li>
          ))}
        </ul>
        <aside className={`${card} h-fit p-5`}>
          <h2 className="font-semibold">Resumen</h2>
          <p className="mt-4 flex justify-between text-lg font-bold">
            <span>Total</span>
            <span>{formatMoney(cart.totalCents)}</span>
          </p>
          <Link to="/checkout" className={`${btnPrimary} mt-5 w-full`}>Continuar al pago</Link>
          <Link to="/" className={`${btnSecondary} mt-3 w-full`}>Seguir comprando</Link>
        </aside>
      </div>
    </>
  )
}
