import { useState } from 'react'
import { Link, useSearchParams } from 'react-router-dom'
import { PaginationBar, useSearchParamsPagination } from './adGroupKycPagination'
import { useEmployerGroupKyc } from '../../hooks/useKyc'
import { useAdGroupKycCampaigns, useAdGroupKycDashboard, useCreateAdGroupKycCampaign } from '../../hooks/useAdGroupKyc'
import type { NotifyChannel } from '../../api/adGroupKyc'
import LoadingSpinner from '../../components/common/LoadingSpinner'
import EmptyState from '../../components/common/EmptyState'
import { fmtDate, fmtDateTime } from '../../utils/format'
import { CAMPAIGN_STATUS_PILL, ChannelChecks, Modal, StatusPill } from './adGroupKycShared'

// AD Group KYC campaign dashboard — V2 port of the V8 Blade admin index
// (admin/ad-group-kyc/index.blade.php): stat tiles, campaigns table,
// create-campaign modal, recent activities.

const REMINDER_OPTIONS = [3, 7, 14]

export default function ADGroupKycCampaignsPage() {
  const [searchParams] = useSearchParams()
  const [showCreate, setShowCreate] = useState(false)

  const filters = {
    search:   searchParams.get('search') || undefined,
    status:   searchParams.get('status') || undefined,
    per_page: 25,
    page:     Number(searchParams.get('page') || '1'),
  }

  const { data: dashboard } = useAdGroupKycDashboard()
  const { data, isLoading, isFetching } = useAdGroupKycCampaigns(filters)
  const { updateFilter, goToPage } = useSearchParamsPagination()

  const stats = dashboard?.stats

  return (
    <div className="p-6 space-y-4">
      <div className="flex items-center justify-between">
        <h1 className="text-2xl font-bold text-ink">AD Group KYC</h1>
        <button
          type="button"
          onClick={() => setShowCreate(true)}
          className="px-4 py-2 text-sm font-medium bg-primary text-primary-contrast rounded-md"
        >
          + Create Campaign
        </button>
      </div>

      {stats && (
        <>
          <div className="grid grid-cols-2 md:grid-cols-4 gap-3">
            <StatCard label="Total Campaigns" value={stats.total_campaigns} />
            <StatCard label="Active Campaigns" value={stats.active_campaigns} />
            <StatCard label="Total Links" value={stats.total_links} />
            <StatCard label="Completion Rate" value={`${stats.completion_rate}%`} />
          </div>
          <div className="grid grid-cols-3 md:grid-cols-6 gap-3">
            <StatCard small label="Sent" value={stats.sent_links} />
            <StatCard small label="Opened" value={stats.opened_links} />
            <StatCard small label="Completed" value={stats.completed_links} />
            <StatCard small label="Pending" value={stats.pending_links} />
            <StatCard small label="Expired" value={stats.expired_links} />
            <StatCard small label="Avg Response (min)" value={stats.response_time} />
          </div>
        </>
      )}

      <div className="flex flex-wrap gap-3 items-end">
        <div>
          <label className="block text-xs font-medium text-ink-muted mb-1">Search</label>
          <input
            type="text"
            placeholder="Campaign name, employer group..."
            defaultValue={filters.search}
            onKeyDown={e => { if (e.key === 'Enter') updateFilter('search', (e.target as HTMLInputElement).value) }}
            onBlur={e => updateFilter('search', e.target.value)}
            className="px-3 py-1.5 border border-line rounded-md text-sm w-64 bg-surface text-ink"
          />
        </div>
        <div>
          <label className="block text-xs font-medium text-ink-muted mb-1">Status</label>
          <select
            value={filters.status ?? ''}
            onChange={e => updateFilter('status', e.target.value)}
            className="px-3 py-1.5 border border-line rounded-md text-sm bg-surface text-ink"
          >
            <option value="">All</option>
            <option value="active">Active</option>
            <option value="paused">Paused</option>
            <option value="completed">Completed</option>
            <option value="draft">Draft</option>
          </select>
        </div>
      </div>

      <div className="bg-surface rounded-lg shadow-sm border border-line overflow-hidden relative">
        {(isLoading || isFetching) && (
          <div className="absolute inset-0 bg-surface/50 z-10 flex items-center justify-center">
            <LoadingSpinner size="md" />
          </div>
        )}
        <div className="overflow-x-auto">
          <table className="w-full text-sm">
            <thead className="bg-surface-2 text-ink-muted uppercase text-xs tracking-wider">
              <tr>
                <th className="px-4 py-3 text-left">ID</th>
                <th className="px-4 py-3 text-left">Name</th>
                <th className="px-4 py-3 text-left">Employer Group</th>
                <th className="px-4 py-3 text-left">Status</th>
                <th className="px-4 py-3 text-left">Links</th>
                <th className="px-4 py-3 text-left">Completion</th>
                <th className="px-4 py-3 text-left">Created</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-line">
              {isLoading ? (
                <tr><td colSpan={7} className="px-4 py-12 text-center"><LoadingSpinner size="md" /></td></tr>
              ) : data?.data.length === 0 ? (
                <tr><td colSpan={7} className="p-0"><EmptyState compact title="No campaigns yet" description="Create a campaign to start collecting KYC for an employer group." /></td></tr>
              ) : (
                data?.data.map(c => (
                  <tr key={c.id} className="hover:bg-surface-2 transition">
                    <td className="px-4 py-2 text-ink-muted">{c.id}</td>
                    <td className="px-4 py-2">
                      <Link to={`/kyc/ad-group/campaigns/${c.id}`} className="font-medium text-primary hover:underline">{c.name}</Link>
                      {c.description && <div className="text-xs text-ink-faint truncate max-w-[280px]">{c.description}</div>}
                    </td>
                    <td className="px-4 py-2 text-ink">{c.employerGroupName || c.employerGroupId || '—'}</td>
                    <td className="px-4 py-2"><StatusPill status={c.status} map={CAMPAIGN_STATUS_PILL} /></td>
                    <td className="px-4 py-2 text-ink">{c.linksCount}</td>
                    <td className="px-4 py-2 w-40">
                      <div className="flex items-center gap-2">
                        <div className="flex-1 h-1.5 rounded bg-surface-2 overflow-hidden">
                          <div
                            className={`h-full rounded ${c.completionRate >= 80 ? 'bg-status-success-fg' : c.completionRate >= 50 ? 'bg-status-warning-fg' : 'bg-status-danger-fg'}`}
                            style={{ width: `${Math.min(100, c.completionRate)}%` }}
                          />
                        </div>
                        <span className="text-xs text-ink-muted w-10 text-right">{c.completionRate}%</span>
                      </div>
                    </td>
                    <td className="px-4 py-2 text-ink-muted whitespace-nowrap">{fmtDate(c.createdAt)}</td>
                  </tr>
                ))
              )}
            </tbody>
          </table>
        </div>
        <PaginationBar meta={data?.meta} goToPage={goToPage} />
      </div>

      {dashboard && dashboard.recent_activities.length > 0 && (
        <div className="bg-surface rounded-lg shadow-sm border border-line">
          <div className="px-4 py-3 border-b border-line text-sm font-semibold text-ink">Recent Activity</div>
          <ul className="divide-y divide-line">
            {dashboard.recent_activities.map(a => (
              <li key={a.id} className="px-4 py-2 flex items-center justify-between gap-3 text-sm">
                <div className="min-w-0">
                  <span className="font-medium text-ink capitalize">{a.activityType.replace(/_/g, ' ')}</span>
                  {a.customerName && <span className="text-ink-muted"> — {a.customerName}</span>}
                  {a.description && <div className="text-xs text-ink-faint truncate">{a.description}</div>}
                </div>
                <span className="text-xs text-ink-faint whitespace-nowrap">{fmtDateTime(a.occurredAt)}</span>
              </li>
            ))}
          </ul>
        </div>
      )}

      {showCreate && <CreateCampaignModal onClose={() => setShowCreate(false)} />}
    </div>
  )
}

