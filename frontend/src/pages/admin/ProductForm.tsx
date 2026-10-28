import { useState, type FormEvent } from 'react'
import { Field, ErrorNotice, btnPrimary, btnSecondary, card, inputCls } from '../../components/ui'
import { api } from '../../lib/api'
import { errorMessage, fieldErrors } from '../../lib/errors'
import type { AdminProduct } from '../../lib/types'

type Props = { product?: AdminProduct; onSaved: () => void; onCancel: () => void }

export default function ProductForm({ product, onSaved, onCancel }: Props) {
  const editing = product !== undefined
  const [error, setError] = useState<string | null>(null)
  const [fields, setFields] = useState<Record<string, string[]>>({})
  const [busy, setBusy] = useState(false)

  async function onSubmit(e: FormEvent<HTMLFormElement>) {
    e.preventDefault()
    const f = new FormData(e.currentTarget)
    const price = Number(String(f.get('price')).replace(',', '.'))
    const image = String(f.get('imagePath')).trim()

    setError(null)
    setFields({})

    const payload = {
      name: String(f.get('name')),
      description: String(f.get('description')),
      // Un precio ilegible se envía como 0 para que el servidor devuelva todos los errores de una vez.
      priceCents: Number.isFinite(price) && price > 0 ? Math.round(price * 100) : 0,
      stock: Number(f.get('stock')),
      imagePath: image === '' ? null : image,
    }
    setBusy(true)
    try {
      if (editing) await api.patch(`/admin/products/${product.id}`, payload)
      else await api.post('/admin/products', { sku: String(f.get('sku')).trim().toUpperCase(), ...payload })
      onSaved()
    } catch (err) {
      setError(errorMessage(err))
      const fe = fieldErrors(err)
      setFields({ ...fe, price: fe.priceCents ? ['Ingresa un precio válido (mayor a 0).'] : [] })
    } finally {
      setBusy(false)
    }
  }

  return (
    <form onSubmit={onSubmit} className={`${card} mb-6 grid gap-4 p-5 sm:grid-cols-2`} noValidate>
      <h2 className="font-semibold sm:col-span-2">{editing ? `Editar ${product.sku}` : 'Nuevo producto'}</h2>
      {!editing && (
        <Field label="SKU" error={fields.sku?.[0]}>
          <input name="sku" className={inputCls} placeholder="NV-XXX-01" />
        </Field>
      )}
      <Field label="Nombre" error={fields.name?.[0]}>
        <input name="name" defaultValue={product?.name} className={inputCls} />
      </Field>
      <div className="sm:col-span-2">
        <Field label="Descripción" error={fields.description?.[0]}>
          <textarea name="description" rows={3} defaultValue={product?.description} className={inputCls} />
        </Field>
      </div>
      <Field label="Precio (Q)" error={fields.price?.[0]}>
        <input name="price" inputMode="decimal" defaultValue={product ? (product.priceCents / 100).toFixed(2) : ''} className={inputCls} />
      </Field>
      <Field label="Stock" error={fields.stock?.[0]}>
        <input name="stock" type="number" min={0} defaultValue={product?.stock ?? 0} className={inputCls} />
      </Field>
      <div className="sm:col-span-2">
        <Field label="Imagen (ruta interna, opcional)" error={fields.imagePath?.[0]}>
          <input name="imagePath" defaultValue={product?.imagePath ?? ''} placeholder="/images/mi-producto.webp" className={inputCls} />
        </Field>
      </div>
      {error && <div className="sm:col-span-2"><ErrorNotice message={error} /></div>}
      <div className="flex gap-3 sm:col-span-2">
        <button disabled={busy} className={btnPrimary}>{busy ? 'Guardando…' : 'Guardar'}</button>
        <button type="button" onClick={onCancel} className={btnSecondary}>Cancelar</button>
      </div>
    </form>
  )
}
