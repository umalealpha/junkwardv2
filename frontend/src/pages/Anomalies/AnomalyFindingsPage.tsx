import { useState } from 'react'
import { useSearchParams } from 'react-router-dom'
import {
  useAnomalyFindings,
  useAnomalySummary,
  useReviewFinding,
  useResolveFinding,
  useDismissFinding,
} from '../../hooks/useAnomalyFindings'
import LoadingSpinner from '../../components/common/LoadingSpinner'
import type { AnomalyFinding, FindingFilters } from '../../api/anomalyFindings'
import { fmtDateTime } from '../../utils/format'

// ── visual config ────────────────────────────────────────────────────────────

const STATUS_BADGE: Record<string, string> = {
  open:      'bg-red-100 text-red-700',
  reviewing: 'bg-blue-100 text-blue-700',
  resolved:  'bg-green-100 text-green-700',
  dismissed: 'bg-gray-100 text-gray-500',
}

const BRANCH_BADGE: Record<string, string> = {
  motor:       'bg-indigo-100 text-indigo-700',
  cellphone:   'bg-violet-100 text-violet-700',
  'non-motor': 'bg-amber-100 text-amber-700',
}

const ANOMALY_TYPE_LABEL: Record<string, string> = {
  duplicate_policies:        'Duplicate active policies',
  cancelled_collecting:      'Cancelled but collecting',
  premium_mismatch:          'Premium amount mismatch',
  large_claim:               'Large claim filed',
  expired_active:            'Expired but active',
  zero_collections:          'Zero collections today',
  payment_failure_spike:     'Payment failure spike',
  lapse_spike:               'Lapse spike',
}

function describeAnomaly(t: string) {
  return ANOMALY_TYPE_LABEL[t] ?? t.replace(/_/g, ' ')
}

// ── component ────────────────────────────────────────────────────────────────

