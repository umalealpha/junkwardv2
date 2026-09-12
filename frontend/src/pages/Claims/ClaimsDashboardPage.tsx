import { useState, type ReactNode } from 'react'
import { useNavigate } from 'react-router-dom'
import { useCanSeeClaimsDashboard } from '../../hooks/useClaimsDashboard'
import { useClaimsTrackerDashboard } from '../../hooks/useClaimsTrackerDashboard'
import type {
  TrackerPipelineStage,
  TrackerSlaBreach,
  TrackerOverdueClaim,
  TrackerLeaderboardRow,
} from '../../api/claimsTrackerDashboard'
import ClaimsChrome from './_chrome/ClaimsChrome'
import EmptyState from '../../components/common/EmptyState'
import StatusBadge from '../../components/common/StatusBadge'
import { Skeleton } from '../../components/common/Skeleton'

// ── Money / number formatting ─────────────────────────────────────────────────
// "P"-prefixed money with en-BW grouping + 2 decimals (matches the tracker,
// e.g. "P 24,106,530.62").
function bwp(
  n: number,
  opts: Intl.NumberFormatOptions = { minimumFractionDigits: 2, maximumFractionDigits: 2 },
): string {
  const v = Number.isFinite(n) ? n : 0
  return 'P ' + v.toLocaleString('en-BW', opts)
}
// Grouped number, no currency prefix (used under "(P)" money columns + counts).
function num(n: number, opts: Intl.NumberFormatOptions = { maximumFractionDigits: 0 }): string {
  const v = Number.isFinite(n) ? n : 0
  return v.toLocaleString('en-BW', opts)
}

// "YYYY-MM" → "Aug 2026".
const MONTH_ABBR = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec']
function formatMonth(ym: string): string {
  const [y, m] = ym.split('-')
  const idx = Number(m) - 1
  return `${MONTH_ABBR[idx] ?? m} ${y}`
}
// Deadline "YYYY-MM-DD" → "06 Aug 2026" (falls back to the raw string).
function fmtDate(s: string | null | undefined): string {
  if (!s) return '—'
  const d = new Date(s)
  if (Number.isNaN(d.getTime())) return s
  return d.toLocaleDateString('en-BW', { day: '2-digit', month: 'short', year: 'numeric' })
}

// ── KPI card ────────────────────────────────────────────────────────────────
type KpiTone = 'navy' | 'red' | 'amber' | 'green' | 'orange' | 'info'

const KPI_BAR: Record<KpiTone, string> = {
  navy: 'bg-brand-navy',
  red: 'bg-status-danger-fg',
  amber: 'bg-status-warning-fg',
  green: 'bg-status-success-fg',
  orange: 'bg-brand-orange',
  info: 'bg-status-info-fg',
}
const KPI_TEXT: Record<KpiTone, string> = {
  navy: 'text-brand-navy',
  red: 'text-status-danger-fg',
  amber: 'text-status-warning-fg',
  green: 'text-status-success-fg',
  orange: 'text-brand-orange',
  info: 'text-status-info-fg',
}

function KpiCard({
  label,
  value,
  sub,
  tone = 'navy',
  onClick,
  alert,
}: {
  label: string
  value: ReactNode
  sub?: string
  tone?: KpiTone
  onClick?: () => void
  /** pulsing red dot in the corner (e.g. overdue > 0). */
  alert?: boolean
}) {
  const clickable = !!onClick
  const body = (
    <>
      <div className={`h-1 w-full ${KPI_BAR[tone]}`} />
      <div className="p-4">
        <div className="flex items-center gap-1.5">
          <span className="text-[11px] font-semibold uppercase tracking-wide text-ink-muted">{label}</span>
          {alert && <span className="inline-block h-2 w-2 rounded-full bg-status-danger-fg animate-pulse" aria-hidden />}
        </div>
        <div className={`mt-1 font-heading text-2xl font-extrabold tabular-nums ${KPI_TEXT[tone]}`}>{value}</div>
        {sub && <div className="mt-0.5 text-[11px] text-ink-faint">{sub}</div>}
      </div>
    </>
  )
  const base = 'relative overflow-hidden rounded-lg border border-line bg-surface text-left shadow-elev-sm'
  if (clickable) {
    return (
      <button
        type="button"
        onClick={onClick}
        className={`${base} min-h-[44px] transition-shadow hover:border-primary/40 hover:shadow-elev-md focus:outline-none focus:ring-2 focus:ring-primary/40 cursor-pointer`}
      >
        {body}
      </button>
    )
  }
  return <div className={base}>{body}</div>
}

