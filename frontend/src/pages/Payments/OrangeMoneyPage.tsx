import { useState, useEffect, useCallback } from 'react'
import apiClient from '../../api/client'
import LoadingSpinner from '../../components/common/LoadingSpinner'
import { fmtPula } from '../../utils/format'

interface OrangeMoneyTransaction {
  id: number
  policy_number: string
  customer_name: string
  amount: number
  status: string
  reference: string
  created_at: string
}

interface Meta {
  total: number
  per_page: number
  current_page: number
  last_page: number
}

const STATUS_BADGE: Record<string, { label: string; classes: string }> = {
  success:   { label: 'Success',   classes: 'bg-green-100 text-green-700' },
  failed:    { label: 'Failed',    classes: 'bg-red-100 text-red-700' },
  pending:   { label: 'Pending',   classes: 'bg-yellow-100 text-yellow-700' },
  completed: { label: 'Completed', classes: 'bg-green-100 text-green-700' },
}

export default function OrangeMoneyPage() {
  const [transactions, setTransactions] = useState<OrangeMoneyTransaction[]>([])
  const [meta, setMeta] = useState<Meta | null>(null)
  const [loading, setLoading] = useState(true)
  const [fetching, setFetching] = useState(false)
  const [search, setSearch] = useState('')
  const [page, setPage] = useState(1)

  const [searchInput, setSearchInput] = useState('')

  const fetchTransactions = useCallback(async () => {
    setFetching(true)
    try {
      const params: Record<string, string | number> = { page, per_page: 25 }
      if (search) params.search = search
      const res = await apiClient.get('/payments/orange-money', { params })
      setTransactions(res.data.data ?? [])
      setMeta(res.data.meta ?? null)
    } catch {
      setTransactions([])
      setMeta(null)
    } finally {
      setLoading(false)
      setFetching(false)
    }
  }, [search, page])

  useEffect(() => {
    fetchTransactions()
  }, [fetchTransactions])

  // Debounced search
  useEffect(() => {
    const timer = setTimeout(() => {
      setSearch(searchInput)
      setPage(1)
    }, 400)
    return () => clearTimeout(timer)
  }, [searchInput])

  const currentPage = meta?.current_page ?? 1
  const lastPage = meta?.last_page ?? 1

  function getPageNumbers(): (number | '...')[] {
    if (lastPage <= 7) return Array.from({ length: lastPage }, (_, i) => i + 1)
    const pages: (number | '...')[] = []
    const addPage = (p: number) => { if (!pages.includes(p)) pages.push(p) }
    addPage(1)
    if (currentPage > 3) pages.push('...')
    for (let i = Math.max(2, currentPage - 1); i <= Math.min(lastPage - 1, currentPage + 1); i++) addPage(i)
    if (currentPage < lastPage - 2) pages.push('...')
    addPage(lastPage)
    return pages
  }

  return (
    <div className="p-6 space-y-4">
      {/* Header */}
      <div className="flex flex-wrap items-center justify-between gap-3">
        <div className="flex items-center gap-3">
          <h1 className="text-2xl font-bold text-gray-800">Orange Money Transactions</h1>
          {meta && (
            <span className="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-600">
              {meta.total.toLocaleString()}
            </span>
          )}
        </div>
      </div>

      {/* Search */}
      <div className="flex flex-wrap gap-3 items-end">
        <div>
          <label className="block text-xs font-medium text-gray-500 mb-1">Search</label>
          <input
            type="text"
            placeholder="Policy number..."
            value={searchInput}
            onChange={e => setSearchInput(e.target.value)}
            className="px-3 py-1.5 border border-gray-300 rounded-md text-sm w-64 focus:ring-1 focus:ring-blue-500 focus:border-blue-500"
          />
        </div>
      </div>

      {/* Table */}
      {loading ? (
        <div className="bg-white rounded-lg border border-gray-200 shadow-sm p-12">
          <LoadingSpinner size="lg" className="mb-3" />
          <p className="text-center text-sm text-gray-400">Loading Orange Money transactions...</p>
        </div>
      ) : (
        <>
          {fetching && (
            <div className="bg-white rounded-lg border border-blue-200 shadow-sm px-4 py-3 flex items-center gap-2">
              <LoadingSpinner size="sm" />
              <span className="text-sm text-blue-600">Updating results...</span>
            </div>
          )}

          <div className={`bg-white rounded-lg border border-gray-200 shadow-sm overflow-x-auto transition-opacity ${fetching ? 'opacity-50 pointer-events-none' : ''}`}>
            <table className="w-full text-sm">
              <thead className="bg-gray-50 text-gray-500 uppercase text-xs tracking-wider">
                <tr>
                  <th className="px-4 py-3 text-left">Policy #</th>
                  <th className="px-4 py-3 text-left">Customer</th>
                  <th className="px-4 py-3 text-right">Amount</th>
                  <th className="px-4 py-3 text-left">Status</th>
                  <th className="px-4 py-3 text-left">Reference</th>
                  <th className="px-4 py-3 text-left">Date</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-gray-100">
                {transactions.map(t => {
                  const statusKey = String(t.status ?? '').toLowerCase()
                  const badge = STATUS_BADGE[statusKey] ?? { label: String(t.status ?? '—'), classes: 'bg-gray-100 text-gray-600' }
                  return (
                    <tr key={t.id} className="hover:bg-gray-50">
                      <td className="px-4 py-2 font-mono text-gray-700">{t.policy_number}</td>
                      <td className="px-4 py-2 text-gray-700">{t.customer_name}</td>
                      <td className="px-4 py-2 text-right font-medium text-gray-800">
                        {fmtPula(t.amount)}
                      </td>
                      <td className="px-4 py-2">
                        <span className={`inline-flex px-2 py-0.5 rounded-full text-xs font-medium ${badge.classes}`}>
                          {badge.label}
                        </span>
                      </td>
                      <td className="px-4 py-2 font-mono text-xs text-gray-500">{t.reference || '—'}</td>
                      <td className="px-4 py-2 text-gray-500 text-xs">
                        {t.created_at ? new Date(t.created_at).toLocaleDateString('en-GB', { day: 'numeric', month: 'short', year: 'numeric' }) : '—'}
                      </td>
                    </tr>
                  )
                })}
                {transactions.length === 0 && (
                  <tr>
                    <td colSpan={6} className="px-4 py-12 text-center">
                      <svg className="w-12 h-12 mx-auto text-gray-300 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24" strokeWidth={1.5}>
                        <path strokeLinecap="round" strokeLinejoin="round" d="M10.5 1.5H8.25A2.25 2.25 0 006 3.75v16.5a2.25 2.25 0 002.25 2.25h7.5A2.25 2.25 0 0018 20.25V3.75a2.25 2.25 0 00-2.25-2.25H13.5m-3 0V3h3V1.5m-3 0h3m-3 18.75h3" />
                      </svg>
                      <h3 className="text-base font-medium text-gray-500 mb-1">No Orange Money transactions found</h3>
                      <p className="text-sm text-gray-400">Try adjusting your search criteria.</p>
                    </td>
                  </tr>
                )}
              </tbody>
            </table>
          </div>

          {/* Pagination */}
          {meta && meta.last_page > 1 && (
            <div className="flex flex-wrap items-center justify-between gap-3 text-sm text-gray-500">
              <span>
                Page {currentPage} of {lastPage.toLocaleString()} ({meta.total.toLocaleString()} total)
              </span>
              <div className="flex items-center gap-1">
                <button
                  disabled={currentPage === 1}
                  onClick={() => setPage(currentPage - 1)}
                  className="px-2.5 py-1 border rounded disabled:opacity-40 hover:bg-gray-50 text-xs"
                >
                  Prev
                </button>
                {getPageNumbers().map((p, i) =>
                  p === '...' ? (
                    <span key={`e${i}`} className="px-1.5 text-gray-400">...</span>
                  ) : (
                    <button
                      key={p}
                      onClick={() => setPage(p)}
                      className={`px-2.5 py-1 border rounded text-xs ${
                        p === currentPage
                          ? 'bg-brand-navy text-white border-brand-navy'
                          : 'hover:bg-gray-50'
                      }`}
                    >
                      {p}
                    </button>
                  )
                )}
                <button
                  disabled={currentPage === lastPage}
                  onClick={() => setPage(currentPage + 1)}
                  className="px-2.5 py-1 border rounded disabled:opacity-40 hover:bg-gray-50 text-xs"
                >
                  Next
                </button>
              </div>
            </div>
          )}
        </>
      )}
    </div>
  )
}
