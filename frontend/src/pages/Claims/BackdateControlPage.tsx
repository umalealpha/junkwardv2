import { useEffect, useMemo, useState } from 'react'
import { useNavigate } from 'react-router-dom'
import {
  useCanManageBackdate,
  useBackdateSettings,
  useSaveBackdateSettings,
  useBackdateGrants,
  useCreateBackdateGrant,
  useRevokeBackdateGrant,
  useBackdateEvents,
  useBackdateRequests,
  useDecideBackdateRequest,
} from '../../hooks/useClaimsBackdate'
import type { BackdateGrant, BackdateRequest } from '../../api/claimsBackdate'
import LoadingSpinner from '../../components/common/LoadingSpinner'
import EmptyState from '../../components/common/EmptyState'
import { useToast } from '../../components/common/Toast'
import { fmtDate, fmtDateTime } from '../../utils/format'

const ALL_MANAGERS = 'ALL_CLAIMS_MANAGERS'

function Card({ title, subtitle, children, right }: { title: string; subtitle?: string; children: React.ReactNode; right?: React.ReactNode }) {
  return (
    <section className="bg-surface rounded-lg border border-line shadow-elev-sm p-5">
      <div className="flex items-start justify-between gap-3 mb-4 flex-wrap">
        <div>
          <h2 className="text-sm font-semibold text-ink-muted uppercase tracking-wide">{title}</h2>
          {subtitle && <p className="text-xs text-ink-faint mt-0.5">{subtitle}</p>}
        </div>
        {right}
      </div>
      {children}
    </section>
  )
}

function StatusPill({ status }: { status: BackdateRequest['status'] }) {
  const cls =
    status === 'approved' ? 'bg-status-success-bg text-status-success-fg'
    : status === 'denied' ? 'bg-status-danger-bg text-status-danger-fg'
    : status === 'expired' ? 'bg-surface-2 text-ink-muted'
    : 'bg-status-warning-bg text-status-warning-fg'
  return <span className={`inline-block px-2 py-0.5 rounded-full text-[11px] font-medium capitalize ${cls}`}>{status}</span>
}

