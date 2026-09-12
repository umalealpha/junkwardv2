import { useMemo, useState } from 'react'
import { Link, useNavigate, useParams, useSearchParams } from 'react-router-dom'
import {
  useADGroupPolicies,
  useDeleteEmployerGroup,
  useEmployerGroup,
  useSendHrCredentials,
  useSendOnboardingEmail,
} from '../../hooks/useEmployerGroups'
import LoadingSpinner from '../../components/common/LoadingSpinner'
import EmptyState from '../../components/common/EmptyState'
import { fmtDate, fmtDateTime } from '../../utils/format'
import { Modal, StatusPill } from '../KYC/adGroupKycShared'
import { PaginationBar, useSearchParamsPagination } from '../KYC/adGroupKycPagination'

// Employer Group detail — V2 port of the V8 Blade employer-groups show
// screen: overview cards, HR emails/users, policies tab, read-only KYC
// submission tab, audit log, and the Send Onboarding / Send HR Access /
// Edit / Delete actions.

const GROUP_STATUS_PILL: Record<string, string> = {
  active:    'bg-status-success-bg text-status-success-fg',
  pending:   'bg-status-warning-bg text-status-warning-fg',
  inactive:  'bg-surface-2 text-ink-faint',
  suspended: 'bg-status-warning-bg text-status-warning-fg',
  cancelled: 'bg-status-danger-bg text-status-danger-fg',
}

type Tab = 'overview' | 'policies' | 'kyc' | 'audit'

function hasPermission(perm: string): boolean {
  try {
    const perms = JSON.parse(localStorage.getItem('user_permissions') || '[]') as string[]
    return perms.length === 0 || perms.includes(perm)
  } catch { return true }
}

