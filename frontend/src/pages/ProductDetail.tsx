import { useState } from 'react'
import { Link, useLocation, useNavigate, useParams } from 'react-router-dom'
import ProductImage from '../components/ProductImage'
import { ErrorNotice, Notice, Spinner, btnPrimary, inputCls } from '../components/ui'
import { useAuth } from '../lib/auth'
import { useCart } from '../lib/cart'
import { errorMessage } from '../lib/errors'
import { formatMoney } from '../lib/format'
import type { Product } from '../lib/types'
import { useFetch } from '../lib/useFetch'
import NotFound from './NotFound'

const MAX = 10

export default function ProductDetail() {
  const { id } = useParams()
  const { data, error, loading } = useFetch<{ product: Product }>(`/products/${Number(id)}`)
  const { user } = useAuth()
  const { add } = useCart()
  const navigate = useNavigate()
  const location = useLocation()
  const [quantity, setQuantity] = useState(1)
  const [message, setMessage] = useState<{ ok: boolean; text: string } | null>(null)
  const [busy, setBusy] = useState(false)

  if (loading) return <Spinner />
  if (!data) return error ? <NotFound /> : null
  const product = data.product
  const limit = Math.min(MAX, product.stock)

  async function onAdd() {
    if (!user) {
      navigate('/login', { state: { from: location.pathname } })
      return
    }
    setBusy(true)
    setMessage(null)
    try {
      await add(product.id, quantity)
      setMessage({ ok: true, text: 'Producto agregado al carrito.' })
    } catch (e) {
      setMessage({ ok: false, text: errorMessage(e) })
    } finally {
      setBusy(false)
    }
  }

  return (
    <div className="grid gap-8 md:grid-cols-2">
      <ProductImage product={product} className="aspect-[4/3] w-full rounded-xl border border-slate-200" />
      <div>
        <Link to="/" className="text-sm text-indigo-600 hover:underline">← Volver al catálogo</Link>
        <h1 className="mt-2 text-3xl font-bold tracking-tight">{product.name}</h1>
        <p className="mt-1 text-xs text-slate-500">SKU {product.sku}</p>
        <p className="mt-4 text-3xl font-bold text-indigo-700">{formatMoney(product.priceCents)}</p>
        <p className="mt-4 text-slate-700">{product.description}</p>
        <p className={`mt-4 text-sm font-medium ${product.stock === 0 ? 'text-red-600' : 'text-emerald-700'}`}>
          {product.stock === 0 ? 'Agotado' : `${product.stock} unidades disponibles`}
        </p>

        {product.stock > 0 && (
          <div className="mt-6 flex items-end gap-3">
            <label className="text-sm font-medium text-slate-700">
              Cantidad
              <select value={quantity} onChange={(e) => setQuantity(Number(e.target.value))} className={`${inputCls} w-24`}>
                {Array.from({ length: limit }, (_, i) => i + 1).map((n) => (
                  <option key={n} value={n}>{n}</option>
                ))}
              </select>
            </label>
            <button onClick={onAdd} disabled={busy} className={btnPrimary}>
              {user ? 'Agregar al carrito' : 'Inicia sesión para comprar'}
            </button>
          </div>
        )}
        <div className="mt-4 space-y-3">
          {message?.ok && (
            <Notice>
              {message.text} <Link to="/cart" className="font-medium underline">Ver carrito</Link>
            </Notice>
          )}
          {message && !message.ok && <ErrorNotice message={message.text} />}
        </div>
      </div>
    </div>
  )
}
