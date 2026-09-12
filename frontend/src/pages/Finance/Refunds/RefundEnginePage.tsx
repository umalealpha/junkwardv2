import { useMemo, useState } from 'react'
import { Link } from 'react-router-dom'
import { useDeleteRefundRequest, useRestoreRefundRequest, useRefundMetrics, useRefundRequestsList } from '../../../hooks/useRefundRequests'
import { exportRefundRequestsCsv, type RefundArea, type RefundStatus } from '../../../api/refundRequests'
import { fmtDateTime, fmtPula } from '../../../utils/format'
import { AREA_LABEL, STATUS_LABEL, STATUS_PILL, apiErrorMessage, useRefundAccess } from './refundShared'

/**
 * Customer Refund Engine — dashboard + area-scoped worklist.
 * INTAKE → REVIEW → APPROVE happen here; the money leg is Omni's (FNB EFT)
 * and comes back as With Omni → Paid → Posted.
 */
/** The next step a row is waiting for — drives the Actions column label. */
function nextActionLabel(status: RefundStatus): string {
  switch (status) {
    case 'draft':              return 'Edit & submit'
    case 'submitted':          return 'Review'
    case 'under_review':       return 'Approve'
    case 'approval_pending_2': return '2nd approval'
    case 'cfo_pending':        return 'CFO approval'
    case 'escalated':          return 'CFO clearance'
    case 'rejected':           return 'Fix & resubmit'
    default:                   return 'View'
  }
}

/** Money has not moved yet, so the request can still be removed. */
function canDelete(status: RefundStatus): boolean {
  return !['handed_off', 'paid', 'posted'].includes(status)
}

