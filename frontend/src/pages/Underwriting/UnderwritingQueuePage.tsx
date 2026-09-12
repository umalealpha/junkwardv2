import { useState, useEffect } from 'react'
import { Link } from 'react-router-dom'
import apiClient from '../../api/client'
import { fmtPula, fmtNumber } from '../../utils/format'

export default function UnderwritingQueuePage() {
  const [items, setItems] = useState<any[]>([])
  const [summary, setSummary] = useState<any>(null)
  const [loading, setLoading] = useState(true)
  const [search, setSearch] = useState('')
  const [page, setPage] = useState(1)
  const [hasMore, setHasMore] = useState(false)
  const [decideModal, setDecideModal] = useState<any>(null)
  const [decision, setDecision] = useState('approve')
  const [notes, setNotes] = useState('')
  const [deciding, setDeciding] = useState(false)
  const [preview, setPreview] = useState<any>(null)
  const [previewLoading, setPreviewLoading] = useState(false)

  // Fetch risk preview when the modal opens. Shows the UW: total SI,
  // premium, per-group breakdown, validation-rule violations (if any),
  // and the current reinsurance-treaty allocation if the calc has run.
  // Legacy graphiteBWV8 had this implicitly via Submit.php; V2 had
  // nothing — making the decide control unenforceable for SI breaches.
  useEffect(() => {
    if (!decideModal) { setPreview(null); return }
    setPreviewLoading(true)
    apiClient.get(`/underwriting/${decideModal.actionId}/preview`)
      .then(r => setPreview(r.data))
      .catch(() => setPreview(null))
      .finally(() => setPreviewLoading(false))
  }, [decideModal])

  const load = () => {
    setLoading(true)
    const params: any = { page, per_page: 25 }
    if (search) params.search = search
    apiClient.get('/underwriting/queue', { params })
      .then(r => { setItems(r.data.data ?? []); setHasMore(r.data.meta?.has_more ?? false); setSummary(r.data.summary) })
      .catch(() => setItems([]))
      .finally(() => setLoading(false))
  }
  useEffect(() => { load() }, [search, page])

  async function handleDecide() {
    if (!decideModal) return
    setDeciding(true)
    try {
      await apiClient.post(`/underwriting/${decideModal.actionId}/decide`, { decision, notes })
      setDecideModal(null); setNotes(''); load()
    } catch (e: any) { alert(e.response?.data?.message || 'Failed') }
    finally { setDeciding(false) }
  }

  return (
    <div className="p-6 space-y-4">
      <div className="flex items-center justify-between">
        <div>
          <h1 className="text-2xl font-bold text-gray-800">Underwriting Queue</h1>
          {summary && (
            <p className="text-sm text-gray-500 mt-1">
              {summary.total} pending | <span className={summary.overSla > 0 ? 'text-red-600 font-medium' : ''}>{summary.overSla} over SLA (3 days)</span>
            </p>
          )}
        </div>
        <input value={search} onChange={e => { setSearch(e.target.value); setPage(1) }} placeholder="Search policy or customer..." className="px-3 py-1.5 border rounded-md text-sm w-64" />
      </div>

      <div className="bg-white shadow rounded-lg overflow-hidden">
        <table className="min-w-full divide-y divide-gray-200 text-sm">
          <thead className="bg-gray-50"><tr>
            <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Policy</th>
            <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Customer</th>
            <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Product</th>
            <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Type</th>
            <th className="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Premium</th>
            <th className="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase">Days Pending</th>
            <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Submitted By</th>
            <th className="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Action</th>
          </tr></thead>
          <tbody className="divide-y divide-gray-200">
            {loading && <tr><td colSpan={8} className="px-4 py-8 text-center text-gray-400">Loading...</td></tr>}
            {!loading && items.length === 0 && <tr><td colSpan={8} className="px-4 py-8 text-center text-green-600 font-medium">Queue is clear — no policies pending approval.</td></tr>}
            {items.map((item: any) => (
              <tr key={item.actionId} className={`hover:bg-gray-50 ${item.daysPending > 3 ? 'bg-red-50/30' : ''}`}>
                <td className="px-4 py-3"><Link to={`/policies/${item.policyId}`} className="text-blue-600 hover:underline font-medium">{item.policyNumber}</Link></td>
                <td className="px-4 py-3">{item.customerName}</td>
                <td className="px-4 py-3 text-xs">{item.productName}</td>
                <td className="px-4 py-3"><span className="px-2 py-0.5 text-xs bg-blue-100 text-blue-700 rounded-full">{item.transactionType}</span></td>
                <td className="px-4 py-3 text-right font-medium">P {item.premium}</td>
                <td className="px-4 py-3 text-center">
                  <span className={`px-2 py-0.5 text-xs rounded-full font-medium ${item.daysPending > 3 ? 'bg-red-100 text-red-700' : item.daysPending > 1 ? 'bg-yellow-100 text-yellow-700' : 'bg-green-100 text-green-700'}`}>{item.daysPending}d</span>
                </td>
                <td className="px-4 py-3 text-xs text-gray-500">{item.submittedBy}</td>
                <td className="px-4 py-3 text-right">
                  <button onClick={() => { setDecideModal(item); setDecision('approve'); setNotes('') }}
                    className="px-3 py-1 text-xs bg-green-600 text-white rounded-md hover:bg-green-700">Decide</button>
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>
      <div className="flex justify-between"><button disabled={page<=1} onClick={()=>setPage(p=>p-1)} className="px-3 py-1.5 text-sm border rounded-md disabled:opacity-30">Previous</button><span className="text-sm text-gray-500">Page {page}</span><button disabled={!hasMore} onClick={()=>setPage(p=>p+1)} className="px-3 py-1.5 text-sm border rounded-md disabled:opacity-30">Next</button></div>

      {decideModal && (
        <div className="fixed inset-0 z-50 flex items-start justify-center bg-black/40 overflow-y-auto py-4 p-4 pt-10" onClick={(e) => { if (e.target === e.currentTarget) setDecideModal(null) }}>
          <div className="bg-white rounded-lg shadow-xl w-full max-w-2xl p-5 space-y-3 max-h-[85vh] overflow-y-auto">
            <div className="flex items-start justify-between">
              <div>
                <h2 className="text-lg font-bold">UW Decision — {decideModal.policyNumber}</h2>
                <p className="text-sm text-gray-500">{decideModal.customerName} | {decideModal.productName} | Premium P {decideModal.premium}</p>
              </div>
              <button onClick={() => setDecideModal(null)} className="text-gray-400 hover:text-gray-600 text-2xl leading-none">×</button>
            </div>

            {/* ─── Risk Preview ────────────────────────────────────────── */}
            {previewLoading && (
              <div className="text-sm text-gray-400 border rounded-md p-3">Loading risk preview…</div>
            )}
            {preview && (
              <div className="space-y-3">
                {/* Top-line numbers */}
                <div className="grid grid-cols-3 gap-2 text-center">
                  <div className="border rounded-md px-3 py-2">
                    <div className="text-xs text-gray-500">Total SI</div>
                    <div className="text-base font-semibold">{fmtPula(preview.total_sum_insured)}</div>
                  </div>
                  <div className="border rounded-md px-3 py-2">
                    <div className="text-xs text-gray-500">Total Premium</div>
                    <div className="text-base font-semibold">{fmtPula(preview.total_premium)}</div>
                  </div>
                  <div className="border rounded-md px-3 py-2">
                    <div className="text-xs text-gray-500">RI Allocations</div>
                    <div className="text-base font-semibold">{preview.allocations_count ?? 0}</div>
                  </div>
                </div>

                {/* Validation errors — what the submit gate would have blocked */}
                {preview.validation_errors && preview.validation_errors.length > 0 && (
                  <div className="bg-red-50 border border-red-200 rounded-md p-3">
                    <div className="text-sm font-semibold text-red-800 mb-1">⚠ Validation rule violations (submitter should not have passed)</div>
                    <ul className="text-xs text-red-700 list-disc ml-5 space-y-0.5">
                      {preview.validation_errors.map((msg: string, i: number) => <li key={i}>{msg}</li>)}
                    </ul>
                  </div>
                )}

                {/* Per-group breakdown */}
                {preview.coverage_groups && preview.coverage_groups.length > 0 && (
                  <div>
                    <div className="text-xs font-medium text-gray-500 mb-1">Sum Insured by reinsurance group</div>
                    <div className="border rounded-md divide-y max-h-36 overflow-y-auto">
                      {preview.coverage_groups.map((g: any) => (
                        <div key={g.group_id} className="flex items-center justify-between px-3 py-1.5 text-xs">
                          <span className="text-gray-700">{g.group_name}</span>
                          <span className="text-gray-500">
                            SI <span className="font-medium">P {fmtNumber(g.sum_insured, 0)}</span>
                            {' · '}
                            Prem <span className="font-medium">P {fmtNumber(g.premium, 0)}</span>
                          </span>
                        </div>
                      ))}
                    </div>
                  </div>
                )}

                {/* Treaty allocation */}
                {preview.allocations && preview.allocations.length > 0 ? (
                  <div>
                    <div className="text-xs font-medium text-gray-500 mb-1">Reinsurance allocation</div>
                    <div className="border rounded-md divide-y max-h-36 overflow-y-auto">
                      {preview.allocations.map((a: any) => (
                        <div key={a.id} className="flex items-center justify-between px-3 py-1.5 text-xs">
                          <span className="text-gray-700">{a.treaty_name || '(no treaty)'} — <span className="text-gray-500">{a.type_name}</span></span>
                          <span className="text-gray-500">
                            {Math.round((Number(a.percentage) || 0) * 100)}%
                            {' · '}
                            Prem <span className="font-medium">P {fmtNumber(a.premium, 0)}</span>
                          </span>
                        </div>
                      ))}
                    </div>
                  </div>
                ) : (
                  <div className="text-xs text-amber-700 bg-amber-50 border border-amber-200 rounded-md px-3 py-2">
                    No reinsurance allocation recorded for this action yet. Click "Recalculate reinsurance" on the policy page before approving, or this policy will be bound 100% net-retained.
                  </div>
                )}
              </div>
            )}

            {/* ─── Decision controls ────────────────────────────────────── */}
            <div>
              <label className="block text-xs font-medium text-gray-500 mb-1">Decision</label>
              <div className="flex gap-2">
                {['approve', 'reject', 'refer'].map(d => (
                  <button key={d} onClick={() => setDecision(d)}
                    className={`px-4 py-2 text-sm rounded-md font-medium capitalize ${decision === d
                      ? d === 'approve' ? 'bg-green-600 text-white' : d === 'reject' ? 'bg-red-600 text-white' : 'bg-yellow-500 text-white'
                      : 'bg-gray-100 text-gray-600'}`}>{d}</button>
                ))}
              </div>
            </div>
            <div>
              <label className="block text-xs font-medium text-gray-500 mb-1">Notes</label>
              <textarea value={notes} onChange={e => setNotes(e.target.value)} rows={3} className="w-full px-3 py-2 border rounded-md text-sm" placeholder="Underwriting notes..." />
            </div>
            <div className="flex justify-end gap-2">
              <button onClick={() => setDecideModal(null)} className="px-4 py-2 text-sm border rounded-md">Cancel</button>
              <button onClick={handleDecide} disabled={deciding}
                className={`px-4 py-2 text-sm text-white rounded-md disabled:opacity-50 ${decision === 'approve' ? 'bg-green-600' : decision === 'reject' ? 'bg-red-600' : 'bg-yellow-500'}`}>
                {deciding ? 'Processing...' : `Confirm ${decision}`}
              </button>
            </div>
          </div>
        </div>
      )}
    </div>
  )
}
