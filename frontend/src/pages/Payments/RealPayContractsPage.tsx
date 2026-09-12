import { useState, useEffect } from 'react'
import { Link } from 'react-router-dom'
import apiClient from '../../api/client'
import EmptyState from '../../components/common/EmptyState'

export default function RealPayContractsPage() {
  const [items, setItems] = useState<any[]>([])
  const [loading, setLoading] = useState(true)
  const [search, setSearch] = useState('')
  const [page, setPage] = useState(1)
  const [hasMore, setHasMore] = useState(false)

  useEffect(() => {
    setLoading(true)
    const params: any = { page, per_page: 25 }
    if (search) params.search = search
    apiClient.get('/realpay-contracts', { params })
      .then(r => { setItems(r.data.data ?? []); setHasMore(r.data.meta?.has_more ?? false) })
      .catch(() => setItems([]))
      .finally(() => setLoading(false))
  }, [search, page])

  return (
    <div className="p-6 space-y-4">
      <div className="flex items-center justify-between">
        <h1 className="text-2xl font-bold text-gray-800">RealPay Contracts</h1>
        <input value={search} onChange={e => { setSearch(e.target.value); setPage(1) }} placeholder="Search policy or customer..." className="px-3 py-1.5 border rounded-md text-sm w-64" />
      </div>
      <div className="bg-white shadow rounded-lg overflow-hidden">
        <table className="min-w-full divide-y divide-gray-200 text-sm">
          <thead className="bg-gray-50"><tr>
            <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">ID</th>
            <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Policy</th>
            <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Customer</th>
            <th className="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase">Status</th>
            <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Created</th>
          </tr></thead>
          <tbody className="divide-y divide-gray-200">
            {loading && <tr><td colSpan={5} className="px-4 py-8 text-center text-gray-400">Loading...</td></tr>}
            {!loading && items.length === 0 && <tr><td colSpan={5} className="p-0"><EmptyState compact title="No contracts found" description="Try adjusting your filters." /></td></tr>}
            {items.map((c: any) => (
              <tr key={c.id} className="hover:bg-gray-50">
                <td className="px-4 py-3 font-mono text-xs">#{c.id}</td>
                <td className="px-4 py-3"><Link to={`/policies/${c.policy_id}`} className="text-blue-600 hover:underline font-medium">{c.policyNumber || `#${c.policy_id}`}</Link></td>
                <td className="px-4 py-3">{c.customer_name || '-'}</td>
                <td className="px-4 py-3 text-center">
                  <span className={`px-2 py-0.5 text-xs rounded-full font-medium ${c.status === 'Active' || c.status === 1 ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500'}`}>{c.status ?? '-'}</span>
                </td>
                <td className="px-4 py-3 text-xs text-gray-400">{c.created_at?.slice(0, 10)}</td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>
      <div className="flex justify-between"><button disabled={page<=1} onClick={()=>setPage(p=>p-1)} className="px-3 py-1.5 text-sm border rounded-md disabled:opacity-30">Previous</button><span className="text-sm text-gray-500">Page {page}</span><button disabled={!hasMore} onClick={()=>setPage(p=>p+1)} className="px-3 py-1.5 text-sm border rounded-md disabled:opacity-30">Next</button></div>
    </div>
  )
}
