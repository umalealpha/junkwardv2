import { useState } from 'react'
import { useNavigate } from 'react-router-dom'
import { useSlaDashboard } from '../../hooks/useHelpDesk'
import { downloadSlaReport, type SlaReportType } from '../../api/helpdesk'
import LoadingSpinner from '../../components/common/LoadingSpinner'
import EmptyState from '../../components/common/EmptyState'

const REPORTS: { type: SlaReportType; label: string }[] = [
  { type: 'compliance', label: 'SLA Compliance' },
  { type: 'breach', label: 'Breaches' },
  { type: 'assignee', label: 'Assignee Performance' },
  { type: 'monthly', label: 'Monthly Summary' },
]

// Brand-primary fill for the inline progress-bar (`background:` inline style
// needs a value, not a token class). Uses the --primary token so it themes:
// navy #010066 in light, lightened #6472F0 in dark (avoids dark-on-dark).
const BRAND = 'rgb(var(--primary))'

function fmtMins(min: number | null): string {
  if (min === null || min === undefined) return '—'
  if (min <= 0) return '0m'
  const h = Math.floor(min / 60), m = min % 60
  return h > 0 ? (m > 0 ? `${h}h ${m}m` : `${h}h`) : `${m}m`
}

function StatCard({ label, value, tone }: { label: string; value: string | number; tone?: 'good' | 'bad' | 'warn' }) {
  const toneCls = tone === 'bad' ? 'text-status-danger-fg' : tone === 'warn' ? 'text-status-warning-fg' : tone === 'good' ? 'text-status-success-fg' : 'text-ink'
  return (
    <div className="bg-surface rounded-lg border border-line shadow-elev-sm p-4">
      <div className={`text-2xl font-bold tabular-nums ${toneCls}`}>{value}</div>
      <div className="text-[11px] uppercase tracking-wide text-ink-muted mt-1">{label}</div>
    </div>
  )
}

const FLAG_CLS: Record<string, string> = {
  breached: 'bg-status-danger-bg text-status-danger-fg',
  nearing: 'bg-status-warning-bg text-status-warning-fg',
  unassigned: 'bg-surface-2 text-ink-muted',
  critical: 'bg-status-accent-bg text-status-accent-fg',
}

