import { useState } from 'react'
import { useSearchParams, Link } from 'react-router-dom'
import { useReconciliationAnomalies, useAcknowledgeAnomaly, useResolveAnomaly, useMarkFalsePositive } from '../../hooks/useReconciliation'
import LoadingSpinner from '../../components/common/LoadingSpinner'
import type { AnomalyFilters } from '../../api/reconciliation'
import { requestExport, exportStatus, downloadExport } from '../../api/reconciliation'
import { fmtDate } from '../../utils/format'


const STATUS_BADGE: Record<string, string> = {
  open: 'bg-red-100 text-red-700',
  acknowledged: 'bg-blue-100 text-blue-700',
  resolved: 'bg-green-100 text-green-700',
  false_positive: 'bg-gray-100 text-gray-500',
}

const ANOMALY_TYPES = [
  'premium_mismatch', 'partial_payment', 'unpaid_invoice',
  'balance_accumulating', 'payment_gap', 'cancelled_but_collecting',
]

function SeverityIcon({ severity }: { severity: string }) {
  switch (severity) {
    case 'critical':
      return (
        <span title="Critical" className="flex-shrink-0">
          <svg className="w-4 h-4 text-red-600" viewBox="0 0 20 20" fill="currentColor">
            <path fillRule="evenodd" d="M8.485 2.495c.673-1.167 2.357-1.167 3.03 0l6.28 10.875c.673 1.167-.17 2.625-1.516 2.625H3.72c-1.347 0-2.189-1.458-1.515-2.625L8.485 2.495zM10 5a.75.75 0 01.75.75v3.5a.75.75 0 01-1.5 0v-3.5A.75.75 0 0110 5zm0 9a1 1 0 100-2 1 1 0 000 2z" clipRule="evenodd" />
          </svg>
        </span>
      )
    case 'high':
      return (
        <span title="High" className="flex-shrink-0">
          <svg className="w-4 h-4 text-orange-500" viewBox="0 0 20 20" fill="currentColor">
            <path fillRule="evenodd" d="M8.485 2.495c.673-1.167 2.357-1.167 3.03 0l6.28 10.875c.673 1.167-.17 2.625-1.516 2.625H3.72c-1.347 0-2.189-1.458-1.515-2.625L8.485 2.495zM10 5a.75.75 0 01.75.75v3.5a.75.75 0 01-1.5 0v-3.5A.75.75 0 0110 5zm0 9a1 1 0 100-2 1 1 0 000 2z" clipRule="evenodd" />
          </svg>
        </span>
      )
    case 'medium':
      return (
        <span title="Medium" className="flex-shrink-0">
          <svg className="w-4 h-4 text-yellow-500" viewBox="0 0 20 20" fill="currentColor">
            <path fillRule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-8-5a.75.75 0 01.75.75v4.5a.75.75 0 01-1.5 0v-4.5A.75.75 0 0110 5zm0 10a1 1 0 100-2 1 1 0 000 2z" clipRule="evenodd" />
          </svg>
        </span>
      )
    case 'low':
      return (
        <span title="Low" className="flex-shrink-0">
          <svg className="w-4 h-4 text-blue-400" viewBox="0 0 20 20" fill="currentColor">
            <path fillRule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-8-5a.75.75 0 01.75.75v4.5a.75.75 0 01-1.5 0v-4.5A.75.75 0 0110 5zm0 10a1 1 0 100-2 1 1 0 000 2z" clipRule="evenodd" />
          </svg>
        </span>
      )
    default:
      return null
  }
}

