import { Component, type ReactNode, type ErrorInfo } from 'react'
import { reportErrorToTeam } from '../../utils/reportError'

interface Props {
  children: ReactNode
  /** Optional label shown in the fallback header (e.g. the route path) */
  scope?: string
}

interface State {
  error: Error | null
  info: ErrorInfo | null
}

// Matches Vite / browser variants for the post-deploy stale-chunk failure:
//   Chrome:  "Failed to fetch dynamically imported module: …PolicyCreatePage-<hash>.js"
//   Firefox: "error loading dynamically imported module"
//   Safari:  "Importing a module script failed"
//   Webpack: "ChunkLoadError"
// After a frontend deploy, the browser's cached index.html still references
// chunks with pre-deploy content hashes that no longer exist on the server,
// so React-Router's lazy import() 404s. Auto-reloading fetches a fresh
// index.html which points at the new hashes — fixing the session in one hop.
const STALE_CHUNK_RE = /dynamically imported module|importing a module script|chunkloaderror|failed to fetch/i

// Time-window debounce for the auto-reload. If the previous auto-reload
// happened within this window, we skip the auto-reload and show the UI
// instead — that's the user's signal that a manual recovery is needed.
// 60s is long enough to catch a true loop (chunk really is broken) but
// short enough that a different deploy a minute later can still self-heal.
const RELOAD_KEY = 'eb:stale-chunk-reloaded-at'
const RELOAD_DEBOUNCE_MS = 60_000

/**
 * Last-resort error boundary. Keeps a buggy leaf from blanking the
 * whole page — the tester reported /policies/create rendering empty
 * with zero console output, which is exactly what React does when a
 * render throws and there's no boundary to catch it (the offending
 * subtree is unmounted silently).
 *
 * Wrapping the route element with <ErrorBoundary> turns that silent
 * blank into a visible message + the error text + a stack trace,
 * plus recovery buttons so operators aren't stranded.
 *
 * Stale-chunk recovery:
 *   - First chunk error → auto-reload, record timestamp in sessionStorage.
 *   - Subsequent chunk errors within RELOAD_DEBOUNCE_MS → show UI with
 *     "Recover Session" button that hard-clears storage and reloads.
 *   - Errors after the debounce window → auto-reload again. Lets a
 *     later deploy self-heal without operator intervention.
 *
 * UAT 2026-05-26: the old one-shot RELOAD_KEY flag persisted across
 * the whole browser session, so any chunk error after the first one
 * stranded operators on a blank page with no path back. The time-window
 * debounce + Recover Session button is the fix.
 */
export default class ErrorBoundary extends Component<Props, State> {
  state: State = { error: null, info: null }

  static getDerivedStateFromError(error: Error): State {
    return { error, info: null }
  }

  componentDidCatch(error: Error, info: ErrorInfo) {
    // eslint-disable-next-line no-console
    console.error('[ErrorBoundary]', this.props.scope ?? '', error, info)

    // Notify the dev team — silent render exceptions are exactly what
    // we want surfaced to developers@theriskco.com. Stale-chunk errors
    // get reported too so we can see deploy-cache hit rates.
    reportErrorToTeam({
      error: error.message ?? String(error),
      stack: error.stack,
      context: `error-boundary:${this.props.scope ?? 'root'}`,
    })

    if (STALE_CHUNK_RE.test(error?.message ?? '')) {
      const now = Date.now()
      let lastReloadAt = 0
      try {
        const raw = sessionStorage.getItem(RELOAD_KEY)
        if (raw) lastReloadAt = parseInt(raw, 10) || 0
      } catch {
        // sessionStorage blocked (private mode, embedded contexts) — treat
        // as never-reloaded so we attempt the auto-recovery once.
      }

      if (now - lastReloadAt > RELOAD_DEBOUNCE_MS) {
        try {
          sessionStorage.setItem(RELOAD_KEY, String(now))
        } catch {
          // Ignore; the worst case is a second auto-reload, which is bounded.
        }
        window.location.reload()
        return
      }
    }

    this.setState({ info })
  }

  /** Wipe the auto-reload debounce so the next chunk error tries to self-heal again. */
  private clearReloadFlag(): void {
    try {
      sessionStorage.removeItem(RELOAD_KEY)
    } catch {
      // ignore
    }
  }

  /** Soft reload — refreshes the page but keeps storage intact. */
  private handleReload = (): void => {
    this.clearReloadFlag()
    window.location.reload()
  }

  /** Hard recovery — clears every persistent client-side store and navigates home. */
  private handleRecoverSession = (): void => {
    try {
      sessionStorage.clear()
    } catch {
      // ignore
    }
    try {
      // Clear chunk-related localStorage entries, but preserve auth-ish ones
      // so the operator doesn't have to log back in unless absolutely needed.
      const drop: string[] = []
      for (let i = 0; i < localStorage.length; i++) {
        const key = localStorage.key(i)
        if (key && /chunk|vite|asset|manifest/i.test(key)) drop.push(key)
      }
      drop.forEach((k) => localStorage.removeItem(k))
    } catch {
      // ignore
    }
    // Hard navigate to root with a cache-buster so the browser fetches a
    // fresh index.html and the new chunk URLs along with it.
    window.location.href = `/?_recovered=${Date.now()}`
  }

  render() {
    if (!this.state.error) return this.props.children

    const isStaleChunk = STALE_CHUNK_RE.test(this.state.error?.message ?? '')

    return (
      <div className="max-w-3xl mx-auto my-12 p-6 bg-red-50 border border-red-200 rounded-xl">
        <h1 className="text-lg font-bold text-red-800 mb-2">
          Something went wrong{this.props.scope ? <> on <code>{this.props.scope}</code></> : null}
        </h1>
        {isStaleChunk ? (
          <p className="text-sm text-red-700 mb-4">
            This usually means the application was updated while your browser
            still had an older version cached. The fastest fix is
            <b> Recover Session</b> — it clears cached files and reloads with the
            latest version. You will stay logged in.
          </p>
        ) : (
          <p className="text-sm text-red-700 mb-4">
            This page threw an exception during render. The stack trace below tells
            us what to fix — please copy it into a ticket or share with the dev
            team along with the URL.
          </p>
        )}
        <pre className="text-xs bg-white border border-red-200 rounded p-3 overflow-x-auto whitespace-pre-wrap text-red-900">
{String(this.state.error?.stack ?? this.state.error?.message ?? this.state.error)}
        </pre>
        {this.state.info?.componentStack && (
          <details className="mt-3">
            <summary className="text-xs text-red-700 cursor-pointer">Component stack</summary>
            <pre className="text-[11px] mt-1 text-red-900 whitespace-pre-wrap">{this.state.info.componentStack}</pre>
          </details>
        )}
        <div className="flex flex-wrap gap-2 mt-4">
          <button
            onClick={this.handleRecoverSession}
            className="px-3 py-1.5 text-sm bg-red-600 text-white rounded hover:bg-red-700"
          >
            Recover Session
          </button>
          <button
            onClick={this.handleReload}
            className="px-3 py-1.5 text-sm border border-red-300 text-red-700 rounded hover:bg-red-100"
          >
            Reload page
          </button>
          <button
            onClick={() => this.setState({ error: null, info: null })}
            className="px-3 py-1.5 text-sm border border-red-300 text-red-700 rounded hover:bg-red-100"
          >
            Dismiss
          </button>
        </div>
      </div>
    )
  }
}
