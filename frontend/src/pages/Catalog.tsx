import { useMemo, useState } from 'react'
import ProductCard from '../components/ProductCard'
import { ErrorNotice, PageHeader, Spinner, inputCls } from '../components/ui'
import { useFetch } from '../lib/useFetch'
import type { Product } from '../lib/types'

export default function Catalog() {
  const { data, error, loading } = useFetch<{ products: Product[] }>('/products')
  const [query, setQuery] = useState('')

  const products = useMemo(() => {
    const q = query.trim().toLowerCase()
    return (data?.products ?? []).filter((p) => !q || p.name.toLowerCase().includes(q) || p.description.toLowerCase().includes(q))
  }, [data, query])

  return (
    <>
      <PageHeader
        title="Tecnología para tu día a día"
        subtitle="Laptops, accesorios y almacenamiento seleccionados."
        actions={
          <input
            type="search"
            value={query}
            onChange={(e) => setQuery(e.target.value)}
            placeholder="Buscar producto…"
            aria-label="Buscar producto"
            className={`${inputCls} mt-0 w-full sm:w-64`}
          />
        }
      />
      {loading && <Spinner />}
      {error && <ErrorNotice message={error} />}
      {data && products.length === 0 && <p className="py-12 text-center text-slate-500">No hay productos que coincidan.</p>}
      <div className="grid gap-5 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
        {products.map((p) => (
          <ProductCard key={p.id} product={p} />
        ))}
      </div>
    </>
  )
}
