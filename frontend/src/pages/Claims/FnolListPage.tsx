import { useEffect, useState } from 'react'
import { Link, useNavigate } from 'react-router-dom'
import { useClaimsFnolEnabled, useFnols, CLAIMS_FNOL_ROLES } from '../../hooks/useFnol'
import { getStoredRoles } from '../../api/auth'
import type { FnolFilters, FnolStatus } from '../../api/fnol'
import DualScrollTable from '../../components/common/DualScrollTable'
import EmptyState from '../../components/common/EmptyState'
import Pagination from '../../components/common/Pagination'
import Button from '../../components/common/Button'
import { SkeletonTable } from '../../components/common/Skeleton'
import { fmtDate, fmtPula } from '../../utils/format'
import FnolStatusBadge from './FnolStatusBadge'

const STATUS_OPTIONS: { value: FnolStatus | ''; label: string }[] = [
  { value: '', label: 'All Statuses' },
  { value: 'open', label: 'Open' },
  { value: 'converted', label: 'Converted' },
  { value: 'closed', label: 'Closed' },
]

export default function FnolListPage() {
  const navigate = useNavigate()
  const enabled = useClaimsFnolEnabled()
  const hasRole = getStoredRoles().some((r) => CLAIMS_FNOL_ROLES.includes(r))
  const canQuery = enabled && hasRole

  const [filters, setFilters] = useState<FnolFilters>({ per_page: 25, page: 1 })
  const [searchInput, setSearchInput] = useState('')

  // Debounce the free-text search — push into filters 400ms after typing stops
  // so we don't fire a request per keystroke. Resets to page 1 on change.
  useEffect(() => {
    const t = setTimeout(() => {
      setFilters((prev) => {
        const next = searchInput.trim() || undefined
        if (prev.search === next) return prev
        return { ...prev, search: next, page: 1 }
      })
    }, 400)
    return () => clearTimeout(t)
  }, [searchInput])

  const { data, isLoading, error, refetch } = useFnols(filters, canQuery)

  const status = (error as { response?: { status?: number } } | undefined)?.response?.status
  const featureOff = !enabled || status === 404

  if (featureOff) {
    return (
      <div className="p-8">
        <EmptyState
          title="FNOL Intake is not enabled"
          description="This module is turned off. An administrator can enable it under Admin → Integrations to start recording first-notification-of-loss intakes."
          action={<button onClick={() => navigate('/claims')} className="text-sm text-primary underline">Back to Claims</button>}
        />
      </div>
    )
  }

  if (!hasRole || status === 403) {
    return (
      <div className="p-8">
        <EmptyState
          title="No access"
          description="FNOL Intake is available to Claims handlers, Claims Managers and administrators."
          action={<button onClick={() => navigate('/claims')} className="text-sm text-primary underline">Back to Claims</button>}
        />
      </div>
    )
  }

  function patch(p: Partial<FnolFilters>) {
    setFilters((prev) => ({ ...prev, ...p, page: 1 }))
  }

  return (
    <div className="space-y-4">
      {/* Header */}
      <div className="flex items-center justify-between flex-wrap gap-3">
        <div>
          <h1 className="font-heading text-2xl font-bold text-ink">FNOL Intake</h1>
          <p className="text-xs text-ink-muted mt-0.5">
            First Notification of Loss — record a reported loss now, chase documents by email, convert to a full claim once complete.
          </p>
        </div>
        <Button onClick={() => navigate('/claims/fnol/new')}>+ New FNOL</Button>
      </div>

      {/* Filters */}
      <div className="bg-surface rounded-lg border border-line p-4">
        <div className="flex flex-wrap gap-3 items-end">
          <div className="flex-1 min-w-[220px]">
            <label className="text-[10px] text-ink-faint uppercase tracking-wide mb-0.5 block">Search</label>
            <input
              type="text"
              value={searchInput}
              onChange={(e) => setSearchInput(e.target.value)}
              placeholder="FNOL #, claimant, policy…"
              className="w-full px-3 py-2 border border-line rounded-md text-sm bg-surface text-ink"
            />
          </div>
          <div className="flex flex-col">
            <label className="text-[10px] text-ink-faint uppercase tracking-wide mb-0.5">Status</label>
            <select
              value={filters.status ?? ''}
              onChange={(e) => patch({ status: (e.target.value || undefined) as FnolFilters['status'] })}
              className="px-3 py-2 border border-line rounded-md text-sm"
            >
              {STATUS_OPTIONS.map((s) => (
                <option key={s.value} value={s.value}>{s.label}</option>
              ))}
            </select>
          </div>
        </div>
      </div>

      {/* Table */}
      <div className="bg-surface rounded-lg border border-line overflow-hidden">
        {isLoading ? (
          <div className="p-4"><SkeletonTable rows={8} cols={7} /></div>
        ) : error ? (
          <div className="p-8 text-center text-status-danger-fg">
            Failed to load FNOLs. {(error as Error).message}
            <div><button onClick={() => refetch()} className="mt-3 text-sm text-primary underline">Try again</button></div>
          </div>
        ) : !data?.data?.length ? (
          <EmptyState
            title="No FNOLs found"
            description="Try adjusting your filters, or record a new first notification of loss."
            action={<Button size="sm" onClick={() => navigate('/claims/fnol/new')}>+ New FNOL</Button>}
          />
        ) : (
          <>
            <DualScrollTable>
              <table className="w-full text-sm table-sticky-header">
                <thead className="bg-surface-2 border-b border-line text-ink-muted uppercase text-xs tracking-wide">
                  <tr>
                    <th className="px-4 py-2 text-left font-medium sticky top-0 bg-surface-2">FNOL #</th>
                    <th className="px-4 py-2 text-left font-medium sticky top-0 bg-surface-2">Claimant</th>
                    <th className="px-4 py-2 text-left font-medium sticky top-0 bg-surface-2">Policy #</th>
                    <th className="px-4 py-2 text-left font-medium sticky top-0 bg-surface-2">Type</th>
                    <th className="px-4 py-2 text-left font-medium sticky top-0 bg-surface-2">Loss Date</th>
                    <th className="px-4 py-2 text-right font-medium sticky top-0 bg-surface-2">Estimate</th>
                    <th className="px-4 py-2 text-left font-medium sticky top-0 bg-surface-2">Status</th>
                    <th className="px-4 py-2 text-right font-medium sticky top-0 bg-surface-2">Reminders</th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-line">
                  {data.data.map((f) => (
                    <tr
                      key={f.id}
                      className="hover:bg-surface-2 cursor-pointer"
                      onClick={() => navigate(`/claims/fnol/${f.id}`)}
                    >
                      <td className="px-4 py-2 whitespace-nowrap align-top">
                        <div className="font-medium text-brand-navy">{f.fnol_number}</div>
                      </td>
                      <td className="px-4 py-2 text-ink align-top">{f.claimant_name || '-'}</td>
                      <td className="px-4 py-2 text-ink-muted whitespace-nowrap align-top">
                        {f.policy_id ? (
                          <Link
                            to={`/policies/${f.policy_id}`}
                            className="text-brand-navy hover:underline"
                            onClick={(e) => e.stopPropagation()}
                          >
                            {f.policy_number || `#${f.policy_id}`}
                          </Link>
                        ) : (f.policy_number || '-')}
                      </td>
                      <td className="px-4 py-2 text-ink-muted align-top">{f.claim_type || '-'}</td>
                      <td className="px-4 py-2 text-ink-muted whitespace-nowrap align-top">{fmtDate(f.loss_date, undefined, '-')}</td>
                      <td className="px-4 py-2 text-right text-ink-muted whitespace-nowrap align-top tabular-nums">
                        {f.estimate_amount != null ? fmtPula(f.estimate_amount) : '-'}
                      </td>
                      <td className="px-4 py-2 align-top"><FnolStatusBadge status={f.status} /></td>
                      <td className="px-4 py-2 text-right text-ink-muted align-top tabular-nums">
                        {f.reminder_count > 0 ? (
                          <span title={f.last_reminder_at ? `Last sent ${fmtDate(f.last_reminder_at)}` : undefined}>
                            {f.reminder_count} sent
                          </span>
                        ) : '—'}
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </DualScrollTable>

            {data.meta && (
              <Pagination
                currentPage={data.meta.current_page}
                lastPage={data.meta.last_page}
                total={data.meta.total}
                from={((data.meta.current_page - 1) * data.meta.per_page) + 1}
                to={Math.min(data.meta.current_page * data.meta.per_page, data.meta.total)}
                onPageChange={(page) => setFilters((p) => ({ ...p, page }))}
              />
            )}
          </>
        )}
      </div>
    </div>
  )
}
