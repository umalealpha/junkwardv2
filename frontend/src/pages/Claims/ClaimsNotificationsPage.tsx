import { useState } from 'react'
import { useNavigate } from 'react-router-dom'
import {
  ResponsiveContainer,
  BarChart, Bar, XAxis, YAxis, CartesianGrid, Tooltip, Legend,
} from 'recharts'
import {
  useClaimsNotificationsEnabled,
  useNotifOverview,
  useNotifByTrigger,
  useNotifDailyVolume,
  useNotifRecent,
  useNotifRecentFailures,
  CLAIMS_NOTIF_ROLES,
} from '../../hooks/useClaimsNotifications'
import { getStoredRoles } from '../../api/auth'
import {
  NOTIF_WINDOWS,
  statusMeta,
  type NotifWindow,
  type NotifMode,
  type NotifRow,
  type NotifStatusTone,
} from '../../api/claimsNotifications'
import LoadingSpinner from '../../components/common/LoadingSpinner'
import EmptyState from '../../components/common/EmptyState'

// recharts categorical fills — SMS (brand navy), Email (brand orange), Failed (danger).
const FILL_SMS = 'rgb(var(--primary))'
const FILL_EMAIL = 'rgb(var(--status-warning-fg))'
const FILL_FAILED = 'rgb(var(--status-danger-fg))'

const toneToChip: Record<NotifStatusTone, string> = {
  good: 'bg-status-success-bg text-status-success-fg',
  bad: 'bg-status-danger-bg text-status-danger-fg',
  warn: 'bg-status-warning-bg text-status-warning-fg',
  muted: 'bg-surface-2 text-ink-muted',
}

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

function StatusChip({ status }: { status: string }) {
  const meta = statusMeta(status)
  return <span className={`inline-block px-2 py-0.5 rounded-full text-[11px] font-medium ${toneToChip[meta.tone]}`}>{meta.label}</span>
}

function ChannelBadge({ channel }: { channel: string }) {
  const isSms = channel.toLowerCase() === 'sms'
  return (
    <span className={`inline-block px-1.5 py-0.5 rounded text-[10px] font-semibold uppercase tracking-wide ${isSms ? 'bg-primary/10 text-primary' : 'bg-surface-2 text-ink-muted'}`}>
      {channel}
    </span>
  )
}

// ── Mode / health banner ──────────────────────────────────────────────────────
function ModeBanner({ mode }: { mode: NotifMode }) {
  const meta =
    mode === 'live'
      ? { cls: 'bg-status-success-bg text-status-success-fg border-status-success-fg/20', dot: 'bg-status-success-fg', label: 'Live', desc: 'Recording claim notifications and showing them here.' }
      : mode === 'pilot'
      ? { cls: 'bg-status-warning-bg text-status-warning-fg border-status-warning-fg/20', dot: 'bg-status-warning-fg', label: 'Pilot', desc: 'Armed, but no claim notifications recorded in this window yet.' }
      : { cls: 'bg-surface-2 text-ink-muted border-line', dot: 'bg-ink-faint', label: 'Disabled', desc: 'Turned off under Admin → Integrations.' }
  return (
    <div className={`flex items-center gap-2.5 rounded-lg border px-3.5 py-2.5 text-sm ${meta.cls}`}>
      <span className={`w-2 h-2 rounded-full ${meta.dot}`} aria-hidden="true" />
      <span className="font-semibold">{meta.label}</span>
      <span className="opacity-80">— {meta.desc}</span>
    </div>
  )
}

// ── Window selector ───────────────────────────────────────────────────────────
function WindowSelector({ value, onChange }: { value: NotifWindow; onChange: (w: NotifWindow) => void }) {
  return (
    <div className="inline-flex rounded-lg border border-line overflow-hidden">
      {NOTIF_WINDOWS.map((w) => (
        <button
          key={w.key}
          type="button"
          onClick={() => onChange(w.key)}
          aria-pressed={value === w.key}
          className={`px-3 py-1.5 text-xs font-medium min-h-[36px] transition-colors cursor-pointer border-r border-line last:border-r-0 ${
            value === w.key ? 'bg-primary text-white' : 'bg-surface text-ink-muted hover:bg-surface-2'
          }`}
        >
          {w.label}
        </button>
      ))}
    </div>
  )
}

// ── Recent-sends / failures row rendering ──────────────────────────────────────
function SendRow({ r, showReason = false }: { r: NotifRow; showReason?: boolean }) {
  return (
    <tr>
      <td className="py-1.5 pr-2 whitespace-nowrap text-ink-muted tabular-nums text-xs">
        {r.created_at ? new Date(r.created_at).toLocaleString('en-GB', { day: '2-digit', month: 'short', hour: '2-digit', minute: '2-digit', hour12: false }) : '—'}
      </td>
      <td className="py-1.5 pr-2"><ChannelBadge channel={r.channel} /></td>
      <td className="py-1.5 pr-2 text-ink">{r.trigger_key}</td>
      <td className="py-1.5 pr-2 text-ink-muted font-mono text-xs">{r.recipient ?? '—'}</td>
      <td className="py-1.5 pr-2 text-ink-muted text-xs">
        {r.claim_number ? r.claim_number : r.claim_id != null ? `#${r.claim_id}` : '—'}
      </td>
      {showReason ? (
        <td className="py-1.5 pr-2 text-status-danger-fg text-xs">{r.reason ?? '—'}</td>
      ) : (
        <td className="py-1.5 pr-2"><StatusChip status={r.status} /></td>
      )}
    </tr>
  )
}

