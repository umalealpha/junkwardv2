import { useState } from 'react'
import { useQuery } from '@tanstack/react-query'
import apiClient from '../../api/client'
import LoadingSpinner from '../../components/common/LoadingSpinner'
import { fmtDate } from '../../utils/format'
import EmptyState from '../../components/common/EmptyState'

interface CustomerItem {
  id: number
  name: string | null
  cellphone: string | null
  email: string | null
  createdAt: string | null
}

export default function CustomerSearchPage() {
  const [search, setSearch] = useState('')
  const [submitted, setSubmitted] = useState('')
  const [page, setPage] = useState(1)
  const [jumpPage, setJumpPage] = useState('')

  const { data, isLoading, isFetching } = useQuery({
    queryKey: ['customer-search', submitted, page],
    queryFn: async () => {
      const { data } = await apiClient.get('/customers', { params: { search: submitted, per_page: 25, page } })
      return data as { data: CustomerItem[]; meta: { total: number; per_page: number; current_page: number; last_page: number; from: number | null; to: number | null } }
    },
    enabled: !!submitted,
    placeholderData: (prev) => prev,
    staleTime: 2 * 60 * 1000,
    refetchOnWindowFocus: false,
  })

  function handleSearch() {
    if (search.trim()) {
      setSubmitted(search.trim())
      setPage(1)
    }
  }

  function goToPage(p: number) {
    setPage(p)
  }

  const meta = data?.meta
  const currentPage = meta?.current_page ?? 1
  const lastPage = meta?.last_page ?? 1

  function getPageNumbers() {
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

  return (
    <div className="p-6 space-y-4">
      <div className="flex items-center justify-between">
        <h1 className="text-2xl font-bold text-gray-800">Customer Search</h1>
      </div>

      {/* Large search input */}
      <div className="bg-white rounded-lg shadow-sm border p-6">
        <label className="block text-sm font-medium text-gray-600 mb-2">Search for a customer</label>
        <div className="flex gap-3">
          <input
            type="text"
            placeholder="Enter customer name, phone, email, omang, or passport number..."
            value={search}
            onChange={e => setSearch(e.target.value)}
            onKeyDown={e => { if (e.key === 'Enter') handleSearch() }}
            className="flex-1 px-4 py-3 border border-gray-300 rounded-lg text-base focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
          />
          <button
            onClick={handleSearch}
            className="px-6 py-3 bg-blue-600 text-white rounded-lg font-medium hover:bg-blue-700 transition"
          >
            Search
          </button>
        </div>
      </div>

      {/* Results */}
      {submitted && (
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
                  <th className="px-4 py-3 text-left">ID</th>
                  <th className="px-4 py-3 text-left">Name</th>
                  <th className="px-4 py-3 text-left">Phone</th>
                  <th className="px-4 py-3 text-left">Email</th>
                  <th className="px-4 py-3 text-left">Date</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-gray-100">
                {isLoading ? (
                  <tr><td colSpan={5} className="px-4 py-12 text-center"><LoadingSpinner size="md" /></td></tr>
                ) : data?.data.length === 0 ? (
                  <tr><td colSpan={5} className="p-0"><EmptyState compact title="No customers found" description={`No customers found for "${submitted}".`} /></td></tr>
                ) : (
                  data?.data.map(item => (
                    <tr key={item.id} className="hover:bg-gray-50 transition">
                      <td className="px-4 py-2 font-medium text-gray-700">{item.id}</td>
                      <td className="px-4 py-2 font-medium text-blue-600">{item.name || '\u2014'}</td>
                      <td className="px-4 py-2">{item.cellphone || '\u2014'}</td>
                      <td className="px-4 py-2 truncate max-w-[200px]" title={item.email ?? ''}>{item.email || '\u2014'}</td>
                      <td className="px-4 py-2 text-gray-500">
                        {fmtDate(item.createdAt)}
                      </td>
                    </tr>
                  ))
                )}
              </tbody>
            </table>
          </div>

          {meta && meta.last_page > 1 && (
            <div className="flex items-center justify-between px-4 py-3 border-t bg-gray-50 text-sm">
              <span className="text-gray-500">Showing {meta.from}\u2013{meta.to} of {meta.total}</span>
              <div className="flex items-center gap-1">
                <button disabled={currentPage === 1} onClick={() => goToPage(currentPage - 1)} className="px-2.5 py-1 rounded border text-gray-600 hover:bg-gray-100 disabled:opacity-40 disabled:cursor-not-allowed">Prev</button>
                {getPageNumbers().map((p, i) =>
                  p === '...' ? (
                    <span key={`e${i}`} className="px-1.5 text-gray-400">...</span>
                  ) : (
                    <button key={p} onClick={() => goToPage(p as number)} className={`px-2.5 py-1 rounded border text-sm ${p === currentPage ? 'bg-blue-600 text-white border-blue-600' : 'text-gray-600 hover:bg-gray-100'}`}>{p}</button>
                  )
                )}
                <button disabled={currentPage === lastPage} onClick={() => goToPage(currentPage + 1)} className="px-2.5 py-1 rounded border text-gray-600 hover:bg-gray-100 disabled:opacity-40 disabled:cursor-not-allowed">Next</button>
                <div className="flex items-center gap-1 ml-3 border-l pl-3">
                  <span className="text-gray-500 text-xs">Go to</span>
                  <input type="number" min={1} max={lastPage} value={jumpPage} onChange={e => setJumpPage(e.target.value)} onKeyDown={e => { if (e.key === 'Enter') { const p = Number(jumpPage); if (p >= 1 && p <= lastPage) { goToPage(p); setJumpPage('') } } }} className="w-14 px-2 py-1 border rounded text-sm text-center" placeholder="#" />
                </div>
              </div>
            </div>
          )}
        </div>
      )}

      {!submitted && (
        <div className="text-center py-12 text-gray-400">
          Enter a search term above to find customers.
        </div>
      )}
    </div>
  )
}