export default function EmployerGroupDetailPage() {
  const { id } = useParams()
  const groupId = Number(id)
  const navigate = useNavigate()

  const [tab, setTab] = useState<Tab>('overview')
  const [confirm, setConfirm] = useState<null | 'delete' | 'onboarding' | 'credentials'>(null)
  const [banner, setBanner] = useState<{ ok: boolean; text: string } | null>(null)

  const { data: group, isLoading } = useEmployerGroup(groupId)
  const del = useDeleteEmployerGroup()
  const sendCreds = useSendHrCredentials(groupId)
  const sendOnboarding = useSendOnboardingEmail(groupId)

  const canEdit = useMemo(() => hasPermission('employer-group-edit'), [])
  const canDelete = useMemo(() => hasPermission('employer-group-delete'), [])
  const canSend = useMemo(() => hasPermission('employer-group-send-comms'), [])

  function reportError(e: any) {
    const deps = e?.response?.data?.dependencies
    const depText = deps
      ? ' (' + Object.entries(deps).map(([k, v]) => `${String(k).replace(/_/g, ' ')}: ${v}`).join(', ') + ')'
      : ''
    setBanner({ ok: false, text: (e?.response?.data?.message || 'Action failed.') + depText })
    setConfirm(null)
  }

  if (isLoading) return <div className="p-12 flex justify-center"><LoadingSpinner size="lg" /></div>
  if (!group) return <div className="p-6"><EmptyState title="Employer group not found" description="It may have been removed." /></div>

  return (
    <div className="p-6 space-y-4">
      <div className="text-sm text-ink-muted">
        <Link to="/ad-group/employer-groups" className="text-primary hover:underline">← Back to Employer Groups</Link>
      </div>

      <div className="flex flex-wrap items-start justify-between gap-3">
        <div>
          <div className="flex items-center gap-3">
            <h1 className="text-2xl font-bold text-ink">{group.name}</h1>
            {group.status && <StatusPill status={group.status.toLowerCase()} map={GROUP_STATUS_PILL} />}
          </div>
          <div className="text-sm text-ink-muted mt-1">
            Group ID <span className="font-mono text-ink">{group.employerGroupId}</span>
            {' · '}{group.industry || 'no industry'} · created {fmtDate(group.createdAt)}
          </div>
        </div>
        <div className="flex flex-wrap gap-2">
          {canSend && (
            <>
              <HeaderButton onClick={() => setConfirm('onboarding')}>Send Onboarding</HeaderButton>
              <HeaderButton onClick={() => setConfirm('credentials')}>Send HR Access</HeaderButton>
            </>
          )}
          {canEdit && (
            <Link to={`/ad-group/employer-groups/${group.id}/edit`}
              className="px-3 py-1.5 text-sm font-medium border border-line rounded-md text-ink hover:bg-surface-2 transition">
              Edit
            </Link>
          )}
          {canDelete && (
            <button type="button" onClick={() => setConfirm('delete')}
              className="px-3 py-1.5 text-sm font-medium border border-status-danger-fg text-status-danger-fg rounded-md hover:bg-status-danger-bg transition">
              Delete
            </button>
          )}
        </div>
      </div>

      {banner && (
        <div className={`px-3 py-2 rounded-md text-sm flex justify-between items-center ${banner.ok ? 'bg-status-success-bg text-status-success-fg' : 'bg-status-danger-bg text-status-danger-fg'}`}>
          <span>{banner.text}</span>
          <button type="button" onClick={() => setBanner(null)} className="ml-3 font-bold" aria-label="Dismiss">×</button>
        </div>
      )}

      <div className="flex gap-1 border-b border-line">
        {([['overview', 'Overview'], ['policies', 'Policies'], ['kyc', 'KYC Submission'], ['audit', `Audit Log (${group.auditLogs.length})`]] as [Tab, string][]).map(([key, label]) => (
          <button key={key} type="button" onClick={() => setTab(key)}
            className={`px-4 py-2 text-sm font-medium border-b-2 -mb-px transition ${tab === key ? 'border-primary text-primary' : 'border-transparent text-ink-muted hover:text-ink'}`}>
            {label}
          </button>
        ))}
      </div>

      {tab === 'overview' && <OverviewTab group={group} />}
      {tab === 'policies' && <PoliciesTab employerGroupId={group.employerGroupId} />}
      {tab === 'kyc' && <KycTab submission={group.kycSubmission} />}
      {tab === 'audit' && <AuditTab logs={group.auditLogs} />}

      {confirm === 'delete' && (
        <Modal title="Delete Employer Group" onClose={() => setConfirm(null)}>
          <p className="text-sm text-ink-muted mb-4">
            Permanently delete <strong className="text-ink">{group.name}</strong> ({group.employerGroupId})?
            Deletion is blocked while HR users, group policies, or KYC campaigns still reference it.
          </p>
          <div className="flex justify-end gap-2">
            <button type="button" onClick={() => setConfirm(null)} className="px-4 py-1.5 text-sm border border-line rounded-md text-ink-muted">Cancel</button>
            <button type="button" disabled={del.isPending}
              onClick={() => del.mutateAsync(group.id)
                .then(() => navigate('/ad-group/employer-groups', { state: { toast: `Employer group ${group.name} deleted.` } }))
                .catch(reportError)}
              className="px-4 py-1.5 text-sm font-medium bg-status-danger-fg text-primary-contrast rounded-md disabled:opacity-50">
              {del.isPending ? 'Deleting…' : 'Yes, Delete'}
            </button>
          </div>
        </Modal>
      )}

      {confirm === 'onboarding' && (
        <Modal title="Send Onboarding Email" onClose={() => setConfirm(null)}>
          <p className="text-sm text-ink-muted mb-4">
            Sends the onboarding email (including the employer-group KYC link) to{' '}
            <strong className="text-ink">{group.contactEmail || 'no contact email on file'}</strong>.
          </p>
          <div className="flex justify-end gap-2">
            <button type="button" onClick={() => setConfirm(null)} className="px-4 py-1.5 text-sm border border-line rounded-md text-ink-muted">Cancel</button>
            <button type="button" disabled={sendOnboarding.isPending || !group.contactEmail}
              onClick={() => sendOnboarding.mutateAsync()
                .then(res => { setConfirm(null); setBanner({ ok: true, text: res.message }) })
                .catch(reportError)}
              className="px-4 py-1.5 text-sm font-medium bg-primary text-primary-contrast rounded-md disabled:opacity-50">
              {sendOnboarding.isPending ? 'Sending…' : 'Send'}
            </button>
          </div>
        </Modal>
      )}

      {confirm === 'credentials' && (
        <Modal title="Send HR Portal Access" onClose={() => setConfirm(null)}>
          <p className="text-sm text-ink-muted mb-2">
            Sends a 48-hour set-password link to each HR email. Missing HR accounts are created automatically.
          </p>
          {group.hrEmails.length > 0 ? (
            <ul className="text-sm text-ink mb-4 list-disc pl-5">
              {group.hrEmails.map(e => <li key={e}>{e}</li>)}
            </ul>
          ) : (
            <p className="text-sm text-status-warning-fg mb-4">No HR emails on file — add them via Edit first.</p>
          )}
          <div className="flex justify-end gap-2">
            <button type="button" onClick={() => setConfirm(null)} className="px-4 py-1.5 text-sm border border-line rounded-md text-ink-muted">Cancel</button>
            <button type="button" disabled={sendCreds.isPending || group.hrEmails.length === 0}
              onClick={() => sendCreds.mutateAsync()
                .then(res => { setConfirm(null); setBanner({ ok: true, text: res.message }) })
                .catch(reportError)}
              className="px-4 py-1.5 text-sm font-medium bg-primary text-primary-contrast rounded-md disabled:opacity-50">
              {sendCreds.isPending ? 'Sending…' : 'Send Access Emails'}
            </button>
          </div>
        </Modal>
      )}
    </div>
  )
}

