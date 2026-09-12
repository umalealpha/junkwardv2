import { useState, useEffect, useCallback } from 'react'
import { useNavigate } from 'react-router-dom'
import { useDashboardStats } from '../../hooks/useDashboardStats'
import { fetchSalesPerformance, type SalesPerformanceData, type PerformanceEntry, type ProductBreakdown } from '../../api/dashboard'
import type { ProductCount } from '../../api/dashboard'
import { useTheme } from '../../hooks/useTheme'
import { fmtPulaCompact } from '../../utils/format'
import Button from '../../components/common/Button'
import EmptyState from '../../components/common/EmptyState'
import { Skeleton } from '../../components/common/Skeleton'
import {
  PRODUCT_TILE_META, chartTheme, seriesColors, type ProductKey,
} from './dashboardTheme'
import {
  BarChart, Bar, XAxis, YAxis, CartesianGrid, Tooltip, Legend,
  ResponsiveContainer, Area, AreaChart,
} from 'recharts'

// ─── Month name helper ──────────────────────────────────────────────────────
const MONTHS = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec']
function formatPeriod(p: string): string {
  if (p.includes('-W')) return p // week label
  const [y, m] = p.split('-')
  return `${MONTHS[parseInt(m, 10) - 1]} ${y.slice(2)}`
}
function formatPula(v: number): string {
  return 'P ' + v.toLocaleString('en-BW', { maximumFractionDigits: 0 })
}

