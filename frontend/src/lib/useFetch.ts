import { useEffect, useState } from 'react'
import { api } from './api'
import { errorMessage } from './errors'

type State<T> = { data: T | null; error: string | null; loading: boolean }

/** GET con estado de carga/error. Pasar `null` como ruta pospone la petición. */
export function useFetch<T>(path: string | null) {
  const [state, setState] = useState<State<T>>({ data: null, error: null, loading: path !== null })
  const [tick, setTick] = useState(0)

  useEffect(() => {
    if (path === null) return
    let cancelled = false
    setState((s) => ({ ...s, loading: true, error: null }))
    api
      .get<T>(path)
      .then((data) => !cancelled && setState({ data, error: null, loading: false }))
      .catch((e) => !cancelled && setState({ data: null, error: errorMessage(e), loading: false }))
    return () => {
      cancelled = true
    }
  }, [path, tick])

  return { ...state, reload: () => setTick((t) => t + 1) }
}
