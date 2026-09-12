/**
 * Fire-and-forget bridge to the backend's data-load error reporter
 * (POST /api/v1/error-report). When a fetch / React Query call fails or
 * the ErrorBoundary catches a render exception, we ping the backend so
 * the team gets an email at developers@theriskco.com tagged
 * [GRAPHITE-V2-DATA-ERR].
 *
 * Important properties:
 *   - DOES NOT throw. Failure to report must not break the calling code.
 *   - DOES NOT recurse. We deliberately use `fetch` rather than our
 *     authenticated apiClient — the reporter itself failing must not
 *     trigger more reports.
 *   - Backend handles throttling (10/min per IP) so we don't need to
 *     debounce or de-duplicate on the frontend.
 *   - Disabled in development so noisy local errors don't reach prod
 *     inboxes. Set via the Vite mode check.
 */

interface ErrorReportPayload {
  error: string
  stack?: string
  route?: string
  context?: string
  userEmail?: string
}

const ENDPOINT = '/api/v1/error-report'

export function reportErrorToTeam(payload: ErrorReportPayload): void {
  // Skip in development — local hot-reload errors should not email prod
  if (import.meta.env.MODE !== 'production') {
    // eslint-disable-next-line no-console
    console.warn('[reportErrorToTeam] skipped in non-production mode', payload)
    return
  }

  const body = {
    error: truncate(payload.error, 2000),
    stack: payload.stack ? truncate(payload.stack, 8000) : undefined,
    route: payload.route ?? (typeof window !== 'undefined' ? window.location.pathname + window.location.search : undefined),
    context: payload.context ? truncate(payload.context, 200) : undefined,
    user_email: payload.userEmail,
    browser: typeof navigator !== 'undefined' ? truncate(navigator.userAgent, 300) : undefined,
    occurred_at: new Date().toISOString(),
  }

  // Use fetch directly rather than apiClient so a failure inside
  // apiClient doesn't try to report itself in a loop.
  try {
    fetch(ENDPOINT, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
      body: JSON.stringify(body),
      // keepalive lets the request survive a page unload — useful for
      // errors that happen during navigation away.
      keepalive: true,
    }).catch(() => {
      // Swallow — no one to report a failed report to.
    })
  } catch {
    // Same — swallow.
  }
}

function truncate(s: string, max: number): string {
  return s.length > max ? s.slice(0, max) : s
}
