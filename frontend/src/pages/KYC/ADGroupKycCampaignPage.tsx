import { useEffect, useMemo, useState } from 'react'
import { Link, useParams, useSearchParams } from 'react-router-dom'
import { PaginationBar, useSearchParamsPagination } from './adGroupKycPagination'
import {
  useAdGroupKycCampaign,
  useAdGroupKycCampaignLinks,
  useBulkNotifyAdGroupKyc,
  useGenerateAdGroupKycLinks,
  useResendAdGroupKycLink,
  useSendAdGroupKycEscalations,
  useSendAdGroupKycReminders,
  useUpdateAdGroupKycCampaign,
} from '../../hooks/useAdGroupKyc'
import { exportAdGroupKycCampaign } from '../../api/adGroupKyc'
import LoadingSpinner from '../../components/common/LoadingSpinner'
import EmptyState from '../../components/common/EmptyState'
import { fmtDate } from '../../utils/format'
import { CAMPAIGN_STATUS_PILL, LINK_STATUS_PILL, Modal, NotifyForm, StatusPill } from './adGroupKycShared'

// Campaign detail — V2 port of the V8 Blade campaign show + edit screens:
// stats tiles, employee/links table with row selection, edit modal,
// reminders / escalations / export / bulk-notify / per-row resend+generate.

const REMINDER_OPTIONS = [3, 7, 14]

