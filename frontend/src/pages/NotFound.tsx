import { Link } from 'react-router-dom'
import { btnPrimary } from '../components/ui'

export default function NotFound({ message = 'La página que buscas no existe.' }: { message?: string }) {
  return (
    <div className="py-20 text-center">
      <h1 className="text-3xl font-bold">404</h1>
      <p className="mt-2 text-slate-600">{message}</p>
      <Link to="/" className={`${btnPrimary} mt-6`}>Volver al inicio</Link>
    </div>
  )
}
