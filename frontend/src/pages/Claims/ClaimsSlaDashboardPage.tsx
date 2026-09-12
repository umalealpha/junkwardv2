import { useMemo, useState } from 'react'
import { useNavigate } from 'react-router-dom'
import {
  useClaimSlaDashboard,
  useClaimSlaLeaderboard,
  useClaimsSlaEnabled,
  CLAIMS_SLA_MANAGER_ROLES,
} from '../../hooks/useClaimsSla'
import { getStoredRoles } from '../../api/auth'
import {
  CLAIM_SLA_STATUS_META,
  type ClaimSlaByStageRow,
  type ClaimSlaLeaderboardRow,
  type ClaimSlaStatus,
} from '../../api/claimsSla'
import LoadingSpinner from '../../components/common/LoadingSpinner'
import EmptyState from '../../components/common/EmptyState'

// Brand-primary fill for inline bars (themes: navy #010066 light → lightened dark).
const BRAND = 'rgb(var(--primary))'

const STAGE_STATUS_KEYS: ClaimSlaStatus[] = ['on_track', 'due_soon', 'breached', 'met', 'missed']

function StatCard({ label, value, tone }: { label: string; value: string | number; tone?: 'good' | 'bad' | 'warn' }) {
  const toneCls =
    tone === 'bad' ? 'text-status-danger-fg'
    : tone === 'warn' ? 'text-status-warning-fg'
    : tone === 'good' ? 'text-status-success-fg'
    : 'text-ink'
  return (
    <div className="bg-surface rounded-lg border border-line shadow-elev-sm p-4">
      <div className={`text-2xl font-bold tabular-nums ${toneCls}`}>{value}</div>
      <div className="text-[11px] uppercase tracking-wide text-ink-muted mt-1">{label}</div>
    </div>
  )
}

function StatusChip({ status }: { status: ClaimSlaStatus }) {
  const meta = CLAIM_SLA_STATUS_META[status]
  return (
    <span className={`inline-block px-2 py-0.5 rounded-full text-[11px] font-medium ${meta.cls}`}>{meta.label}</span>
  )
}

// ── Leaderboard (sortable) ─────────────────────────────────────────────────
type LbSortKey = 'handler_name' | 'total' | 'breached' | 'completed' | 'on_time_pct'

function Leaderboard({ rows }: { rows: ClaimSlaLeaderboardRow[] }) {
  const [sortKey, setSortKey] = useState<LbSortKey>('on_time_pct')
  const [dir, setDir] = useState<'asc' | 'desc'>('desc')

  const sorted = useMemo(() => {
    const copy = [...rows]
    copy.sort((a, b) => {
      let av: number | string = a[sortKey] as number | string
      let bv: number | string = b[sortKey] as number | string
      if (sortKey === 'handler_name') {
        av = String(av).toLowerCase()
        bv = String(bv).toLowerCase()
        return dir === 'asc' ? (av < bv ? -1 : av > bv ? 1 : 0) : (av > bv ? -1 : av < bv ? 1 : 0)
      }
      return dir === 'asc' ? (av as number) - (bv as number) : (bv as number) - (av as number)
    })
    return copy
  }, [rows, sortKey, dir])

  function toggle(k: LbSortKey) {
    if (k === sortKey) {
      setDir((d) => (d === 'asc' ? 'desc' : 'asc'))
    } else {
      setSortKey(k)
      setDir(k === 'handler_name' ? 'asc' : 'desc')
    }
  }

  const Th = ({ k, children, right }: { k: LbSortKey; children: React.ReactNode; right?: boolean }) => (
    <th className={right ? 'text-right' : 'text-left'}>
      <button
        onClick={() => toggle(k)}
        className={`inline-flex items-center gap-1 py-1 hover:text-ink transition ${sortKey === k ? 'text-ink font-semibold' : ''}`}
      >
        {children}
        {sortKey === k && <span className="text-[9px]">{dir === 'asc' ? '▲' : '▼'}</span>}
      </button>
    </th>
  )

  return (
    <div className="overflow-x-auto">
      <table className="w-full text-sm">
        <thead className="text-xs text-ink-muted uppercase">
          <tr>
            <Th k="handler_name">Handler</Th>
            <Th k="total" right>Total</Th>
            <Th k="completed" right>Completed</Th>
            <Th k="breached" right>Breached</Th>
            <Th k="on_time_pct" right>On-time %</Th>
          </tr>
        </thead>
        <tbody className="divide-y divide-line">
          {sorted.length === 0 ? (
            <tr><td colSpan={5} className="p-0"><EmptyState compact title="No handler data" description="No tracked claims have an allocated handler yet." /></td></tr>
          ) : sorted.map((r) => (
            <tr key={r.handler_id ?? 'unassigned'}>
              <td className="py-1.5 text-ink">{r.handler_name}</td>
              <td className="text-right tabular-nums text-ink">{r.total}</td>
              <td className="text-right tabular-nums text-ink-muted">{r.completed}</td>
              <td className="text-right tabular-nums text-status-danger-fg">{r.breached}</td>
              <td className="py-1.5">
                <div className="flex items-center gap-2 justify-end">
                  <div className="w-24 h-2.5 bg-surface-2 rounded-full overflow-hidden">
                    <div className="h-full rounded-full" style={{ width: `${Math.min(100, Math.max(0, r.on_time_pct))}%`, background: BRAND }} />
                  </div>
                  <span className="w-12 text-right tabular-nums text-ink">{r.on_time_pct}%</span>
                </div>
              </td>
            </tr>
          ))}
        </tbody>
      </table>
    </div>
  )
}

