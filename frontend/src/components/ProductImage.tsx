import { useState } from 'react'
import type { Product } from '../lib/types'

type Props = { product: Pick<Product, 'name' | 'imagePath'>; className?: string }

export default function ProductImage({ product, className = '' }: Props) {
  const [failed, setFailed] = useState(false)

  if (!product.imagePath || failed) {
    return (
      <div className={`flex items-center justify-center bg-slate-100 text-slate-300 ${className}`} role="img" aria-label={product.name}>
        <svg viewBox="0 0 24 24" className="h-12 w-12" fill="none" stroke="currentColor" strokeWidth="1.5" aria-hidden="true">
          <path strokeLinecap="round" strokeLinejoin="round" d="M21 7.5l-9-5.25L3 7.5m18 0l-9 5.25m9-5.25v9l-9 5.25M3 7.5l9 5.25M3 7.5v9l9 5.25m0-9v9" />
        </svg>
      </div>
    )
  }
  return <img src={product.imagePath} alt={product.name} loading="lazy" onError={() => setFailed(true)} className={`object-cover ${className}`} />
}
