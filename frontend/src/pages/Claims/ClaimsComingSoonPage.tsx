import { useLocation } from 'react-router-dom'
import ClaimsChrome from './_chrome/ClaimsChrome'

/**
 * Placeholder for legacy Claims Tracker screens not yet ported into Graphite
 * (How It Works, Policy Library, Audit Log, API Access). Keeps the tab strip
 * complete — no dead links — while each screen is replicated in turn. Replace
 * the corresponding route with the real page as it lands.
 */

const TITLES: Record<string, string> = {
  'how-it-works': 'How It Works',
  'policy-library': 'Policy Library',
  'audit-log': 'Audit Log',
  'api-access': 'API Access',
}

export default function ClaimsComingSoonPage() {
  const seg = useLocation().pathname.split('/').pop() ?? ''
  const title = TITLES[seg] ?? 'This screen'

  return (
    <div className="p-6">
      <ClaimsChrome />
      <div className="mx-auto mt-10 max-w-lg rounded-lg border border-line bg-surface px-8 py-10 text-center">
        <div className="text-4xl" aria-hidden>🛠️</div>
        <h2 className="mt-3 font-heading text-lg font-bold text-brand-navy">{title}</h2>
        <p className="mt-2 text-sm text-ink-muted">
          {title} is being migrated from the Claims Tracker into Graphite. It will appear
          here as part of the screen-by-screen rollout — the tab is in place so nothing
          moves once it lands.
        </p>
      </div>
    </div>
  )
}
