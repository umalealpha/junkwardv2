import apiClient from '../api/client'

/**
 * Alpha Bridge "Report an Issue" widget loader.
 *
 * Ticket CREATION has moved from Graphite's built-in Help Desk submit page
 * to the Alpha Bridge helpdesk (https://bridge.alphadirect.co.bw). Bridge
 * ships an embeddable widget (widget.js, self-contained, Shadow-DOM) whose
 * dialog posts straight to Bridge; it authenticates per-submit with a
 * short-lived JWT fetched through our backend token proxy
 * (POST /api/v1/bridge-widget/token — the shared secret stays server-side).
 *
 * We suppress the widget's own floating button (Graphite already has the
 * ActionRail Help Desk tab) by passing a `trigger` selector nothing renders,
 * and open the dialog programmatically from our existing entry points via
 * openAlphaBridgeWidget().
 *
 * See d:\ADRisk\Alpha-Bridge\docs\widget.md.
 */

export interface AlphaBridgeAssignee {
  email: string
  name?: string
}

interface AlphaBridgeWidgetApi {
  init: (options: {
    apiBase: string
    getToken: () => Promise<string>
    /** CSS selector — suppresses the floating button, opens from matches. */
    trigger?: string
    zIndex?: number
    /**
     * Optional "Assign to" support (widget ≥ the assign-to release; older
     * widget.js safely ignores this option). `load` is called lazily when
     * the dialog first opens; `default` is pre-selected in the field.
     */
    assignees?: {
      load: () => Promise<AlphaBridgeAssignee[]>
      default?: string
    }
  }) => { open: () => void; close: () => void; destroy: () => void }
  open: () => void
  close: () => void
  destroy: () => void
}

declare global {
  interface Window {
    AlphaBridgeWidget?: AlphaBridgeWidgetApi
  }
}

export const ALPHA_BRIDGE_BASE = 'https://bridge.alphadirect.co.bw'

/** Bridge's Kanban board — the primary helpdesk destination post-cutover
    (sidebar nav + archive-page links). Same-tab; Azure SSO carries over. */
export const ALPHA_BRIDGE_BOARD_URL = `${ALPHA_BRIDGE_BASE}/it/board`

let initPromise: Promise<void> | null = null

function loadScript(): Promise<void> {
  if (window.AlphaBridgeWidget) return Promise.resolve()
  return new Promise((resolve, reject) => {
    const script = document.createElement('script')
    script.src = `${ALPHA_BRIDGE_BASE}/widget.js`
    script.async = true
    script.onload = () => resolve()
    script.onerror = () => {
      script.remove()
      reject(new Error('Failed to load the Alpha Bridge widget script'))
    }
    document.head.appendChild(script)
  })
}

/**
 * Load widget.js (once) and init the widget. Safe to call repeatedly —
 * concurrent and repeat calls share one promise. A failed load (Bridge down,
 * offline) resets so the next entry-point click retries instead of being
 * permanently broken.
 */
export function initAlphaBridgeWidget(): Promise<void> {
  if (!initPromise) {
    initPromise = (async () => {
      await loadScript()
      window.AlphaBridgeWidget!.init({
        apiBase: ALPHA_BRIDGE_BASE,
        // Called fresh on every submit — tokens last 15 min, so no caching.
        getToken: async () => (await apiClient.post('/bridge-widget/token')).data.token,
        // No element carries this attribute; it only suppresses the
        // widget's default floating button. We open programmatically.
        trigger: '[data-alpha-bridge-trigger]',
        // "Assign to" autocomplete: active Graphite portal users, with the
        // dev-team mailbox pre-selected (it's a mailbox, not a users row —
        // the widget pins it above the loaded list). Older widget.js
        // ignores this option, so deploy order doesn't matter.
        assignees: {
          load: async (): Promise<AlphaBridgeAssignee[]> =>
            (await apiClient.get('/bridge-widget/assignees')).data.assignees,
          default: 'developers@theriskco.com',
        },
      })
    })().catch((err) => {
      initPromise = null
      throw err
    })
  }
  return initPromise
}

/** Open the report dialog (loading + initing the widget first if needed). */
export async function openAlphaBridgeWidget(): Promise<void> {
  await initAlphaBridgeWidget()
  window.AlphaBridgeWidget!.open()
}