// ── Settings card ────────────────────────────────────────────────────────────
function SettingsCard() {
  const { toast } = useToast()
  const settings = useBackdateSettings()
  const save = useSaveBackdateSettings()

  const [maxDays, setMaxDays] = useState('')
  const [webhook, setWebhook] = useState('')
  const [recipients, setRecipients] = useState('')

  useEffect(() => {
    if (!settings.data) return
    setMaxDays(String(settings.data.max_days_back))
    setWebhook(settings.data.teams_webhook)
    setRecipients(settings.data.alert_recipients)
  }, [settings.data])

  if (settings.isLoading) return <Card title="Settings"><div className="py-6 flex justify-center"><LoadingSpinner /></div></Card>
  const d = settings.data
  if (!d) return <Card title="Settings"><p className="text-sm text-status-danger-fg">Could not load settings.</p></Card>

  async function handleSave() {
    try {
      await save.mutateAsync({
        max_days_back: Number(maxDays),
        teams_webhook: webhook.trim() || null,
        alert_recipients: recipients.trim() || null,
      })
      toast.success('Backdate settings saved.')
    } catch (err: any) {
      toast.error(err?.response?.data?.error || 'Failed to save settings.')
    }
  }

  return (
    <Card
      title="Settings"
      subtitle="The floor is fixed to the current financial year start. Alerts are only sent while enforcement is ON and a channel is configured."
    >
      <div className="grid grid-cols-1 md:grid-cols-2 gap-x-6 gap-y-4">
        <div>
          <label htmlFor="bd-max" className="block text-[11px] font-medium text-ink-muted mb-1">Maximum days back (cap)</label>
          <input
            id="bd-max"
            type="number"
            min={d.max_days_back_min}
            max={d.max_days_back_max}
            value={maxDays}
            onChange={(e) => setMaxDays(e.target.value)}
            className="w-full min-h-[44px] md:min-h-0 px-2.5 py-2 text-sm rounded-md bg-surface text-ink border border-line focus:outline-none focus:ring-1 focus:ring-primary"
          />
          <p className="text-[11px] text-ink-faint mt-1">Between {d.max_days_back_min} and {d.max_days_back_max} days.</p>
        </div>
        <div>
          <label className="block text-[11px] font-medium text-ink-muted mb-1">Financial-year floor (fixed)</label>
          <p className="text-sm text-ink min-h-[44px] md:min-h-0 flex items-center">{fmtDate(d.fy_start)}</p>
          <p className="text-[11px] text-ink-faint mt-1">Nothing may be backdated before this date. Today: {fmtDate(d.today)}.</p>
        </div>
        <div className="md:col-span-2">
          <label htmlFor="bd-webhook" className="block text-[11px] font-medium text-ink-muted mb-1">Teams webhook URL (optional)</label>
          <input
            id="bd-webhook"
            type="url"
            placeholder="https://…"
            value={webhook}
            onChange={(e) => setWebhook(e.target.value)}
            className="w-full min-h-[44px] md:min-h-0 px-2.5 py-2 text-sm rounded-md bg-surface text-ink border border-line focus:outline-none focus:ring-1 focus:ring-primary"
          />
        </div>
        <div className="md:col-span-2">
          <label htmlFor="bd-recip" className="block text-[11px] font-medium text-ink-muted mb-1">Alert recipients (comma-separated emails, optional)</label>
          <input
            id="bd-recip"
            type="text"
            placeholder="claims-lead@alphadirect.co.bw, cfo@…"
            value={recipients}
            onChange={(e) => setRecipients(e.target.value)}
            className="w-full min-h-[44px] md:min-h-0 px-2.5 py-2 text-sm rounded-md bg-surface text-ink border border-line focus:outline-none focus:ring-1 focus:ring-primary"
          />
        </div>
      </div>
      <div className="mt-4 flex justify-end">
        <button
          onClick={handleSave}
          disabled={save.isPending}
          className="px-3 py-1.5 text-xs font-semibold rounded-md bg-primary text-primary-contrast hover:opacity-90 transition disabled:opacity-50"
        >
          {save.isPending ? 'Saving…' : 'Save settings'}
        </button>
      </div>
    </Card>
  )
}

// ── Pending requests card ────────────────────────────────────────────────────
function PendingRequestsCard() {
  const { toast } = useToast()
  const requests = useBackdateRequests({ status: 'pending' })
  const decide = useDecideBackdateRequest()
  const [notes, setNotes] = useState<Record<number, string>>({})

  async function act(id: number, action: 'approve' | 'deny') {
    try {
      await decide.mutateAsync({ id, action, note: notes[id] })
      toast.success(action === 'approve' ? 'Request approved — grant issued.' : 'Request denied.')
    } catch (err: any) {
      toast.error(err?.response?.data?.error || 'Failed to record the decision.')
    }
  }

  return (
    <Card title="Pending Requests" subtitle="Approving a request issues a time-limited grant to the requester.">
      {requests.isLoading ? (
        <div className="py-6 flex justify-center"><LoadingSpinner /></div>
      ) : (requests.data ?? []).length === 0 ? (
        <EmptyState compact title="No pending requests" description="Requests awaiting a decision will appear here." />
      ) : (
        <div className="space-y-3">
          {(requests.data ?? []).map((r) => (
            <div key={r.id} className="border border-line rounded-md p-3">
              <div className="flex items-start justify-between gap-3 flex-wrap">
                <div>
                  <div className="text-sm font-medium text-ink">
                    {r.requester_name || r.requester_username}
                    <span className="text-ink-faint font-normal"> · {r.requester_role || 'unknown role'}</span>
                    {r.urgency === 'urgent' && <span className="ml-2 text-[10px] uppercase font-bold text-status-danger-fg">Urgent</span>}
                    <span className="ml-2"><StatusPill status={r.status} /></span>
                  </div>
                  <div className="text-xs text-ink-muted mt-0.5">
                    {r.duration_hours}h window · {r.claim_ids.length} claim(s){r.claim_numbers ? ` · ${r.claim_numbers}` : ''}
                  </div>
                  <p className="text-xs text-ink mt-1 whitespace-pre-wrap break-words">{r.reason}</p>
                  <p className="text-[11px] text-ink-faint mt-1">Submitted {r.created_at ? fmtDateTime(r.created_at) : '—'}</p>
                </div>
                <div className="flex flex-col items-end gap-2 min-w-[220px]">
                  <input
                    type="text"
                    placeholder="Decision note (optional)"
                    value={notes[r.id] ?? ''}
                    onChange={(e) => setNotes((s) => ({ ...s, [r.id]: e.target.value }))}
                    className="w-full px-2 py-1.5 text-xs rounded-md bg-surface text-ink border border-line focus:outline-none focus:ring-1 focus:ring-primary"
                  />
                  <div className="flex gap-2">
                    <button
                      onClick={() => act(r.id, 'deny')}
                      disabled={decide.isPending}
                      className="px-3 py-1.5 text-xs font-medium rounded-md border border-line text-ink-muted hover:bg-surface-2 transition disabled:opacity-50"
                    >
                      Deny
                    </button>
                    <button
                      onClick={() => act(r.id, 'approve')}
                      disabled={decide.isPending}
                      className="px-3 py-1.5 text-xs font-semibold rounded-md bg-primary text-primary-contrast hover:opacity-90 transition disabled:opacity-50"
                    >
                      Approve
                    </button>
                  </div>
                </div>
              </div>
            </div>
          ))}
        </div>
      )}
    </Card>
  )
}