export default function SlaDashboardPage() {
  const navigate = useNavigate()
  const { data, isLoading, isError, error } = useSlaDashboard()
  const [format, setFormat] = useState<'xlsx' | 'csv'>('xlsx')
  const [downloading, setDownloading] = useState<SlaReportType | null>(null)

  async function handleExport(type: SlaReportType) {
    setDownloading(type)
    try {
      await downloadSlaReport(type, format)
    } finally {
      setDownloading(null)
    }
  }

  if (isLoading) {
    return <div className="p-12 flex justify-center"><LoadingSpinner size="lg" /></div>
  }
  if (isError) {
    const status = (error as any)?.response?.status
    return (
      <div className="p-8 text-center">
        <p className="text-status-danger-fg font-medium">
          {status === 403 ? 'You do not have access to the SLA dashboard.' : 'Could not load the SLA dashboard.'}
        </p>
        <button onClick={() => navigate('/help-desk')} className="mt-4 text-sm text-brand-navy underline">Back to Help Desk</button>
      </div>
    )
  }
  if (!data) return null

  const s = data.summary
  const maxAging = Math.max(1, ...Object.values(data.aging))

  return (
    <div className="p-6 space-y-5">
      <div className="flex items-center justify-between flex-wrap gap-3">
        <h1 className="font-heading text-2xl font-bold text-ink">SLA Dashboard</h1>
        <div className="flex items-center gap-2 flex-wrap">
          <span className="text-xs text-ink-faint mr-1">Updated {new Date(data.generated_at).toLocaleString('en-GB')}</span>
          <select
            value={format}
            onChange={e => setFormat(e.target.value as 'xlsx' | 'csv')}
            className="px-2 py-1 bg-surface text-ink border border-line rounded-md text-xs focus:ring-1 focus:ring-brand-navy"
            title="Export format"
          >
            <option value="xlsx">Excel</option>
            <option value="csv">CSV</option>
          </select>
          {REPORTS.map(r => (
            <button
              key={r.type}
              onClick={() => handleExport(r.type)}
              disabled={downloading !== null}
              className="px-3 py-1.5 text-xs font-medium rounded-md border border-brand-navy/30 text-brand-navy hover:bg-brand-navy/5 transition disabled:opacity-50"
            >
              {downloading === r.type ? 'Exporting…' : r.label}
            </button>
          ))}
        </div>
      </div>

      {/* Summary cards */}
      <div className="grid grid-cols-2 md:grid-cols-4 xl:grid-cols-7 gap-3">
        <StatCard label="Total Tickets" value={s.total} />
        <StatCard label="Within SLA" value={s.within_sla} tone="good" />
        <StatCard label="Response Breached" value={s.response_breached} tone="bad" />
        <StatCard label="Resolution Breached" value={s.resolution_breached} tone="bad" />
        <StatCard label="Open Breaches" value={s.open_breaches} tone="warn" />
        <StatCard label="Closed Breaches" value={s.closed_breaches} />
        <StatCard label="SLA Compliance" value={s.compliance_pct !== null ? `${s.compliance_pct}%` : '—'} tone={s.compliance_pct !== null && s.compliance_pct >= 90 ? 'good' : 'warn'} />
      </div>

      <div className="grid grid-cols-1 lg:grid-cols-2 gap-5">
        {/* Aging buckets */}
        <section className="bg-surface rounded-lg border border-line shadow-elev-sm p-5">
          <h2 className="text-sm font-semibold text-ink-muted uppercase tracking-wide mb-3">Ticket Aging (open)</h2>
          <div className="space-y-2">
            {Object.entries(data.aging).map(([bucket, count]) => (
              <div key={bucket} className="flex items-center gap-3">
                <span className="w-20 text-xs text-ink-muted">{bucket}</span>
                <div className="flex-1 h-3 bg-surface-2 rounded-full overflow-hidden">
                  <div className="h-full rounded-full" style={{ width: `${(count / maxAging) * 100}%`, background: BRAND }} />
                </div>
                <span className="w-8 text-right text-xs tabular-nums text-ink">{count}</span>
              </div>
            ))}
          </div>
        </section>

        {/* Priority breakdown */}
        <section className="bg-surface rounded-lg border border-line shadow-elev-sm p-5">
          <h2 className="text-sm font-semibold text-ink-muted uppercase tracking-wide mb-3">Priority Breakdown</h2>
          <table className="w-full text-sm">
            <thead className="text-xs text-ink-muted uppercase">
              <tr><th className="text-left py-1">Priority</th><th className="text-right">Open</th><th className="text-right">Breached</th><th className="text-right">Resolved</th></tr>
            </thead>
            <tbody className="divide-y divide-line">
              {Object.entries(data.priority).map(([p, v]) => (
                <tr key={p}>
                  <td className="py-1.5 capitalize text-ink">{p}</td>
                  <td className="text-right tabular-nums text-ink">{v.open}</td>
                  <td className="text-right tabular-nums text-status-danger-fg">{v.breached}</td>
                  <td className="text-right tabular-nums text-ink-muted">{v.resolved}</td>
                </tr>
              ))}
            </tbody>
          </table>
        </section>
      </div>

      {/* Assignee performance */}
      <section className="bg-surface rounded-lg border border-line shadow-elev-sm p-5">
        <h2 className="text-sm font-semibold text-ink-muted uppercase tracking-wide mb-3">Assignee Performance</h2>
        <div className="overflow-x-auto">
          <table className="w-full text-sm">
            <thead className="text-xs text-ink-muted uppercase">
              <tr>
                <th className="text-left py-1">Assignee</th>
                <th className="text-right">Total</th>
                <th className="text-right">Avg Response</th>
                <th className="text-right">Avg Resolution</th>
                <th className="text-right">Breaches</th>
                <th className="text-right">Compliance</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-line">
              {data.assignees.length === 0 ? (
                <tr><td colSpan={6} className="p-0"><EmptyState compact title="No assignee data" description="No tickets have been assigned in this period." /></td></tr>
              ) : data.assignees.map(a => (
                <tr key={a.assignee}>
                  <td className="py-1.5 text-ink">{a.assignee}</td>
                  <td className="text-right tabular-nums text-ink">{a.total}</td>
                  <td className="text-right tabular-nums text-ink">{fmtMins(a.avg_response_min)}</td>
                  <td className="text-right tabular-nums text-ink">{fmtMins(a.avg_resolution_min)}</td>
                  <td className="text-right tabular-nums text-status-danger-fg">{a.breaches}</td>
                  <td className="text-right tabular-nums text-ink">{a.compliance_pct !== null ? `${a.compliance_pct}%` : '—'}</td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
        <p className="text-[11px] text-ink-faint mt-2">Average times are elapsed wall-clock (indicative); breach counts use the precise business-hours SLA.</p>
      </section>

      {/* Heat map */}
      <section className="bg-surface rounded-lg border border-line shadow-elev-sm p-5">
        <div className="flex items-center justify-between mb-3">
          <h2 className="text-sm font-semibold text-ink-muted uppercase tracking-wide">Management Heat Map</h2>
          <div className="flex items-center gap-2 text-[11px]">
            <span className="px-2 py-0.5 rounded-full bg-status-danger-bg text-status-danger-fg">Breached</span>
            <span className="px-2 py-0.5 rounded-full bg-status-warning-bg text-status-warning-fg">Nearing</span>
            <span className="px-2 py-0.5 rounded-full bg-surface-2 text-ink-muted">Unassigned</span>
            <span className="px-2 py-0.5 rounded-full bg-status-accent-bg text-status-accent-fg">Critical</span>
          </div>
        </div>
        {data.heatmap.length === 0 ? (
          <p className="text-sm text-ink-muted">Nothing flagged — all clear. 🎉</p>
        ) : (
          <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-2">
            {data.heatmap.map(t => {
              const border = t.severity === 3 ? 'border-status-danger-fg/40' : t.severity === 2 ? 'border-status-warning-fg/40' : 'border-line'
              return (
                <button
                  key={t.id}
                  onClick={() => navigate(`/help-desk/${t.id}`)}
                  className={`text-left bg-surface border ${border} rounded-md p-2.5 hover:shadow-elev-sm transition`}
                >
                  <div className="flex items-center justify-between">
                    <span className="text-sm font-medium text-brand-navy">{t.ticketRef}</span>
                    <span className="text-[11px] text-ink-faint capitalize">{t.priority}</span>
                  </div>
                  <div className="text-xs text-ink-muted truncate mt-0.5">{t.assignee}</div>
                  <div className="flex flex-wrap gap-1 mt-1.5">
                    {t.flags.map(f => (
                      <span key={f} className={`px-1.5 py-0.5 rounded-full text-[10px] font-medium capitalize ${FLAG_CLS[f] ?? 'bg-surface-2 text-ink-muted'}`}>{f}</span>
                    ))}
                  </div>
                </button>
              )
            })}
          </div>
        )}
      </section>
    </div>
  )
}
