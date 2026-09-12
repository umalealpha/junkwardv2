import { useEffect, useState } from 'react'
import { useNavigate } from 'react-router-dom'
import ClaimsChrome from './_chrome/ClaimsChrome'
import {
  useClaimsIncentiveEnabled,
  hasIncentiveRole,
  useSupplierSearch,
  useMarkSupplierIncentiveFlags,
} from '../../hooks/useClaimsIncentive'
import { type SupplierLite } from '../../api/claimsIncentive'
import { useClaimsTrackerIncentive } from '../../hooks/useClaimsTrackerIncentive'
import {
  type TrackerIncentiveBucket,
  type TrackerIncentiveHandler,
} from '../../api/claimsTrackerIncentive'
import Card from '../../components/common/Card'
import EmptyState from '../../components/common/EmptyState'
import LoadingSpinner from '../../components/common/LoadingSpinner'

/**
 * Claims → Incentive Report — a 1:1 rebuild of the legacy Claims Tracker
 * "Handler Incentive Report" screen, fed by GET /claims-v2/tracker-incentive.
 * Renders inside Graphite's AdminLayout with the shared <ClaimsChrome /> tab
 * strip on top (like ClaimListPage). Canonical brand + design tokens only (no
 * hardcoded hex — the glass section's teal accent uses status-info-* tokens);
 * emoji labels kept for exact familiarity.
 *
 * The whole feature stays gated behind the runtime `claims_incentive_report`
 * flag + role check (useClaimsIncentiveEnabled + hasIncentiveRole) — the
 * original not-enabled / no-access EmptyStates are preserved. The report is
 * month-scoped and, matching the tracker, shows an empty state until a month
 * is chosen.
 *
 * The approved-supplier admin panel (SupplierFlagsPanel) is retained below the
 * report — it drives the approved lists the report counts against.
 */

// ── Helpers ───────────────────────────────────────────────────────────────

/** "2026-08" → "Aug 2026". */
function fmtMonthLabel(ym: string): string {
  const m = /^(\d{4})-(\d{2})$/.exec(ym)
  if (!m) return ym
  const d = new Date(Number(m[1]), Number(m[2]) - 1, 1)
  if (isNaN(d.getTime())) return ym
  return d.toLocaleDateString('en-GB', { month: 'short', year: 'numeric' })
}

// ── Small building blocks ───────────────────────────────────────────────────

/** One headline stat tile. `accent` = 'navy' | 'info' colours the numeral. */
function StatTile({
  label,
  value,
  accent,
}: {
  label: string
  value: string | number
  accent?: 'navy' | 'info'
}) {
  const numCls =
    accent === 'navy' ? 'text-brand-navy'
    : accent === 'info' ? 'text-status-info-fg'
    : 'text-ink'
  return (
    <div className="bg-surface-2 rounded-lg border border-line p-3.5">
      <div className={`text-2xl font-bold tabular-nums ${numCls}`}>{value}</div>
      <div className="text-[11px] uppercase tracking-wide text-ink-muted mt-0.5">{label}</div>
    </div>
  )
}

/**
 * Approval-rate bar + percentage. Colour breakpoints differ per category:
 *   panel-beater → ≥90 success / ≥70 warning / else danger
 *   glass        → ≥80 success / ≥60 warning / else danger
 */
function ApprovalRateBar({
  pct,
  goodAt,
  warnAt,
}: {
  pct: number
  goodAt: number
  warnAt: number
}) {
  const tone =
    pct >= goodAt ? 'bg-status-success-fg'
    : pct >= warnAt ? 'bg-status-warning-fg'
    : 'bg-status-danger-fg'
  const width = Math.max(0, Math.min(100, pct))
  return (
    <div className="flex items-center gap-2 min-w-[140px]">
      <div className="h-2 flex-1 rounded-full bg-surface-2 overflow-hidden">
        <div className={`h-full rounded-full ${tone}`} style={{ width: `${width}%` }} />
      </div>
      <span className="tabular-nums text-xs font-semibold text-ink w-11 text-right">{pct}%</span>
    </div>
  )
}

