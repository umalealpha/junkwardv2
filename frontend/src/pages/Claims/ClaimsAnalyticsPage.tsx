import { useMemo, type ReactNode } from 'react'
import { useNavigate } from 'react-router-dom'
import {
  ResponsiveContainer,
  BarChart, Bar, XAxis, YAxis, CartesianGrid, Tooltip, Legend,
  PieChart, Pie, Cell,
} from 'recharts'
import { hasAnalyticsRole, useClaimsDashboard } from '../../hooks/useClaimsAnalytics'
import { useClaimsTrackerDashboard } from '../../hooks/useClaimsTrackerDashboard'
import { useTheme } from '../../hooks/useTheme'
import { chartTheme } from '../Dashboard/dashboardTheme'
import ClaimsChrome from './_chrome/ClaimsChrome'
import Card from '../../components/common/Card'
import EmptyState from '../../components/common/EmptyState'
import { Skeleton } from '../../components/common/Skeleton'

// ─── Chart colours ────────────────────────────────────────────────────────
// Positional categorical palette shared across the bar/pie charts. These are
// plain JS strings applied via recharts <Cell fill> / fill="…" props — NOT
// className/style — so the design-token guardrail leaves them alone.
const PALETTE = ['#0B1272', '#FF6600', '#1aab6d', '#e03c3c', '#e5a800', '#3b82f6', '#2b9cba', '#06b6d4', '#f43f5e']

// Claims-by-Status donut palette (blue/gold/red/green/orange, the tracker's set).
// NOTE: colours are applied POSITIONALLY over /claims-v2/dashboard's byStatus
// rows (raw claim status, no fixed order), so a slice's colour is not keyed to a
// specific status. A follow-up (statusDistribution over the SLA-derived
// overallStatus buckets) is planned for exact tracker parity.
const STATUS_COLORS = ['#3b82f6', '#e5a800', '#e03c3c', '#1aab6d', '#FF6600']
// Channel split — Broker navy, Direct orange.
const CHANNEL_COLORS: Record<string, string> = { Broker: '#0B1272', Direct: '#FF6600' }
// On-time vs delayed donut — green / red.
const ONTIME_COLORS = ['#1aab6d', '#e03c3c']
// Solid single-series bar fills.
const NAVY = '#0B1272'
const ORANGE = '#FF6600'

const MONTH_LABELS = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec']

// "YYYY-MM" → "Aug 2026".
function formatMonth(ym: string): string {
  const [y, m] = (ym || '').split('-')
  const idx = Number(m) - 1
  return `${MONTH_LABELS[idx] ?? m ?? ''} ${y ?? ''}`.trim()
}

/** A titled chart panel with consistent loading / error / empty handling. */
function ChartCard({
  title,
  subtitle,
  loading,
  error,
  empty,
  emptyNote,
  children,
  height = 220,
  className = '',
}: {
  title: string
  subtitle: string
  loading?: boolean
  error?: boolean
  empty?: boolean
  emptyNote?: string
  children?: ReactNode
  height?: number
  className?: string
}) {
  return (
    <Card
      className={className}
      padding="p-4"
      header={
        <div>
          <h2 className="font-heading text-sm font-bold text-brand-navy">{title}</h2>
          <p className="text-[11px] text-ink-faint mt-0.5">{subtitle}</p>
        </div>
      }
    >
      {loading ? (
        <div style={{ height }}><Skeleton className="h-full w-full rounded-lg" /></div>
      ) : error ? (
        <div className="flex items-center justify-center text-sm text-status-danger-fg" style={{ height }}>
          Could not load this chart.
        </div>
      ) : empty ? (
        <div style={{ height }}>
          <EmptyState compact title="No data" description={emptyNote ?? 'No data available for this chart.'} />
        </div>
      ) : (
        <div style={{ width: '100%', height }}>{children}</div>
      )}
    </Card>
  )
}