// ── Generic panel + section header ────────────────────────────────────────────
function Panel({ children, className = '' }: { children: ReactNode; className?: string }) {
  return <section className={`rounded-lg border border-line bg-surface shadow-elev-sm ${className}`}>{children}</section>
}

function PanelHead({ title, note, right }: { title: ReactNode; note?: string; right?: ReactNode }) {
  return (
    <div className="flex flex-wrap items-center justify-between gap-2 border-b border-line px-5 py-3">
      <div>
        <h2 className="font-heading text-sm font-bold uppercase tracking-wide text-brand-navy">{title}</h2>
        {note && <p className="mt-0.5 text-[11px] text-ink-faint">{note}</p>}
      </div>
      {right}
    </div>
  )
}

// ── Registration tile ─────────────────────────────────────────────────────────
function RegTile({
  emoji,
  label,
  value,
  tone,
  sub,
  onClick,
}: {
  emoji: string
  label: string
  value: number
  tone: KpiTone
  sub?: string
  onClick?: () => void
}) {
  const inner = (
    <div className="flex items-center gap-3 p-4">
      <span className="text-2xl" aria-hidden>{emoji}</span>
      <div>
        <div className={`font-heading text-2xl font-extrabold tabular-nums ${KPI_TEXT[tone]}`}>{num(value)}</div>
        <div className="text-[11px] font-semibold uppercase tracking-wide text-ink-muted">{label}</div>
        {sub && <div className="mt-0.5 text-[11px] text-ink-faint">{sub}</div>}
      </div>
    </div>
  )
  const base = 'relative flex-1 overflow-hidden rounded-lg border border-line bg-surface shadow-elev-sm'
  const bar = <div className={`h-1 w-full ${KPI_BAR[tone]}`} />
  if (onClick) {
    return (
      <button
        type="button"
        onClick={onClick}
        className={`${base} min-h-[44px] text-left transition-shadow hover:border-primary/40 hover:shadow-elev-md focus:outline-none focus:ring-2 focus:ring-primary/40 cursor-pointer`}
      >
        {bar}
        {inner}
      </button>
    )
  }
  return (
    <div className={base}>
      {bar}
      {inner}
    </div>
  )
}

// ── Pipeline stage step (Motor / Glass) ─────────────────────────────────────────
function StageStep({ stage }: { stage: TrackerPipelineStage }) {
  const active = stage.count > 0
  return (
    <div
      className={`min-w-[115px] shrink-0 rounded-lg border p-3 ${
        active ? 'border-brand-orange/40 bg-brand-orange/5' : 'border-line bg-surface-2/50'
      }`}
    >
      <div className="truncate text-[11px] font-semibold uppercase tracking-wide text-ink-muted" title={stage.stage}>
        {stage.stage}
      </div>
      <div className={`mt-1 font-heading text-2xl font-extrabold tabular-nums ${active ? 'text-brand-navy' : 'text-ink-faint'}`}>
        {num(stage.count)}
      </div>
      <div className="mt-1.5 space-y-0.5 text-[11px] tabular-nums">
        <div className="text-status-success-fg">✓ {stage.onTime === null ? '—' : num(stage.onTime)} on time</div>
        <div className="text-status-danger-fg">⚠ {stage.delayed === null ? '—' : num(stage.delayed)} delayed</div>
        <div className="text-ink-muted">{stage.pct === null ? '—' : `${stage.pct}%`}</div>
      </div>
    </div>
  )
}