export default function ReconciliationAnomaliesPage() {
  const [searchParams, setSearchParams] = useSearchParams()
  const [jumpPage, setJumpPage] = useState('')
  const [resolveId, setResolveId] = useState<number | null>(null)
  const [resolveNotes, setResolveNotes] = useState('')
  const [isExporting, setIsExporting] = useState(false)

  const filters: AnomalyFilters = {
    anomaly_type: searchParams.get('anomaly_type') || undefined,
    severity: searchParams.get('severity') || undefined,
    status: searchParams.get('status') || undefined,
    search: searchParams.get('search') || undefined,
    per_page: 25,
    page: Number(searchParams.get('page') || '1'),
  }

  const { data, isLoading, isFetching } = useReconciliationAnomalies(filters)
  const acknowledgeMutation = useAcknowledgeAnomaly()
  const resolveMutation = useResolveAnomaly()
  const falsePositiveMutation = useMarkFalsePositive()

  function updateFilter(key: string, value: string) {
    const next = new URLSearchParams(searchParams)
    if (value) next.set(key, value)
    else next.delete(key)
    next.delete('page')
    setSearchParams(next)
  }

  function goToPage(page: number) {
    const next = new URLSearchParams(searchParams)
    next.set('page', String(page))
    setSearchParams(next)
  }

  function handleResolve() {
    if (resolveId && resolveNotes.trim()) {
      resolveMutation.mutate({ id: resolveId, notes: resolveNotes.trim() }, {
        onSuccess: () => { setResolveId(null); setResolveNotes('') },
      })
    }
  }

  async function handleExportAll() {
    if (isExporting) return
    setIsExporting(true)
    try {
      // Step 1: request job
      const { job_id } = await requestExport({
        anomaly_type: filters.anomaly_type,
        severity: filters.severity,
        status: filters.status,
        search: filters.search,
      })

      // Step 2: poll until complete (backend generates synchronously so usually 1 poll)
      let attempts = 0
      while (attempts < 60) {
        await new Promise(r => setTimeout(r, 1500))
        const { status } = await exportStatus(job_id)
        if (status === 'completed') {
          // Step 3: download blob
          const blob = await downloadExport(job_id)
          const url = URL.createObjectURL(blob)
          const a = document.createElement('a')
          a.href = url
          a.download = `reconciliation_anomalies_${new Date().toISOString().slice(0, 10)}.csv`
          document.body.appendChild(a)
          a.click()
          document.body.removeChild(a)
          URL.revokeObjectURL(url)
          break
        }
        if (status === 'failed') throw new Error('Export job failed on server')
        attempts++
      }
      if (attempts >= 60) throw new Error('Export timed out — try again')
    } catch (err) {
      console.error('Export failed', err)
      alert('Export failed. Please try again.')
    } finally {
      setIsExporting(false)
    }
  }

  const meta = data?.meta
  const currentPage = meta?.current_page ?? 1
  const hasMore = meta?.has_more ?? false
  const lastPage = meta?.last_page ?? (hasMore ? currentPage + 1 : currentPage)

  function getPageNumbers() {
    // Only show numbered pages when we have a total count
    if (!meta?.total) return []
    const pages: (number | '...')[] = []
    if (lastPage <= 7) {
      for (let i = 1; i <= lastPage; i++) pages.push(i)
    } else {
      pages.push(1)
      if (currentPage > 3) pages.push('...')
      for (let i = Math.max(2, currentPage - 1); i <= Math.min(lastPage - 1, currentPage + 1); i++) pages.push(i)
      if (currentPage < lastPage - 2) pages.push('...')
      pages.push(lastPage)
    }
    return pages
  }

  function fmtAmount(val: number | null) {
    if (val === null || val === undefined) return '--'
    return new Intl.NumberFormat('en-BW', { style: 'currency', currency: 'BWP' }).format(val)
  }

  return (
    <div className="p-6 space-y-4">
      <div className="flex items-center justify-between">
        <h1 className="text-2xl font-bold text-gray-800">Reconciliation Anomalies</h1>
        <div className="flex items-center gap-3">
          <button
            onClick={handleExportAll}
            disabled={isExporting || !data?.data?.length}
            className="flex items-center gap-1.5 px-3 py-1.5 text-sm border border-green-300 text-green-700 rounded-md hover:bg-green-50 disabled:opacity-40 transition"
            title="Export filtered anomalies as CSV"
          >
            {isExporting ? (
              <>
                <svg className="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24">
                  <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4" />
                  <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
                </svg>
                Exporting…
              </>
            ) : (
              <>
                <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" /></svg>
                Export CSV
              </>
            )}
          </button>
          <Link to="/reconciliation" className="text-sm text-blue-600 hover:underline">Back to Dashboard</Link>
        </div>
      </div>

      {/* Filters */}
      <div className="flex flex-wrap gap-3 items-end">
        <div>
          <label className="block text-xs font-medium text-gray-500 mb-1">Search</label>
          <input
            type="text"
            placeholder="Policy number, customer, description..."
            defaultValue={filters.search}
            onKeyDown={e => { if (e.key === 'Enter') updateFilter('search', (e.target as HTMLInputElement).value) }}
            onBlur={e => updateFilter('search', e.target.value)}
            className="px-3 py-1.5 border border-gray-300 rounded-md text-sm w-64 focus:ring-1 focus:ring-blue-500 focus:border-blue-500"
          />
        </div>
        <div>
          <label className="block text-xs font-medium text-gray-500 mb-1">Type</label>
          <select
            value={filters.anomaly_type ?? ''}
            onChange={e => updateFilter('anomaly_type', e.target.value)}
            className="px-3 py-1.5 border border-gray-300 rounded-md text-sm focus:ring-1 focus:ring-blue-500"
          >
            <option value="">All Types</option>
            {ANOMALY_TYPES.map(t => (
              <option key={t} value={t}>{t.replace(/_/g, ' ').replace(/\b\w/g, c => c.toUpperCase())}</option>
            ))}
          </select>
        </div>
        <div>
          <label className="block text-xs font-medium text-gray-500 mb-1">Severity</label>
          <select
            value={filters.severity ?? ''}
            onChange={e => updateFilter('severity', e.target.value)}
            className="px-3 py-1.5 border border-gray-300 rounded-md text-sm focus:ring-1 focus:ring-blue-500"
          >
            <option value="">All Severities</option>
            <option value="critical">Critical</option>
            <option value="high">High</option>
            <option value="medium">Medium</option>
            <option value="low">Low</option>
          </select>
        </div>
        <div>
          <label className="block text-xs font-medium text-gray-500 mb-1">Status</label>
          <select
            value={filters.status ?? ''}
            onChange={e => updateFilter('status', e.target.value)}
            className="px-3 py-1.5 border border-gray-300 rounded-md text-sm focus:ring-1 focus:ring-blue-500"
          >
            <option value="">All Statuses</option>
            <option value="open">Open</option>
            <option value="acknowledged">Acknowledged</option>
            <option value="resolved">Resolved</option>
            <option value="false_positive">False Positive</option>
          </select>
        </div>
      </div>

      {/* Resolve Modal */}
      {resolveId !== null && (
        <div className="fixed inset-0 z-50 flex items-start justify-center bg-black/40 p-4 pt-10 overflow-y-auto" onClick={(e) => { if (e.target === e.currentTarget) { setResolveId(null); setResolveNotes('') } }}>
          <div className="bg-white rounded-lg shadow-xl p-5 w-full max-w-md max-h-[85vh] overflow-y-auto">
            <h3 className="text-lg font-semibold text-gray-800 mb-3">Resolve Anomaly #{resolveId}</h3>
            <textarea
              value={resolveNotes}
              onChange={e => setResolveNotes(e.target.value)}
              placeholder="Enter resolution notes..."
              rows={4}
              className="w-full border border-gray-300 rounded-md px-3 py-2 text-sm focus:ring-1 focus:ring-blue-500 focus:border-blue-500"
            />
            <div className="flex justify-end gap-2 mt-4">
              <button
                onClick={() => { setResolveId(null); setResolveNotes('') }}
                className="px-4 py-2 text-sm border rounded-md text-gray-600 hover:bg-gray-50"
              >
                Cancel
              </button>
              <button
                onClick={handleResolve}
                disabled={!resolveNotes.trim() || resolveMutation.isPending}
                className="px-4 py-2 text-sm bg-green-600 text-white rounded-md hover:bg-green-700 disabled:opacity-50 disabled:cursor-not-allowed"
              >
                {resolveMutation.isPending ? 'Saving...' : 'Resolve'}
              </button>
            </div>
          </div>
        </div>
      )}

      {/* Table */}
      <div className="bg-white rounded-lg shadow-sm border overflow-hidden">
        {(isLoading || isFetching) && (
          <div className="absolute inset-0 bg-white/50 z-10 flex items-center justify-center">
            <LoadingSpinner size="md" />
          </div>
        )}
        <div className="overflow-x-auto relative">
          <table className="w-full text-sm">
            <thead className="bg-gray-50 text-gray-600 uppercase text-xs tracking-wider">
              <tr>
                <th className="px-4 py-3 text-left">Policy #</th>
                <th className="px-4 py-3 text-left">Description</th>
                <th className="px-4 py-3 text-right">Expected</th>
                <th className="px-4 py-3 text-right">Actual</th>
                <th className="px-4 py-3 text-right">Diff</th>
                <th className="px-4 py-3 text-left">Status</th>
                <th className="px-4 py-3 text-left">Last Payment</th>
                <th className="px-4 py-3 text-left">Date</th>
                <th className="px-4 py-3 text-left">Actions</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-gray-100">
              {isLoading ? (
                <tr><td colSpan={9} className="px-4 py-12 text-center"><LoadingSpinner size="md" /></td></tr>
              ) : data?.data.length === 0 ? (
                <tr>
                  <td colSpan={9} className="px-4 py-14 text-center">
                    <div className="flex flex-col items-center gap-3">
                      <div className="w-14 h-14 bg-green-100 rounded-full flex items-center justify-center">
                        <svg className="w-8 h-8 text-green-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2.5}>
                          <path strokeLinecap="round" strokeLinejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                      </div>
                      <p className="text-base font-semibold text-gray-700">All Clear</p>
                      <p className="text-sm text-gray-400">No anomalies match the current filters.</p>
                    </div>
                  </td>
                </tr>
              ) : (
                data?.data.map(anomaly => (
                  <tr key={anomaly.id} className="hover:bg-gray-50 transition">
                    <td className="px-4 py-2">
                      <div className="flex items-center gap-2">
                        <SeverityIcon severity={anomaly.severity} />
                        <Link to={`/policies?search=${anomaly.policyNumber}`} className="font-medium text-blue-600 hover:underline whitespace-nowrap">
                          {anomaly.policyNumber}
                        </Link>
                      </div>
                    </td>
                    <td className="px-4 py-2 text-gray-700 text-xs leading-relaxed">
                      {anomaly.description}
                    </td>
                    <td className="px-4 py-2 text-right font-mono text-xs">{fmtAmount(anomaly.expectedAmount)}</td>
                    <td className="px-4 py-2 text-right font-mono text-xs">{fmtAmount(anomaly.actualAmount)}</td>
                    <td className="px-4 py-2 text-right font-mono text-xs">
                      {anomaly.difference !== null ? (
                        <span className={anomaly.difference < 0 ? 'text-red-600' : 'text-green-600'}>
                          {fmtAmount(anomaly.difference)}
                        </span>
                      ) : '--'}
                    </td>
                    <td className="px-4 py-2">
                      <span className={`px-2 py-0.5 rounded-full text-xs font-medium capitalize ${STATUS_BADGE[anomaly.status] ?? 'bg-gray-100 text-gray-600'}`}>
                        {anomaly.status.replace(/_/g, ' ').replace(/\b\w/g, c => c.toUpperCase())}
                      </span>
                    </td>
                    <td className="px-4 py-2 text-xs">
                      {(anomaly as any).lastPaymentDate ? (
                        <span className={
                          new Date((anomaly as any).lastPaymentDate) < new Date(Date.now() - 90 * 86400000)
                            ? 'text-red-600 font-medium' : 'text-gray-600'
                        }>
                          {new Date((anomaly as any).lastPaymentDate).toLocaleDateString('en-GB', { day: 'numeric', month: 'short', year: 'numeric' })}
                        </span>
                      ) : <span className="text-gray-300">--</span>}
                    </td>
                    <td className="px-4 py-2 text-gray-500 text-xs">
                      {fmtDate(anomaly.createdAt, undefined, '--')}
                    </td>
                    <td className="px-4 py-2">
                      <div className="flex items-center gap-1">
                        {anomaly.status === 'open' && (
                          <button
                            onClick={() => acknowledgeMutation.mutate(anomaly.id)}
                            disabled={acknowledgeMutation.isPending}
                            className="px-2 py-1 text-xs bg-blue-50 text-blue-600 rounded hover:bg-blue-100 disabled:opacity-50"
                            title="Acknowledge"
                          >
                            Ack
                          </button>
                        )}
                        {(anomaly.status === 'open' || anomaly.status === 'acknowledged') && (
                          <>
                            <button
                              onClick={() => setResolveId(anomaly.id)}
                              className="px-2 py-1 text-xs bg-green-50 text-green-600 rounded hover:bg-green-100"
                              title="Resolve"
                            >
                              Resolve
                            </button>
                            <button
                              onClick={() => falsePositiveMutation.mutate(anomaly.id)}
                              disabled={falsePositiveMutation.isPending}
                              className="px-2 py-1 text-xs bg-gray-50 text-gray-500 rounded hover:bg-gray-100 disabled:opacity-50"
                              title="False Positive"
                            >
                              FP
                            </button>
                          </>
                        )}
                      </div>
                    </td>
                  </tr>
                ))
              )}
            </tbody>
          </table>
        </div>

        {/* Pagination */}
        {meta && (currentPage > 1 || hasMore) && (
          <div className="flex items-center justify-between px-4 py-3 border-t bg-gray-50 text-sm">
            <span className="text-gray-500">
              {meta.from != null && meta.to != null ? (
                meta.total != null
                  ? `Showing ${meta.from}–${meta.to} of ${meta.total.toLocaleString()}`
                  : `Showing ${meta.from}–${meta.to}`
              ) : (
                `Page ${currentPage}`
              )}
            </span>
            <div className="flex items-center gap-1">
              <button
                disabled={currentPage === 1}
                onClick={() => goToPage(currentPage - 1)}
                className="px-2.5 py-1 rounded border text-gray-600 hover:bg-gray-100 disabled:opacity-40 disabled:cursor-not-allowed"
              >
                Prev
              </button>
              {/* Numbered pages only when we have a total count */}
              {getPageNumbers().map((p, i) =>
                p === '...' ? (
                  <span key={`e${i}`} className="px-1.5 text-gray-400">...</span>
                ) : (
                  <button
                    key={p}
                    onClick={() => goToPage(p as number)}
                    className={`px-2.5 py-1 rounded border text-sm ${
                      p === currentPage ? 'bg-blue-600 text-white border-blue-600' : 'text-gray-600 hover:bg-gray-100'
                    }`}
                  >
                    {p}
                  </button>
                )
              )}
              {/* Current page badge when no numbered pages */}
              {getPageNumbers().length === 0 && (
                <span className="px-2.5 py-1 rounded border bg-blue-600 text-white text-sm">{currentPage}</span>
              )}
              <button
                disabled={!hasMore && currentPage === lastPage}
                onClick={() => goToPage(currentPage + 1)}
                className="px-2.5 py-1 rounded border text-gray-600 hover:bg-gray-100 disabled:opacity-40 disabled:cursor-not-allowed"
              >
                Next
              </button>
              {meta.total != null && (
                <div className="flex items-center gap-1 ml-3 border-l pl-3">
                  <span className="text-gray-500 text-xs">Go to</span>
                  <input
                    type="number"
                    min={1}
                    max={lastPage}
                    value={jumpPage}
                    onChange={e => setJumpPage(e.target.value)}
                    onKeyDown={e => {
                      if (e.key === 'Enter') {
                        const p = Number(jumpPage)
                        if (p >= 1 && p <= lastPage) { goToPage(p); setJumpPage('') }
                      }
                    }}
                    className="w-14 px-2 py-1 border rounded text-sm text-center"
                    placeholder="#"
                  />
                </div>
              )}
            </div>
          </div>
        )}
      </div>
    </div>
  )
}