export default function ADGroupKycCampaignPage() {
  const { id } = useParams()
  const campaignId = Number(id)
  const [searchParams] = useSearchParams()

  const [selected, setSelected] = useState<Set<number>>(new Set())
  const [modal, setModal] = useState<null | 'edit' | 'bulk' | 'reminders' | 'escalations'>(null)
  const [resendLinkId, setResendLinkId] = useState<number | null>(null)
  const [banner, setBanner] = useState<{ ok: boolean; text: string } | null>(null)
  const [exporting, setExporting] = useState(false)

  const filters = {
    search:   searchParams.get('search') || undefined,
    status:   searchParams.get('status') || undefined,
    per_page: 25,
    page:     Number(searchParams.get('page') || '1'),
  }

  const { data: campaign, isLoading } = useAdGroupKycCampaign(campaignId)
  const { data: links, isFetching } = useAdGroupKycCampaignLinks(campaignId, filters)
  const { updateFilter, goToPage } = useSearchParamsPagination()

  // Selection is page-scoped: clear it whenever search/status/page change so
  // bulk notify can never target rows the user is no longer looking at.
  const paramsKey = searchParams.toString()
  useEffect(() => { setSelected(new Set()) }, [paramsKey])

  const generate = useGenerateAdGroupKycLinks(campaignId)
  const resend = useResendAdGroupKycLink(campaignId)
  const bulk = useBulkNotifyAdGroupKyc(campaignId)
  const reminders = useSendAdGroupKycReminders(campaignId)
  const escalations = useSendAdGroupKycEscalations(campaignId)

  const selectableLinkIds = useMemo(
    () => (links?.data ?? []).filter(r => r.linkId != null).map(r => r.linkId as number),
    [links]
  )

  function toggleRow(linkId: number) {
    setSelected(prev => {
      const next = new Set(prev)
      next.has(linkId) ? next.delete(linkId) : next.add(linkId)
      return next
    })
  }

  function toggleAll() {
    setSelected(prev =>
      selectableLinkIds.length > 0 && selectableLinkIds.every(id => prev.has(id))
        ? new Set()
        : new Set(selectableLinkIds)
    )
  }

  function report(res: { message: string } | undefined, fallback: string) {
    setBanner({ ok: true, text: res?.message ?? fallback })
    setModal(null)
    setResendLinkId(null)
  }

  function reportError(e: any) {
    setBanner({ ok: false, text: e?.response?.data?.message || 'Action failed.' })
  }

  async function handleExport() {
    setExporting(true)
    try {
      const blob = await exportAdGroupKycCampaign(campaignId)
      const url = URL.createObjectURL(blob)
      const a = document.createElement('a')
      a.href = url
      a.download = `ad_group_kyc_campaign_${campaignId}.csv`
      a.click()
      URL.revokeObjectURL(url)
    } catch (e: any) {
      reportError(e)
    } finally {
      setExporting(false)
    }
  }

  if (isLoading) return <div className="p-12 flex justify-center"><LoadingSpinner size="lg" /></div>
  if (!campaign) return <div className="p-6"><EmptyState title="Campaign not found" description="It may have been removed." /></div>

  const stats = campaign.stats

  return (
    <div className="p-6 space-y-4">
      <div className="text-sm text-ink-muted">
        <Link to="/kyc/ad-group" className="text-primary hover:underline">← Back to AD Group KYC</Link>
      </div>

      <div className="flex flex-wrap items-start justify-between gap-3">
        <div>
          <div className="flex items-center gap-3">
            <h1 className="text-2xl font-bold text-ink">{campaign.name}</h1>
            <StatusPill status={campaign.status} map={CAMPAIGN_STATUS_PILL} />
          </div>
          <div className="text-sm text-ink-muted mt-1">
            {campaign.employerGroupName || campaign.employerGroupId} · created {fmtDate(campaign.createdAt)}
          </div>
          {campaign.description && <p className="text-sm text-ink-faint mt-1 max-w-2xl">{campaign.description}</p>}
        </div>
        <div className="flex flex-wrap gap-2">
          <ActionButton onClick={() => setModal('edit')}>Edit</ActionButton>
          <ActionButton onClick={() => setModal('reminders')}>Send Reminders</ActionButton>
          <ActionButton onClick={() => setModal('escalations')}>Send Escalations</ActionButton>
          <ActionButton onClick={handleExport} disabled={exporting}>{exporting ? 'Exporting…' : 'Export CSV'}</ActionButton>
        </div>
      </div>

      {banner && (
        <div className={`px-3 py-2 rounded-md text-sm flex justify-between items-center ${banner.ok ? 'bg-status-success-bg text-status-success-fg' : 'bg-status-danger-bg text-status-danger-fg'}`}>
          {banner.text}
          <button type="button" onClick={() => setBanner(null)} className="ml-3 font-bold" aria-label="Dismiss">×</button>
        </div>
      )}

      <div className="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-8 gap-3">
        <Tile label="Total" value={stats.total_links} />
        <Tile label="Pending" value={stats.pending_links} />
        <Tile label="Sent" value={stats.sent_links} />
        <Tile label="Opened" value={stats.opened_links} />
        <Tile label="OTP Verified" value={stats.otp_verified} />
        <Tile label="Completed" value={stats.completed_links} />
        <Tile label="Expired" value={stats.expired_links} />
        <Tile label="Completion" value={`${stats.completion_rate}%`} />
      </div>

      <div className="flex flex-wrap gap-3 items-end">
        <div>
          <label className="block text-xs font-medium text-ink-muted mb-1">Search</label>
          <input
            type="text"
            placeholder="Name, email, phone, policy #..."
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
            <option value="not_generated">Not generated</option>
            <option value="pending">Pending</option>
            <option value="sent">Sent</option>
            <option value="opened">Opened</option>
            <option value="otp_verified">OTP verified</option>
            <option value="completed">Completed</option>
            <option value="expired">Expired</option>
            <option value="failed">Failed</option>
          </select>
        </div>
        {selected.size > 0 && (
          <button
            type="button"
            onClick={() => setModal('bulk')}
            className="px-4 py-1.5 text-sm font-medium bg-primary text-primary-contrast rounded-md"
          >
            Notify Selected ({selected.size})
          </button>
        )}
      </div>

      <div className="bg-surface rounded-lg shadow-sm border border-line overflow-hidden relative">
        {isFetching && (
          <div className="absolute inset-0 bg-surface/50 z-10 flex items-center justify-center">
            <LoadingSpinner size="md" />
          </div>
        )}
        <div className="overflow-x-auto">
          <table className="w-full text-sm">
            <thead className="bg-surface-2 text-ink-muted uppercase text-xs tracking-wider">
              <tr>
                <th className="px-3 py-3">
                  <input
                    type="checkbox"
                    checked={selectableLinkIds.length > 0 && selectableLinkIds.every(id => selected.has(id))}
                    onChange={toggleAll}
                    className="rounded border-line"
                    aria-label="Select all"
                  />
                </th>
                <th className="px-4 py-3 text-left">Customer</th>
                <th className="px-4 py-3 text-left">Phone</th>
                <th className="px-4 py-3 text-left">Policy #</th>
                <th className="px-4 py-3 text-left">Status</th>
                <th className="px-4 py-3 text-left">Sent</th>
                <th className="px-4 py-3 text-left">Opened</th>
                <th className="px-4 py-3 text-left">Completed</th>
                <th className="px-4 py-3 text-left">Expires</th>
                <th className="px-4 py-3 text-left">Actions</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-line">
              {links?.data.length === 0 ? (
                <tr><td colSpan={10} className="p-0"><EmptyState compact title="No employees found" description="No AD Group policies match this campaign's employer group." /></td></tr>
              ) : (
                links?.data.map(r => {
                  const expired = r.expiresAt ? new Date(r.expiresAt) < new Date() : false
                  return (
                    <tr key={`${r.policyId}`} className="hover:bg-surface-2 transition">
                      <td className="px-3 py-2 text-center">
                        <input
                          type="checkbox"
                          disabled={r.linkId == null}
                          checked={r.linkId != null && selected.has(r.linkId)}
                          onChange={() => r.linkId != null && toggleRow(r.linkId)}
                          className="rounded border-line disabled:opacity-30"
                          aria-label={`Select ${r.customerName}`}
                        />
                      </td>
                      <td className="px-4 py-2">
                        <div className="font-medium text-ink">{r.customerName || '—'}</div>
                        <div className="text-xs text-ink-faint">{r.email || '—'}</div>
                      </td>
                      <td className="px-4 py-2 text-ink-muted whitespace-nowrap">{r.cellphone || '—'}</td>
                      <td className="px-4 py-2 text-ink-muted whitespace-nowrap">{r.policyNumber || '—'}</td>
                      <td className="px-4 py-2"><StatusPill status={r.status} map={LINK_STATUS_PILL} /></td>
                      <td className="px-4 py-2 text-ink-muted whitespace-nowrap">{r.sentAt ? fmtDate(r.sentAt) : '—'}</td>
                      <td className="px-4 py-2 text-ink-muted whitespace-nowrap">{r.openedAt ? fmtDate(r.openedAt) : '—'}</td>
                      <td className="px-4 py-2 text-ink-muted whitespace-nowrap">{r.completedAt ? fmtDate(r.completedAt) : '—'}</td>
                      <td className={`px-4 py-2 whitespace-nowrap ${expired ? 'text-status-danger-fg' : 'text-ink-muted'}`}>
                        {r.expiresAt ? fmtDate(r.expiresAt) : '—'}
                      </td>
                      <td className="px-4 py-2 whitespace-nowrap space-x-2">
                        {r.linkId != null ? (
                          <>
                            <Link to={`/kyc/ad-group/links/${r.linkId}`} className="text-primary hover:underline text-xs font-medium">View</Link>
                            <button type="button" onClick={() => setResendLinkId(r.linkId)} className="text-primary hover:underline text-xs font-medium">Resend</button>
                          </>
                        ) : (
                          <button
                            type="button"
                            disabled={generate.isPending}
                            onClick={() =>
                              generate.mutateAsync([r.policyId])
                                .then(() => setBanner({ ok: true, text: `Link generated for ${r.customerName}.` }))
                                .catch(reportError)
                            }
                            className="text-primary hover:underline text-xs font-medium disabled:opacity-50"
                          >
                            Generate
                          </button>
                        )}
                      </td>
                    </tr>
                  )
                })
              )}
            </tbody>
          </table>
        </div>
        <PaginationBar meta={links?.meta} goToPage={goToPage} />
      </div>

      {modal === 'edit' && <EditCampaignModal campaign={campaign} campaignId={campaignId} onClose={() => setModal(null)} onSaved={() => report(undefined, 'Campaign updated.')} />}

      {modal === 'bulk' && (
        <Modal title={`Notify ${selected.size} selected`} onClose={() => setModal(null)}>
          <NotifyForm
            submitLabel="Send Notifications"
            busy={bulk.isPending}
            onSubmit={(channels, message) =>
              bulk.mutateAsync({ linkIds: [...selected], channels, message })
                .then(res => { setSelected(new Set()); report(res, 'Notifications sent.') })
                .catch(reportError)
            }
          />
        </Modal>
      )}

      {modal === 'reminders' && (
        <Modal title="Send Reminders" onClose={() => setModal(null)}>
          <p className="text-sm text-ink-muted mb-3">
            Re-sends the KYC link to everyone whose link was sent 3+ days ago and is not yet completed.
          </p>
          <NotifyForm
            submitLabel="Send Reminders"
            busy={reminders.isPending}
            showMessage={false}
            onSubmit={(channels) =>
              reminders.mutateAsync({ channels }).then(res => report(res, 'Reminders sent.')).catch(reportError)
            }
          />
        </Modal>
      )}

      {modal === 'escalations' && (
        <Modal title="Send Escalations" onClose={() => setModal(null)}>
          <p className="text-sm text-ink-muted mb-3">
            Targets links still incomplete after the campaign's escalation window ({campaign.escalationDays} days).
          </p>
          <NotifyForm
            submitLabel="Send Escalations"
            busy={escalations.isPending}
            showMessage={false}
            onSubmit={(channels) =>
              escalations.mutateAsync({ channels }).then(res => report(res, 'Escalations sent.')).catch(reportError)
            }
          />
        </Modal>
      )}

      {resendLinkId != null && (
        <Modal title="Resend KYC Link" onClose={() => setResendLinkId(null)}>
          <NotifyForm
            submitLabel="Resend"
            busy={resend.isPending}
            onSubmit={(channels, message) =>
              resend.mutateAsync({ linkId: resendLinkId, channels, message })
                .then(res => report(res, 'Notification re-sent.'))
                .catch(reportError)
            }
          />
        </Modal>
      )}
    </div>
  )
}