// ── Grants card ────────────────────────────────────────────────────────────
function GrantsCard() {
  const { toast } = useToast()
  const grants = useBackdateGrants()
  const create = useCreateBackdateGrant()
  const revoke = useRevokeBackdateGrant()

  const [allManagers, setAllManagers] = useState(true)
  const [userId, setUserId] = useState('')
  const [durationDays, setDurationDays] = useState('1')
  const [reason, setReason] = useState('')

  const rows = grants.data ?? []
  const active = useMemo(() => rows.filter((g) => g.active), [rows])
  const history = useMemo(() => rows.filter((g) => !g.active), [rows])

  async function handleCreate() {
    const target = allManagers ? ALL_MANAGERS : userId.trim()
    if (!target) { toast.error('Enter a target user id or choose all managers.'); return }
    if (reason.trim().length < 10) { toast.error('Reason must be at least 10 characters.'); return }
    try {
      await create.mutateAsync({ target_user_id: target, duration_days: Number(durationDays), reason: reason.trim() })
      toast.success('Grant issued.')
      setReason(''); setUserId('')
    } catch (err: any) {
      toast.error(err?.response?.data?.error || 'Failed to issue grant.')
    }
  }

  async function handleRevoke(g: BackdateGrant) {
    try {
      await revoke.mutateAsync(g.id)
      toast.success('Grant revoked.')
    } catch (err: any) {
      toast.error(err?.response?.data?.error || 'Failed to revoke grant.')
    }
  }

  return (
    <Card title="Grants" subtitle="Time-limited permission for a request-role user (or all of them) to backdate.">
      {/* Issue a grant */}
      <div className="border border-line rounded-md p-3 mb-4">
        <div className="grid grid-cols-1 md:grid-cols-4 gap-3 items-end">
          <div className="md:col-span-2">
            <label className="block text-[11px] font-medium text-ink-muted mb-1">Target</label>
            <label className="flex items-center gap-2 text-sm text-ink mb-1.5">
              <input type="checkbox" checked={allManagers} onChange={(e) => setAllManagers(e.target.checked)} />
              All Claims Managers
            </label>
            {!allManagers && (
              <input
                type="text"
                placeholder="User id"
                value={userId}
                onChange={(e) => setUserId(e.target.value)}
                className="w-full px-2.5 py-2 text-sm rounded-md bg-surface text-ink border border-line focus:outline-none focus:ring-1 focus:ring-primary"
              />
            )}
          </div>
          <div>
            <label htmlFor="bd-dur" className="block text-[11px] font-medium text-ink-muted mb-1">Duration (days)</label>
            <select
              id="bd-dur"
              value={durationDays}
              onChange={(e) => setDurationDays(e.target.value)}
              className="w-full min-h-[44px] md:min-h-0 px-2.5 py-2 text-sm rounded-md bg-surface text-ink border border-line focus:outline-none focus:ring-1 focus:ring-primary"
            >
              {[1, 2, 3, 4, 5, 6, 7].map((n) => <option key={n} value={n}>{n} day{n === 1 ? '' : 's'}</option>)}
            </select>
          </div>
          <div>
            <button
              onClick={handleCreate}
              disabled={create.isPending}
              className="w-full px-3 py-2 text-xs font-semibold rounded-md bg-primary text-primary-contrast hover:opacity-90 transition disabled:opacity-50"
            >
              {create.isPending ? 'Issuing…' : 'Issue grant'}
            </button>
          </div>
        </div>
        <div className="mt-3">
          <label htmlFor="bd-reason" className="block text-[11px] font-medium text-ink-muted mb-1">Reason (min 10 chars)</label>
          <input
            id="bd-reason"
            type="text"
            value={reason}
            onChange={(e) => setReason(e.target.value)}
            className="w-full px-2.5 py-2 text-sm rounded-md bg-surface text-ink border border-line focus:outline-none focus:ring-1 focus:ring-primary"
          />
        </div>
      </div>

      {grants.isLoading ? (
        <div className="py-6 flex justify-center"><LoadingSpinner /></div>
      ) : (
        <>
          <h3 className="text-xs font-semibold text-ink uppercase tracking-wide mb-2">Active grants</h3>
          {active.length === 0 ? (
            <EmptyState compact title="No active grants" description="No time-limited backdate windows are currently open." />
          ) : (
            <div className="overflow-x-auto mb-5">
              <table className="w-full text-sm">
                <thead className="text-xs text-ink-muted uppercase">
                  <tr>
                    <th className="text-left py-1">Target</th>
                    <th className="text-left">Granted by</th>
                    <th className="text-left">Expires</th>
                    <th className="text-left">Reason</th>
                    <th className="text-right"></th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-line">
                  {active.map((g) => (
                    <tr key={g.id}>
                      <td className="py-1.5 text-ink">{g.target_label}</td>
                      <td className="text-ink-muted">{g.granted_by}</td>
                      <td className="text-ink-muted">{g.expires_at ? fmtDateTime(g.expires_at) : '—'}</td>
                      <td className="text-ink-muted max-w-[280px] truncate" title={g.reason ?? ''}>{g.reason}</td>
                      <td className="text-right">
                        <button
                          onClick={() => handleRevoke(g)}
                          disabled={revoke.isPending}
                          className="px-2.5 py-1 text-[11px] font-medium rounded-md border border-line text-status-danger-fg hover:bg-surface-2 transition disabled:opacity-50"
                        >
                          Revoke
                        </button>
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          )}

          <h3 className="text-xs font-semibold text-ink uppercase tracking-wide mb-2">Grant history</h3>
          {history.length === 0 ? (
            <EmptyState compact title="No past grants" description="Expired / revoked grants will appear here." />
          ) : (
            <div className="overflow-x-auto">
              <table className="w-full text-sm">
                <thead className="text-xs text-ink-muted uppercase">
                  <tr>
                    <th className="text-left py-1">Target</th>
                    <th className="text-left">Granted by</th>
                    <th className="text-left">Granted</th>
                    <th className="text-left">Expired / revoked</th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-line">
                  {history.map((g) => (
                    <tr key={g.id}>
                      <td className="py-1.5 text-ink">{g.target_label}</td>
                      <td className="text-ink-muted">{g.granted_by}</td>
                      <td className="text-ink-muted">{g.granted_at ? fmtDate(g.granted_at) : '—'}</td>
                      <td className="text-ink-muted">
                        {g.revoked_at ? `Revoked ${fmtDateTime(g.revoked_at)}${g.revoked_by ? ` by ${g.revoked_by}` : ''}` : (g.expires_at ? `Expired ${fmtDateTime(g.expires_at)}` : '—')}
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          )}
        </>
      )}
    </Card>
  )
}

// ── Recent events card ────────────────────────────────────────────────────────
function EventsCard() {
  const events = useBackdateEvents()
  const rows = events.data ?? []

  return (
    <Card title="Recent Backdate Events" subtitle="Every successful backdate of a claim stage date (most recent first).">
      {events.isLoading ? (
        <div className="py-6 flex justify-center"><LoadingSpinner /></div>
      ) : rows.length === 0 ? (
        <EmptyState compact title="No backdate events" description="Recorded backdates will appear here once enforcement is on." />
      ) : (
        <div className="overflow-x-auto">
          <table className="w-full text-sm">
            <thead className="text-xs text-ink-muted uppercase">
              <tr>
                <th className="text-left py-1">When</th>
                <th className="text-left">Claim</th>
                <th className="text-left">By</th>
                <th className="text-left">Grant</th>
                <th className="text-left">Changes</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-line">
              {rows.map((e) => (
                <tr key={e.id}>
                  <td className="py-1.5 text-ink-muted whitespace-nowrap">{e.created_at ? fmtDateTime(e.created_at) : '—'}</td>
                  <td className="text-ink">{e.claim_number || e.claim_id}</td>
                  <td className="text-ink-muted">{e.username}<span className="text-ink-faint"> · {e.user_role || '—'}</span></td>
                  <td className="text-ink-muted">{e.grant_id ? `#${e.grant_id}` : 'override'}</td>
                  <td className="text-ink-muted">
                    <div className="space-y-0.5">
                      {e.changes.map((c, i) => (
                        <div key={i} className="text-[11px]">
                          <span className="font-medium text-ink">{c.field}</span>: {c.old || '(none)'} → {c.new}
                        </div>
                      ))}
                    </div>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}
    </Card>
  )
}

// ── Page ────────────────────────────────────────────────────────────────────
export default function BackdateControlPage() {
  const navigate = useNavigate()
  const canManage = useCanManageBackdate()
  const settings = useBackdateSettings(canManage)

  if (!canManage) {
    return (
      <div className="p-8">
        <EmptyState
          title="No access"
          description="Backdate Control is available to administrators."
          action={<button onClick={() => navigate('/claims')} className="text-sm text-primary underline">Back to Claims</button>}
        />
      </div>
    )
  }

  const enforcementOn = !!settings.data?.enabled

  return (
    <div className="p-6 space-y-5">
      <div className="flex items-center justify-between flex-wrap gap-3">
        <div>
          <h1 className="font-heading text-2xl font-bold text-ink">Backdate Control</h1>
          <p className="text-xs text-ink-muted mt-0.5">Governs backdating of claim stage dates (Claims Tracker → Graphite).</p>
        </div>
        <span className={`inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-medium ${enforcementOn ? 'bg-status-success-bg text-status-success-fg' : 'bg-surface-2 text-ink-muted'}`}>
          <span className={`w-1.5 h-1.5 rounded-full ${enforcementOn ? 'bg-status-success-fg' : 'bg-ink-faint'}`} />
          Enforcement {enforcementOn ? 'ON' : 'OFF'}
        </span>
      </div>

      {!enforcementOn && (
        <div className="rounded-lg border border-status-warning-fg/30 bg-status-warning-bg px-4 py-3 text-sm text-status-warning-fg">
          <strong>Preview mode.</strong> Enforcement is off — claim date edits are not being validated and no alerts are sent.
          You can configure settings and grants here; turn enforcement on under Admin → Integrations (“Claims Backdate Governance”) when ready.
        </div>
      )}

      <PendingRequestsCard />
      <SettingsCard />
      <GrantsCard />
      <EventsCard />
    </div>
  )
}