function HeaderButton({ children, onClick }: { children: React.ReactNode; onClick: () => void }) {
  return (
    <button type="button" onClick={onClick}
      className="px-3 py-1.5 text-sm font-medium border border-line rounded-md text-ink hover:bg-surface-2 transition">
      {children}
    </button>
  )
}

function Section({ title, children }: { title: string; children: React.ReactNode }) {
  return (
    <div className="bg-surface rounded-lg shadow-sm border border-line">
      <div className="px-4 py-3 border-b border-line text-sm font-semibold text-ink">{title}</div>
      <dl className="p-4 space-y-2 text-sm">{children}</dl>
    </div>
  )
}

function Row({ label, value }: { label: string; value: React.ReactNode }) {
  return (
    <div className="flex justify-between gap-3">
      <dt className="text-ink-faint shrink-0">{label}</dt>
      <dd className="text-ink text-right min-w-0 break-words">{value ?? '—'}</dd>
    </div>
  )
}

function OverviewTab({ group }: { group: NonNullable<ReturnType<typeof useEmployerGroup>['data']> }) {
  return (
    <div className="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">
      <Section title="Basic Information">
        <Row label="Name" value={group.name} />
        <Row label="Group ID" value={<span className="font-mono">{group.employerGroupId}</span>} />
        <Row label="Industry" value={group.industry === 'Other' ? (group.otherIndustry || 'Other') : group.industry} />
        <Row label="Employees" value={group.noOfEmployees} />
        <Row label="Status" value={group.status} />
      </Section>
      <Section title="Address">
        <Row label="Address" value={group.address} />
        <Row label="Town" value={group.town} />
        <Row label="Postal Code" value={group.postalCode} />
      </Section>
      <Section title="Contact">
        <Row label="Name" value={group.contactName} />
        <Row label="Phone" value={group.contactPhone} />
        <Row label="Email" value={group.contactEmail} />
      </Section>
      <Section title="Broker & Billing">
        <Row label="Broker" value={group.broker} />
        <Row label="Payment Method" value={group.paymentMethod} />
        <Row label="Account Name" value={group.accountName} />
        <Row label="Account Number" value={group.accountNumber} />
        <Row label="Bank / Branch" value={group.bankNameBranch} />
      </Section>
      <div className="bg-surface rounded-lg shadow-sm border border-line">
        <div className="px-4 py-3 border-b border-line text-sm font-semibold text-ink">HR Emails & Portal Users</div>
        <div className="p-4 space-y-3 text-sm">
          {group.hrEmails.length === 0 ? (
            <p className="text-ink-faint">No HR emails on file.</p>
          ) : (
            <div className="flex flex-wrap gap-1.5">
              {group.hrEmails.map(e => (
                <span key={e} className="px-2 py-0.5 rounded-full bg-surface-2 text-ink text-xs">{e}</span>
              ))}
            </div>
          )}
          {group.hrUsers.length > 0 && (
            <ul className="divide-y divide-line border-t border-line pt-2">
              {group.hrUsers.map(u => (
                <li key={u.id} className="py-1.5 flex items-center justify-between gap-2">
                  <span className="text-ink truncate">{u.email}</span>
                  <span className="text-xs text-ink-faint whitespace-nowrap">
                    {u.isActive ? 'active' : 'disabled'}
                    {' · '}{u.hasPassword ? 'password set' : 'no password yet'}
                    {u.lastLoginAt ? ` · last login ${fmtDate(u.lastLoginAt)}` : ''}
                  </span>
                </li>
              ))}
            </ul>
          )}
        </div>
      </div>
      <div className="bg-surface rounded-lg shadow-sm border border-line">
        <div className="px-4 py-3 border-b border-line text-sm font-semibold text-ink">Documents & Notes</div>
        <div className="p-4 space-y-2 text-sm">
          {group.documents.length === 0 ? (
            <p className="text-ink-faint">No documents uploaded.</p>
          ) : (
            group.documents.map(d => (
              <div key={d.key} className="flex justify-between gap-2">
                <span className="text-ink-faint">{d.label}</span>
                {d.url
                  ? <a href={d.url} target="_blank" rel="noreferrer" className="text-primary hover:underline truncate">{d.filename || 'Download'}</a>
                  : <span className="text-ink-faint">{d.filename || 'unavailable'}</span>}
              </div>
            ))
          )}
          {group.notes && <p className="text-ink-muted border-t border-line pt-2 whitespace-pre-wrap">{group.notes}</p>}
        </div>
      </div>
    </div>
  )
}