function Tile({ label, value }: { label: string; value: number | string }) {
  return (
    <div className="bg-surface border border-line rounded-lg px-3 py-2">
      <div className="text-lg font-bold text-ink">{value}</div>
      <div className="text-xs text-ink-muted uppercase tracking-wide">{label}</div>
    </div>
  )
}

function ActionButton({ children, onClick, disabled }: { children: React.ReactNode; onClick: () => void; disabled?: boolean }) {
  return (
    <button
      type="button"
      onClick={onClick}
      disabled={disabled}
      className="px-3 py-1.5 text-sm font-medium border border-line rounded-md text-ink hover:bg-surface-2 transition disabled:opacity-50"
    >
      {children}
    </button>
  )
}

function EditCampaignModal({ campaign, campaignId, onClose, onSaved }: {
  campaign: { name: string; description: string | null; status: string; escalationDays: number; reminderDays: number[] }
  campaignId: number
  onClose: () => void
  onSaved: () => void
}) {
  const update = useUpdateAdGroupKycCampaign(campaignId)
  const [form, setForm] = useState({
    name: campaign.name,
    description: campaign.description ?? '',
    status: (['active', 'completed', 'paused'].includes(campaign.status) ? campaign.status : 'active') as 'active' | 'completed' | 'paused',
    escalation_days: campaign.escalationDays || 7,
  })
  const [reminderDays, setReminderDays] = useState<number[]>(campaign.reminderDays?.length ? campaign.reminderDays : [3, 7, 14])
  const [error, setError] = useState<string | null>(null)

  async function submit() {
    setError(null)
    try {
      await update.mutateAsync({ ...form, description: form.description || undefined, reminder_days: reminderDays })
      onSaved()
    } catch (e: any) {
      const errs = e?.response?.data?.errors
      setError(errs ? Object.values(errs).flat().join(' ') : (e?.response?.data?.message || 'Failed to update campaign.'))
    }
  }

  return (
    <Modal title="Edit Campaign" onClose={onClose}>
      <div className="space-y-3">
        {error && <div className="px-3 py-2 rounded-md bg-status-danger-bg text-status-danger-fg text-sm">{error}</div>}
        <div>
          <label className="block text-xs font-medium text-ink-muted mb-1">Name *</label>
          <input value={form.name} onChange={e => setForm(p => ({ ...p, name: e.target.value }))} maxLength={255}
            className="w-full px-3 py-1.5 border border-line rounded-md text-sm bg-surface text-ink" />
        </div>
        <div>
          <label className="block text-xs font-medium text-ink-muted mb-1">Status *</label>
          <select value={form.status} onChange={e => setForm(p => ({ ...p, status: e.target.value as typeof form.status }))}
            className="w-full px-3 py-1.5 border border-line rounded-md text-sm bg-surface text-ink">
            <option value="active">Active</option>
            <option value="paused">Paused</option>
            <option value="completed">Completed</option>
          </select>
        </div>
        <div>
          <label className="block text-xs font-medium text-ink-muted mb-1">Description</label>
          <textarea value={form.description} onChange={e => setForm(p => ({ ...p, description: e.target.value }))} rows={2} maxLength={1000}
            className="w-full px-3 py-1.5 border border-line rounded-md text-sm bg-surface text-ink" />
        </div>
        <div>
          <label className="block text-xs font-medium text-ink-muted mb-1">Escalation After (days)</label>
          <input type="number" min={1} max={365} value={form.escalation_days}
            onChange={e => setForm(p => ({ ...p, escalation_days: Number(e.target.value) }))}
            className="w-full px-3 py-1.5 border border-line rounded-md text-sm bg-surface text-ink" />
        </div>
        <div>
          <label className="block text-xs font-medium text-ink-muted mb-1">Reminder Days</label>
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
        <div className="flex justify-end gap-2 pt-2 border-t border-line">
          <button type="button" onClick={onClose} className="px-4 py-1.5 text-sm border border-line rounded-md text-ink-muted">Cancel</button>
          <button type="button" disabled={!form.name.trim() || update.isPending} onClick={submit}
            className="px-4 py-1.5 text-sm font-medium bg-primary text-primary-contrast rounded-md disabled:opacity-50">
            {update.isPending ? 'Saving…' : 'Save Changes'}
          </button>
        </div>
      </div>
    </Modal>
  )
}