/** ✓ QUALIFIES / ✗ Does Not Qualify pill. */
function StatusPill({ qualifies }: { qualifies: boolean }) {
  return qualifies ? (
    <span className="inline-flex items-center rounded-full px-2.5 py-0.5 text-[11px] font-semibold whitespace-nowrap bg-status-success-bg text-status-success-fg">
      ✓ QUALIFIES
    </span>
  ) : (
    <span className="inline-flex items-center rounded-full px-2.5 py-0.5 text-[11px] font-semibold whitespace-nowrap bg-status-danger-bg text-status-danger-fg">
      ✗ Does Not Qualify
    </span>
  )
}

/**
 * A single incentive category (panel-beater / glass). `accent` themes the
 * sub-header + summary card (orange for panel-beater, teal/info for glass).
 */
function CategorySection({
  emoji,
  title,
  criteriaLine,
  claimsLabel,
  approvedLabel,
  bucket,
  goodAt,
  warnAt,
  accent,
  month,
  emptyLabel,
}: {
  emoji: string
  title: string
  criteriaLine: string
  claimsLabel: string
  approvedLabel: string
  bucket: TrackerIncentiveBucket
  goodAt: number
  warnAt: number
  accent: 'orange' | 'info'
  month: string
  /** e.g. "MIS/DOM Motor" / "Glass" — used in the no-data empty state. */
  emptyLabel: string
}) {
  const handlers: TrackerIncentiveHandler[] = bucket.handlers ?? []
  const noData = bucket.totalClaims === 0

  const headerCls =
    accent === 'orange'
      ? 'text-brand-orange'
      : 'text-status-info-fg'
  const summaryCls =
    accent === 'orange'
      ? 'bg-brand-orange/10 border-brand-orange/25'
      : 'bg-status-info-bg border-status-info-fg/25'
  const rateAccent = accent === 'orange' ? 'navy' : 'info'

  return (
    <section className="space-y-3">
      <h2 className={`font-heading text-base font-bold ${headerCls}`}>
        {emoji} {title}
      </h2>

      {/* Summary card: criteria line + 3 stat tiles */}
      <div className={`rounded-[10px] border p-4 ${summaryCls}`}>
        <p className="text-sm font-medium text-ink">{criteriaLine}</p>
        <div className="grid grid-cols-1 sm:grid-cols-3 gap-3 mt-3">
          <StatTile label={claimsLabel} value={bucket.totalClaims} accent={rateAccent} />
          <StatTile label={approvedLabel} value={bucket.approvedCount} accent={rateAccent} />
          <StatTile label="Overall Rate" value={`${bucket.overallPct}%`} accent={rateAccent} />
        </div>
      </div>

      {/* Per-handler table */}
      {noData ? (
        <Card padding="p-0">
          <EmptyState
            compact
            title={`No ${emptyLabel} claims with suppliers found for ${fmtMonthLabel(month)}`}
            description="Once claims in this category have a supplier assigned, handlers will appear here."
          />
        </Card>
      ) : (
        <Card padding="p-0" className="overflow-hidden">
          <div className="overflow-x-auto">
            <table className="w-full min-w-[720px] text-sm border-collapse">
              <thead className="bg-brand-navy text-white uppercase text-[0.68rem] tracking-wide">
                <tr>
                  <th className="px-3 py-2.5 text-left font-semibold">Claims Handler</th>
                  <th className="px-3 py-2.5 text-right font-semibold">{claimsLabel}</th>
                  <th className="px-3 py-2.5 text-right font-semibold">{approvedLabel}</th>
                  <th className="px-3 py-2.5 text-right font-semibold">Non-Approved</th>
                  <th className="px-3 py-2.5 text-left font-semibold">Approval Rate</th>
                  <th className="px-3 py-2.5 text-left font-semibold">Status</th>
                </tr>
              </thead>
              <tbody>
                {handlers.length === 0 ? (
                  <tr>
                    <td colSpan={6} className="px-4 py-10 text-center text-ink-muted">
                      No handlers to show for this month.
                    </td>
                  </tr>
                ) : (
                  handlers.map((h) => (
                    <tr key={h.handler} className="border-b border-line hover:bg-surface-2/60">
                      <td className="px-3 py-2.5 text-ink font-medium">{h.handler || '—'}</td>
                      <td className="px-3 py-2.5 text-right tabular-nums text-ink">{h.total}</td>
                      <td className="px-3 py-2.5 text-right tabular-nums text-status-success-fg font-semibold">
                        {h.approved}
                      </td>
                      <td className="px-3 py-2.5 text-right tabular-nums text-ink-muted">
                        {h.nonApproved > 0 ? (
                          <span className="text-status-danger-fg font-semibold">{h.nonApproved}</span>
                        ) : (
                          <span className="text-ink-faint">0</span>
                        )}
                      </td>
                      <td className="px-3 py-2.5">
                        <ApprovalRateBar pct={h.pct} goodAt={goodAt} warnAt={warnAt} />
                      </td>
                      <td className="px-3 py-2.5">
                        <StatusPill qualifies={h.qualifies} />
                      </td>
                    </tr>
                  ))
                )}
              </tbody>
            </table>
          </div>
        </Card>
      )}
    </section>
  )
}

