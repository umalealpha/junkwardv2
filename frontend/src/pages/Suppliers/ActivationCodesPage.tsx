import { useState, useEffect } from 'react'
import apiClient from '../../api/client'
import EmptyState from '../../components/common/EmptyState'

export default function ActivationCodesPage() {
  const [items, setItems] = useState<any[]>([])
  const [loading, setLoading] = useState(true)
  const [search, setSearch] = useState('')
  const [checkCode, setCheckCode] = useState('')
  const [checkResult, setCheckResult] = useState<any>(null)
  const [tab, setTab] = useState<'all' | 'activated'>('all')
  const [page, setPage] = useState(1)
  const [hasMore, setHasMore] = useState(false)

  useEffect(() => {
    setLoading(true)
    const params: any = { page, per_page: 25 }
    if (search) params.search = search
    const endpoint = tab === 'activated' ? '/activation-codes/activated' : '/activation-codes'
    apiClient.get(endpoint, { params })
      .then(r => { setItems(r.data.data ?? []); setHasMore(r.data.meta?.has_more ?? false) })
      .catch(() => setItems([]))
      .finally(() => setLoading(false))
  }, [search, page, tab])

  async function handleCheck() {
    if (!checkCode) return
    try {
      const { data } = await apiClient.post('/activation-codes/check', { code: checkCode })
      setCheckResult(data.data)
    } catch { setCheckResult({ valid: false, status: 'Not Found' }) }
  }

  return (
    <div className="p-6 space-y-4">
      <h1 className="text-2xl font-bold text-gray-800">Activation Codes</h1>

      {/* Check Code */}
      <div className="bg-white shadow rounded-lg p-4 flex items-end gap-3">
        <div className="flex-1">
          <label className="block text-xs font-medium text-gray-500 mb-1">Check Activation Code</label>
          <input value={checkCode} onChange={e => setCheckCode(e.target.value)} onKeyDown={e => e.key === 'Enter' && handleCheck()}
            placeholder="Enter serial or activation code..." className="w-full px-3 py-2 border rounded-md text-sm" />
        </div>
        <button onClick={handleCheck} className="px-4 py-2 bg-blue-600 text-white text-sm rounded-md hover:bg-blue-700">Check</button>
        {checkResult && (
          <div className={`px-4 py-2 rounded-md text-sm font-medium ${checkResult.valid ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700'}`}>
            {checkResult.valid ? `Valid — ${checkResult.productName} (${checkResult.activationCode})` : `${checkResult.status || 'Invalid/Used'}`}
          </div>
        )}
      </div>

      {/* Tabs */}
      <div className="flex gap-2">
        {(['all', 'activated'] as const).map(t => (
          <button key={t} onClick={() => { setTab(t); setPage(1) }}
            className={`px-4 py-1.5 text-sm rounded-md font-medium ${tab === t ? 'bg-blue-600 text-white' : 'bg-gray-100 text-gray-600 hover:bg-gray-200'}`}>
            {t === 'all' ? 'All Codes' : 'Activated'}
          </button>
        ))}
        <input value={search} onChange={e => { setSearch(e.target.value); setPage(1) }} placeholder="Search..." className="ml-auto px-3 py-1.5 border rounded-md text-sm w-48" />
      </div>

      <div className="bg-white shadow rounded-lg overflow-hidden">
        <table className="min-w-full divide-y divide-gray-200 text-sm">
          <thead className="bg-gray-50"><tr>
            <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Serial Code</th>
            <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Activation Code</th>
            <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Product</th>
            <th className="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase">Status</th>
            {tab === 'activated' && <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Policy</th>}
          </tr></thead>
          <tbody className="divide-y divide-gray-200">
            {loading && <tr><td colSpan={5} className="px-4 py-8 text-center text-gray-400">Loading...</td></tr>}
            {!loading && items.length === 0 && <tr><td colSpan={5} className="p-0"><EmptyState compact title="No codes found" description="Try adjusting your filters." /></td></tr>}
            {items.map((c: any) => (
              <tr key={c.id} className="hover:bg-gray-50">
                <td className="px-4 py-2.5 font-mono text-xs">{c.serialCode ?? c.serial_code}</td>
                <td className="px-4 py-2.5 font-mono text-xs text-blue-700">{c.activationCode ?? c.activation_code}</td>
                <td className="px-4 py-2.5">{c.productName ?? c.product_name ?? '-'}</td>
                <td className="px-4 py-2.5 text-center">
                  <span className={`px-2 py-0.5 text-xs rounded-full font-medium ${(c.status === 'Activated' || c.status === 1) ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500'}`}>
                    {c.status === 1 ? 'Activated' : c.status || 'Available'}
                  </span>
                </td>
                {tab === 'activated' && <td className="px-4 py-2.5 text-blue-600 font-medium">{c.policyNumber || '-'}</td>}
              </tr>
            ))}
          </tbody>
        </table>
      </div>
      <div className="flex justify-between"><button disabled={page<=1} onClick={()=>setPage(p=>p-1)} className="px-3 py-1.5 text-sm border rounded-md disabled:opacity-30">Previous</button><span className="text-sm text-gray-500">Page {page}</span><button disabled={!hasMore} onClick={()=>setPage(p=>p+1)} className="px-3 py-1.5 text-sm border rounded-md disabled:opacity-30">Next</button></div>
    </div>
  )
}