export default function AnomalyFindingsPage() {
  const [searchParams, setSearchParams] = useSearchParams()
  const [jumpPage, setJumpPage] = useState('')
  const [resolveTarget, setResolveTarget] = useState<AnomalyFinding | null>(null)
  const [dismissTarget, setDismissTarget] = useState<AnomalyFinding | null>(null)
  const [actionNotes, setActionNotes] = useState('')

  const filters: FindingFilters = {
    anomaly_type: searchParams.get('anomaly_type') || undefined,
    branch:       searchParams.get('branch')       || undefined,
    status:       searchParams.get('status')       || undefined,
    search:       searchParams.get('search')       || undefined,
    date_from:    searchParams.get('date_from')    || undefined,
    date_to:      searchParams.get('date_to')      || undefined,
    page:         Number(searchParams.get('page') || '1'),
    per_page:     25,
  }

  const { data, isLoading, isFetching } = useAnomalyFindings(filters)
  const { data: summary } = useAnomalySummary()
  const reviewMut  = useReviewFinding()
  const resolveMut = useResolveFinding()
  const dismissMut = useDismissFinding()

  function setFilter(key: string, value: string) {
    const next = new URLSearchParams(searchParams)
    if (value) next.set(key, value); else next.delete(key)
    next.delete('page')
    setSearchParams(next)
  }

  function goToPage(page: number) {
    const next = new URLSearchParams(searchParams)
    next.set('page', String(page))
    setSearchParams(next)
  }

  function clearFilters() {
    setSearchParams(new URLSearchParams())
  }

  const filterActive = !!(filters.anomaly_type || filters.branch || filters.status || filters.search || filters.date_from || filters.date_to)

  function submitResolve() {
    if (!resolveTarget || actionNotes.trim().length < 5) return
    resolveMut.mutate({ id: resolveTarget.id, notes: actionNotes.trim() }, {
      onSuccess: () => { setResolveTarget(null); setActionNotes('') },
    })
  }

  function submitDismiss() {
    if (!dismissTarget || actionNotes.trim().length < 5) return
    dismissMut.mutate({ id: dismissTarget.id, notes: actionNotes.trim() }, {
      onSuccess: () => { setDismissTarget(null); setActionNotes('') },
    })
  }

  if (isLoading) {
    return <div className="p-6 flex items-center justify-center min-h-[400px]"><LoadingSpinner size="lg" /></div>
  }

  return (
    <div className="p-6 space-y-6">

      {/* ── Header ── */}
      <div className="flex items-center justify-between">
        <div>
          <h1 className="text-2xl font-bold text-gray-800">Anomaly Findings</h1>
          <p className="text-sm text-gray-500 mt-1">
            Operational anomalies detected by the engine — duplicate policies, premium mismatches, lapse spikes, and more.
          </p>
        </div>
        <div className="flex items-center gap-3">
          {isFetching && <span className="text-xs text-gray-400">refreshing…</span>}
        </div>
      </div>

      {/* ── Summary tiles ── */}
      {summary && (
        <div className="grid grid-cols-2 sm:grid-cols-4 gap-3">
          <SummaryTile label="Open"      value={summary.byStatus?.open      ?? 0} bg="bg-red-50"    text="text-red-700" />
          <SummaryTile label="Reviewing" value={summary.byStatus?.reviewing ?? 0} bg="bg-blue-50"   text="text-blue-700" />
          <SummaryTile label="Resolved"  value={summary.byStatus?.resolved  ?? 0} bg="bg-green-50"  text="text-green-700" />
          <SummaryTile label="Dismissed" value={summary.byStatus?.dismissed ?? 0} bg="bg-gray-50"   text="text-gray-600" />
        </div>
      )}

      {/* ── Filters ── */}
      <div className="bg-white border border-gray-200 rounded-lg p-4 flex flex-wrap items-end gap-3">
        <div className="flex-1 min-w-[180px]">
          <label className="block text-xs font-medium text-gray-600 mb-1">Anomaly type</label>
          <select className="w-full border border-gray-300 rounded px-2 py-1.5 text-sm"
                  value={filters.anomaly_type ?? ''}
                  onChange={e => setFilter('anomaly_type', e.target.value)}>
            <option value="">All</option>
            {Object.entries(ANOMALY_TYPE_LABEL).map(([k, v]) => (
              <option key={k} value={k}>{v}</option>
            ))}
          </select>
        </div>

        <div className="flex-1 min-w-[140px]">
          <label className="block text-xs font-medium text-gray-600 mb-1">Branch</label>
          <select className="w-full border border-gray-300 rounded px-2 py-1.5 text-sm"
                  value={filters.branch ?? ''}
                  onChange={e => setFilter('branch', e.target.value)}>
            <option value="">All</option>
            <option value="motor">Motor</option>
            <option value="cellphone">Cellphone / electronic</option>
            <option value="non-motor">Other non-motor</option>
          </select>
        </div>

        <div className="flex-1 min-w-[140px]">
          <label className="block text-xs font-medium text-gray-600 mb-1">Status</label>
          <select className="w-full border border-gray-300 rounded px-2 py-1.5 text-sm"
                  value={filters.status ?? ''}
                  onChange={e => setFilter('status', e.target.value)}>
            <option value="">All</option>
            <option value="open">Open</option>
            <option value="reviewing">Reviewing</option>
            <option value="resolved">Resolved</option>
            <option value="dismissed">Dismissed</option>
          </select>
        </div>

        <div className="flex-1 min-w-[200px]">
          <label className="block text-xs font-medium text-gray-600 mb-1">Search</label>
          <input type="text" className="w-full border border-gray-300 rounded px-2 py-1.5 text-sm"
                 placeholder="Customer / product / plate / IMEI / policy"
                 defaultValue={filters.search ?? ''}
                 onKeyDown={e => { if (e.key === 'Enter') setFilter('search', (e.target as HTMLInputElement).value) }} />
        </div>

        <div>
          <label className="block text-xs font-medium text-gray-600 mb-1">From</label>
          <input type="date" className="border border-gray-300 rounded px-2 py-1.5 text-sm"
                 value={filters.date_from ?? ''}
                 onChange={e => setFilter('date_from', e.target.value)} />
        </div>
        <div>
          <label className="block text-xs font-medium text-gray-600 mb-1">To</label>
          <input type="date" className="border border-gray-300 rounded px-2 py-1.5 text-sm"
                 value={filters.date_to ?? ''}
                 onChange={e => setFilter('date_to', e.target.value)} />
        </div>

        {filterActive && (
          <button onClick={clearFilters}
                  className="text-sm text-gray-500 hover:text-gray-700 underline self-end pb-1.5">
            Clear filters
          </button>
        )}
      </div>

      {/* ── Findings table ── */}
      <div className="bg-white border border-gray-200 rounded-lg overflow-hidden">
        <table className="w-full text-sm">
          <thead className="bg-gray-50 text-xs text-gray-600 uppercase">
            <tr>
              <th className="px-3 py-2 text-left">Detected</th>
              <th className="px-3 py-2 text-left">Type</th>
              <th className="px-3 py-2 text-left">Branch</th>
              <th className="px-3 py-2 text-left">Customer</th>
              <th className="px-3 py-2 text-left">Product</th>
              <th className="px-3 py-2 text-left">Device key</th>
              <th className="px-3 py-2 text-center"># pol.</th>
              <th className="px-3 py-2 text-left">Policies</th>
              <th className="px-3 py-2 text-left">Status</th>
              <th className="px-3 py-2 text-right">Actions</th>
            </tr>
          </thead>
          <tbody className="divide-y divide-gray-100">
            {data?.data.map(f => (
              <tr key={f.id} className="hover:bg-gray-50">
                <td className="px-3 py-2 whitespace-nowrap text-gray-600">{fmtDateTime(f.detectedAt)}</td>
                <td className="px-3 py-2 text-gray-800">{describeAnomaly(f.anomalyType)}</td>
                <td className="px-3 py-2">
                  {f.branch && (
                    <span className={`px-2 py-0.5 rounded text-xs font-medium ${BRANCH_BADGE[f.branch] ?? 'bg-gray-100 text-gray-600'}`}>
                      {f.branch}
                    </span>
                  )}
                </td>
                <td className="px-3 py-2 text-gray-700">
                  {f.customerName ?? '—'}
                  {f.customerId && <span className="text-xs text-gray-400 ml-1">#{f.customerId}</span>}
                </td>
                <td className="px-3 py-2 text-gray-700">{f.productName ?? '—'}</td>
                <td className="px-3 py-2 font-mono text-xs text-gray-600">{f.deviceKey ?? '—'}</td>
                <td className="px-3 py-2 text-center font-semibold text-gray-800">{f.policyCount}</td>
                <td className="px-3 py-2 font-mono text-xs text-gray-600">{f.policyNumbers ?? '—'}</td>
                <td className="px-3 py-2">
                  <span className={`px-2 py-0.5 rounded text-xs font-medium ${STATUS_BADGE[f.status]}`}>
                    {f.status}
                  </span>
                </td>
                <td className="px-3 py-2 text-right">
                  {f.status === 'open' && (
                    <div className="flex items-center justify-end gap-1">
                      <button
                        onClick={() => reviewMut.mutate(f.id)}
                        className="text-xs px-2 py-1 rounded border border-blue-300 text-blue-700 hover:bg-blue-50">
                        Review
                      </button>
                      <button
                        onClick={() => { setResolveTarget(f); setActionNotes('') }}
                        className="text-xs px-2 py-1 rounded border border-green-300 text-green-700 hover:bg-green-50">
                        Resolve
                      </button>
                      <button
                        onClick={() => { setDismissTarget(f); setActionNotes('') }}
                        className="text-xs px-2 py-1 rounded border border-gray-300 text-gray-600 hover:bg-gray-50">
                        Dismiss
                      </button>
                    </div>
                  )}
                  {f.status === 'reviewing' && (
                    <div className="flex items-center justify-end gap-1">
                      <button
                        onClick={() => { setResolveTarget(f); setActionNotes('') }}
                        className="text-xs px-2 py-1 rounded border border-green-300 text-green-700 hover:bg-green-50">
                        Resolve
                      </button>
                      <button
                        onClick={() => { setDismissTarget(f); setActionNotes('') }}
                        className="text-xs px-2 py-1 rounded border border-gray-300 text-gray-600 hover:bg-gray-50">
                        Dismiss
                      </button>
                    </div>
                  )}
                  {(f.status === 'resolved' || f.status === 'dismissed') && f.reviewNote && (
                    <span className="text-xs text-gray-400 italic" title={f.reviewNote}>
                      "{f.reviewNote.slice(0, 30)}{f.reviewNote.length > 30 ? '…' : ''}"
                    </span>
                  )}
                </td>
              </tr>
            ))}
            {(!data?.data || data.data.length === 0) && (
              <tr>
                <td colSpan={10} className="px-3 py-8 text-center text-sm text-gray-400">
                  No anomaly findings match your filters.
                </td>
              </tr>
            )}
          </tbody>
        </table>

        {/* Pagination */}
        {data && data.lastPage > 1 && (
          <div className="px-4 py-3 border-t border-gray-100 flex items-center justify-between text-sm">
            <span className="text-gray-500">
              Page {data.currentPage} of {data.lastPage} — {data.total} total findings
            </span>
            <div className="flex items-center gap-2">
              <button
                disabled={data.currentPage <= 1}
                onClick={() => goToPage(data.currentPage - 1)}
                className="px-2 py-1 border border-gray-300 rounded disabled:opacity-40">
                ← Prev
              </button>
              <input
                type="number"
                value={jumpPage}
                onChange={e => setJumpPage(e.target.value)}
                onKeyDown={e => {
                  if (e.key === 'Enter') {
                    const n = Number(jumpPage)
                    if (n >= 1 && n <= data.lastPage) { goToPage(n); setJumpPage('') }
                  }
                }}
                placeholder="Jump"
                className="w-16 border border-gray-300 rounded px-2 py-1 text-sm" />
              <button
                disabled={data.currentPage >= data.lastPage}
                onClick={() => goToPage(data.currentPage + 1)}
                className="px-2 py-1 border border-gray-300 rounded disabled:opacity-40">
                Next →
              </button>
            </div>
          </div>
        )}
      </div>

      {/* ── Resolve modal ── */}
      {resolveTarget && (
        <ActionModal
          title="Resolve finding"
          target={resolveTarget}
          notes={actionNotes}
          setNotes={setActionNotes}
          onConfirm={submitResolve}
          confirmLabel="Resolve"
          confirmClass="bg-green-600 hover:bg-green-700"
          onClose={() => { setResolveTarget(null); setActionNotes('') }}
          pending={resolveMut.isPending}
          help="Describe how the underlying issue was fixed (e.g. duplicate policy MIS… cancelled, premium adjusted on policy MIS…)."
        />
      )}

      {/* ── Dismiss modal ── */}
      {dismissTarget && (
        <ActionModal
          title="Dismiss finding"
          target={dismissTarget}
          notes={actionNotes}
          setNotes={setActionNotes}
          onConfirm={submitDismiss}
          confirmLabel="Dismiss"
          confirmClass="bg-gray-700 hover:bg-gray-800"
          onClose={() => { setDismissTarget(null); setActionNotes('') }}
          pending={dismissMut.isPending}
          help="Why is this not a real issue? (false positive, intentional duplicate, etc.) — needed for audit."
        />
      )}
    </div>
  )
}

// ── small components ─────────────────────────────────────────────────────────

function SummaryTile({ label, value, bg, text }: { label: string; value: number; bg: string; text: string }) {
  return (
    <div className={`${bg} ${text} rounded-lg p-4`}>
      <div className="text-xs uppercase tracking-wide opacity-80">{label}</div>
      <div className="text-2xl font-bold mt-1">{value}</div>
    </div>
  )
}

function ActionModal({
  title, target, notes, setNotes, onConfirm, confirmLabel, confirmClass, onClose, pending, help,
}: {
  title: string
  target: AnomalyFinding
  notes: string
  setNotes: (s: string) => void
  onConfirm: () => void
  confirmLabel: string
  confirmClass: string
  onClose: () => void
  pending: boolean
  help: string
}) {
  return (
    <div className="fixed inset-0 z-50 bg-black/40 flex items-start justify-center p-4 pt-10 overflow-y-auto" onClick={onClose}>
      <div className="bg-white rounded-lg shadow-xl max-w-lg w-full p-5 space-y-3 max-h-[85vh] overflow-y-auto" onClick={e => e.stopPropagation()}>
        <h3 className="text-lg font-semibold text-gray-800">{title}</h3>
        <div className="text-sm text-gray-600 bg-gray-50 rounded p-3 space-y-1">
          <div><span className="text-gray-400">Customer:</span> {target.customerName ?? '—'} <span className="text-xs text-gray-400">#{target.customerId}</span></div>
          <div><span className="text-gray-400">Product:</span> {target.productName ?? '—'}</div>
          {target.deviceKey && <div><span className="text-gray-400">Device:</span> <span className="font-mono">{target.deviceKey}</span></div>}
          <div><span className="text-gray-400">Policies:</span> <span className="font-mono">{target.policyNumbers}</span></div>
        </div>
        <div>
          <label className="block text-xs font-medium text-gray-600 mb-1">Notes (required, min 5 chars)</label>
          <textarea
            value={notes}
            onChange={e => setNotes(e.target.value)}
            rows={3}
            className="w-full border border-gray-300 rounded px-2 py-1.5 text-sm"
            placeholder={help} />
          <p className="text-xs text-gray-400 mt-1">{help}</p>
        </div>
        <div className="flex items-center justify-end gap-2 pt-2">
          <button onClick={onClose} className="px-3 py-1.5 text-sm rounded border border-gray-300 text-gray-700 hover:bg-gray-50">
            Cancel
          </button>
          <button
            onClick={onConfirm}
            disabled={pending || notes.trim().length < 5}
            className={`px-3 py-1.5 text-sm rounded text-white ${confirmClass} disabled:opacity-50`}>
            {pending ? 'Saving…' : confirmLabel}
          </button>
        </div>
      </div>
    </div>
  )
}