// ─── Main Page ──────────────────────────────────────────────────────────────
export default function DashboardPage() {
  const { data, isLoading, error } = useDashboardStats()
  const navigate = useNavigate()
  const { isDark } = useTheme()
  const chart = chartTheme(isDark)

  const [perf, setPerf] = useState<SalesPerformanceData | null>(null)
  const [perfLoading, setPerfLoading] = useState(true)
  const [perfError, setPerfError] = useState<string | null>(null)
  const [view, setView] = useState<'month' | 'week'>('month')
  const [productId, setProductId] = useState('all')
  const [graphMode, setGraphMode] = useState<'policies' | 'premium'>('policies')
  const [period, setPeriod] = useState<'fy' | 'this_month' | 'last_month'>('fy')

  // UAT 2026-05-26 (Prathap BUG-019): chart was stuck on "Loading chart
  // data…" indefinitely because catch{} silently swallowed errors and
  // there was no timeout on the underlying fetch. Now timeouts on the
  // apiClient surface as exceptions, and we track them in perfError so
  // the chart panel renders a clear error with a retry button rather
  // than an infinite spinner. The QueryCache.onError reporter will
  // notify developers@theriskco.com in parallel.
  const loadPerf = useCallback(async () => {
    setPerfLoading(true)
    setPerf(null)
    setPerfError(null)
    try {
      const d = await fetchSalesPerformance(view, productId, period)
      setPerf(d)
    } catch (e: any) {
      setPerfError(e?.message ?? 'Could not load chart data — the team has been notified.')
    } finally {
      setPerfLoading(false)
    }
  }, [view, productId, period])

  useEffect(() => { loadPerf() }, [loadPerf])

  if (isLoading) {
    return (
      <div className="p-6 space-y-6">
        {/* Skeleton: product tiles */}
        <div className="grid grid-cols-1 md:grid-cols-3 gap-6">
          {[1,2,3].map(i => <Skeleton key={i} className="h-44 rounded-xl" />)}
        </div>
        {/* Skeleton: stat cards */}
        <div className="grid grid-cols-2 md:grid-cols-4 gap-4">
          {[1,2,3,4].map(i => <Skeleton key={i} className="h-24 rounded-lg" />)}
        </div>
        {/* Skeleton: claims cards */}
        <div className="grid grid-cols-2 md:grid-cols-6 gap-4">
          {[1,2,3,4,5,6].map(i => <Skeleton key={i} className="h-24 rounded-lg" />)}
        </div>
        {/* Skeleton: graph */}
        <Skeleton className="h-80 rounded-xl" />
        {/* Skeleton: leaderboards */}
        <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
          {[1,2].map(i => <Skeleton key={i} className="h-72 rounded-xl" />)}
        </div>
      </div>
    )
  }

  if (error || !data) {
    return <div className="p-6 text-status-danger-fg">Failed to load dashboard stats.</div>
  }

  const productTiles: { key: ProductKey; data: ProductCount }[] = [
    { key: 'commercial', data: data.products.commercial },
    { key: 'domestic',   data: data.products.domestic },
    { key: 'instant',    data: data.products.instant },
  ]

  const graphData = (perf?.salesGraph ?? []).map(p => ({
    ...p,
    label: formatPeriod(p.period),
    premium: Math.round(Number(p.premium)),
  }))

  return (
    <div className="p-6 space-y-6">
      <div className="flex items-center justify-between">
        <div>
          <h1 className="font-heading text-2xl font-bold text-ink">Sales Dashboard</h1>
          {perf && <p className="text-xs text-ink-faint mt-0.5">Financial Year from {perf.fyStart}</p>}
        </div>
      </div>

      {/* ── Product Tiles ── */}
      <div className="grid grid-cols-1 md:grid-cols-3 gap-6">
        {productTiles.map((tile) => {
          const meta = PRODUCT_TILE_META[tile.key]
          return (
            <button
              key={tile.key}
              onClick={() => {
                const params = tile.data.product_id
                  ? `?product_id=${tile.data.product_id}`
                  : '?product_id=instant'
                navigate(`/policies${params}`)
              }}
              className="relative overflow-hidden rounded-xl border border-line bg-surface shadow-elev-sm hover:shadow-elev-md transition-all duration-200 text-left group"
            >
              <div className={`bg-gradient-to-r ${meta.gradient} px-5 py-4 flex items-center gap-3`}>
                <svg className="w-6 h-6 text-white/80" fill="none" stroke="currentColor" viewBox="0 0 24 24" strokeWidth={1.5}>
                  <path strokeLinecap="round" strokeLinejoin="round" d={meta.icon} />
                </svg>
                <div>
                  <h3 className="text-white font-semibold text-lg">{meta.label}</h3>
                  <p className="text-white/70 text-xs">{tile.data.total.toLocaleString()} total policies</p>
                </div>
                <svg className="w-5 h-5 text-white/50 ml-auto group-hover:translate-x-1 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 5l7 7-7 7" />
                </svg>
              </div>
              <div className="grid grid-cols-3 divide-x divide-line px-1 py-4">
                <CountBadge label="Active" value={tile.data.active} className="text-status-success-fg" />
                <CountBadge label="In-Active" value={tile.data.inactive} className="text-ink-muted" />
                <CountBadge label="Cancelled" value={tile.data.cancelled} className="text-status-danger-fg" />
              </div>
            </button>
          )
        })}
      </div>

      {/* ── Policy & Claims summary ── */}
      <div className="grid grid-cols-2 md:grid-cols-4 gap-4">
        <StatCard label="Total Policies" value={data.policies.total} />
        <StatCard label="Active"         value={data.policies.active}    tone="success" />
        <StatCard label="In-Active"      value={data.policies.inactive} />
        <StatCard label="Cancelled"      value={data.policies.cancelled} tone="danger" />
        {/* Expired tile hidden: policies.status never set to 3 (Expired); count
            sourced from expired_policies_import instead. Hidden per request. */}
      </div>

      <div className="grid grid-cols-2 md:grid-cols-6 gap-4">
        <StatCard label="Total Claims" value={data.claims.total} />
        <StatCard label="Pending"      value={data.claims.pending}  tone="warning" />
        <StatCard label="Approved"     value={data.claims.approved} tone="success" />
        <StatCard label="Rejected"     value={data.claims.rejected} tone="danger" />
        <StatCard label="Closed"       value={data.claims.closed} />
        <StatCard label="Reopen"       value={data.claims.reopen}   tone="warning" />
      </div>

      {/* ═══════════════════════════════════════════════════════════════════════ */}
      {/* SALES PERFORMANCE SECTION                                               */}
      {/* ═══════════════════════════════════════════════════════════════════════ */}

      <div className="border-t border-line pt-6">
        <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 mb-5">
          <h2 className="text-xl font-bold text-ink">Sales Performance (FY)</h2>

          <div className="flex flex-wrap items-center gap-2">
            {/* Product filter */}
            <select
              value={productId}
              onChange={e => setProductId(e.target.value)}
              className="text-sm border border-line rounded-lg px-3 py-1.5 bg-surface text-ink focus:ring-2 focus:ring-primary/30 focus:border-primary outline-none"
            >
              <option value="all">All Products</option>
              {(perf?.products ?? []).map(p => (
                <option key={p.id} value={p.id}>{p.name}</option>
              ))}
            </select>

            {/* Month / Week toggle */}
            <div className="inline-flex rounded-lg border border-line overflow-hidden">
              <button
                onClick={() => setView('month')}
                className={`px-3 py-1.5 text-sm font-medium transition-colors ${view === 'month' ? 'bg-primary text-primary-contrast' : 'bg-surface text-ink-muted hover:bg-surface-2'}`}
              >Month</button>
              <button
                onClick={() => setView('week')}
                className={`px-3 py-1.5 text-sm font-medium transition-colors ${view === 'week' ? 'bg-primary text-primary-contrast' : 'bg-surface text-ink-muted hover:bg-surface-2'}`}
              >Week</button>
            </div>

            {/* Policies / Premium toggle */}
            <div className="inline-flex rounded-lg border border-line overflow-hidden">
              <button
                onClick={() => setGraphMode('policies')}
                className={`px-3 py-1.5 text-sm font-medium transition-colors ${graphMode === 'policies' ? 'bg-primary text-primary-contrast' : 'bg-surface text-ink-muted hover:bg-surface-2'}`}
              >Policies</button>
              <button
                onClick={() => setGraphMode('premium')}
                className={`px-3 py-1.5 text-sm font-medium transition-colors ${graphMode === 'premium' ? 'bg-primary text-primary-contrast' : 'bg-surface text-ink-muted hover:bg-surface-2'}`}
              >Premium</button>
            </div>
          </div>
        </div>

        {/* ── Sales Graph ── */}
        <div className="bg-surface rounded-xl border border-line shadow-elev-sm p-5 mb-6">
          <h3 className="text-sm font-semibold text-ink-muted mb-4">
            {graphMode === 'policies' ? 'New Policies' : 'Premium (P)'} — July {new Date().getFullYear() - (new Date().getMonth() < 6 ? 1 : 0)} to Date
          </h3>
          {perfLoading ? (
            <Skeleton className="h-80 rounded-lg" />
          ) : perfError ? (
            <EmptyState
              title="Could not load chart data"
              description={perfError}
              icon={
                <svg viewBox="0 0 24 24" fill="none" className="h-6 w-6" stroke="currentColor" strokeWidth={1.6} aria-hidden="true">
                  <path strokeLinecap="round" strokeLinejoin="round" d="M12 9v4m0 4h.01M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0Z" />
                </svg>
              }
              action={<Button variant="danger" size="sm" onClick={() => loadPerf()}>Retry</Button>}
            />
          ) : graphData.length === 0 ? (
            <EmptyState compact title="No data for this period" description="Try a different product filter or period." />
          ) : (
            <ResponsiveContainer width="100%" height={320}>
              {graphMode === 'policies' ? (
                <BarChart data={graphData} margin={{ top: 5, right: 20, left: 10, bottom: 5 }}>
                  <CartesianGrid strokeDasharray="3 3" stroke={chart.grid} />
                  <XAxis dataKey="label" tick={{ fontSize: 12, fill: chart.axis }} stroke={chart.axis} />
                  <YAxis
                    tick={{ fontSize: 12, fill: chart.axis }}
                    stroke={chart.axis}
                    domain={[0, (max: number) => Math.ceil(max / 1000) * 1000]}
                    tickFormatter={v => v >= 1000 ? `${(v / 1000).toFixed(0)}k` : String(v)}
                  />
                  <Tooltip
                    contentStyle={chart.tooltip}
                    labelStyle={{ color: chart.tooltipLabel }}
                    itemStyle={{ color: chart.tooltipLabel }}
                    cursor={{ fill: chart.cursor }}
                    formatter={(v: number, name: string) => [v.toLocaleString(), name]}
                  />
                  <Legend wrapperStyle={{ fontSize: '12px', color: chart.axis }} />
                  <Bar dataKey="policies" name="Total Sales" fill={seriesColors.total} radius={[4, 4, 0, 0]} />
                  <Bar dataKey="active" name="Active" fill={seriesColors.active} radius={[4, 4, 0, 0]} />
                  <Bar dataKey="cancelled" name="Cancelled" fill={seriesColors.cancelled} radius={[4, 4, 0, 0]} />
                </BarChart>
              ) : (
                <AreaChart data={graphData} margin={{ top: 5, right: 20, left: 10, bottom: 5 }}>
                  <defs>
                    <linearGradient id="premiumFill" x1="0" y1="0" x2="0" y2="1">
                      <stop offset="0%" stopColor={seriesColors.premium} stopOpacity={0.35} />
                      <stop offset="100%" stopColor={seriesColors.premium} stopOpacity={0.03} />
                    </linearGradient>
                  </defs>
                  <CartesianGrid strokeDasharray="3 3" stroke={chart.grid} />
                  <XAxis dataKey="label" tick={{ fontSize: 12, fill: chart.axis }} stroke={chart.axis} />
                  <YAxis
                    tick={{ fontSize: 12, fill: chart.axis }}
                    stroke={chart.axis}
                    domain={[0, (max: number) => Math.ceil(max / 100000) * 100000]}
                    tickFormatter={v => v >= 1000000 ? `P ${(v / 1000000).toFixed(1)}M` : `P ${(v / 1000).toFixed(0)}k`}
                  />
                  <Tooltip
                    contentStyle={chart.tooltip}
                    labelStyle={{ color: chart.tooltipLabel }}
                    itemStyle={{ color: chart.tooltipLabel }}
                    cursor={{ stroke: chart.cursor }}
                    formatter={(v: number) => [formatPula(v), 'Premium']}
                  />
                  <Area type="monotone" dataKey="premium" stroke={seriesColors.premium} fill="url(#premiumFill)" strokeWidth={2.5} name="Premium" />
                </AreaChart>
              )}
            </ResponsiveContainer>
          )}
        </div>

        {/* ── Period toggle for leaderboards ── */}
        <div className="flex items-center justify-between mb-4">
          <h3 className="text-base font-bold text-ink-muted">Agent & Store Performance</h3>
          <div className="inline-flex rounded-lg border border-line overflow-hidden">
            {([
              ['fy', 'FY to Date'],
              ['this_month', 'This Month'],
              ['last_month', 'Last Month'],
            ] as const).map(([val, label]) => (
              <button
                key={val}
                onClick={() => setPeriod(val)}
                className={`px-3 py-1.5 text-sm font-medium transition-colors ${period === val ? 'bg-primary text-primary-contrast' : 'bg-surface text-ink-muted hover:bg-surface-2'}`}
              >{label}</button>
            ))}
          </div>
        </div>

        {/* ── Agent & Store Leaderboards ── */}
        <div className="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
          <LeaderboardCard
            title="Top Performing Agents"
            subtitle={period === 'fy' ? 'Financial year to date' : period === 'this_month' ? 'This month' : 'Last month'}
            entries={perf?.topAgents ?? []}
            loading={perfLoading}
            variant="top"
          />
          <LeaderboardCard
            title="Top Performing Stores"
            subtitle={period === 'fy' ? 'Financial year to date' : period === 'this_month' ? 'This month' : 'Last month'}
            entries={perf?.topStores ?? []}
            loading={perfLoading}
            variant="top"
          />
        </div>

        <div className="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
          <LeaderboardCard
            title="Lowest Performing Agents"
            subtitle="Needs attention"
            entries={perf?.bottomAgents ?? []}
            loading={perfLoading}
            variant="bottom"
          />
          <LeaderboardCard
            title="Lowest Performing Stores"
            subtitle="Needs attention"
            entries={perf?.bottomStores ?? []}
            loading={perfLoading}
            variant="bottom"
          />
        </div>
      </div>

    </div>
  )
}

