import type { ReactNode } from 'react'
import type { Meta, OrderStatus } from '../lib/types'

const focus = 'focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500 focus-visible:ring-offset-2'
export const btnPrimary = `inline-flex items-center justify-center rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-indigo-500 disabled:cursor-not-allowed disabled:opacity-50 ${focus}`
export const btnSecondary = `inline-flex items-center justify-center rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-50 ${focus}`
export const btnDanger = `inline-flex items-center justify-center rounded-lg bg-red-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-red-500 disabled:cursor-not-allowed disabled:opacity-50 ${focus}`
export const inputCls =
  'mt-1 w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500'
export const card = 'rounded-xl border border-slate-200 bg-white shadow-sm'

export function Spinner() {
  return (
    <div role="status" className="flex justify-center py-16">
      <span className="h-8 w-8 animate-spin rounded-full border-4 border-slate-200 border-t-indigo-600" />
      <span className="sr-only">Cargando…</span>
    </div>
  )
}

export function ErrorNotice({ message }: { message: string }) {
  return (
    <p role="alert" className="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
      {message}
    </p>
  )
}

export function Notice({ children }: { children: ReactNode }) {
  return <p className="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">{children}</p>
}

export function PageHeader({ title, subtitle, actions }: { title: string; subtitle?: string; actions?: ReactNode }) {
  return (
    <div className="mb-6 flex flex-wrap items-end justify-between gap-3">
      <div>
        <h1 className="text-2xl font-bold tracking-tight">{title}</h1>
        {subtitle && <p className="mt-1 text-sm text-slate-600">{subtitle}</p>}
      </div>
      {actions}
    </div>
  )
}

export function Field({ label, error, children }: { label: string; error?: string; children: ReactNode }) {
  return (
    <label className="block text-sm font-medium text-slate-700">
      {label}
      {children}
      {error && <span className="mt-1 block text-xs font-normal text-red-600">{error}</span>}
    </label>
  )
}

const STATUS: Record<OrderStatus, { label: string; cls: string }> = {
  pending: { label: 'Pendiente', cls: 'bg-amber-100 text-amber-800' },
  paid: { label: 'Pagado', cls: 'bg-emerald-100 text-emerald-800' },
  failed: { label: 'Fallido', cls: 'bg-red-100 text-red-800' },
  cancelled: { label: 'Cancelado', cls: 'bg-slate-200 text-slate-700' },
}

export function StatusBadge({ status }: { status: OrderStatus }) {
  const s = STATUS[status]
  return <span className={`rounded-full px-2.5 py-0.5 text-xs font-medium ${s.cls}`}>{s.label}</span>
}

export function Pagination({ meta, onPage }: { meta: Meta; onPage: (page: number) => void }) {
  const pages = Math.max(1, Math.ceil(meta.total / meta.perPage))
  return (
    <nav aria-label="Paginación" className="mt-4 flex items-center justify-between text-sm text-slate-600">
      <span>
        Página {meta.page} de {pages} · {meta.total} registros
      </span>
      <span className="flex gap-2">
        <button className={btnSecondary} disabled={meta.page <= 1} onClick={() => onPage(meta.page - 1)}>
          Anterior
        </button>
        <button className={btnSecondary} disabled={meta.page >= pages} onClick={() => onPage(meta.page + 1)}>
          Siguiente
        </button>
      </span>
    </nav>
  )
}