// ── By-stage row (stacked count cells with chips) ──────────────────────────
function StageRow({ row }: { row: ClaimSlaByStageRow }) {
  const total = STAGE_STATUS_KEYS.reduce((n, k) => n + (row[k] ?? 0), 0)
  return (
    <tr>
      <td className="py-2 text-ink">{row.label}</td>
      {STAGE_STATUS_KEYS.map((k) => (
        <td key={k} className="text-center tabular-nums text-ink">
          {row[k] ? row[k] : <span className="text-ink-faint">—</span>}
        </td>
      ))}
      <td className="text-right tabular-nums text-ink-muted">{total}</td>
    </tr>
  )
}

export default function ClaimsSlaDashboardPage() {
  const navigate = useNavigate()
  const enabled = useClaimsSlaEnabled()
  const hasRole = getStoredRoles().some((r) => CLAIMS_SLA_MANAGER_ROLES.includes(r))
  const canQuery = enabled && hasRole

  const dash = useClaimSlaDashboard(canQuery)
  const lb = useClaimSlaLeaderboard(canQuery)

  // Feature off (flag) — either the integrations flag is off, or the API 404s
  // because it was flipped off after load.
  const dashStatus = (dash.error as any)?.response?.status
  const featureOff = !enabled || dashStatus === 404
  if (featureOff) {
    return (
      <div className="p-8">
        <EmptyState
          title="Claims SLA is not enabled"
          description="This module is turned off. An administrator can enable it under Admin → Integrations."
          action={<button onClick={() => navigate('/claims')} className="text-sm text-primary underline">Back to Claims</button>}
        />
      </div>
    )
  }

  if (!hasRole || dashStatus === 403) {
    return (
      <div className="p-8">
        <EmptyState
          title="No access"
          description="The claims SLA dashboard is available to Claims Managers and administrators."
          action={<button onClick={() => navigate('/claims')} className="text-sm text-primary underline">Back to Claims</button>}
        />
      </div>
    )
  }

  if (dash.isLoading) {
    return <div className="p-12 flex justify-center"><LoadingSpinner size="lg" /></div>
  }
  if (dash.isError) {
    return (
      <div className="p-8 text-center">
        <p className="text-status-danger-fg font-medium">Could not load the claims SLA dashboard.</p>
        <button onClick={() => dash.refetch()} className="mt-4 text-sm text-primary underline">Try again</button>
      </div>
    )
  }
  if (!dash.data) return null

  const s = dash.data.summary
  const bs = s.by_status

  return (
    <div className="p-6 space-y-5">
      <div className="flex items-center justify-between flex-wrap gap-3">
        <div>
          <h1 className="font-heading text-2xl font-bold text-ink">Claims SLA Dashboard</h1>
          <p className="text-xs text-ink-muted mt-0.5">Working-day SLA across tracked claims (Claims Tracker → Graphite, Phase 1).</p>
        </div>
        <span className="text-xs text-ink-faint">Updated {new Date(dash.data.generated_at).toLocaleString('en-GB')}</span>
      </div>

      {/* Summary cards */}
      <div className="grid grid-cols-2 md:grid-cols-4 xl:grid-cols-7 gap-3">
        <StatCard label="Tracked Claims" value={s.total_claims} />
        <StatCard label="Breached" value={s.breached} tone={s.breached > 0 ? 'bad' : 'good'} />
        <StatCard label="On Track" value={bs.on_track} tone="good" />
        <StatCard label="Due Soon" value={bs.due_soon} tone="warn" />
        <StatCard label="Met" value={bs.met} tone="good" />
        <StatCard label="Missed" value={bs.missed} tone="bad" />
        <StatCard label="Overdue (open)" value={bs.breached} tone={bs.breached > 0 ? 'bad' : undefined} />
      </div>

      {/* By class */}
      <section className="bg-surface rounded-lg border border-line shadow-elev-sm p-5">
        <h2 className="text-sm font-semibold text-ink-muted uppercase tracking-wide mb-3">By Claim Class</h2>
        <div className="overflow-x-auto">
          <table className="w-full text-sm">
            <thead className="text-xs text-ink-muted uppercase">
              <tr>
                <th className="text-left py-1">Class</th>
                <th className="text-right">Total</th>
                <th className="text-right">Completed</th>
                <th className="text-right">Breached</th>
                <th className="text-right">Breach %</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-line">
              {dash.data.by_class.length === 0 ? (
                <tr><td colSpan={5} className="p-0"><EmptyState compact title="No tracked claims" description="No claims have a stage timeline row yet." /></td></tr>
              ) : dash.data.by_class.map((c) => {
                const pct = c.total > 0 ? Math.round((c.breached / c.total) * 100) : 0
                return (
                  <tr key={c.class}>
                    <td className="py-1.5 text-ink">{c.label}</td>
                    <td className="text-right tabular-nums text-ink">{c.total}</td>
                    <td className="text-right tabular-nums text-ink-muted">{c.completed}</td>
                    <td className="text-right tabular-nums text-status-danger-fg">{c.breached}</td>
                    <td className={`text-right tabular-nums ${pct > 0 ? 'text-status-danger-fg' : 'text-ink-muted'}`}>{pct}%</td>
                  </tr>
                )
              })}
            </tbody>
          </table>
        </div>
      </section>

      {/* By stage */}
      <section className="bg-surface rounded-lg border border-line shadow-elev-sm p-5">
        <div className="flex items-center justify-between mb-3 gap-2 flex-wrap">
          <h2 className="text-sm font-semibold text-ink-muted uppercase tracking-wide">By Stage</h2>
          <div className="flex items-center gap-1.5 flex-wrap">
            {STAGE_STATUS_KEYS.map((k) => <StatusChip key={k} status={k} />)}
          </div>
        </div>
        <div className="overflow-x-auto">
          <table className="w-full text-sm">
            <thead className="text-xs text-ink-muted uppercase">
              <tr>
                <th className="text-left py-1">Stage</th>
                {STAGE_STATUS_KEYS.map((k) => (
                  <th key={k} className="text-center">{CLAIM_SLA_STATUS_META[k].label}</th>
                ))}
                <th className="text-right">Total</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-line">
              {dash.data.by_stage.length === 0 ? (
                <tr><td colSpan={7} className="p-0"><EmptyState compact title="No stage data" description="No claims have recorded stages yet." /></td></tr>
              ) : dash.data.by_stage.map((row) => <StageRow key={row.stage} row={row} />)}
            </tbody>
          </table>
        </div>
      </section>

      {/* Handler leaderboard */}
      <section className="bg-surface rounded-lg border border-line shadow-elev-sm p-5">
        <h2 className="text-sm font-semibold text-ink-muted uppercase tracking-wide mb-3">Handler Leaderboard</h2>
        {lb.isLoading ? (
          <div className="py-8 flex justify-center"><LoadingSpinner /></div>
        ) : lb.isError ? (
          <p className="text-sm text-status-danger-fg">Could not load the leaderboard.</p>
        ) : (
          <Leaderboard rows={lb.data ?? []} />
        )}
        <p className="text-[11px] text-ink-faint mt-2">On-time % = claims not breached ÷ total for that handler. “Unassigned” groups claims with no allocated handler.</p>
      </section>
    </div>
  )
}