// ─── Sub-components ─────────────────────────────────────────────────────────

function CountBadge({ label, value, className }: { label: string; value: number; className: string }) {
  return (
    <div className="text-center px-2">
      <p className={`text-xl font-bold tabular-nums ${className}`}>{value.toLocaleString()}</p>
      <p className="text-[11px] text-ink-faint mt-0.5">{label}</p>
    </div>
  )
}

type StatTone = 'neutral' | 'success' | 'danger' | 'warning'

function StatCard({ label, value, tone = 'neutral' }: { label: string; value: number; tone?: StatTone }) {
  // Neutral cards are plain tokenized surfaces. Where the metric encodes
  // status (active=success, cancelled/rejected=danger, pending/reopen=warning)
  // we tint the NUMBER only — the card surface stays neutral so it reads in
  // both light and dark. The big figure is always full-weight tabular-nums.
  const numberTone: Record<StatTone, string> = {
    neutral: 'text-ink',
    success: 'text-status-success-fg',
    danger:  'text-status-danger-fg',
    warning: 'text-status-warning-fg',
  }
  return (
    <div className="rounded-lg border border-line bg-surface shadow-elev-sm p-4">
      <p className="text-sm text-ink-muted">{label}</p>
      <p className={`text-3xl font-bold tabular-nums mt-1 ${numberTone[tone]}`}>{value.toLocaleString()}</p>
    </div>
  )
}