export default function RefundEnginePage() {
  const access = useRefundAccess()
  const [statusFilter, setStatusFilter] = useState<string>('')
  const [areaFilter, setAreaFilter] = useState<string>('')
  const [policySearch, setPolicySearch] = useState('')
  const [clientSearch, setClientSearch] = useState('')
  const [dateFrom, setDateFrom] = useState('')
  const [dateTo, setDateTo] = useState('')
  const [amountMin, setAmountMin] = useState('')
  const [amountMax, setAmountMax] = useState('')
  const [flaggedOnly, setFlaggedOnly] = useState(false)
  const [showFilters, setShowFilters] = useState(false)
  const [page, setPage] = useState(1)
  const [exporting, setExporting] = useState(false)
  const [deleteTarget, setDeleteTarget] = useState<{ id: number; ref: string } | null>(null)
  const [deleteReason, setDeleteReason] = useState('')
  const [deleteError, setDeleteError] = useState<string | null>(null)
  const [viewDeleted, setViewDeleted] = useState(false)
  const del = useDeleteRefundRequest()
  const restore = useRestoreRefundRequest()

  const params = useMemo(() => ({
    ...(statusFilter ? { status: statusFilter as RefundStatus } : {}),
    ...(areaFilter ? { area: areaFilter as RefundArea } : {}),
    ...(policySearch.trim() ? { policy_number: policySearch.trim() } : {}),
    ...(clientSearch.trim() ? { customer_name: clientSearch.trim() } : {}),
    ...(dateFrom ? { date_from: dateFrom } : {}),
    ...(dateTo ? { date_to: dateTo } : {}),
    ...(amountMin ? { amount_min: amountMin } : {}),
    ...(amountMax ? { amount_max: amountMax } : {}),
    ...(flaggedOnly ? { flagged: true } : {}),
    ...(viewDeleted ? { trashed: 'only' as const } : {}),
    page,
    per_page: 25,
  }), [statusFilter, areaFilter, policySearch, clientSearch, dateFrom, dateTo, amountMin, amountMax, flaggedOnly, viewDeleted, page])

  const list = useRefundRequestsList(params)
  const metrics = useRefundMetrics()

  if (!access.areas.length) {
    return (
      <div className="p-6">
        <h1 className="text-2xl font-bold text-ink">Customer Refunds</h1>
        <p className="mt-4 text-sm text-ink-muted">
          You are not assigned to a refund area (MIS or Domestic &amp; Commercial).
          Ask IT to add you to a Refund Engine role.
        </p>
      </div>
    )
  }

  const sc = metrics.data?.status_counts ?? {}
  const openCount = ['submitted', 'under_review', 'escalated', 'approval_pending_2', 'cfo_pending']
    .reduce((acc, s) => acc + (sc[s]?.count ?? 0), 0)
  const cards: { label: string; value: string; sub?: string; accent: string }[] = [
    {
      label: "Today's Intake",
      value: String(metrics.data?.today.count ?? 0),
      sub: fmtPula(metrics.data?.today.value ?? 0),
      accent: 'border-brand-navy',
    },
    { label: 'Awaiting Action', value: String(openCount), sub: 'submitted · review · escalated · CFO', accent: 'border-brand-orange' },
    { label: 'Rejected', value: String(sc.rejected?.count ?? 0), sub: fmtPula(sc.rejected?.value ?? 0), accent: 'border-red-400' },
    {
      label: 'Paid / Posted',
      value: String((sc.paid?.count ?? 0) + (sc.posted?.count ?? 0)),
      sub: fmtPula((sc.paid?.value ?? 0) + (sc.posted?.value ?? 0)),
      accent: 'border-green-500',
    },
  ]

  const rows = list.data?.data ?? []
  const meta = list.data?.meta

  async function handleExport() {
    setExporting(true)
    try {
      await exportRefundRequestsCsv(params)
    } finally {
      setExporting(false)
    }
  }

  return (
    <div className="p-6 space-y-4">
      <div className="flex flex-wrap items-center justify-between gap-3">
        <div>
          <h1 className="text-2xl font-bold text-ink">Customer Refunds</h1>
          <p className="text-sm text-ink-muted mt-1">
            Intake → Review → Approve. Money leaves via Omni/FNB after final approval; paid refunds post
            back to the policy automatically. Areas: {access.areas.map(a => AREA_LABEL[a]).join(', ')}.
          </p>
        </div>
        <div className="flex items-center gap-2">
          {access.canReport && (
            <button
              onClick={handleExport}
              disabled={exporting}
              className="px-3 py-2 text-sm rounded border border-line text-ink hover:bg-surface-2 disabled:opacity-50"
            >
              {exporting ? 'Exporting…' : 'Export CSV'}
            </button>
          )}
          {access.canCreate && (
            <Link
              to="/finance/refund-engine/new"
              className="px-3 py-2 text-sm rounded bg-brand-navy text-white hover:opacity-90"
            >
              + New Refund Request
            </Link>
          )}
        </div>
      </div>

      {/* Metric cards */}
      <div className="grid grid-cols-2 md:grid-cols-4 gap-3">
        {cards.map(c => (
          <div key={c.label} className={`bg-surface rounded-lg shadow-sm border-l-4 ${c.accent} p-4`}>
            <div className="text-xs uppercase tracking-wide text-ink-muted">{c.label}</div>
            <div className="text-2xl font-bold text-ink mt-1">{c.value}</div>
            {c.sub && <div className="text-xs text-ink-muted mt-1">{c.sub}</div>}
          </div>
        ))}
      </div>

      {/* Filters */}
      <div className="flex flex-wrap items-center gap-2">
        <select
          value={areaFilter}
          onChange={e => { setAreaFilter(e.target.value); setPage(1) }}
          className="border border-line rounded px-2 py-1.5 text-sm"
        >
          <option value="">All my areas</option>
          {access.areas.map(a => <option key={a} value={a}>{AREA_LABEL[a]}</option>)}
        </select>
        <select
          value={statusFilter}
          onChange={e => { setStatusFilter(e.target.value); setPage(1) }}
          className="border border-line rounded px-2 py-1.5 text-sm"
        >
          <option value="">All statuses</option>
          {(Object.keys(STATUS_LABEL) as RefundStatus[]).map(s => (
            <option key={s} value={s}>{STATUS_LABEL[s]}</option>
          ))}
        </select>
        <input
          value={policySearch}
          onChange={e => { setPolicySearch(e.target.value); setPage(1) }}
          placeholder="Search policy number…"
          className="border border-line rounded px-2 py-1.5 text-sm w-56"
        />
        <input
          value={clientSearch}
          onChange={e => { setClientSearch(e.target.value); setPage(1) }}
          placeholder="Search client name…"
          className="border border-line rounded px-2 py-1.5 text-sm w-48"
        />
        <button
          onClick={() => setShowFilters(v => !v)}
          className="px-3 py-1.5 text-sm rounded border border-line text-ink hover:bg-surface-2"
        >
          {showFilters ? 'Fewer filters' : 'More filters'}
        </button>
        {access.canCfoApprove && (
          <button
            onClick={() => { setViewDeleted(v => !v); setPage(1) }}
            className={`px-3 py-1.5 text-sm rounded border ${viewDeleted ? 'bg-red-50 border-red-300 text-red-700' : 'border-line text-ink hover:bg-surface-2'}`}
            title="Show deleted refund requests so they can be restored"
          >
            {viewDeleted ? '← Back to active' : 'Deleted'}
          </button>
        )}
        {(dateFrom || dateTo || amountMin || amountMax || flaggedOnly || clientSearch || policySearch || statusFilter || areaFilter) && (
          <button
            onClick={() => {
              setAreaFilter(''); setStatusFilter(''); setPolicySearch(''); setClientSearch('')
              setDateFrom(''); setDateTo(''); setAmountMin(''); setAmountMax(''); setFlaggedOnly(false); setPage(1)
            }}
            className="px-3 py-1.5 text-sm text-brand-navy hover:underline"
          >
            Clear all
          </button>
        )}
      </div>

      {showFilters && (
        <div className="flex flex-wrap items-end gap-3 bg-surface rounded-lg shadow-sm p-3">
          <label className="text-xs text-ink-muted">
            Created from
            <input type="date" value={dateFrom} onChange={e => { setDateFrom(e.target.value); setPage(1) }}
              className="block border border-line rounded px-2 py-1.5 text-sm mt-1" />
          </label>
          <label className="text-xs text-ink-muted">
            Created to
            <input type="date" value={dateTo} onChange={e => { setDateTo(e.target.value); setPage(1) }}
              className="block border border-line rounded px-2 py-1.5 text-sm mt-1" />
          </label>
          <label className="text-xs text-ink-muted">
            Amount from (BWP)
            <input type="number" min="0" step="0.01" value={amountMin} onChange={e => { setAmountMin(e.target.value); setPage(1) }}
              className="block border border-line rounded px-2 py-1.5 text-sm mt-1 w-32" />
          </label>
          <label className="text-xs text-ink-muted">
            Amount to (BWP)
            <input type="number" min="0" step="0.01" value={amountMax} onChange={e => { setAmountMax(e.target.value); setPage(1) }}
              className="block border border-line rounded px-2 py-1.5 text-sm mt-1 w-32" />
          </label>
          <label className="flex items-center gap-2 text-sm text-ink pb-1.5">
            <input type="checkbox" checked={flaggedOnly}
              onChange={e => { setFlaggedOnly(e.target.checked); setPage(1) }} className="rounded" />
            Fraud-flagged only
          </label>
        </div>
      )}

      {/* Worklist */}
      <div className="bg-surface rounded-lg shadow-sm overflow-x-auto">
        <table className="min-w-full text-sm">
          <thead>
            <tr className="border-b bg-surface-2 text-left text-xs uppercase tracking-wide text-ink-muted">
              <th className="px-4 py-3">Ref</th>
              <th className="px-4 py-3">Area</th>
              <th className="px-4 py-3">Policy</th>
              <th className="px-4 py-3">Customer</th>
              <th className="px-4 py-3 text-right">Amount</th>
              <th className="px-4 py-3">Status</th>
              <th className="px-4 py-3">Docs</th>
              <th className="px-4 py-3">Created</th>
              <th className="px-4 py-3 text-right">Actions</th>
            </tr>
          </thead>
          <tbody>
            {list.isLoading && (
              <tr><td colSpan={9} className="px-4 py-8 text-center text-ink-faint">Loading…</td></tr>
            )}
            {!list.isLoading && rows.length === 0 && (
              <tr><td colSpan={9} className="px-4 py-8 text-center text-ink-faint">No refund requests found.</td></tr>
            )}
            {rows.map(r => (
              <tr key={r.id} className="border-b last:border-0 hover:bg-surface-2">
                <td className="px-4 py-2.5">
                  <Link to={`/finance/refund-engine/${r.id}`} className="text-brand-navy font-medium hover:underline">
                    {r.graphite_ref}
                  </Link>
                  {r.after_cutoff && (
                    <span className="ml-2 text-[10px] px-1.5 py-0.5 rounded bg-amber-100 text-amber-800" title="Submitted after the 15:00 cut-off — processes next business day">
                      after 15:00
                    </span>
                  )}
                  {(r.fraud_score ?? 0) > 0 && (
                    <span
                      className={`ml-2 text-[10px] px-1.5 py-0.5 rounded font-bold ${r.fraud_score >= 25 ? 'bg-red-600 text-white' : 'bg-amber-100 text-amber-800'}`}
                      title={r.fraud_score >= 25
                        ? 'CRITICAL fraud flags — approval blocked, CFO clearance required'
                        : 'Fraud signals — open the request to review'}>
                      ⚑ {r.fraud_score}
                    </span>
                  )}
                </td>
                <td className="px-4 py-2.5 text-ink-muted">{AREA_LABEL[r.area]}</td>
                <td className="px-4 py-2.5">{r.policy_number}</td>
                <td className="px-4 py-2.5 text-ink-muted">{r.customer_name || '—'}</td>
                <td className="px-4 py-2.5 text-right font-medium">{fmtPula(r.refund_amount)}</td>
                <td className="px-4 py-2.5">
                  <span className={`inline-block px-2 py-0.5 rounded-full text-xs font-medium ${STATUS_PILL[r.status]}`}>
                    {STATUS_LABEL[r.status]}
                  </span>
                </td>
                <td className="px-4 py-2.5 text-ink-muted">{r.documents_count ?? 0}</td>
                <td className="px-4 py-2.5 text-ink-muted">{fmtDateTime(r.created_at)}</td>
                {/* Actions (Finance ask): the next step for THIS row's status, so
                    reviewers/approvers can see what is theirs to do without
                    opening every request. The action itself (with its reason and
                    confirmations) always happens on the detail page. */}
                <td className="px-4 py-2.5 text-right whitespace-nowrap">
                  {viewDeleted ? (
                    access.canCfoApprove && (
                      <button
                        disabled={restore.isPending}
                        onClick={() => restore.mutateAsync({ id: r.id }).catch(() => {})}
                        className="text-green-700 hover:underline font-medium disabled:opacity-40"
                        title="Restore this deleted refund request">
                        Restore
                      </button>
                    )
                  ) : (
                    <>
                      <Link
                        to={['draft', 'rejected'].includes(r.status) && access.canCreate
                          ? `/finance/refund-engine/${r.id}/edit`
                          : `/finance/refund-engine/${r.id}`}
                        className="text-brand-navy hover:underline font-medium">
                        {nextActionLabel(r.status)}
                      </Link>
                      {canDelete(r.status) && access.canCfoApprove && (
                        <button
                          onClick={() => { setDeleteTarget({ id: r.id, ref: r.graphite_ref }); setDeleteReason(''); setDeleteError(null) }}
                          className="ml-3 text-red-600 hover:underline"
                          title="Delete this refund request (recoverable from the Deleted tab, logged)">
                          Delete
                        </button>
                      )}
                    </>
                  )}
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>

      {/* Delete confirmation — soft delete, recoverable, reason recorded */}
      {deleteTarget && (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4">
          <div className="bg-surface rounded-lg shadow-lg w-full max-w-md p-5 space-y-4">
            <h3 className="text-lg font-semibold text-ink">Delete {deleteTarget.ref}?</h3>
            <p className="text-sm text-ink-muted">
              The request moves to the <strong>Deleted</strong> tab and can be restored from there. It stays in the
              audit trail and still counts towards the fraud checks. Requests where money has already
              moved cannot be deleted.
            </p>
            <textarea value={deleteReason} onChange={e => setDeleteReason(e.target.value)} rows={2}
              placeholder="Reason, at least 10 characters (recorded against your name)…"
              className="w-full border border-line rounded px-3 py-2 text-sm" />
            {deleteError && (
              <div className="rounded border border-red-200 bg-red-50 text-red-700 text-sm px-3 py-2">{deleteError}</div>
            )}
            <div className="flex justify-end gap-2">
              <button onClick={() => { setDeleteTarget(null); setDeleteError(null) }}
                className="px-3 py-2 text-sm rounded border border-line text-ink">Cancel</button>
              <button
                disabled={deleteReason.trim().length < 10 || del.isPending}
                onClick={async () => {
                  setDeleteError(null)
                  try {
                    await del.mutateAsync({ id: deleteTarget.id, reason: deleteReason.trim() })
                    setDeleteTarget(null)
                  } catch (e) {
                    setDeleteError(apiErrorMessage(e))
                  }
                }}
                className="px-3 py-2 text-sm rounded bg-red-600 text-white disabled:opacity-40">
                {del.isPending ? 'Deleting…' : 'Delete'}
              </button>
            </div>
          </div>
        </div>
      )}

      {/* Pagination */}
      {meta && meta.last_page > 1 && (
        <div className="flex items-center justify-between text-sm text-ink-muted">
          <span>Page {meta.current_page} of {meta.last_page} · {meta.total} requests</span>
          <div className="flex gap-2">
            <button
              disabled={meta.current_page <= 1}
              onClick={() => setPage(p => p - 1)}
              className="px-3 py-1.5 rounded border border-line disabled:opacity-40"
            >
              Previous
            </button>
            <button
              disabled={meta.current_page >= meta.last_page}
              onClick={() => setPage(p => p + 1)}
              className="px-3 py-1.5 rounded border border-line disabled:opacity-40"
            >
              Next
            </button>
          </div>
        </div>
      )}
    </div>
  )
}