function PoliciesTab({ employerGroupId }: { employerGroupId: string }) {
  const [searchParams] = useSearchParams()
  const filters = {
    employer_group_id: employerGroupId,
    search:   searchParams.get('search') || undefined,
    per_page: 25,
    page:     Number(searchParams.get('page') || '1'),
  }
  const { data, isLoading } = useADGroupPolicies(filters)
  const { updateFilter, goToPage } = useSearchParamsPagination()

  return (
    <div className="space-y-3">
      <input
        type="text"
        placeholder="Policy #, customer, employee ID..."
        defaultValue={filters.search}
        onKeyDown={e => { if (e.key === 'Enter') updateFilter('search', (e.target as HTMLInputElement).value) }}
        onBlur={e => updateFilter('search', e.target.value)}
        className="px-3 py-1.5 border border-line rounded-md text-sm w-72 bg-surface text-ink"
      />
      <div className="bg-surface rounded-lg shadow-sm border border-line overflow-hidden">
        <div className="overflow-x-auto">
          <table className="w-full text-sm">
            <thead className="bg-surface-2 text-ink-muted uppercase text-xs tracking-wider">
              <tr>
                <th className="px-4 py-3 text-left">Policy #</th>
                <th className="px-4 py-3 text-left">Employee ID</th>
                <th className="px-4 py-3 text-left">Customer</th>
                <th className="px-4 py-3 text-left">Product</th>
                <th className="px-4 py-3 text-left">Premium</th>
                <th className="px-4 py-3 text-left">Status</th>
                <th className="px-4 py-3 text-left">Date</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-line">
              {isLoading ? (
                <tr><td colSpan={7} className="px-4 py-12 text-center"><LoadingSpinner size="md" /></td></tr>
              ) : data?.data.length === 0 ? (
                <tr><td colSpan={7} className="p-0"><EmptyState compact title="No policies in this group" /></td></tr>
              ) : (
                data?.data.map(p => (
                  <tr key={p.id} className="hover:bg-surface-2 transition">
                    <td className="px-4 py-2">
                      <Link to={`/policies/${p.id}`} className="text-primary hover:underline font-medium">{p.policyNumber}</Link>
                    </td>
                    <td className="px-4 py-2 text-ink-muted">{p.employeeId || '—'}</td>
                    <td className="px-4 py-2 text-ink">{p.customerName || '—'}</td>
                    <td className="px-4 py-2 text-ink-muted">{p.productName || '—'}</td>
                    <td className="px-4 py-2 text-ink">{p.premium ?? '—'}</td>
                    <td className="px-4 py-2 text-ink-muted">{p.status}</td>
                    <td className="px-4 py-2 text-ink-muted whitespace-nowrap">{fmtDate(p.createdAt)}</td>
                  </tr>
                ))
              )}
            </tbody>
          </table>
        </div>
        <PaginationBar meta={data?.meta} goToPage={goToPage} />
      </div>
    </div>
  )
}