function LeaderboardCard({
  title, subtitle, entries, loading, variant,
}: {
  title: string
  subtitle: string
  entries: PerformanceEntry[]
  loading: boolean
  variant: 'top' | 'bottom'
}) {
  const isTop = variant === 'top'
  // Standardized on brand-aligned gradients (same palette family as the
  // product tiles): top = brand navy→blue, bottom = brand orange.
  const headerColor = isTop ? 'from-brand-navy to-brand-navy-light' : 'from-brand-orange to-status-danger-fg'
  const badgeColors = isTop
    ? ['bg-status-warning-bg text-status-warning-fg', 'bg-surface-2 text-ink-muted', 'bg-brand-orange text-white']
    : []
  const [expandedId, setExpandedId] = useState<number | null>(null)

  return (
    <div className="bg-surface rounded-xl border border-line shadow-elev-sm overflow-hidden">
      <div className={`bg-gradient-to-r ${headerColor} px-5 py-3 flex items-center justify-between`}>
        <div>
          <h3 className="text-white font-semibold text-sm">{title}</h3>
          <p className="text-white/70 text-xs">{subtitle}</p>
        </div>
        {isTop && (
          <svg className="w-5 h-5 text-brand-orange-light" fill="currentColor" viewBox="0 0 20 20">
            <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z" />
          </svg>
        )}
      </div>

      {loading ? (
        <div className="p-4 space-y-3">
          {[1,2,3,4,5].map(i => <Skeleton key={i} className="h-12 rounded" />)}
        </div>
      ) : entries.length === 0 ? (
        <EmptyState compact title="No data for this period" />
      ) : (
        <div className="divide-y divide-line">
          {entries.map((e, i) => {
            const isExpanded = expandedId === e.id
            const prods: ProductBreakdown[] = e.products ?? []
            return (
              <div key={e.id}>
                <button
                  onClick={() => setExpandedId(isExpanded ? null : e.id)}
                  className="w-full flex items-center gap-3 px-5 py-3 hover:bg-surface-2 transition-colors text-left"
                >
                  {/* Rank */}
                  <div className="flex-shrink-0 w-7 text-center">
                    {isTop && i < 3 ? (
                      <span className={`inline-flex items-center justify-center w-6 h-6 rounded-full text-xs font-bold ${badgeColors[i]}`}>
                        {i + 1}
                      </span>
                    ) : (
                      <span className="text-xs font-semibold text-ink-faint">{i + 1}</span>
                    )}
                  </div>

                  {/* Name + stats */}
                  <div className="flex-1 min-w-0">
                    <p className="text-sm font-medium text-ink truncate">{e.name.trim() || `ID ${e.id}`}</p>
                    <div className="flex items-center gap-2 mt-0.5 flex-wrap">
                      <span className="text-xs text-status-success-fg font-medium">{Number(e.active).toLocaleString()} active</span>
                      <span className="text-xs text-ink-faint">|</span>
                      <span className="text-xs text-status-danger-fg">{Number(e.cancelled).toLocaleString()} cancelled</span>
                      <span className="text-xs text-ink-faint">|</span>
                      <span className="text-xs text-status-accent-fg font-medium tabular-nums">{fmtPulaCompact(Math.round(Number(e.premium)))}</span>
                    </div>
                  </div>

                  {/* Total + expand arrow */}
                  <div className="flex items-center gap-2 flex-shrink-0">
                    <div className="text-right">
                      <p className="text-lg font-bold text-ink tabular-nums">{Number(e.policies).toLocaleString()}</p>
                      <p className="text-[10px] text-ink-faint uppercase tracking-wide">policies</p>
                    </div>
                    <svg
                      className={`w-4 h-4 text-ink-faint transition-transform ${isExpanded ? 'rotate-180' : ''}`}
                      fill="none" stroke="currentColor" viewBox="0 0 24 24"
                    >
                      <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M19 9l-7 7-7-7" />
                    </svg>
                  </div>
                </button>

                {/* Expanded product breakdown */}
                {isExpanded && prods.length > 0 && (
                  <div className="bg-surface-2 px-5 pb-3 pt-1">
                    <table className="w-full text-xs">
                      <thead>
                        <tr className="text-ink-muted border-b border-line">
                          <th className="text-left py-1.5 font-semibold">Product</th>
                          <th className="text-right py-1.5 font-semibold">Policies</th>
                          <th className="text-right py-1.5 font-semibold">Premium</th>
                        </tr>
                      </thead>
                      <tbody>
                        {prods.slice(0, 8).map((p, pi) => (
                          <tr key={pi} className="border-b border-line last:border-0">
                            <td className="py-1.5 text-ink-muted">{p.product}</td>
                            <td className="py-1.5 text-right font-medium text-ink tabular-nums">{p.count.toLocaleString()}</td>
                            <td className="py-1.5 text-right text-ink-muted tabular-nums">{formatPula(Math.round(Number(p.premium)))}</td>
                          </tr>
                        ))}
                      </tbody>
                    </table>
                  </div>
                )}
              </div>
            )
          })}
        </div>
      )}
    </div>
  )
}
