import { ApiError } from './api'

export function errorMessage(e: unknown): string {
  return e instanceof ApiError ? e.message : 'No se pudo conectar con el servidor.'
}

export function fieldErrors(e: unknown): Record<string, string[]> {
  return e instanceof ApiError ? (e.fields ?? {}) : {}
}