// ── SLA breach row ──────────────────────────────────────────────────────────────
function BreachRow({ b, onOpen }: { b: TrackerSlaBreach; onOpen: () => void }) {
  const severe = b.daysLate >= 3
  const catCls = b.category === 'M' ? 'bg-brand-navy text-white' : 'bg-brand-navy-light text-white'
  return (
    <li className="flex flex-wrap items-center gap-2 border-b border-line py-2 last:border-0">
      <span className={`rounded px-1.5 py-0.5 text-[10px] font-bold ${catCls}`}>{b.category}</span>
      <button type="button" onClick={onOpen} className="font-heading text-sm font-bold text-brand-navy hover:underline cursor-pointer">
        {b.claimNumber}
      </button>
      <span className="text-[13px] text-ink-muted">— {b.clientName} · {b.stageInfo}</span>
      <span
        className={`ml-auto rounded-full px-2 py-0.5 text-[11px] font-semibold tabular-nums ${
          severe ? 'bg-status-danger-bg text-status-danger-fg' : 'bg-status-warning-bg text-status-warning-fg'
        }`}
      >
        {num(b.daysLate)} working days late
      </span>
    </li>
  )
}

// ── Overdue-claims row ────────────────────────────────────────────────────────
function OverdueRow({ c, onOpen }: { c: TrackerOverdueClaim; onOpen: () => void }) {
  return (
    <tr className="border-b border-line last:border-0 hover:bg-surface-2/60">
      {/* Claim # + chips */}
      <td className="whitespace-nowrap px-3 py-2">
        <div className="flex flex-wrap items-center gap-1">
          {c.category === 'NM' && (
            <span className="rounded px-1 py-0.5 text-[9px] font-bold text-white bg-brand-navy-light">NM</span>
          )}
          {c.isMajor && (
            <span className="rounded px-1 py-0.5 text-[9px] font-bold bg-status-danger-bg text-status-danger-fg">🚨 MAJOR</span>
          )}
          {c.isFac && (
            <span className="rounded px-1 py-0.5 text-[9px] font-bold text-white bg-brand-navy">📋 FAC</span>
          )}
          <button type="button" onClick={onOpen} className="font-heading text-sm font-bold text-brand-navy hover:underline cursor-pointer">
            {c.claimNumber}
          </button>
        </div>
      </td>
      <td className="px-3 py-2 text-ink">{c.client || '—'}</td>
      <td className="px-3 py-2 text-ink-muted">{c.handler || '—'}</td>
      {/* Stage pill + stage chips */}
      <td className="px-3 py-2">
        <div className="flex flex-wrap items-center gap-1">
          <span className="rounded-full bg-brand-orange/15 px-2 py-0.5 text-[11px] font-semibold text-brand-orange">{c.stage || '—'}</span>
          {c.stageChips.map((chip, i) => (
            <span
              key={`${chip.abbr}-${i}`}
              className={`rounded px-1 py-0.5 text-[10px] font-semibold tabular-nums ${
                chip.onTime ? 'bg-status-success-bg text-status-success-fg' : 'bg-status-danger-bg text-status-danger-fg'
              }`}
              title={chip.onTime ? 'On time' : 'Late'}
            >
              {chip.onTime ? '✓' : '✗'}{chip.abbr}
            </span>
          ))}
        </div>
      </td>
      <td className="px-3 py-2"><StatusBadge status={c.status} /></td>
      <td className={`px-3 py-2 text-right tabular-nums ${c.daysLate > 0 ? 'font-semibold text-status-danger-fg' : 'text-ink'}`}>
        {num(c.daysLate)}
      </td>
      <td className="whitespace-nowrap px-3 py-2 text-right tabular-nums text-ink-muted">{fmtDate(c.deadline)}</td>
      <td className="whitespace-nowrap px-3 py-2 no-print">
        <div className="flex items-center justify-end gap-1.5">
          <button type="button" onClick={onOpen} className="min-h-[32px] rounded border border-line bg-surface-2 px-2.5 py-1 text-[11px] font-semibold text-ink hover:bg-line/40 cursor-pointer">
            View
          </button>
          <button type="button" onClick={onOpen} className="min-h-[32px] rounded border border-brand-navy bg-brand-navy px-2.5 py-1 text-[11px] font-semibold text-white hover:bg-brand-navy/90 cursor-pointer">
            Edit
          </button>
        </div>
      </td>
    </tr>
  )
}