export default function ClaimsAnalyticsPage() {
  const navigate = useNavigate()
  const hasRole = hasAnalyticsRole()

  const { isDark } = useTheme()
  const chart = chartTheme(isDark)

  // Tracker dashboard aggregate — backs charts 2-7 + the chrome overdue banner.
  const tracker = useClaimsTrackerDashboard(null, hasRole)
  const dash = tracker.data
  // Claims-by-Status distribution comes from the claims-v2 dashboard summary.
  const status = useClaimsDashboard(hasRole)

  // 1 ── Claims by Status (donut) ──────────────────────────────────────────
  const byStatus = useMemo(
    () => (status.data?.byStatus ?? [])
      .filter((r) => r.status)
      .map((r) => ({ name: r.status, value: r.count })),
    [status.data],
  )

  // 2 ── Pipeline stage distribution (column) — the 8 motor stages ──────────
  const stageRows = useMemo(
    () => (dash?.pipeline.motor ?? []).map((s) => ({ name: s.stage, value: s.count })),
    [dash],
  )

  // 3 ── Channel split (donut) — Broker vs Direct ───────────────────────────
  const channelRows = useMemo(
    () => (dash?.channelSplit ?? []).map((c) => ({ name: c.channel, value: c.count })),
    [dash],
  )

  // 4 ── Claims by type (pie) ────────────────────────────────────────────────
  const typeRows = useMemo(
    () => (dash?.typeSummary ?? []).map((t) => ({ name: t.claimType, value: t.count })),
    [dash],
  )

  // 5 ── Monthly volume (full-width column) ─────────────────────────────────
  const monthly = useMemo(
    () => (dash?.monthlyVolume ?? []).map((m) => ({ name: formatMonth(m.month), value: m.count })),
    [dash],
  )

  // 6 ── On-time vs delayed (donut) — summed across motor + glass stages ────
  const onTimeVsDelayed = useMemo(() => {
    const stages = [...(dash?.pipeline.motor ?? []), ...(dash?.pipeline.glass ?? [])]
    const onTime = stages.reduce((n, s) => n + (s.onTime ?? 0), 0)
    const delayed = stages.reduce((n, s) => n + (s.delayed ?? 0), 0)
    return { rows: [{ name: 'On Time', value: onTime }, { name: 'Delayed', value: delayed }], total: onTime + delayed }
  }, [dash])

  // 7 ── Top handlers by claim load (horizontal bar) — top 5 ────────────────
  const topHandlers = useMemo(
    () => [...(dash?.leaderboard ?? [])]
      .sort((a, b) => b.total - a.total)
      .slice(0, 5)
      .map((r) => ({ name: r.handler, value: r.total })),
    [dash],
  )

  const tooltipProps = {
    contentStyle: chart.tooltip,
    labelStyle: { color: chart.tooltipLabel },
    itemStyle: { color: chart.tooltipLabel },
    cursor: { fill: chart.cursor },
  }
  const legendStyle = { fontSize: '12px', color: chart.axis }

  if (!hasRole) {
    return (
      <div className="p-8">
        <EmptyState
          title="No access"
          description="Claims analytics is available to claims handlers, managers and administrators."
          action={<button onClick={() => navigate('/claims')} className="text-sm text-primary underline cursor-pointer">Back to Claims</button>}
        />
      </div>
    )
  }

  return (
    <div className="space-y-5 p-6">
      {/* Claims Tracker replica chrome: action bar + tab strip + overdue banner */}
      <ClaimsChrome
        overdueCount={dash?.kpis.overdueStages}
        onRefresh={tracker.refetch}
        refreshing={tracker.isFetching}
      />

      <div className="grid grid-cols-1 md:grid-cols-2 gap-5">
        {/* 1 ── Claims by Status ─────────────────────────────────────────── */}
        <ChartCard
          title="Claims by Status"
          subtitle="Overall status distribution"
          loading={status.isLoading}
          error={status.isError}
          empty={byStatus.length === 0}
        >
          <ResponsiveContainer>
            <PieChart>
              <Pie data={byStatus} dataKey="value" nameKey="name" cx="50%" cy="50%" innerRadius={55} outerRadius={95} paddingAngle={2}>
                {byStatus.map((_, i) => <Cell key={i} fill={STATUS_COLORS[i % STATUS_COLORS.length]} />)}
              </Pie>
              <Tooltip {...tooltipProps} formatter={(v: number, n: string) => [v.toLocaleString(), n]} />
              <Legend wrapperStyle={legendStyle} />
            </PieChart>
          </ResponsiveContainer>
        </ChartCard>

        {/* 2 ── Pipeline Stage Distribution ──────────────────────────────── */}
        <ChartCard
          title="Pipeline Stage Distribution"
          subtitle="Active claims by current stage"
          loading={tracker.isLoading}
          error={tracker.isError}
          empty={stageRows.length === 0}
        >
          <ResponsiveContainer>
            <BarChart data={stageRows} margin={{ top: 5, right: 16, left: 0, bottom: 5 }}>
              <CartesianGrid strokeDasharray="3 3" stroke={chart.grid} />
              <XAxis dataKey="name" tick={{ fontSize: 10, fill: chart.axis }} stroke={chart.axis} interval={0} angle={-30} textAnchor="end" height={70} />
              <YAxis allowDecimals={false} tick={{ fontSize: 11, fill: chart.axis }} stroke={chart.axis} />
              <Tooltip {...tooltipProps} formatter={(v: number) => [v.toLocaleString(), 'Claims']} />
              <Bar dataKey="value" name="Claims" radius={[4, 4, 0, 0]}>
                {stageRows.map((_, i) => <Cell key={i} fill={PALETTE[i % PALETTE.length]} />)}
              </Bar>
            </BarChart>
          </ResponsiveContainer>
        </ChartCard>

        {/* 3 ── Channel Split ────────────────────────────────────────────── */}
        <ChartCard
          title="Channel Split"
          subtitle="Broker vs Direct"
          loading={tracker.isLoading}
          error={tracker.isError}
          empty={channelRows.length === 0}
        >
          <ResponsiveContainer>
            <PieChart>
              <Pie data={channelRows} dataKey="value" nameKey="name" cx="50%" cy="50%" innerRadius={55} outerRadius={95} paddingAngle={2}>
                {channelRows.map((r, i) => <Cell key={i} fill={CHANNEL_COLORS[r.name] ?? PALETTE[i % PALETTE.length]} />)}
              </Pie>
              <Tooltip {...tooltipProps} formatter={(v: number, n: string) => [v.toLocaleString(), n]} />
              <Legend wrapperStyle={legendStyle} />
            </PieChart>
          </ResponsiveContainer>
        </ChartCard>

        {/* 4 ── Claims by Type ───────────────────────────────────────────── */}
        <ChartCard
          title="Claims by Type"
          subtitle="Breakdown by category"
          loading={tracker.isLoading}
          error={tracker.isError}
          empty={typeRows.length === 0}
        >
          <ResponsiveContainer>
            <PieChart>
              <Pie data={typeRows} dataKey="value" nameKey="name" cx="50%" cy="50%" innerRadius={0} outerRadius={95} paddingAngle={2}>
                {typeRows.map((_, i) => <Cell key={i} fill={PALETTE[i % PALETTE.length]} />)}
              </Pie>
              <Tooltip {...tooltipProps} formatter={(v: number, n: string) => [v.toLocaleString(), n]} />
              <Legend wrapperStyle={legendStyle} />
            </PieChart>
          </ResponsiveContainer>
        </ChartCard>

        {/* 5 ── Monthly Volume (full width) ──────────────────────────────── */}
        <ChartCard
          title="Monthly Volume"
          subtitle="Claims registered per month"
          className="md:col-span-2"
          height={280}
          loading={tracker.isLoading}
          error={tracker.isError}
          empty={monthly.length === 0}
        >
          <ResponsiveContainer>
            <BarChart data={monthly} margin={{ top: 5, right: 16, left: 0, bottom: 5 }}>
              <CartesianGrid strokeDasharray="3 3" stroke={chart.grid} />
              <XAxis dataKey="name" tick={{ fontSize: 11, fill: chart.axis }} stroke={chart.axis} />
              <YAxis allowDecimals={false} tick={{ fontSize: 11, fill: chart.axis }} stroke={chart.axis} />
              <Tooltip {...tooltipProps} formatter={(v: number) => [v.toLocaleString(), 'Claims']} />
              <Bar dataKey="value" name="Claims" fill={NAVY} radius={[4, 4, 0, 0]} />
            </BarChart>
          </ResponsiveContainer>
        </ChartCard>

        {/* 6 ── On Time vs Delayed ───────────────────────────────────────── */}
        <ChartCard
          title="On Time vs Delayed"
          subtitle="Completed claims performance"
          loading={tracker.isLoading}
          error={tracker.isError}
          empty={onTimeVsDelayed.total === 0}
        >
          <ResponsiveContainer>
            <PieChart>
              <Pie data={onTimeVsDelayed.rows} dataKey="value" nameKey="name" cx="50%" cy="50%" innerRadius={55} outerRadius={95} paddingAngle={2}>
                {onTimeVsDelayed.rows.map((_, i) => <Cell key={i} fill={ONTIME_COLORS[i % ONTIME_COLORS.length]} />)}
              </Pie>
              <Tooltip {...tooltipProps} formatter={(v: number, n: string) => [v.toLocaleString(), n]} />
              <Legend wrapperStyle={legendStyle} />
            </PieChart>
          </ResponsiveContainer>
        </ChartCard>

        {/* 7 ── Top Handlers — Claim Load ────────────────────────────────── */}
        <ChartCard
          title="Top Handlers — Claim Load"
          subtitle="Claims per handler"
          loading={tracker.isLoading}
          error={tracker.isError}
          empty={topHandlers.length === 0}
        >
          <ResponsiveContainer>
            <BarChart data={topHandlers} layout="vertical" margin={{ top: 5, right: 16, left: 10, bottom: 5 }}>
              <CartesianGrid strokeDasharray="3 3" stroke={chart.grid} horizontal={false} />
              <XAxis type="number" allowDecimals={false} tick={{ fontSize: 11, fill: chart.axis }} stroke={chart.axis} />
              <YAxis type="category" dataKey="name" width={140} tick={{ fontSize: 10, fill: chart.axis }} stroke={chart.axis} />
              <Tooltip {...tooltipProps} formatter={(v: number) => [v.toLocaleString(), 'Claims']} />
              <Bar dataKey="value" name="Claims" fill={ORANGE} radius={[0, 4, 4, 0]} />
            </BarChart>
          </ResponsiveContainer>
        </ChartCard>
      </div>
    </div>
  )
}
