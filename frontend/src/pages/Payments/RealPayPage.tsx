import { useState, useEffect, useCallback } from 'react'
import apiClient from '../../api/client'
import LoadingSpinner from '../../components/common/LoadingSpinner'
import { reportErrorToTeam } from '../../utils/reportError'

interface RealPayTransaction {
  id: number
  policyNumber: string
  eventType: string
  status: string
  responseMessage: string
  createdAt: string
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
  processed: { label: 'Processed', classes: 'bg-blue-100 text-blue-700' },
}

export default function RealPayPage() {
  const [transactions, setTransactions] = useState<RealPayTransaction[]>([])
  const [meta, setMeta] = useState<Meta | null>(null)
  const [loading, setLoading] = useState(true)
  const [loadError, setLoadError] = useState<string | null>(null)
  const [fetching, setFetching] = useState(false)
  const [search, setSearch] = useState('')
  const [page, setPage] = useState(1)

  const [searchInput, setSearchInput] = useState('')

  const fetchTransactions = useCallback(async () => {
    setFetching(true)
    try {
      const params: Record<string, string | number> = { page, per_page: 25 }
      if (search) params.search = search
      const res = await apiClient.get('/payments/realpay', { params })
      setTransactions(res.data.data ?? [])
      setMeta(res.data.meta ?? null)
      setLoadError(null)
    } catch (err: any) {
      const message = err?.response?.data?.message ?? err?.message ?? 'Failed to load RealPay transactions.'
      setTransactions([])
      setMeta(null)
      setLoadError(message)
      reportErrorToTeam({
        error: message,
        stack: err?.stack,
        context: 'RealPayPage:fetchTransactions',
      })
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
          <h1 className="text-2xl font-bold text-gray-800">RealPay Transactions</h1>
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
          <p className="text-center text-sm text-gray-400">Loading RealPay transactions...</p>
        </div>
      ) : loadError ? (
        <div className="bg-red-50 border border-red-200 rounded-lg p-6 space-y-3">
          <p className="text-sm font-medium text-red-700">Failed to load RealPay transactions.</p>
          <p className="text-xs text-red-600">{loadError}</p>
          <button onClick={fetchTransactions} className="px-3 py-1.5 bg-red-600 text-white rounded text-sm hover:bg-red-700">Try again</button>
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
                  <th className="px-4 py-3 text-left">Event Type</th>
                  <th className="px-4 py-3 text-left">Status</th>
                  <th className="px-4 py-3 text-left">Response</th>
                  <th className="px-4 py-3 text-left">Date</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-gray-100">
                {transactions.map(t => {
                  const statusKey = String(t.status ?? '').toLowerCase()
                  const badge = STATUS_BADGE[statusKey] ?? { label: String(t.status ?? '—'), classes: 'bg-gray-100 text-gray-600' }
                  return (
                    <tr key={t.id} className="hover:bg-gray-50">
                      <td className="px-4 py-2 font-mono text-gray-700">{t.policyNumber || '—'}</td>
                      <td className="px-4 py-2 text-gray-700">{t.eventType || '—'}</td>
                      <td className="px-4 py-2">
                        <span className={`inline-flex px-2 py-0.5 rounded-full text-xs font-medium ${badge.classes}`}>
                          {badge.label}
                        </span>
                      </td>
                      <td className="px-4 py-2 text-gray-500 text-xs max-w-[300px] truncate" title={t.responseMessage}>
                        {t.responseMessage || '—'}
                      </td>
                      <td className="px-4 py-2 text-gray-500 text-xs">
                        {t.createdAt ? new Date(t.createdAt).toLocaleDateString('en-GB', { day: 'numeric', month: 'short', year: 'numeric' }) : '—'}
                      </td>
                    </tr>
                  )
                })}
                {transactions.length === 0 && (
                  <tr>
                    <td colSpan={5} className="px-4 py-12 text-center">
                      <svg className="w-12 h-12 mx-auto text-gray-300 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24" strokeWidth={1.5}>
                        <path strokeLinecap="round" strokeLinejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z" />
                      </svg>
                      <h3 className="text-base font-medium text-gray-500 mb-1">No RealPay transactions found</h3>
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