// ── Leaderboard row ────────────────────────────────────────────────────────────
const RANK_MEDAL = ['🥇', '🥈', '🥉']

function otRateTone(pct: number): string {
  if (pct >= 80) return 'text-status-success-fg'
  if (pct >= 60) return 'text-status-warning-fg'
  return 'text-status-danger-fg'
}
function otBarColor(pct: number): string {
  if (pct >= 80) return 'rgb(var(--success-fg))'
  if (pct >= 60) return 'rgb(var(--warning-fg))'
  return 'rgb(var(--danger-fg))'
}
function scorePill(score: number): string {
  if (score >= 80) return 'bg-status-success-bg text-status-success-fg'
  if (score >= 60) return 'bg-status-warning-bg text-status-warning-fg'
  return 'bg-status-danger-bg text-status-danger-fg'
}

function LeaderRow({ r }: { r: TrackerLeaderboardRow }) {
  const medal = r.rank >= 1 && r.rank <= 3 ? RANK_MEDAL[r.rank - 1] : null
  const supplier = (pct: number | null, met: boolean) => {
    if (r.insufficient || pct === null) return <span className="text-ink-faint">—</span>
    return (
      <span className={`tabular-nums ${met ? 'text-status-success-fg' : 'text-status-danger-fg'}`}>
        {pct}% {met ? '✓' : '✗'}
      </span>
    )
  }
  return (
    <tr className="border-b border-line last:border-0 hover:bg-surface-2/60">
      <td className="px-3 py-2 tabular-nums text-ink-muted">{medal ?? r.rank}</td>
      <td className="px-3 py-2 text-ink">{r.handler || '—'}</td>
      <td className="px-3 py-2 text-right tabular-nums text-ink">{num(r.total)}</td>
      <td className="px-3 py-2">
        <div className="flex items-center justify-end gap-2">
          <div className="h-2 w-20 overflow-hidden rounded-full bg-surface-2">
            <div
              className="h-full rounded-full"
              style={{ width: `${Math.min(100, Math.max(0, r.onTimeRate))}%`, background: otBarColor(r.onTimeRate) }}
            />
          </div>
          <span className={`w-10 text-right tabular-nums font-semibold ${otRateTone(r.onTimeRate)}`}>{r.onTimeRate}%</span>
        </div>
      </td>
      <td className="px-3 py-2 text-right">{supplier(r.panelBeaterPct, r.panelBeaterMet)}</td>
      <td className="px-3 py-2 text-right">{supplier(r.glassPct, r.glassMet)}</td>
      <td className="px-3 py-2 text-right">
        <span className={`inline-block rounded-full px-2.5 py-0.5 text-xs font-bold tabular-nums ${scorePill(r.overallScore)}`}>
          {r.insufficient ? '—' : r.overallScore}
        </span>
      </td>
    </tr>
  )
}

// ── Pipeline tabs ──────────────────────────────────────────────────────────────
type PipeTab = 'motor' | 'glass' | 'nonMotor'
const PIPE_TABS: { key: PipeTab; label: string }[] = [
  { key: 'motor', label: '🚗 Motor' },
  { key: 'glass', label: '🪟 Glass' },
  { key: 'nonMotor', label: '📋 Non-Motor' },
]