function StatCard({ label, value, small }: { label: string; value: number | string; small?: boolean }) {
  return (
    <div className={`bg-surface border border-line rounded-lg ${small ? 'px-3 py-2' : 'px-4 py-3'}`}>
      <div className={`font-bold text-ink ${small ? 'text-lg' : 'text-2xl'}`}>{value}</div>
      <div className="text-xs text-ink-muted uppercase tracking-wide">{label}</div>
    </div>
  )
}

/** Create-campaign modal with the FULL validated field set — the V8 form
 *  omitted required fields and always 422'd. */
function CreateCampaignModal({ onClose }: { onClose: () => void }) {
  const { data: groups } = useEmployerGroupKyc({ per_page: 100 })
  const create = useCreateAdGroupKycCampaign()
  const [form, setForm] = useState({
    name: '',
    description: '',
    employer_group_id: '',
    link_expiry_hours: 72,
    otp_expiry_minutes: 10,
    max_attempts: 3,
    escalation_days: 7,
  })
  const [reminderDays, setReminderDays] = useState<number[]>([3, 7, 14])
  const [channels, setChannels] = useState<NotifyChannel[]>(['email'])
  const [error, setError] = useState<string | null>(null)

  function set<K extends keyof typeof form>(key: K, value: (typeof form)[K]) {
    setForm(prev => ({ ...prev, [key]: value }))
  }

  async function submit() {
    setError(null)
    try {
      await create.mutateAsync({
        ...form,
        description: form.description || undefined,
        reminder_days: reminderDays,
        notification_channels: channels,
      })
      onClose()
    } catch (e: any) {
      const errs = e?.response?.data?.errors
      setError(errs ? Object.values(errs).flat().join(' ') : (e?.response?.data?.message || 'Failed to create campaign.'))
    }
  }

  const valid = form.name.trim() && form.employer_group_id && reminderDays.length > 0 && channels.length > 0

  return (
    <Modal title="Create Campaign" onClose={onClose} wide>
      <div className="space-y-3">
        {error && <div className="px-3 py-2 rounded-md bg-status-danger-bg text-status-danger-fg text-sm">{error}</div>}
        <div className="grid grid-cols-1 md:grid-cols-2 gap-3">
          <div className="md:col-span-2">
            <label className="block text-xs font-medium text-ink-muted mb-1">Campaign Name *</label>
            <input value={form.name} onChange={e => set('name', e.target.value)} maxLength={255}
              className="w-full px-3 py-1.5 border border-line rounded-md text-sm bg-surface text-ink" />
          </div>
          <div className="md:col-span-2">
            <label className="block text-xs font-medium text-ink-muted mb-1">Employer Group *</label>
            <select value={form.employer_group_id} onChange={e => set('employer_group_id', e.target.value)}
              className="w-full px-3 py-1.5 border border-line rounded-md text-sm bg-surface text-ink">
              <option value="">Select employer group…</option>
              {groups?.data.map(g => (
                <option key={g.id} value={g.employerGroupId ?? ''}>{g.name} ({g.employerGroupId})</option>
              ))}
            </select>
          </div>
          <div className="md:col-span-2">
            <label className="block text-xs font-medium text-ink-muted mb-1">Description</label>
            <textarea value={form.description} onChange={e => set('description', e.target.value)} rows={2} maxLength={1000}
              className="w-full px-3 py-1.5 border border-line rounded-md text-sm bg-surface text-ink" />
          </div>
          <NumberField label="Link Expiry (hours) *" value={form.link_expiry_hours} min={1} max={720}
            onChange={v => set('link_expiry_hours', v)} />
          <NumberField label="OTP Expiry (minutes) *" value={form.otp_expiry_minutes} min={1} max={60}
            onChange={v => set('otp_expiry_minutes', v)} />
          <NumberField label="Max OTP Attempts *" value={form.max_attempts} min={1} max={10}
            onChange={v => set('max_attempts', v)} />
          <NumberField label="Escalation After (days) *" value={form.escalation_days} min={1} max={30}
            onChange={v => set('escalation_days', v)} />
          <div>
            <label className="block text-xs font-medium text-ink-muted mb-1">Reminder Days *</label>
            <div className="flex gap-4 pt-1">
              {REMINDER_OPTIONS.map(d => (
                <label key={d} className="flex items-center gap-1.5 text-sm text-ink cursor-pointer">
                  <input type="checkbox" checked={reminderDays.includes(d)}
                    onChange={() => setReminderDays(prev => prev.includes(d) ? prev.filter(x => x !== d) : [...prev, d].sort((a, b) => a - b))}
                    className="rounded border-line" />
                  Day {d}
                </label>
              ))}
            </div>
          </div>
          <div>
            <label className="block text-xs font-medium text-ink-muted mb-1">Notification Channels *</label>
            <ChannelChecks channels={channels} onChange={setChannels} />
          </div>
        </div>
        <div className="flex justify-end gap-2 pt-2 border-t border-line">
          <button type="button" onClick={onClose} className="px-4 py-1.5 text-sm border border-line rounded-md text-ink-muted">Cancel</button>
          <button type="button" disabled={!valid || create.isPending} onClick={submit}
            className="px-4 py-1.5 text-sm font-medium bg-primary text-primary-contrast rounded-md disabled:opacity-50">
            {create.isPending ? 'Creating…' : 'Create Campaign'}
          </button>
        </div>
      </div>
    </Modal>
  )
}

function NumberField({ label, value, min, max, onChange }: {
  label: string; value: number; min: number; max: number; onChange: (v: number) => void
}) {
  return (
    <div>
      <label className="block text-xs font-medium text-ink-muted mb-1">{label}</label>
      <input type="number" value={value} min={min} max={max}
        onChange={e => onChange(Number(e.target.value))}
        className="w-full px-3 py-1.5 border border-line rounded-md text-sm bg-surface text-ink" />
    </div>
  )
}
