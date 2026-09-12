import { useQuery } from '@tanstack/react-query'
import { Link } from 'react-router-dom'
import { fetchFinanceDashboard } from '../../api/financeDashboard'
import { useCronReports } from '../../hooks/useCronReports'
import { getReportDownloadUrl } from '../../api/cronReports'
import { fmtDate, fmtPulaCompact } from '../../utils/format'
import Card from '../../components/common/Card'
import EmptyState from '../../components/common/EmptyState'
import { Skeleton } from '../../components/common/Skeleton'
import { PRODUCT_TILE_META, type ProductKey } from './dashboardTheme'

function fmt(n: number) {
  return n.toLocaleString()
}

function pctChange(current: number, previous: number): { text: string; color: string } {
  if (previous === 0) return { text: current > 0 ? '+100%' : '0%', color: current > 0 ? 'text-status-success-fg' : 'text-ink-faint' }
  const pct = ((current - previous) / previous) * 100
  return {
    text: `${pct >= 0 ? '+' : ''}${pct.toFixed(1)}%`,
    color: pct >= 0 ? 'text-status-success-fg' : 'text-status-danger-fg',
  }
}

function ProductCard({ productKey, active, premium }: {
  productKey: ProductKey; active: number; premium: number
}) {
  const meta = PRODUCT_TILE_META[productKey]
  const avg = active > 0 ? premium / active : 0
  return (
    <div className={`bg-gradient-to-r ${meta.gradient} rounded-xl shadow-elev-sm text-white`}>
      <div className="px-5 pt-4 pb-2 flex items-center gap-2">
        <svg className="w-6 h-6 text-white/80" fill="none" stroke="currentColor" viewBox="0 0 24 24" strokeWidth={1.5}>
          <path strokeLinecap="round" strokeLinejoin="round" d={meta.icon} />
        </svg>
        <h3 className="font-bold text-lg">{meta.label}</h3>
      </div>
      <div className="px-5 pb-4 grid grid-cols-2 gap-x-4 gap-y-3">
        <div>
          <p className="text-white/50 text-[10px] uppercase tracking-widest">Active Policies</p>
          <p className="text-3xl font-black leading-tight tabular-nums">{fmt(active)}</p>
        </div>
        <div>
          <p className="text-white/50 text-[10px] uppercase tracking-widest">Monthly Premium</p>
          <p className="text-2xl font-bold leading-tight tabular-nums">{fmtPulaCompact(premium)}</p>
        </div>
      </div>
      <div className="px-5 py-2.5 bg-black/15 text-white/80 text-xs flex justify-between">
        <span>Avg per policy</span>
        <span className="font-semibold tabular-nums">{fmtPulaCompact(avg)}</span>
      </div>
    </div>
  )
}

function StatCard({ label, value, subLabel, subValue, change }: {
  label: string; value: string; subLabel?: string; subValue?: string; change?: { text: string; color: string }
}) {
  return (
    <div className="bg-surface rounded-lg shadow-elev-sm border border-line p-4">
      <p className="text-xs text-ink-muted uppercase tracking-wider">{label}</p>
      <p className="text-2xl font-bold text-ink tabular-nums mt-1">{value}</p>
      {subLabel && <p className="text-xs text-ink-faint mt-1">{subLabel}: <span className="font-medium text-ink-muted tabular-nums">{subValue}</span></p>}
      {change && <p className={`text-xs font-medium mt-1 ${change.color}`}>{change.text} vs previous</p>}
    </div>
  )
}

