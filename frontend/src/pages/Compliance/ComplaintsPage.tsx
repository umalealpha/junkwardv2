import { useState, useEffect } from 'react'
import apiClient from '../../api/client'
import EmptyState from '../../components/common/EmptyState'
import { exportComplaintsRegister, fetchComplaintLookups } from '../../api/claims'

/**
 * Regulatory Complaints Register — read/filter/export view.
 *
 * Complaints are captured by handlers against the relevant claim (Claim detail →
 * Complaint Log tab). This page lists them for compliance, filters by date range
 * + status, and exports the quarterly regulator register (xlsx).
 */
export default function ComplaintsPage() {
  const [items, setItems] = useState<any[]>([])
  const [loading, setLoading] = useState(true)
  const [page, setPage] = useState(1)
  const [hasMore, setHasMore] = useState(false)
  const [statusOpts, setStatusOpts] = useState<string[]>([])
  const [exporting, setExporting] = useState(false)
  const [filters, setFilters] = useState({ date_from: '', date_to: '', status: '' })

  const cleanFilters = () => {
    const o: Record<string, string> = {}
    if (filters.date_from) o.date_from = filters.date_from
    if (filters.date_to) o.date_to = filters.date_to
    if (filters.status) o.status = filters.status
    return o
  }

  const load = () => {
    setLoading(true)
    apiClient.get('/complaints', { params: { page, per_page: 25, ...cleanFilters() } })
      .then(r => { setItems(r.data.data ?? []); setHasMore(r.data.meta?.has_more ?? false) })
      .catch(() => setItems([]))
      .finally(() => setLoading(false))
  }

  useEffect(() => { load() }, [page])
  useEffect(() => { fetchComplaintLookups().then(l => setStatusOpts(l.status ?? [])).catch(() => setStatusOpts([])) }, [])

  const applyFilters = () => { setPage(1); load() }

  async function handleExport() {
    setExporting(true)
    try { await exportComplaintsRegister(cleanFilters()) }
    catch (e: any) { alert(e?.response?.data?.message || 'Export failed') }
    finally { setExporting(false) }
  }

  return (
    <div className="p-6 space-y-4">
      <div className="flex items-center justify-between">
        <div>
          <h1 className="text-2xl font-bold text-ink">Complaints Register</h1>
          <p className="text-sm text-ink-muted mt-1">Regulatory register · TCF (Treating Customers Fairly). Log complaints from the claim's Complaint Log tab.</p>
        </div>
        <button onClick={handleExport} disabled={exporting}
          className="px-4 py-2 bg-primary text-white text-sm rounded-md hover:bg-primary disabled:opacity-50">
          {exporting ? 'Exporting…' : 'Export Register (xlsx)'}
        </button>
      </div>

      {/* Filters */}
      <div className="bg-surface shadow rounded-lg p-4 flex flex-wrap items-end gap-3">
        <div>
          <label className="block text-xs font-medium text-ink-muted mb-1">Date filed from</label>
          <input type="date" value={filters.date_from} onChange={e => setFilters(f => ({ ...f, date_from: e.target.value }))} className="px-3 py-2 border border-line rounded-md text-sm" />
        </div>
        <div>
          <label className="block text-xs font-medium text-ink-muted mb-1">Date filed to</label>
          <input type="date" value={filters.date_to} onChange={e => setFilters(f => ({ ...f, date_to: e.target.value }))} className="px-3 py-2 border border-line rounded-md text-sm" />
        </div>
        <div>
          <label className="block text-xs font-medium text-ink-muted mb-1">Status</label>
          <select value={filters.status} onChange={e => setFilters(f => ({ ...f, status: e.target.value }))} className="px-3 py-2 border border-line rounded-md text-sm">
            <option value="">All</option>
            {statusOpts.map(s => <option key={s} value={s}>{s}</option>)}
          </select>
        </div>
        <button onClick={applyFilters} className="px-4 py-2 border border-line bg-surface hover:bg-surface-2 text-ink text-sm rounded-md">Apply</button>
        {(filters.date_from || filters.date_to || filters.status) && (
          <button onClick={() => { setFilters({ date_from: '', date_to: '', status: '' }); setPage(1); setTimeout(load, 0) }}
            className="px-3 py-2 text-sm border border-line rounded-md text-ink-muted">Clear</button>
        )}
      </div>

      <div className="bg-surface shadow rounded-lg overflow-x-auto">
        <table className="min-w-full divide-y divide-line text-sm">
          <thead className="bg-surface-2"><tr>
            <th className="px-4 py-3 text-left text-xs font-medium text-ink-muted uppercase">Reference</th>
            <th className="px-4 py-3 text-left text-xs font-medium text-ink-muted uppercase">Complainant</th>
            <th className="px-4 py-3 text-left text-xs font-medium text-ink-muted uppercase">Nature</th>
            <th className="px-4 py-3 text-left text-xs font-medium text-ink-muted uppercase">Status</th>
            <th className="px-4 py-3 text-left text-xs font-medium text-ink-muted uppercase">Handler</th>
            <th className="px-4 py-3 text-left text-xs font-medium text-ink-muted uppercase">Date Filed</th>
            <th className="px-4 py-3 text-left text-xs font-medium text-ink-muted uppercase">Logged By</th>
          </tr></thead>
          <tbody className="divide-y divide-line">
            {loading && <tr><td colSpan={7} className="px-4 py-8 text-center text-ink-faint">Loading...</td></tr>}
            {!loading && items.length === 0 && <tr><td colSpan={7} className="p-0"><EmptyState compact title="No complaints found" description="No complaints match the current filters." /></td></tr>}
            {items.map((c: any) => (
              <tr key={c.id} className="hover:bg-surface-2">
                <td className="px-4 py-3 font-medium text-primary">{c.reference_number || c.claim_number || c.policyNumber || '-'}</td>
                <td className="px-4 py-3">{c.complainant_name || '-'}</td>
                <td className="px-4 py-3">{c.nature || c.complaint_of || '-'}</td>
                <td className="px-4 py-3">{c.status || '-'}</td>
                <td className="px-4 py-3 text-xs">{c.handler_name || '-'}</td>
                <td className="px-4 py-3 text-xs text-ink-faint">{(c.date_filed || c.created_at)?.slice(0, 10)}</td>
                <td className="px-4 py-3 text-xs">{c.added_by_name || '-'}</td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>

      <div className="flex justify-between">
        <button disabled={page <= 1} onClick={() => setPage(p => p - 1)} className="px-3 py-1.5 text-sm border border-line rounded-md disabled:opacity-30">Previous</button>
        <span className="text-sm text-ink-muted">Page {page}</span>
        <button disabled={!hasMore} onClick={() => setPage(p => p + 1)} className="px-3 py-1.5 text-sm border border-line rounded-md disabled:opacity-30">Next</button>
      </div>
    </div>
  )
}