export default function ClaimsNotificationsPage() {
  const navigate = useNavigate()
  const enabled = useClaimsNotificationsEnabled()
  const hasRole = getStoredRoles().some((r) => CLAIMS_NOTIF_ROLES.includes(r))
  const canQuery = enabled && hasRole

  const [window, setWindow] = useState<NotifWindow>('7d')

  const overview = useNotifOverview(window, canQuery)
  const byTrigger = useNotifByTrigger(window, canQuery)
  const dailyVolume = useNotifDailyVolume(window, canQuery)
  const recent = useNotifRecent(window, canQuery)
  const failures = useNotifRecentFailures(window, canQuery)

  const ovStatus = (overview.error as { response?: { status?: number } } | undefined)?.response?.status
  const featureOff = !enabled || ovStatus === 404

  if (featureOff) {
    return (
      <div className="p-8">
        <EmptyState
          title="Claims Notifications is not enabled"
          description="This module is turned off. An administrator can enable it under Admin → Integrations to start recording and previewing claim notifications."
          action={<button onClick={() => navigate('/claims')} className="text-sm text-primary underline">Back to Claims</button>}
        />
      </div>
    )
  }

  if (!hasRole || ovStatus === 403) {
    return (
      <div className="p-8">
        <EmptyState
          title="No access"
          description="The Claims Notifications dashboard is available to Claims Managers and administrators."
          action={<button onClick={() => navigate('/claims')} className="text-sm text-primary underline">Back to Claims</button>}
        />
      </div>
    )
  }

  if (overview.isLoading) {
    return <div className="p-12 flex justify-center"><LoadingSpinner size="lg" /></div>
  }
  if (overview.isError) {
    return (
      <div className="p-8 text-center">
        <p className="text-status-danger-fg font-medium">Could not load the notifications dashboard.</p>
        <button onClick={() => overview.refetch()} className="mt-4 text-sm text-primary underline">Try again</button>
      </div>
    )
  }
  if (!overview.data) return null

  const ov = overview.data
  const s = ov.stats
  const volume = dailyVolume.data ?? []
  const triggers = byTrigger.data ?? []
  const fails = failures.data ?? []
  const sends = recent.data ?? []

  return (
    <div className="p-6 space-y-5">
      {/* Header + window selector */}
      <div className="flex items-center justify-between flex-wrap gap-3">
        <div>
          <h1 className="font-heading text-2xl font-bold text-ink">Claims Notifications</h1>
          <p className="text-xs text-ink-muted mt-0.5">Every claim SMS / email Graphite sent (Claims Tracker → Graphite). Read-only log — this screen never sends anything.</p>
        </div>
        <div className="flex items-center gap-3">
          <WindowSelector value={window} onChange={setWindow} />
          <span className="text-xs text-ink-faint hidden sm:inline">Updated {new Date(ov.generated_at).toLocaleTimeString('en-GB')}</span>
        </div>
      </div>

      <ModeBanner mode={ov.mode} />

      {/* Stat cards */}
      <div className="grid grid-cols-2 md:grid-cols-3 xl:grid-cols-6 gap-3">
        <StatCard label="Total Sent" value={s.total} />
        <StatCard label="Delivered" value={s.delivered} tone="good" />
        <StatCard label="Failed" value={s.failed} tone={s.failed > 0 ? 'bad' : undefined} />
        <StatCard label="Suppressed" value={s.suppressed} tone={s.suppressed > 0 ? 'warn' : undefined} />
        <StatCard label="Pending" value={s.pending} tone={s.pending > 0 ? 'warn' : undefined} />
        <StatCard label="Cost (BWP)" value={s.cost_units.toFixed(2)} />
      </div>
      <p className="text-[11px] text-ink-faint -mt-2">
        Channels: {ov.by_channel.sms} SMS · {ov.by_channel.email} email. Cost is an estimate (SMS units) for trend only — not a billing source. “Delivered” counts sends accepted by the provider; delivery-receipt reconciliation is not wired yet.
      </p>

      {/* By-trigger + Recent-failures side by side */}
      <div className="grid grid-cols-1 lg:grid-cols-2 gap-5">
        <section className="bg-surface rounded-lg border border-line shadow-elev-sm p-5">
          <h2 className="text-sm font-semibold text-ink-muted uppercase tracking-wide mb-3">By Trigger</h2>
          <div className="overflow-x-auto">
            <table className="w-full text-sm">
              <thead className="text-xs text-ink-muted uppercase">
                <tr>
                  <th className="text-left py-1">Trigger</th>
                  <th className="text-right">Total</th>
                  <th className="text-right">Delivered</th>
                  <th className="text-right">Failed</th>
                  <th className="text-right">Suppressed</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-line">
                {triggers.length === 0 ? (
                  <tr><td colSpan={5} className="p-0"><EmptyState compact title="No notifications" description="No claim notifications recorded in this window." /></td></tr>
                ) : triggers.map((t) => (
                  <tr key={t.trigger_key}>
                    <td className="py-1.5 text-ink">{t.trigger_key}</td>
                    <td className="text-right tabular-nums text-ink">{t.total}</td>
                    <td className="text-right tabular-nums text-status-success-fg">{t.delivered}</td>
                    <td className={`text-right tabular-nums ${t.failed > 0 ? 'text-status-danger-fg' : 'text-ink-muted'}`}>{t.failed}</td>
                    <td className="text-right tabular-nums text-ink-muted">{t.suppressed}</td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        </section>

        <section className="bg-surface rounded-lg border border-line shadow-elev-sm p-5">
          <h2 className="text-sm font-semibold text-ink-muted uppercase tracking-wide mb-3">Recent Failures</h2>
          {failures.isLoading ? (
            <div className="py-8 flex justify-center"><LoadingSpinner /></div>
          ) : (
            <div className="overflow-x-auto">
              <table className="w-full text-sm">
                <thead className="text-xs text-ink-muted uppercase">
                  <tr>
                    <th className="text-left py-1">When</th>
                    <th className="text-left">Ch</th>
                    <th className="text-left">Trigger</th>
                    <th className="text-left">To</th>
                    <th className="text-left">Claim</th>
                    <th className="text-left">Reason</th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-line">
                  {fails.length === 0 ? (
                    <tr><td colSpan={6} className="p-0"><EmptyState compact title="No failures" description="No failed claim notifications in this window." /></td></tr>
                  ) : fails.map((r) => <SendRow key={r.id} r={r} showReason />)}
                </tbody>
              </table>
            </div>
          )}
        </section>
      </div>

      {/* Daily volume */}
      <section className="bg-surface rounded-lg border border-line shadow-elev-sm p-5">
        <h2 className="text-sm font-semibold text-ink-muted uppercase tracking-wide mb-3">Daily Volume</h2>
        {volume.length === 0 ? (
          <EmptyState compact title="No volume" description="No claim notifications recorded in this window." />
        ) : (
          <div className="h-64 w-full">
            <ResponsiveContainer width="100%" height="100%">
              <BarChart data={volume} margin={{ top: 8, right: 8, bottom: 4, left: -16 }}>
                <CartesianGrid strokeDasharray="3 3" stroke="rgb(var(--line))" vertical={false} />
                <XAxis dataKey="day" tick={{ fontSize: 11, fill: 'rgb(var(--ink-muted))' }} tickFormatter={(d: string) => d.slice(5)} />
                <YAxis allowDecimals={false} tick={{ fontSize: 11, fill: 'rgb(var(--ink-muted))' }} />
                <Tooltip
                  contentStyle={{ fontSize: 12, borderRadius: 8, border: '1px solid rgb(var(--line))', background: 'rgb(var(--surface))', color: 'rgb(var(--ink))' }}
                  labelStyle={{ color: 'rgb(var(--ink))' }}
                />
                <Legend wrapperStyle={{ fontSize: 11 }} />
                <Bar dataKey="sms" name="SMS" stackId="a" fill={FILL_SMS} radius={[0, 0, 0, 0]} />
                <Bar dataKey="email" name="Email" stackId="a" fill={FILL_EMAIL} radius={[3, 3, 0, 0]} />
                <Bar dataKey="failed" name="Failed" fill={FILL_FAILED} radius={[3, 3, 0, 0]} />
              </BarChart>
            </ResponsiveContainer>
          </div>
        )}
      </section>

      {/* Recent sends log */}
      <section className="bg-surface rounded-lg border border-line shadow-elev-sm p-5">
        <div className="flex items-center justify-between mb-3 gap-2 flex-wrap">
          <h2 className="text-sm font-semibold text-ink-muted uppercase tracking-wide">Recent Sends</h2>
          <span className="text-[11px] text-ink-faint">Latest {sends.length}</span>
        </div>
        {recent.isLoading ? (
          <div className="py-8 flex justify-center"><LoadingSpinner /></div>
        ) : (
          <div className="overflow-x-auto">
            <table className="w-full text-sm">
              <thead className="text-xs text-ink-muted uppercase">
                <tr>
                  <th className="text-left py-1">When</th>
                  <th className="text-left">Ch</th>
                  <th className="text-left">Trigger</th>
                  <th className="text-left">To</th>
                  <th className="text-left">Claim</th>
                  <th className="text-left">Status</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-line">
                {sends.length === 0 ? (
                  <tr><td colSpan={6} className="p-0"><EmptyState compact title="No sends" description="No claim notifications recorded in this window." /></td></tr>
                ) : sends.map((r) => <SendRow key={r.id} r={r} />)}
              </tbody>
            </table>
          </div>
        )}
      </section>
    </div>
  )
}