// ── Page ────────────────────────────────────────────────────────────────────────
export default function ClaimsDashboardPage() {
  const navigate = useNavigate()
  const canSee = useCanSeeClaimsDashboard()

  const [month, setMonth] = useState<string | null>(null)
  const [pipeTab, setPipeTab] = useState<PipeTab>('motor')
  const [slaDismissed, setSlaDismissed] = useState(false)

  const { data, isLoading, isError, refetch, isFetching } = useClaimsTrackerDashboard(month, canSee)

  // ── Access gate ──
  if (!canSee) {
    return (
      <div className="p-8">
        <EmptyState
          title="No access"
          description="The claims dashboard is available to claims, finance and underwriting staff."
          action={<button onClick={() => navigate('/claims')} className="text-sm text-primary underline cursor-pointer">Back to Claims</button>}
        />
      </div>
    )
  }

  // ── Loading skeleton ──
  if (isLoading) {
    return (
      <div className="space-y-5 p-6">
        <Skeleton className="h-8 w-64 rounded" />
        <Skeleton className="h-12 w-full rounded-lg" />
        <div className="grid grid-cols-2 gap-3 md:grid-cols-4 xl:grid-cols-5">
          {Array.from({ length: 10 }).map((_, i) => <Skeleton key={i} className="h-24 rounded-lg" />)}
        </div>
        <Skeleton className="h-56 rounded-lg" />
        <Skeleton className="h-72 rounded-lg" />
      </div>
    )
  }

  // ── Error / retry ──
  if (isError || !data) {
    return (
      <div className="p-8 text-center">
        <p className="font-medium text-status-danger-fg">Could not load the claims dashboard.</p>
        <button onClick={() => refetch()} className="mt-4 text-sm text-primary underline cursor-pointer">Try again</button>
      </div>
    )
  }

  // syncTiles is a required type field, but guard for a response that omits it
  // (e.g. an older backend task still draining during a rolling deploy) so the
  // page never white-screens on `syncTiles.synced`.
  const { kpis, syncTiles = { synced: 0, pending: 0, failed: 0 }, slaBreaches, typeSummary, typeTotals, pipeline, overdueClaims, leaderboard, gaps } = data
  const showBreaches = slaBreaches.length > 0 && !slaDismissed
  const pipeStages = pipeTab === 'glass' ? pipeline.glass : pipeline.motor

  return (
    <div className="space-y-5 p-6">
      {/* Claims Tracker replica chrome: action bar + tab strip + overdue banner */}
      <ClaimsChrome
        overdueCount={kpis.overdueStages}
        onRefresh={refetch}
        refreshing={isFetching}
      />

      {/* 1 ── Month filter bar ─────────────────────────────────────────────── */}
      <Panel className="p-4">
        <div className="flex flex-wrap items-center justify-between gap-3">
          <label className="flex items-center gap-2 text-sm font-semibold text-ink">
            <span aria-hidden>📅</span> Filter by Month:
            <select
              value={month ?? ''}
              onChange={(e) => setMonth(e.target.value || null)}
              className="min-h-[44px] rounded-md border border-line bg-surface px-3 py-1.5 text-sm text-ink focus:outline-none focus:ring-2 focus:ring-primary/40 cursor-pointer"
            >
              <option value="">All Months</option>
              {data.availableMonths.map((m) => (
                <option key={m} value={m}>{formatMonth(m)}</option>
              ))}
            </select>
          </label>
          <span className="text-[13px] text-ink-muted tabular-nums">
            {month ? `Showing ${num(kpis.total)} claims for ${formatMonth(month)}` : `Showing all ${num(kpis.total)} claims`}
          </span>
        </div>
      </Panel>

      {/* 2 ── 10 KPI cards (2 rows of 5) ────────────────────────────────────── */}
      <div className="grid grid-cols-2 gap-3 md:grid-cols-4 xl:grid-cols-5">
        <KpiCard label="Total Claims" value={num(kpis.total)} sub="All claims" tone="navy" onClick={() => navigate('/claims')} />
        <KpiCard label="In Progress" value={num(kpis.inProgress)} sub="Open" tone="info" onClick={() => navigate('/claims?status=Open')} />
        <KpiCard label="Overdue Stages" value={num(kpis.overdueStages)} sub="SLA breached" tone="red" alert={kpis.overdueStages > 0} onClick={() => navigate('/claims/sla')} />
        <KpiCard label="Delayed" value={num(kpis.delayed)} sub="Behind schedule" tone="amber" onClick={() => navigate('/claims/sla')} />
        <KpiCard label="Completed" value={num(kpis.completed)} sub="Closed" tone="green" onClick={() => navigate('/claims?status=Closed')} />
        <KpiCard label="Avg Days Late" value={num(kpis.avgDaysLate, { maximumFractionDigits: 1 })} sub="Working days" tone="orange" onClick={() => navigate('/claims/sla')} />
        <KpiCard label="Total Reserve" value={bwp(kpis.totalReserve)} sub="Outstanding" tone="navy" />
        <KpiCard label="Total Paid" value={bwp(kpis.totalPaid)} sub="Settled" tone="green" />
        <KpiCard label="Major Claims" value={num(kpis.majorClaims)} sub="High value" tone="red" onClick={() => navigate('/claims?major=1')} />
        <KpiCard label="FAC Claims" value={kpis.facAvailable ? num(kpis.facClaims ?? 0) : '—'} sub="Facultative RI" tone="navy" />
      </div>

      {/* 3 ── Graphite sync status tiles ─────────────────────────────────────── */}
      <section>
        <h2 className="mb-0.5 font-heading text-sm font-bold uppercase tracking-wide text-brand-navy">🔗 Graphite Sync Status</h2>
        <p className="mb-2 text-[11px] text-ink-faint">Cross-system claim numbers</p>
        <div className="flex flex-col gap-3 sm:flex-row">
          <RegTile emoji="🔗" label="Synced" value={syncTiles.synced} tone="green" sub="Linked to Graphite" />
          <RegTile emoji="⏳" label="Pending" value={syncTiles.pending} tone="amber" sub="Will retry automatically" />
          <RegTile emoji="🚨" label="Sync Failed" value={syncTiles.failed} tone="red" sub="Needs admin retry" />
        </div>
      </section>

      {/* 4 ── SLA breach alerts ──────────────────────────────────────────────── */}
      {showBreaches && (
        <Panel>
          <div className="flex flex-wrap items-center justify-between gap-2 border-b border-status-danger-fg/30 bg-status-danger-bg px-5 py-3">
            <h2 className="font-heading text-sm font-bold uppercase tracking-wide text-status-danger-fg">⚠ SLA Breach Alerts</h2>
            <button
              type="button"
              onClick={() => setSlaDismissed(true)}
              className="min-h-[32px] rounded border border-status-danger-fg/40 px-2.5 py-1 text-[11px] font-semibold text-status-danger-fg hover:bg-status-danger-fg/10 cursor-pointer"
            >
              Dismiss
            </button>
          </div>
          <ul className="px-5 py-2">
            {slaBreaches.map((b) => (
              <BreachRow key={b.claimId} b={b} onOpen={() => navigate(`/claims/${b.claimId}`)} />
            ))}
          </ul>
        </Panel>
      )}

      {/* 5 ── Claim type summary ─────────────────────────────────────────────── */}
      <Panel>
        <PanelHead title="Claim Type Summary" note="Counts & reserves by claim type" />
        <div className="overflow-x-auto">
          <table className="w-full text-sm">
            <thead className="bg-surface-2 text-xs uppercase text-ink-muted">
              <tr>
                <th className="px-3 py-2 text-left">Claim Type</th>
                <th className="px-3 py-2 text-right">Count</th>
                <th className="px-3 py-2 text-right">In Progress</th>
                <th className="px-3 py-2 text-right">Completed</th>
                <th className="px-3 py-2 text-right">Total Reserve (P)</th>
                <th className="px-3 py-2 text-right">Total Paid (P)</th>
              </tr>
            </thead>
            <tbody>
              {typeSummary.length === 0 ? (
                <tr><td colSpan={6} className="px-3 py-6 text-center text-ink-muted">No claims recorded for this period.</td></tr>
              ) : typeSummary.map((r) => (
                <tr key={r.claimType} className="border-b border-line last:border-0 hover:bg-surface-2/60">
                  <td className="px-3 py-2 text-ink">{r.claimType}</td>
                  <td className="px-3 py-2 text-right tabular-nums text-ink">{num(r.count)}</td>
                  <td className="px-3 py-2 text-right tabular-nums text-status-warning-fg">{num(r.inProgress)}</td>
                  <td className="px-3 py-2 text-right tabular-nums text-status-success-fg">{num(r.completed)}</td>
                  <td className="px-3 py-2 text-right tabular-nums text-ink">{num(r.reserve, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</td>
                  <td className="px-3 py-2 text-right tabular-nums text-ink">{num(r.paid, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</td>
                </tr>
              ))}
            </tbody>
            {typeSummary.length > 0 && (
              <tfoot>
                <tr className="border-t-2 border-line bg-surface-2 font-bold">
                  <td className="px-3 py-2 text-brand-navy">Total</td>
                  <td className="px-3 py-2 text-right tabular-nums text-brand-navy">{num(typeTotals.count)}</td>
                  <td className="px-3 py-2 text-right tabular-nums text-status-warning-fg">{num(typeTotals.inProgress)}</td>
                  <td className="px-3 py-2 text-right tabular-nums text-status-success-fg">{num(typeTotals.completed)}</td>
                  <td className="px-3 py-2 text-right tabular-nums text-brand-navy">{num(typeTotals.reserve, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</td>
                  <td className="px-3 py-2 text-right tabular-nums text-brand-navy">{num(typeTotals.paid, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</td>
                </tr>
              </tfoot>
            )}
          </table>
        </div>
      </Panel>

      {/* 6 ── Claims pipeline ─────────────────────────────────────────────────── */}
      <Panel>
        <PanelHead title="Claims Pipeline" />
        {/* Tab bar */}
        <div className="flex items-stretch border-b border-line px-3">
          {PIPE_TABS.map((t) => {
            const active = pipeTab === t.key
            return (
              <button
                key={t.key}
                type="button"
                onClick={() => setPipeTab(t.key)}
                className={`-mb-px whitespace-nowrap border-b-2 px-4 py-2.5 text-sm font-semibold transition-colors cursor-pointer ${
                  active ? 'border-brand-orange text-brand-navy' : 'border-transparent text-ink-muted hover:text-brand-navy'
                }`}
              >
                {t.label}
              </button>
            )
          })}
        </div>

        <div className="p-5">
          {pipeTab !== 'nonMotor' ? (
            pipeStages.length === 0 ? (
              <p className="py-6 text-center text-sm text-ink-muted">No pipeline stages for this class.</p>
            ) : (
              <div className="flex items-stretch gap-2 overflow-x-auto pb-1">
                {pipeStages.map((s, i) => (
                  <div key={s.stage} className="flex items-center gap-2">
                    <StageStep stage={s} />
                    {i < pipeStages.length - 1 && <span className="text-lg text-ink-faint" aria-hidden>›</span>}
                  </div>
                ))}
              </div>
            )
          ) : (
            <div className="space-y-4">
              <div className="flex flex-col gap-3 sm:flex-row">
                <RegTile emoji="⏳" label="In Progress" value={pipeline.nonMotor.inProgress} tone="amber" />
                <RegTile emoji="✅" label="Resolved" value={pipeline.nonMotor.resolved} tone="green" />
                <RegTile emoji="⚠️" label="SLA Breached" value={pipeline.nonMotor.breached} tone="red" />
              </div>
              <div className="rounded-lg border border-line p-4">
                <h3 className="mb-2 text-[11px] font-semibold uppercase tracking-wide text-ink-muted">Claim Type Breakdown</h3>
                {pipeline.nonMotor.byType.length === 0 ? (
                  <p className="text-sm text-ink-muted">No non-motor claims for this period.</p>
                ) : (
                  <div className="flex flex-wrap gap-2">
                    {pipeline.nonMotor.byType.map((t) => (
                      <span key={t.subType} className="inline-flex items-center gap-1.5 rounded-full border border-line bg-surface-2 px-2.5 py-1 text-[12px] text-ink">
                        {t.subType}
                        <span className="rounded-full bg-brand-navy px-1.5 py-0.5 text-[10px] font-bold tabular-nums text-white">{num(t.count)}</span>
                      </span>
                    ))}
                  </div>
                )}
              </div>
            </div>
          )}

          {/* Legend */}
          <div className="mt-4 flex flex-wrap items-center gap-4 text-[11px] text-ink-muted">
            <span className="flex items-center gap-1.5"><span className="inline-block h-2.5 w-2.5 rounded-full bg-status-success-fg" /> On Time</span>
            <span className="flex items-center gap-1.5"><span className="inline-block h-2.5 w-2.5 rounded-full bg-status-danger-fg" /> Delayed</span>
            <span className="flex items-center gap-1.5"><span className="inline-block h-2.5 w-2.5 rounded-full bg-surface-2 border border-line" /> Empty</span>
          </div>
        </div>
      </Panel>

      {/* 7 ── Overdue & delayed claims ────────────────────────────────────────── */}
      <Panel>
        <PanelHead
          title="Overdue & Delayed Claims"
          right={
            <button
              type="button"
              onClick={() => navigate('/claims')}
              className="min-h-[36px] rounded border border-line bg-surface-2 px-3 py-1.5 text-[12px] font-semibold text-brand-navy hover:bg-line/40 cursor-pointer"
            >
              View All →
            </button>
          }
        />
        {overdueClaims.length === 0 ? (
          <div className="px-5 py-8 text-center text-sm text-status-success-fg">✅ No overdue or delayed claims — all on track!</div>
        ) : (
          <div className="overflow-x-auto">
            <table className="w-full text-sm">
              <thead className="bg-surface-2 text-xs uppercase text-ink-muted">
                <tr>
                  <th className="px-3 py-2 text-left">Claim #</th>
                  <th className="px-3 py-2 text-left">Client</th>
                  <th className="px-3 py-2 text-left">Handler</th>
                  <th className="px-3 py-2 text-left">Stage</th>
                  <th className="px-3 py-2 text-left">Status</th>
                  <th className="px-3 py-2 text-right">Days Late</th>
                  <th className="px-3 py-2 text-right">Cycle Deadline</th>
                  <th className="px-3 py-2 text-right no-print">Action</th>
                </tr>
              </thead>
              <tbody>
                {overdueClaims.map((c) => (
                  <OverdueRow key={c.claimId} c={c} onOpen={() => navigate(`/claims/${c.claimId}`)} />
                ))}
              </tbody>
            </table>
          </div>
        )}
      </Panel>

      {/* 8 ── Handler performance leaderboard ─────────────────────────────────── */}
      <Panel>
        <PanelHead title="🏆 Handler Performance Leaderboard" />
        {leaderboard.length === 0 ? (
          <div className="px-5 py-8 text-center text-sm text-ink-muted">No handler performance data for this period.</div>
        ) : (
          <div className="overflow-x-auto">
            <table className="w-full text-sm">
              <thead className="bg-surface-2 text-xs uppercase text-ink-muted">
                <tr>
                  <th className="px-3 py-2 text-left">Rank</th>
                  <th className="px-3 py-2 text-left">Claims Handler</th>
                  <th className="px-3 py-2 text-right">Total Claims</th>
                  <th className="px-3 py-2 text-right">On-Time Rate</th>
                  <th className="px-3 py-2 text-right">Panel Beater (90%)</th>
                  <th className="px-3 py-2 text-right">Glass Supplier (80%)</th>
                  <th className="px-3 py-2 text-right">Overall Score</th>
                </tr>
              </thead>
              <tbody>
                {leaderboard.map((r) => <LeaderRow key={`${r.rank}-${r.handler}`} r={r} />)}
              </tbody>
            </table>
          </div>
        )}
        <p className="border-t border-line px-5 py-3 text-[11px] text-ink-faint">
          Overall Score = On-Time Rate (40%) + Panel-Beater compliance (30%) + Glass-Supplier compliance (30%).
          Handlers with fewer than 5 claims show “—” for supplier compliance and score.
        </p>
      </Panel>

      {/* Backend-reported data gaps (surfaced, not blocking) */}
      {gaps.length > 0 && (
        <p className="text-[11px] text-ink-faint">
          Note: {gaps.join(' · ')}
        </p>
      )}
    </div>
  )
}
