import { useState, type FormEvent } from 'react'
import { ErrorNotice, Pagination, Spinner, btnSecondary, card, inputCls } from '../../components/ui'
import { formatDate } from '../../lib/format'
import type { AuditEvent, Meta } from '../../lib/types'
import { useFetch } from '../../lib/useFetch'

export default function AdminAudit() {
  const [page, setPage] = useState(1)
  const [action, setAction] = useState('')
  const query = `/admin/audit-logs?page=${page}&perPage=15${action ? `&action=${encodeURIComponent(action)}` : ''}`
  const { data, error, loading } = useFetch<{ events: AuditEvent[]; meta: Meta }>(query)

  function onFilter(e: FormEvent<HTMLFormElement>) {
    e.preventDefault()
    setAction(String(new FormData(e.currentTarget).get('action')).trim())
    setPage(1)
  }

  return (
    <>
      <form onSubmit={onFilter} className="mb-4 flex flex-wrap items-center gap-2">
        <input name="action" aria-label="Filtrar por acción" placeholder="Acción, p. ej. auth.login_failed" defaultValue={action} className={`${inputCls} mt-0 w-72`} />
        <button className={btnSecondary}>Filtrar</button>
      </form>
      {loading && !data && <Spinner />}
      {error && <ErrorNotice message={error} />}
      {data && (
        <>
          <div className={`${card} overflow-x-auto`}>
            <table className="w-full text-left text-sm">
              <thead className="border-b border-slate-200 bg-slate-50 text-xs uppercase text-slate-500">
                <tr><th className="p-3">Fecha</th><th className="p-3">Acción</th><th className="p-3">Usuario</th><th className="p-3">IP</th><th className="p-3">Detalle</th></tr>
              </thead>
              <tbody className="divide-y divide-slate-100">
                {data.events.map((ev) => (
                  <tr key={ev.id}>
                    <td className="whitespace-nowrap p-3">{formatDate(ev.createdAt)}</td>
                    <td className="p-3 font-mono text-xs">{ev.action}</td>
                    <td className="p-3">{ev.userId ?? '—'}</td>
                    <td className="p-3 font-mono text-xs">{ev.ip}</td>
                    <td className="p-3 font-mono text-xs text-slate-600">{ev.metadata ? JSON.stringify(ev.metadata) : ''}</td>
                  </tr>
                ))}
                {data.events.length === 0 && <tr><td colSpan={5} className="p-6 text-center text-slate-500">Sin eventos.</td></tr>}
              </tbody>
            </table>
          </div>
          <Pagination meta={data.meta} onPage={setPage} />
        </>
      )}
    </>
  )
}