export default function FinanceDashboardPage() {
  const { data, isLoading, error } = useQuery({
    queryKey: ['finance-dashboard'],
    queryFn: fetchFinanceDashboard,
    staleTime: 2 * 60 * 1000,
    refetchOnWindowFocus: false,
  })
  const { data: reports = [] } = useCronReports()

  if (isLoading) {
    return (
      <div className="p-6 space-y-6">
        <Skeleton className="h-8 w-64 rounded" />
        <Skeleton className="h-24 rounded-xl" />
        <div className="grid grid-cols-1 md:grid-cols-3 gap-5">
          {[1,2,3].map(i => <Skeleton key={i} className="h-40 rounded-xl" />)}
        </div>
        <Skeleton className="h-20 rounded-lg" />
        <div className="grid grid-cols-2 md:grid-cols-4 gap-4">
          {[1,2,3,4].map(i => <Skeleton key={i} className="h-24 rounded-lg" />)}
        </div>
        <div className="grid grid-cols-1 md:grid-cols-3 gap-5">
          {[1,2,3].map(i => <Skeleton key={i} className="h-48 rounded-lg" />)}
        </div>
      </div>
    )
  }
  if (error || !data) return <div className="p-6 text-status-danger-fg">Failed to load finance dashboard.</div>

  const weekChange = pctChange(data.collections.thisWeek, data.collections.lastWeek)
  const monthChange = pctChange(data.collections.thisMonth, data.collections.lastMonth)
  const activatedChange = pctChange(data.policyMovement.activatedThisWeek, data.policyMovement.activatedLastWeek)

  return (
    <div className="p-6 space-y-6">
      <div className="flex items-center justify-between">
        <div>
          <h1 className="font-heading text-2xl font-bold text-ink">Finance Dashboard</h1>
          {data.dataDate && (
            <p className="text-xs text-ink-faint mt-0.5">
              {/* UAT 2026-05-26 (Arjun M3, Prathap BUG-009): "Data as of"
                  vs "Computed" was confusing when they were weeks apart.
                  Re-labelled to make the ETL semantics explicit. */}
              {/* UAT 2026-05-26 (Arjun L4): same widget, two different date
                  formats — fixed to use the same en-GB long form on both
                  so they read consistently. */}
              Snapshot date <span title="The business date the underlying data was extracted from production">(business date)</span>:
              {' '}<span className="font-medium text-ink-muted">{fmtDate(data.dataDate, { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' })}</span>
              {data.computedAt && (
                <>
                  {' '}&nbsp;·&nbsp; View generated
                  <span title="When this dashboard view was assembled (current time minus a small render delay)"> (refresh time)</span>:
                  {' '}<span className="font-medium text-ink-muted">
                    {fmtDate(data.computedAt, { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' })}
                    {' '}at{' '}
                    {new Date(data.computedAt).toLocaleTimeString('en-GB', { hour: '2-digit', minute: '2-digit' })}
                  </span>
                </>
              )}
            </p>
          )}
        </div>
        <Link to="/" className="text-sm text-primary hover:underline">Switch to Sales Dashboard</Link>
      </div>

      {/* Active Policies Summary Bar */}
      <div className="bg-gradient-to-r from-brand-navy to-brand-navy-light rounded-xl p-5 text-white shadow-elev-sm">
        <div className="flex items-center justify-between">
          <div>
            <p className="text-sm text-white/70">Total Active Policies</p>
            <p className="text-4xl font-black tabular-nums">{fmt(data.products.commercial.active + data.products.domestic.active + data.products.instant.active)}</p>
          </div>
          <div className="grid grid-cols-3 gap-8 text-center">
            <div>
              <p className="text-2xl font-bold tabular-nums text-white">{fmt(data.products.commercial.active)}</p>
              <p className="text-xs text-white/60">Commercial</p>
            </div>
            <div>
              <p className="text-2xl font-bold tabular-nums text-emerald-300">{fmt(data.products.domestic.active)}</p>
              <p className="text-xs text-white/60">Domestic</p>
            </div>
            <div>
              <p className="text-2xl font-bold tabular-nums text-brand-orange-light">{fmt(data.products.instant.active)}</p>
              <p className="text-xs text-white/60">Instant</p>
            </div>
          </div>
        </div>
      </div>

      {/* Product Tiles */}
      <div className="grid grid-cols-1 md:grid-cols-3 gap-5">
        <ProductCard productKey="commercial" active={data.products.commercial.active} premium={data.premiums.commercialPremium} />
        <ProductCard productKey="domestic" active={data.products.domestic.active} premium={data.premiums.domesticPremium} />
        <ProductCard productKey="instant" active={data.products.instant.active} premium={data.premiums.instantPremium} />
      </div>

      {/* Total Premium Bar */}
      <div className="bg-surface rounded-lg shadow-elev-sm border border-line p-4 flex items-center justify-between">
        <div className="flex items-center gap-6">
          <div>
            <p className="text-xs text-ink-muted">Total Monthly Premium (Active)</p>
            <p className="text-2xl font-black text-ink tabular-nums">{fmtPulaCompact(data.premiums.totalPremium)}</p>
          </div>
          <div className="h-8 w-px bg-line" />
          <div>
            <p className="text-xs text-ink-muted">Avg Per Policy</p>
            <p className="text-lg font-bold text-ink-muted tabular-nums">{fmtPulaCompact(data.premiums.avgPremium)}</p>
          </div>
        </div>
        <div className="text-right">
          <p className="text-xs text-ink-muted">Annual Projection</p>
          <p className="text-lg font-bold text-status-success-fg tabular-nums">{fmtPulaCompact(data.premiums.totalPremium * 12)}</p>
          <p className="text-[10px] text-ink-faint">Est. · monthly ×12</p>
        </div>
      </div>

      {/* Collections — This Week vs Last Week, This Month vs Last Month */}
      <div>
        <h2 className="text-base font-semibold text-ink mb-3">Collections</h2>
        <div className="grid grid-cols-2 md:grid-cols-4 gap-4">
          <StatCard label="This Week" value={fmtPulaCompact(data.collections.thisWeek)}
            subLabel="Transactions" subValue={fmt(data.collections.thisWeekCount)}
            change={weekChange} />
          <StatCard label="Last Week" value={fmtPulaCompact(data.collections.lastWeek)}
            subLabel="Transactions" subValue={fmt(data.collections.lastWeekCount)} />
          <StatCard label="This Month" value={fmtPulaCompact(data.collections.thisMonth)}
            subLabel="Transactions" subValue={fmt(data.collections.thisMonthCount)}
            change={monthChange} />
          <StatCard label="Last Month" value={fmtPulaCompact(data.collections.lastMonth)}
            subLabel="Transactions" subValue={fmt(data.collections.lastMonthCount)} />
        </div>
      </div>

      {/* Collections by Payment Method + Failed + Policy Movement */}
      <div className="grid grid-cols-1 md:grid-cols-3 gap-5">
        {/* By Method */}
        <Card title="Collections by Method (This Month)" padding="p-5">
          <div className="space-y-2">
            {data.collectionsByMethod.map(m => (
              <div key={m.paymentMethod} className="flex items-center justify-between">
                <span className="text-sm text-ink-muted">{m.paymentMethod || 'Unknown'}</span>
                <div className="text-right">
                  <span className="text-sm font-bold text-ink tabular-nums">{fmtPulaCompact(parseFloat(m.total))}</span>
                  <span className="text-xs text-ink-faint ml-2 tabular-nums">({fmt(m.count)})</span>
                </div>
              </div>
            ))}
            {data.collectionsByMethod.length === 0 && (
              <EmptyState compact title="No collections this month" />
            )}
          </div>
        </Card>

        {/* Failed Payments */}
        <Card title="Failed Payments (This Month)" padding="p-5">
          <div className="space-y-3">
            <div>
              <p className="text-xs text-ink-muted">Total Failed</p>
              <p className="text-2xl font-bold text-status-danger-fg tabular-nums">{fmt(data.failedPayments.totalFailed)}</p>
            </div>
            <div>
              <p className="text-xs text-ink-muted">Failed Amount</p>
              <p className="text-lg font-bold text-status-danger-fg tabular-nums">{fmtPulaCompact(data.failedPayments.totalFailedAmount)}</p>
            </div>
            <div>
              <p className="text-xs text-ink-muted">Failed This Week</p>
              <p className="text-lg font-bold text-status-warning-fg tabular-nums">{fmt(data.failedPayments.failedThisWeek)}</p>
            </div>
          </div>
        </Card>

        {/* Policy Movement */}
        <Card title="Policy Movement" padding="p-5">
          <div className="space-y-3">
            <div className="flex justify-between items-center">
              <span className="text-sm text-ink-muted">Activated This Week</span>
              <span className="text-lg font-bold text-status-success-fg tabular-nums">{fmt(data.policyMovement.activatedThisWeek)}
                <span className={`text-xs ml-1 ${activatedChange.color}`}>{activatedChange.text}</span>
              </span>
            </div>
            <div className="flex justify-between items-center">
              <span className="text-sm text-ink-muted">Activated Last Week</span>
              <span className="text-sm font-medium text-ink tabular-nums">{fmt(data.policyMovement.activatedLastWeek)}</span>
            </div>
            <div className="flex justify-between items-center">
              <span className="text-sm text-ink-muted">Activated This Month</span>
              <span className="text-lg font-bold text-status-success-fg tabular-nums">{fmt(data.policyMovement.activatedThisMonth)}</span>
            </div>
            <div className="flex justify-between items-center">
              <span className="text-sm text-ink-muted">Activated Last Month</span>
              <span className="text-sm font-medium text-ink tabular-nums">{fmt(data.policyMovement.activatedLastMonth)}</span>
            </div>
            <div className="border-t border-line pt-2 flex justify-between items-center">
              <span className="text-sm text-ink-muted">Cancelled This Week</span>
              <span className="text-lg font-bold text-status-danger-fg tabular-nums">{fmt(data.policyMovement.cancelledThisWeek)}</span>
            </div>
            <div className="flex justify-between items-center">
              <span className="text-sm text-ink-muted">Cancelled This Month</span>
              <span className="text-sm font-medium text-status-danger-fg tabular-nums">{fmt(data.policyMovement.cancelledThisMonth)}</span>
            </div>
          </div>
        </Card>
      </div>

      {/* Reconciliation Alert (if data exists) */}
      {data.reconciliation && data.reconciliation.openAnomalies > 0 && (
        <div className="bg-status-danger-bg border border-line rounded-lg p-4 flex items-center justify-between">
          <div>
            <h3 className="font-semibold text-status-danger-fg">Payment Anomalies Detected</h3>
            <p className="text-sm text-status-danger-fg">{fmt(data.reconciliation.openAnomalies)} open anomalies found by reconciliation agent</p>
          </div>
          <Link to="/reconciliation" className="px-4 py-2 bg-status-danger-fg text-white rounded-md text-sm font-medium hover:opacity-90">
            View Anomalies
          </Link>
        </div>
      )}

      {/* Finance Reports Section */}
      <div>
        <div className="flex items-center justify-between mb-3">
          <h2 className="text-base font-semibold text-ink">Finance Reports</h2>
          <a href="/system/report-config" className="text-xs text-primary hover:underline">Configure →</a>
        </div>
        <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
          {reports.map(r => {
            const statusClass = r.status === 'ok' ? 'text-status-success-fg bg-status-success-bg'
              : r.status === 'error' ? 'text-status-danger-fg bg-status-danger-bg'
              : r.status === 'running' ? 'text-status-info-fg bg-status-info-bg'
              : 'text-ink-muted bg-surface-2'

            // Extract key summary figures for display
            const summaryEntries = Object.entries(r.summary ?? {})
              .filter(([k]) => !k.includes('output_') && k !== 'report' && k !== 'generated_at')
              .slice(0, 4)

            return (
              <div key={r.job_key} className="bg-surface rounded-lg border border-line shadow-elev-sm p-4 flex flex-col gap-3">
                <div className="flex items-start justify-between gap-2">
                  <div className="flex-1">
                    <h3 className="text-sm font-semibold text-ink">{r.label}</h3>
                    <p className="text-xs text-ink-faint mt-0.5">{r.description}</p>
                  </div>
                  <span className={`text-xs px-2 py-0.5 rounded-full ${statusClass} whitespace-nowrap`}>
                    {r.status === 'never' ? 'Never run' : r.status}
                  </span>
                </div>

                {summaryEntries.length > 0 && (
                  <div className="grid grid-cols-2 gap-1">
                    {summaryEntries.map(([k, v]) => (
                      <div key={k} className="bg-surface-2 rounded px-2 py-1.5">
                        <p className="text-[10px] text-ink-faint uppercase tracking-wide">{k.replace(/_/g,' ')}</p>
                        <p className="text-sm font-bold text-ink tabular-nums">
                          {typeof v === 'number' ? v.toLocaleString() : String(v ?? '-')}
                        </p>
                      </div>
                    ))}
                  </div>
                )}

                <div className="flex items-center justify-between pt-1 border-t border-line mt-auto">
                  <div className="text-xs text-ink-faint">
                    {r.last_run_at
                      ? `${fmtDate(r.last_run_at, { day: 'numeric', month: 'short' }, 'Never run')} ${r.elapsed ? `(${r.elapsed})` : ''}`
                      : 'Never run'}
                  </div>
                  {r.has_file && r.filename && (
                    <a
                      href={getReportDownloadUrl(r.filename)}
                      className="text-xs bg-primary text-primary-contrast px-3 py-1 rounded hover:opacity-90"
                      download
                    >
                      ⬇ Download
                    </a>
                  )}
                </div>
              </div>
            )
          })}
          {reports.length === 0 && (
            <div className="col-span-full">
              <EmptyState title="No report data available yet" description="Reports run on schedule and appear here once complete." />
            </div>
          )}
        </div>
      </div>
    </div>
  )
}