// ── Approved-supplier flags panel (retained admin extra) ─────────────────────
function SupplierFlagsPanel() {
  const [term, setTerm] = useState('')
  const search = useSupplierSearch(term)
  const mark = useMarkSupplierIncentiveFlags()
  const [busy, setBusy] = useState<string | null>(null) // `${id}:${cat}`
  const [done, setDone] = useState<Record<string, string>>({})

  async function apply(s: SupplierLite, cat: 'panel' | 'glass', approved: boolean) {
    const key = `${s.id}:${cat}`
    setBusy(key)
    try {
      await mark.mutateAsync({
        id: s.id,
        flags: cat === 'panel'
          ? { is_approved_panel_beater: approved }
          : { is_approved_glass_supplier: approved },
      })
      setDone((d) => ({
        ...d,
        [String(s.id)]: `${cat === 'panel' ? 'Panel-beater' : 'Glass'} ${approved ? 'marked approved' : 'approval removed'}`,
      }))
    } catch {
      setDone((d) => ({ ...d, [String(s.id)]: 'Update failed — try again' }))
    } finally {
      setBusy(null)
    }
  }

  return (
    <Card padding="p-5" title="Manage approved suppliers">
      <p className="text-xs text-ink-muted mb-3">
        Mark which suppliers count as approved panel-beaters / glass suppliers. This drives the
        approved counts and approval rates in the report above. Search by name or email.
      </p>
      <input
        value={term}
        onChange={(e) => setTerm(e.target.value)}
        placeholder="Search suppliers…"
        className="w-full max-w-sm px-3 py-2 text-sm rounded-lg border border-line bg-surface text-ink placeholder:text-ink-faint focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary"
      />

      <div className="mt-3">
        {term.trim().length < 2 ? (
          <p className="text-xs text-ink-faint">Type at least 2 characters to search.</p>
        ) : search.isLoading ? (
          <div className="py-6 flex justify-center"><LoadingSpinner /></div>
        ) : search.isError ? (
          <p className="text-sm text-status-danger-fg">Could not search suppliers.</p>
        ) : (search.data?.data.length ?? 0) === 0 ? (
          <EmptyState compact title="No matching suppliers" description="Try a different name or email." />
        ) : (
          <div className="overflow-x-auto">
            <table className="w-full text-sm">
              <thead className="text-xs text-ink-muted uppercase">
                <tr>
                  <th className="text-left py-1.5">Supplier</th>
                  <th className="text-left">Type</th>
                  <th className="text-center">Panel-beater</th>
                  <th className="text-center">Glass</th>
                  <th className="text-right">Status</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-line">
                {search.data!.data.map((s) => (
                  <tr key={s.id}>
                    <td className="py-2 text-ink">{s.name}</td>
                    <td className="text-ink-muted">{s.type ?? '—'}</td>
                    <td className="text-center">
                      <div className="inline-flex gap-1">
                        <button
                          onClick={() => apply(s, 'panel', true)}
                          disabled={busy === `${s.id}:panel`}
                          className="px-2 py-0.5 rounded-md text-[11px] font-medium bg-status-success-bg text-status-success-fg hover:opacity-80 disabled:opacity-40 cursor-pointer"
                        >Approve</button>
                        <button
                          onClick={() => apply(s, 'panel', false)}
                          disabled={busy === `${s.id}:panel`}
                          className="px-2 py-0.5 rounded-md text-[11px] font-medium bg-surface-2 text-ink-muted hover:opacity-80 disabled:opacity-40 cursor-pointer"
                        >Remove</button>
                      </div>
                    </td>
                    <td className="text-center">
                      <div className="inline-flex gap-1">
                        <button
                          onClick={() => apply(s, 'glass', true)}
                          disabled={busy === `${s.id}:glass`}
                          className="px-2 py-0.5 rounded-md text-[11px] font-medium bg-status-success-bg text-status-success-fg hover:opacity-80 disabled:opacity-40 cursor-pointer"
                        >Approve</button>
                        <button
                          onClick={() => apply(s, 'glass', false)}
                          disabled={busy === `${s.id}:glass`}
                          className="px-2 py-0.5 rounded-md text-[11px] font-medium bg-surface-2 text-ink-muted hover:opacity-80 disabled:opacity-40 cursor-pointer"
                        >Remove</button>
                      </div>
                    </td>
                    <td className="text-right text-[11px] text-ink-muted">{done[String(s.id)] ?? ''}</td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}
      </div>
      <p className="text-[11px] text-ink-faint mt-3">
        Note: the suppliers list API does not yet return current approved flags, so this panel sets
        them explicitly rather than showing a live on/off state.
      </p>
    </Card>
  )
}

// ── Page ─────────────────────────────────────────────────────────────────────
export default function ClaimsIncentiveReportPage() {
  const navigate = useNavigate()
  const enabled = useClaimsIncentiveEnabled()
  const hasRole = hasIncentiveRole()

  // Matching the legacy tracker: no month is auto-selected — the report is
  // empty until the user picks one from the dropdown.
  const [month, setMonth] = useState<string>('')

  const canQuery = enabled && hasRole
  const report = useClaimsTrackerIncentive(month || null, canQuery)

  const status = (report.error as any)?.response?.status
  const featureOff = !enabled || status === 403

  const data = report.data?.data
  const availableMonths = data?.availableMonths ?? []

  // If the backend ever returns available months but the current selection is
  // stale (not in the list), fall back to "no selection" so the select stays
  // consistent with its options.
  useEffect(() => {
    if (month && availableMonths.length > 0 && !availableMonths.includes(month)) {
      setMonth('')
    }
  }, [month, availableMonths])

  const monthPicked = !!month

  if (!hasRole) {
    return (
      <div className="p-8">
        <EmptyState
          title="No access"
          description="The claims incentive report is available to Claims Managers and administrators."
          action={<button onClick={() => navigate('/claims')} className="text-sm text-primary underline cursor-pointer">Back to Claims</button>}
        />
      </div>
    )
  }

  if (featureOff) {
    return (
      <div className="p-8">
        <EmptyState
          title="Claims Incentive Report is not enabled"
          description="This module is turned off. An administrator can enable it under Admin → Integrations."
          action={<button onClick={() => navigate('/claims')} className="text-sm text-primary underline cursor-pointer">Back to Claims</button>}
        />
      </div>
    )
  }

  return (
    <div>
      <ClaimsChrome onRefresh={() => report.refetch()} refreshing={report.isFetching} />

      {/* Header + month filter */}
      <div className="flex items-end justify-between flex-wrap gap-3 mb-5">
        <div>
          <h1 className="font-heading text-xl font-extrabold text-brand-navy">🏆 Handler Incentive Report</h1>
          <p className="text-sm text-ink-muted mt-0.5">
            % of MIS/DOM motor claims routed to approved panel-beaters and glass claims to approved
            glass suppliers, by claims handler.
          </p>
        </div>
        <label className="text-xs text-ink-muted">
          <span className="block mb-1 font-medium uppercase tracking-wide">📅 Select Month</span>
          <select
            value={month}
            onChange={(e) => setMonth(e.target.value)}
            className="px-3 py-2 text-sm rounded-lg border border-line bg-surface text-ink cursor-pointer focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary min-w-[180px]"
            title="Select month"
          >
            <option value="">Select Month…</option>
            {availableMonths.map((m) => (
              <option key={m} value={m}>{fmtMonthLabel(m)}</option>
            ))}
          </select>
        </label>
      </div>

      {report.isLoading ? (
        <div className="p-12 flex justify-center"><LoadingSpinner size="lg" /></div>
      ) : report.isError ? (
        <div className="p-8 text-center">
          <p className="text-status-danger-fg font-medium">Could not load the incentive report.</p>
          <button onClick={() => report.refetch()} className="mt-4 text-sm text-primary underline cursor-pointer">Try again</button>
        </div>
      ) : !monthPicked ? (
        <Card padding="p-0">
          <EmptyState
            title="Select a month to view the incentive report"
            description="Pick a month from the dropdown above to see per-handler approval rates for panel-beaters and glass suppliers."
          />
        </Card>
      ) : data ? (
        <div className="space-y-8">
          {/* Panel-beater section */}
          <CategorySection
            emoji="🔧"
            title="Panel Beater Incentive (MIS/DOM Motor Claims)"
            criteriaLine={`≥ ${data.panelBeater.threshold}% of MIS/DOM Motor claims must use Approved Panel Beaters`}
            claimsLabel={data.panelBeater.label || 'MIS/DOM Motor Claims'}
            approvedLabel="Approved Panel"
            bucket={data.panelBeater}
            goodAt={data.panelBeater.threshold}
            warnAt={70}
            accent="orange"
            month={month}
            emptyLabel="MIS/DOM Motor"
          />

          {/* Glass section */}
          <CategorySection
            emoji="🪟"
            title="Glass Supplier Incentive (Glass Claims)"
            criteriaLine={`≥ ${data.glass.threshold}% of Glass claims must use Approved Glass Suppliers`}
            claimsLabel={data.glass.label || 'Glass Claims'}
            approvedLabel="Approved Supplier"
            bucket={data.glass}
            goodAt={data.glass.threshold}
            warnAt={60}
            accent="info"
            month={month}
            emptyLabel="Glass"
          />

          {/* Footer note */}
          <Card padding="p-4">
            <ul className="text-xs text-ink-muted space-y-1.5">
              <li>
                <span className="font-semibold text-ink">Panel Beater Incentive:</span> at least{' '}
                {data.panelBeater.threshold}% of MIS/DOM Motor claims must use Approved Panel Beaters for
                a handler to qualify.
              </li>
              <li>
                <span className="font-semibold text-ink">Glass Supplier Incentive:</span> at least{' '}
                {data.glass.threshold}% of Glass claims must use Approved Glass Suppliers for a handler to
                qualify.
              </li>
              <li className="text-ink-faint">Only claims with a supplier assigned are counted.</li>
            </ul>
          </Card>

          {/* Admin extra — drives the approved lists behind the report */}
          <SupplierFlagsPanel />
        </div>
      ) : null}
    </div>
  )
}