function KycTab({ submission }: { submission: NonNullable<ReturnType<typeof useEmployerGroup>['data']>['kycSubmission'] }) {
  if (!submission) {
    return <EmptyState title="No KYC submission yet" description="The employer group has not completed the corporate KYC form." />
  }

  const dataEntries = Object.entries(submission.data).filter(([, v]) => v !== null && v !== '')

  return (
    <div className="space-y-4">
      <div className="text-sm text-ink-muted">Submitted {fmtDateTime(submission.createdAt)}</div>
      <div className="bg-surface rounded-lg shadow-sm border border-line">
        <div className="px-4 py-3 border-b border-line text-sm font-semibold text-ink">Submission Details</div>
        <dl className="p-4 grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-x-6 gap-y-2 text-sm">
          {dataEntries.map(([k, v]) => (
            <div key={k} className="flex justify-between gap-3">
              <dt className="text-ink-faint capitalize shrink-0">{k.replace(/_/g, ' ')}</dt>
              <dd className="text-ink text-right break-words min-w-0">{String(v)}</dd>
            </div>
          ))}
        </dl>
      </div>
      {(['directors', 'shareholders'] as const).map(kind => (
        submission[kind].length > 0 && (
          <div key={kind} className="bg-surface rounded-lg shadow-sm border border-line overflow-hidden">
            <div className="px-4 py-3 border-b border-line text-sm font-semibold text-ink capitalize">{kind} ({submission[kind].length})</div>
            <div className="overflow-x-auto">
              <table className="w-full text-sm">
                <thead className="bg-surface-2 text-ink-muted uppercase text-xs tracking-wider">
                  <tr>
                    {Object.keys(submission[kind][0]).filter(k => !['id', 'submission_id', 'created_at', 'updated_at'].includes(k)).map(k => (
                      <th key={k} className="px-4 py-2 text-left capitalize">{k.replace(/_/g, ' ')}</th>
                    ))}
                  </tr>
                </thead>
                <tbody className="divide-y divide-line">
                  {submission[kind].map((row, i) => (
                    <tr key={i}>
                      {Object.entries(row).filter(([k]) => !['id', 'submission_id', 'created_at', 'updated_at'].includes(k)).map(([k, v]) => (
                        <td key={k} className="px-4 py-2 text-ink">{v == null || v === '' ? '—' : String(v)}</td>
                      ))}
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          </div>
        )
      ))}
      {submission.documents.length > 0 && (
        <div className="bg-surface rounded-lg shadow-sm border border-line">
          <div className="px-4 py-3 border-b border-line text-sm font-semibold text-ink">Uploaded Documents ({submission.documents.length})</div>
          <ul className="divide-y divide-line text-sm">
            {submission.documents.map((d: any, i) => (
              <li key={i} className="px-4 py-2 flex justify-between gap-2">
                <span className="text-ink">{d.original_name || d.field_key || `Document ${i + 1}`}</span>
                {d.url
                  ? <a href={String(d.url)} target="_blank" rel="noreferrer" className="text-primary hover:underline">View</a>
                  : <span className="text-ink-faint text-xs">{d.mime_type || ''}</span>}
              </li>
            ))}
          </ul>
        </div>
      )}
    </div>
  )
}

function AuditTab({ logs }: { logs: NonNullable<ReturnType<typeof useEmployerGroup>['data']>['auditLogs'] }) {
  if (logs.length === 0) {
    return <EmptyState title="No audit entries" description="Actions on this employer group will appear here." />
  }
  return (
    <div className="bg-surface rounded-lg shadow-sm border border-line overflow-hidden">
      <div className="overflow-x-auto">
        <table className="w-full text-sm">
          <thead className="bg-surface-2 text-ink-muted uppercase text-xs tracking-wider">
            <tr>
              <th className="px-4 py-3 text-left">Time</th>
              <th className="px-4 py-3 text-left">Event</th>
              <th className="px-4 py-3 text-left">Actor</th>
              <th className="px-4 py-3 text-left">Details</th>
            </tr>
          </thead>
          <tbody className="divide-y divide-line">
            {logs.map(l => (
              <tr key={l.id} className="hover:bg-surface-2 transition">
                <td className="px-4 py-2 text-ink-muted whitespace-nowrap">{fmtDateTime(l.createdAt)}</td>
                <td className="px-4 py-2"><span className="capitalize font-medium text-ink">{l.event.replace(/_/g, ' ')}</span></td>
                <td className="px-4 py-2 text-ink-muted">{l.actor || '—'}</td>
                <td className="px-4 py-2 text-ink-faint text-xs break-all">
                  {l.details ? JSON.stringify(l.details) : '—'}
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>
    </div>
  )
}
