/**
 * Placeholder for features that are not yet wired up. Renders an
 * informative card instead of a blank page / infinite spinner / silent
 * redirect — so UAT testers (Manus AI, Claude AI, and human reviewers
 * Prathap CFO / Arjun COO) get a clear answer about what's happening
 * rather than reporting "blank page" as a defect.
 *
 * Use the trackingId so reports can reference a specific module without
 * ambiguity ("V2-FEAT-RECONCILIATION" is unmissable in a defect log).
 *
 * Once a feature actually lands, swap this for the real component in
 * App.tsx — no changes needed here.
 */

interface ComingSoonPageProps {
  /** Human-readable module name shown as the page heading */
  moduleName: string
  /** Stable identifier for defect tracking, e.g. "V2-FEAT-RECONCILIATION" */
  trackingId: string
  /** One-line explanation of why the page is not live yet */
  description?: string
  /** Optional indicative timeline (e.g. "Targeted for the 15 June drop") */
  expectedTimeline?: string
}

export default function ComingSoonPage({
  moduleName,
  trackingId,
  description = 'This module is being migrated from the legacy admin panel and is not yet wired into the V2 release.',
  expectedTimeline,
}: ComingSoonPageProps) {
  return (
    <div className="max-w-2xl mx-auto my-16 px-4">
      <div className="bg-surface border border-line rounded-xl shadow-sm overflow-hidden">
        <div className="px-6 py-5" style={{ background: '#0B1272' }}>
          <div className="text-[11px] font-semibold tracking-widest uppercase opacity-70" style={{ color: '#FF6600' }}>
            Coming Soon
          </div>
          <h1 className="text-white text-2xl font-semibold mt-1">{moduleName}</h1>
        </div>
        <div className="px-6 py-6 text-ink-muted">
          <p className="text-sm leading-relaxed mb-4">{description}</p>
          {expectedTimeline ? (
            <p className="text-sm mb-4">
              <span className="font-semibold text-ink">Expected:</span> {expectedTimeline}
            </p>
          ) : null}
          <div className="mt-4 pt-4 border-t border-line">
            <div className="grid grid-cols-1 md:grid-cols-2 gap-3 text-sm">
              <div>
                <div className="text-xs text-ink-muted uppercase tracking-wide font-semibold mb-1">
                  Tracking ID
                </div>
                <code className="text-[13px] bg-surface-2 px-2 py-1 rounded text-ink">
                  {trackingId}
                </code>
              </div>
              <div>
                <div className="text-xs text-ink-muted uppercase tracking-wide font-semibold mb-1">
                  Questions / urgent need
                </div>
                <a
                  href="mailto:developers@theriskco.com?subject=Graphite V2 — Re: {trackingId}"
                  className="text-[13px] text-blue-700 hover:underline"
                >
                  developers@theriskco.com
                </a>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  )
}
