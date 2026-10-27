import { Link } from 'react-router-dom'
import { formatMoney } from '../lib/format'
import type { Product } from '../lib/types'
import ProductImage from './ProductImage'

export default function ProductCard({ product }: { product: Product }) {
  const soldOut = product.stock === 0
  return (
    <Link
      to={`/products/${product.id}`}
      className="group overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm transition hover:-translate-y-0.5 hover:shadow-md focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500"
    >
      <ProductImage product={product} className="aspect-[4/3] w-full transition group-hover:scale-[1.02]" />
      <div className="p-4">
        <h2 className="line-clamp-1 font-semibold">{product.name}</h2>
        <p className="mt-1 line-clamp-2 min-h-10 text-sm text-slate-600">{product.description}</p>
        <div className="mt-3 flex items-center justify-between">
          <span className="text-lg font-bold text-indigo-700">{formatMoney(product.priceCents)}</span>
          <span className={`text-xs font-medium ${soldOut ? 'text-red-600' : 'text-emerald-700'}`}>{soldOut ? 'Agotado' : 'Disponible'}</span>
        </div>
      </div>
    </Link>
  )
}
