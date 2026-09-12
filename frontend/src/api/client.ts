import axios, { AxiosError, AxiosRequestConfig } from 'axios'

const apiClient = axios.create({
  baseURL: `${import.meta.env.VITE_API_URL}/api/v1`,
  headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
  // 30s default timeout so backend hangs surface as errors rather than
  // indefinite loading spinners. UAT 2026-05-26 (Prathap BUG-019, BUG-020):
  // several pages were stuck "Loading..." forever because the underlying
  // fetch never resolved or rejected — the dashboard's New Policies chart,
  // Reconciliation, Audit Trail, Cron Portal, etc. With a timeout, the
  // promise rejects, the page can render an error state, and the new
  // data-load error reporter (utils/reportError) notifies the dev team.
  timeout: 30_000,
})

// Attach Sanctum token from localStorage on every request
apiClient.interceptors.request.use((config) => {
  const token = localStorage.getItem('sanctum_token')
  if (token) {
    config.headers.Authorization = `Bearer ${token}`
  }
  return config
})

/**
 * 401 handling policy (v5 — session probe):
 *
 * When a 401 comes back we have two scenarios:
 *   (a) the session is genuinely dead (token expired / rejected by Sanctum)
 *       → log out and redirect so the user can sign back in.
 *   (b) the session is fine but this particular endpoint 401'd (permission
 *       edge case, backend blip, CORS) → reject the error but keep session.
 *
 * To tell them apart we probe `/auth/user`. If it returns 200, session is
 * healthy and we just reject the original error. If it also 401s, the
 * session is dead and we clear storage + redirect.
 *
 *   1. 401 on /auth/login or /auth/sso  → reject (normal credential error)
 *   2. 401 on /auth/user                → session dead → log out + redirect
 *   3. 401 on anything else             → silent retry once, then probe
 *                                         /auth/user. Act on outcome.
 */

function isLoginEndpoint(url: string): boolean {
  return url.includes('/auth/login') || url.includes('/auth/sso')
}

function isAuthProbe(url: string): boolean {
  return url.includes('/auth/user') || url.includes('/auth/me')
}

function killSessionAndRedirect() {
  if (window.location.pathname === '/login') return
  const returnTo = window.location.pathname + window.location.search
  sessionStorage.setItem('return_to', returnTo)
  localStorage.removeItem('sanctum_token')
  localStorage.removeItem('user')
  localStorage.removeItem('user_permissions')
  localStorage.removeItem('user_roles')
  window.location.href = '/login'
}

// Probe is cached briefly to avoid hammering /auth/user when multiple calls
// 401 in parallel (e.g. claim detail page fires several requests at once).
let probeInFlight: Promise<boolean> | null = null
let lastProbeResult: { ok: boolean; at: number } | null = null
const PROBE_CACHE_MS = 5000

async function sessionIsAlive(): Promise<boolean> {
  const now = Date.now()
  if (lastProbeResult && now - lastProbeResult.at < PROBE_CACHE_MS) {
    return lastProbeResult.ok
  }
  if (probeInFlight) return probeInFlight
  probeInFlight = (async () => {
    try {
      const token = localStorage.getItem('sanctum_token')
      if (!token) return false
      // Use raw fetch to avoid re-entering the interceptor.
      // Double-probe: a single /auth/user 401 was bouncing users during
      // rolling deploys / transient DB blips on the token-lookup path,
      // producing the "logged out after 10 minutes" complaint. Retry
      // once after a short delay; only both failing counts as a dead
      // session.
      const base = `${import.meta.env.VITE_API_URL}/api/v1`
      const ask = () => fetch(`${base}/auth/user`, {
        headers: { Authorization: `Bearer ${token}`, Accept: 'application/json' },
      })
      let r = await ask()
      if (r.status === 401) {
        await new Promise(res => setTimeout(res, 700))
        r = await ask()
      }
      const ok = r.status < 400
      lastProbeResult = { ok, at: Date.now() }
      return ok
    } catch {
      // Network failure — assume session still valid, don't log out
      lastProbeResult = { ok: true, at: Date.now() }
      return true
    } finally {
      probeInFlight = null
    }
  })()
  return probeInFlight
}

apiClient.interceptors.response.use(
  (response) => response,
  async (error: AxiosError) => {
    const config = error.config as AxiosRequestConfig & { _retryCount?: number }
    const url = config?.url || ''

    if (error.response?.status !== 401 || isLoginEndpoint(url)) {
      return Promise.reject(error)
    }

    // /auth/user itself 401'd → session definitively dead
    if (isAuthProbe(url)) {
      killSessionAndRedirect()
      return Promise.reject(error)
    }

    // One silent retry with a freshly-read token
    config._retryCount = config._retryCount ?? 0
    if (config._retryCount < 1) {
      config._retryCount += 1
      const token = localStorage.getItem('sanctum_token')
      if (token && config.headers) {
        (config.headers as any).Authorization = `Bearer ${token}`
      }
      await new Promise(r => setTimeout(r, 300))
      return apiClient.request(config)
    }

    // Still 401 after retry — probe the session
    const alive = await sessionIsAlive()
    if (!alive) {
      killSessionAndRedirect()
    }
    return Promise.reject(error)
  }
)

export default apiClient
