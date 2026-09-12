import { useEffect } from 'react'
import { ALPHA_BRIDGE_BOARD_URL } from '../../utils/alphaBridge'

/**
 * Old native helpdesk URLs (/help-desk, /help-desk/sla, /help-desk/:id) —
 * the module was retired 2026-07-14 after the Alpha Bridge cutover (the
 * native pages showed pre-migration snapshots that diverge from Bridge).
 * Bookmarks and stale links land here and are forwarded to the Bridge
 * board, which owns all ticket status/SLA data now. The page components
 * stay on disk (unrouted) in case a rollback is ever needed.
 */
export default function BridgeBoardRedirect() {
  useEffect(() => {
    window.location.replace(ALPHA_BRIDGE_BOARD_URL)
  }, [])

  return (
    <div className="p-12 text-center text-sm text-ink-muted">
      The helpdesk has moved to Alpha Bridge — taking you there…{' '}
      <a href={ALPHA_BRIDGE_BOARD_URL} className="text-brand-navy font-medium underline">Open Alpha Bridge</a>
    </div>
  )
}
