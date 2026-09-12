import { useState } from 'react'
import { useSearchParams, Link } from 'react-router-dom'
import { useQuotes } from '../../hooks/useQuotes'
import LoadingSpinner from '../../components/common/LoadingSpinner'
import EmptyState from '../../components/common/EmptyState'
import type { QuoteFilters } from '../../api/quotes'
import { fmtDate } from '../../utils/format'

const STATUS_BADGE: Record<string, string> = {
  active:   'bg-green-100 text-green-700',
  used:     'bg-blue-100 text-blue-700',
  draft:    'bg-gray-100 text-gray-600',
  expired:  'bg-yellow-100 text-yellow-700',
  rejected: 'bg-red-100 text-red-700',
  unknown:  'bg-gray-100 text-gray-500',
}

export default function QuoteListPage() {
  const [searchParams, setSearchParams] = useSearchParams()
  const [jumpPage, setJumpPage] = useState('')

  const filters: QuoteFilters = {
    status:   searchParams.get('status') || undefined,
    search:   searchParams.get('search') || undefined,
    per_page: 25,
    page:     Number(searchParams.get('page') || '1'),
  }

  const { data, isLoading, isFetching } = useQuotes(filters)

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
        <h1 className="text-2xl font-bold text-ink">Quotes</h1>
      </div>

      {/* Filters */}
      <div className="flex flex-wrap gap-3 items-end">
        <div>
          <label className="block text-xs font-medium text-ink-faint mb-1">Search</label>
          <input
            type="text"
            placeholder="Quote code, customer name, phone..."
            defaultValue={filters.search}
            onKeyDown={e => { if (e.key === 'Enter') updateFilter('search', (e.target as HTMLInputElement).value) }}
            onBlur={e => updateFilter('search', e.target.value)}
            className="px-3 py-1.5 border border-line rounded-md text-sm w-64 focus:ring-1 focus:ring-blue-500 focus:border-blue-500"
          />
        </div>
        <div>
          <label className="block text-xs font-medium text-ink-faint mb-1">Status</label>
          <select
            value={filters.status ?? ''}
            onChange={e => updateFilter('status', e.target.value)}
            className="px-3 py-1.5 border border-line rounded-md text-sm focus:ring-1 focus:ring-blue-500"
          >
            <option value="">All Status</option>
            <option value="1">Active</option>
            <option value="2">Used (Policy Generated)</option>
            <option value="3">Expired</option>
            <option value="4">Rejected</option>
            <option value="0">Draft</option>
          </select>
        </div>
      </div>

      {/* Table */}
      <div className="bg-surface rounded-lg shadow-sm border border-line overflow-hidden">
        {(isLoading || isFetching) && (
          <div className="absolute inset-0 bg-white/50 z-10 flex items-center justify-center">
            <LoadingSpinner size="md" />
          </div>
        )}
        <div className="overflow-x-auto relative">
          <table className="w-full text-sm table-sticky-header">
            <thead className="bg-surface-2 text-ink-muted uppercase text-xs tracking-wider">
              <tr>
                <th className="px-4 py-2 text-left">Quote Code</th>
                <th className="px-4 py-2 text-left">Customer</th>
                <th className="px-4 py-2 text-left">Phone</th>
                <th className="px-4 py-2 text-left">Agent</th>
                <th className="px-4 py-2 text-left">Policy No</th>
                <th className="px-4 py-2 text-left">Status</th>
                <th className="px-4 py-2 text-left">Created</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-line">
              {isLoading ? (
                <tr><td colSpan={7} className="px-4 py-12 text-center"><LoadingSpinner size="md" /></td></tr>
              ) : data?.data.length === 0 ? (
                <tr><td colSpan={7} className="p-0"><EmptyState compact title="No quotes found" description="Try adjusting your filters, or create a new quote to get started." /></td></tr>
              ) : (
                data?.data.map(quote => (
                  <tr key={quote.id} className={`hover:bg-surface-2 transition ${quote.statusLabel && ['Used', 'Expired', 'Rejected'].includes(quote.statusLabel) ? 'opacity-60' : ''}`}>
                    <td className="px-4 py-2 font-medium text-blue-600">
                      <Link to={`/quotes/${quote.id}`} className="hover:underline">{quote.quoteCode || '—'}</Link>
                    </td>
                    <td className="px-4 py-2 truncate max-w-[180px]" title={quote.customerName ?? ''}>{quote.customerName || '—'}</td>
                    <td className="px-4 py-2">{quote.customerPhone || '—'}</td>
                    <td className="px-4 py-2 truncate max-w-[120px]" title={quote.agentName ?? ''}>{quote.agentName || '—'}</td>
                    <td className="px-4 py-2">
                      {quote.policyNumber ? <Link to={`/policies?search=${quote.policyNumber}`} className="text-blue-600 hover:underline">{quote.policyNumber}</Link> : '—'}
                    </td>
                    <td className="px-4 py-2">
                      {quote.statusLabel ? (
                        <span className={`px-2 py-0.5 rounded-full text-xs font-medium capitalize ${STATUS_BADGE[quote.statusLabel.toLowerCase()] ?? 'bg-gray-100 text-gray-600'}`}>
                          {quote.statusLabel}
                        </span>
                      ) : '—'}
                    </td>
                    <td className="px-4 py-2 text-ink-faint">
                      {fmtDate(quote.createdAt)}
                    </td>
                  </tr>
                ))
              )}
            </tbody>
          </table>
        </div>

        {/* Pagination */}
        {meta && meta.last_page > 1 && (
          <div className="flex items-center justify-between px-4 py-3 border-t border-line bg-surface-2 text-sm">
            <span className="text-ink-faint">
              Showing {meta.from}–{meta.to} of {meta.total}
            </span>
            <div className="flex items-center gap-1">
              <button
                disabled={currentPage === 1}
                onClick={() => goToPage(currentPage - 1)}
                className="px-2.5 py-1 rounded border border-line text-ink-muted hover:bg-surface-2 disabled:opacity-40 disabled:cursor-not-allowed"
              >
                Prev
              </button>
              {getPageNumbers().map((p, i) =>
                p === '...' ? (
                  <span key={`e${i}`} className="px-1.5 text-ink-faint">...</span>
                ) : (
                  <button
                    key={p}
                    onClick={() => goToPage(p as number)}
                    className={`px-2.5 py-1 rounded border border-line text-sm ${
                      p === currentPage ? 'bg-blue-600 text-white border-blue-600' : 'text-ink-muted hover:bg-surface-2'
                    }`}
                  >
                    {p}
                  </button>
                )
              )}
              <button
                disabled={currentPage === lastPage}
                onClick={() => goToPage(currentPage + 1)}
                className="px-2.5 py-1 rounded border border-line text-ink-muted hover:bg-surface-2 disabled:opacity-40 disabled:cursor-not-allowed"
              >
                Next
              </button>
              <div className="flex items-center gap-1 ml-3 border-l border-line pl-3">
                <span className="text-ink-faint text-xs">Go to</span>
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
                  className="w-14 px-2 py-1 border border-line rounded text-sm text-center"
                  placeholder="#"
                />
              </div>
            </div>
          </div>
        )}
      </div>
    </div>
  )
}
