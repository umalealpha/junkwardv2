import { useState, useEffect } from 'react'
import apiClient from '../../api/client'
import { fmtPula } from '../../utils/format'
import EmptyState from '../../components/common/EmptyState'

export default function SubLedgerPage() {
  const [entries, setEntries] = useState<any[]>([])
  const [loading, setLoading] = useState(true)
  const [filters, setFilters] = useState({ policy_id: '', account_id: '', date_from: '', date_to: '' })
  const [page, setPage] = useState(1)
  const [hasMore, setHasMore] = useState(false)

  useEffect(() => {
    setLoading(true)
    const params: any = { page, per_page: 50 }
    Object.entries(filters).forEach(([k, v]) => { if (v) params[k] = v })
    apiClient.get('/sub-ledger', { params })
      .then(r => { setEntries(r.data.data ?? []); setHasMore(r.data.meta?.has_more ?? false) })
      .catch(() => setEntries([]))
      .finally(() => setLoading(false))
  }, [filters, page])

  const fmt = (v: any) => fmtPula(v)

  return (
    <div className="p-6 space-y-4">
      <h1 className="text-2xl font-bold text-gray-800">Sub Ledger</h1>
      <div className="flex gap-3 flex-wrap">
        <input value={filters.policy_id} onChange={e => { setFilters({...filters, policy_id: e.target.value}); setPage(1) }} placeholder="Policy ID" className="px-3 py-1.5 border rounded-md text-sm w-32" />
        <input type="date" value={filters.date_from} onChange={e => { setFilters({...filters, date_from: e.target.value}); setPage(1) }} className="px-3 py-1.5 border rounded-md text-sm" />
        <input type="date" value={filters.date_to} onChange={e => { setFilters({...filters, date_to: e.target.value}); setPage(1) }} className="px-3 py-1.5 border rounded-md text-sm" />
      </div>
      <div className="bg-white shadow rounded-lg overflow-hidden">
        <table className="min-w-full divide-y divide-gray-200 text-sm">
          <thead className="bg-gray-50"><tr>
            <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">ID</th>
            <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Account</th>
            <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Policy</th>
            <th className="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Debit</th>
            <th className="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Credit</th>
            <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Date</th>
          </tr></thead>
          <tbody className="divide-y divide-gray-200">
            {loading && <tr><td colSpan={6} className="px-4 py-8 text-center text-gray-400">Loading...</td></tr>}
            {!loading && entries.length === 0 && <tr><td colSpan={6} className="p-0"><EmptyState compact title="No entries found" description="Try adjusting your filters." /></td></tr>}
            {entries.map((e: any) => (
              <tr key={e.id} className="hover:bg-gray-50">
                <td className="px-4 py-2.5 font-mono text-xs text-gray-500">#{e.id}</td>
                <td className="px-4 py-2.5">{e.account_name || `#${e.account_id}`}</td>
                <td className="px-4 py-2.5 text-blue-600 font-medium">{e.policy_id}</td>
                <td className="px-4 py-2.5 text-right text-red-600 font-medium">{e.debit > 0 ? fmt(e.debit) : '-'}</td>
                <td className="px-4 py-2.5 text-right text-green-600 font-medium">{e.credit > 0 ? fmt(e.credit) : '-'}</td>
                <td className="px-4 py-2.5 text-xs text-gray-400">{e.created_at?.slice(0, 10)}</td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>
      <div className="flex justify-between items-center">
        <button disabled={page <= 1} onClick={() => setPage(p => p - 1)} className="px-3 py-1.5 text-sm border rounded-md disabled:opacity-30">Previous</button>
        <span className="text-sm text-gray-500">Page {page}</span>
        <button disabled={!hasMore} onClick={() => setPage(p => p + 1)} className="px-3 py-1.5 text-sm border rounded-md disabled:opacity-30">Next</button>
      </div>
    </div>
  )
}
