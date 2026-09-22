import type { ApiErrorBody } from '@/types/api'

export const API_URL = import.meta.env.VITE_API_URL ?? 'http://localhost:8081/api/v1'

const TOKEN_KEY = 'flowstock_token'
export const USER_KEY = 'flowstock_user'

export function getToken(): string | null {
  return localStorage.getItem(TOKEN_KEY)
}

export function setToken(token: string): void {
  localStorage.setItem(TOKEN_KEY, token)
}

export function clearToken(): void {
  localStorage.removeItem(TOKEN_KEY)
}

/** Clears both the token and the cached user — the only correct way to end a session. */
export function clearSession(): void {
  clearToken()
  localStorage.removeItem(USER_KEY)
}

export class ApiError extends Error {
  status: number
  errors?: Record<string, string[]>

  constructor(status: number, body: ApiErrorBody) {
    super(body.message)
    this.status = status
    this.errors = body.errors
  }
}

interface RequestOptions {
  method?: 'GET' | 'POST' | 'PUT' | 'PATCH' | 'DELETE'
  body?: unknown
  params?: object
}

function buildUrl(path: string, params?: RequestOptions['params']): string {
  const url = new URL(`${API_URL}${path}`)
  if (params) {
    for (const [key, value] of Object.entries(params)) {
      if (value !== undefined && value !== '') {
        url.searchParams.set(key, String(value))
      }
    }
  }
  return url.toString()
}

export async function apiFetch<T>(path: string, options: RequestOptions = {}): Promise<T> {
  const token = getToken()

  const response = await fetch(buildUrl(path, options.params), {
    method: options.method ?? 'GET',
    headers: {
      Accept: 'application/json',
      ...(options.body ? { 'Content-Type': 'application/json' } : {}),
      ...(token ? { Authorization: `Bearer ${token}` } : {}),
    },
    body: options.body ? JSON.stringify(options.body) : undefined,
  })

  if (response.status === 204) {
    return undefined as T
  }

  const data = await response.json().catch(() => ({ message: 'Unexpected response from server.' }))

  if (!response.ok) {
    if (response.status === 401) {
      clearSession()
      window.location.assign('/login')
    }
    throw new ApiError(response.status, data as ApiErrorBody)
  }

  return data as T
}

export const api = {
  get: <T>(path: string, params?: RequestOptions['params']) => apiFetch<T>(path, { method: 'GET', params }),
  post: <T>(path: string, body?: unknown) => apiFetch<T>(path, { method: 'POST', body }),
  put: <T>(path: string, body?: unknown) => apiFetch<T>(path, { method: 'PUT', body }),
  delete: <T>(path: string) => apiFetch<T>(path, { method: 'DELETE' }),
}
