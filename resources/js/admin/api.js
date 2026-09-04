import { auth } from './stores/auth'

const BASE = '/api/v1'

/**
 * One place where every admin request is shaped, so the token, the JSON
 * headers and the error handling cannot drift between callers.
 *
 * A 401 clears the session rather than silently retrying: the refresh window
 * is generous, but an admin whose token has truly lapsed should be sent back
 * to the login screen instead of watching pages fail one by one.
 */
async function request(path, { method = 'GET', body, params } = {}) {
  const url = new URL(BASE + path, window.location.origin)

  if (params) {
    Object.entries(params).forEach(([key, value]) => {
      if (value !== '' && value !== null && value !== undefined) {
        url.searchParams.set(key, value)
      }
    })
  }

  const response = await fetch(url, {
    method,
    headers: {
      Accept: 'application/json',
      'Content-Type': 'application/json',
      ...(auth.token ? { Authorization: `Bearer ${auth.token}` } : {}),
    },
    body: body ? JSON.stringify(body) : undefined,
  })

  if (response.status === 204) return null

  let payload = null
  try {
    payload = await response.json()
  } catch {
    payload = null
  }

  if (response.ok) return payload

  if (response.status === 401) auth.clear()

  const error = new Error(payload?.message || `Request failed (${response.status})`)
  error.status = response.status
  // Laravel returns { errors: { field: [messages] } } on 422.
  error.errors = payload?.errors || null
  throw error
}

export const api = {
  login: (email, password) =>
    request('/auth/login', { method: 'POST', body: { email, password } }),

  me: () => request('/auth/me'),

  logout: () => request('/auth/logout', { method: 'POST' }),

  terminals: (params) => request('/admin/terminals', { params }),

  terminal: (id) => request(`/admin/terminals/${id}`),

  updateTerminal: (id, body) =>
    request(`/admin/terminals/${id}`, { method: 'PATCH', body }),

  transactions: (params) => request('/admin/transactions', { params }),

  summary: (params) => request('/admin/transactions/summary', { params }),
}
