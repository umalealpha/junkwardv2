import { useState } from 'react'
import { useSearchParams } from 'react-router-dom'
import { useBatchReport } from '../../hooks/useBatchProcessing'
import LoadingSpinner from '../../components/common/LoadingSpinner'
import type { BatchReportFilters } from '../../api/batchProcessing'
import { fmtDate } from '../../utils/format'
import EmptyState from '../../components/common/EmptyState'

const STATUS_BADGE: Record<string, string> = {
  completed:  'bg-green-100 text-green-700',
  processing: 'bg-blue-100 text-blue-700',
  pending:    'bg-yellow-100 text-yellow-700',
  failed:     'bg-red-100 text-red-700',
}

export default function BatchReportPage() {
  const [searchParams, setSearchParams] = useSearchParams()
  const [jumpPage, setJumpPage] = useState('')

  const filters: BatchReportFilters = {
    remarks: searchParams.get('remarks') || undefined,
    search: searchParams.get('search') || undefined,
    per_page: 25,
    page: Number(searchParams.get('page') || '1'),
  }

  const { data, isLoading, isFetching } = useBatchReport(filters)

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
        <h1 className="text-2xl font-bold text-gray-800">Batch Report</h1>
      </div>

      {/* Filters */}
      <div className="flex flex-wrap gap-3 items-end">
        <div>
          <label className="block text-xs font-medium text-gray-500 mb-1">Search</label>
          <input
            type="text"
            placeholder="File name, uploaded by..."
            defaultValue={filters.search}
            onKeyDown={e => { if (e.key === 'Enter') updateFilter('search', (e.target as HTMLInputElement).value) }}
            onBlur={e => updateFilter('search', e.target.value)}
            className="px-3 py-1.5 border border-gray-300 rounded-md text-sm w-64 focus:ring-1 focus:ring-blue-500 focus:border-blue-500"
          />
        </div>
        <div>
          <label className="block text-xs font-medium text-gray-500 mb-1">Type (Remarks)</label>
          <select
            value={filters.remarks ?? ''}
            onChange={e => updateFilter('remarks', e.target.value)}
            className="px-3 py-1.5 border border-gray-300 rounded-md text-sm focus:ring-1 focus:ring-blue-500"
          >
            <option value="">All Types</option>
            <option value="batch_create">Batch Create</option>
            <option value="batch_cancel">Batch Cancel</option>
            <option value="batch_update">Batch Update</option>
          </select>
        </div>
      </div>

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
                <th className="px-4 py-3 text-left">ID</th>
                <th className="px-4 py-3 text-left">File Name</th>
                <th className="px-4 py-3 text-left">Uploaded By</th>
                <th className="px-4 py-3 text-left">Status</th>
                <th className="px-4 py-3 text-left">Type</th>
                <th className="px-4 py-3 text-left">Report File</th>
                <th className="px-4 py-3 text-left">Date</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-gray-100">
              {isLoading ? (
                <tr><td colSpan={7} className="px-4 py-12 text-center"><LoadingSpinner size="md" /></td></tr>
              ) : data?.data.length === 0 ? (
                <tr><td colSpan={7} className="p-0"><EmptyState compact title="No batch reports found" description="Try adjusting your filters." /></td></tr>
              ) : (
                data?.data.map(item => {
                  const statusLower = (item.status ?? '').toLowerCase()
                  return (
                    <tr key={item.id} className="hover:bg-gray-50 transition">
                      <td className="px-4 py-2 text-gray-600">{item.id}</td>
                      <td className="px-4 py-2 font-medium truncate max-w-[200px]" title={item.fileName ?? ''}>{item.fileName || '—'}</td>
                      <td className="px-4 py-2">{item.uploadedBy || '—'}</td>
                      <td className="px-4 py-2">
                        {item.status ? (
                          <span className={`px-2 py-0.5 rounded-full text-xs font-medium capitalize ${STATUS_BADGE[statusLower] ?? 'bg-gray-100 text-gray-600'}`}>
                            {item.status}
                          </span>
                        ) : '—'}
                      </td>
                      <td className="px-4 py-2 capitalize">{item.remarks?.replace(/_/g, ' ') || '—'}</td>
                      <td className="px-4 py-2">
                        {item.reportFile ? (
                          <a href={item.reportFile} target="_blank" rel="noopener noreferrer" className="text-blue-600 hover:underline text-xs">
                            Download
                          </a>
                        ) : '—'}
                      </td>
                      <td className="px-4 py-2 text-gray-500">
                        {fmtDate(item.createdAt)}
                      </td>
                    </tr>
                  )
                })
              )}
            </tbody>
          </table>
        </div>

        {/* Pagination */}
        {meta && meta.last_page > 1 && (
          <div className="flex items-center justify-between px-4 py-3 border-t bg-gray-50 text-sm">
            <span className="text-gray-500">
              Showing {meta.from}–{meta.to} of {meta.total}
            </span>
            <div className="flex items-center gap-1">
              <button
                disabled={currentPage === 1}
                onClick={() => goToPage(currentPage - 1)}
                className="px-2.5 py-1 rounded border text-gray-600 hover:bg-gray-100 disabled:opacity-40 disabled:cursor-not-allowed"
              >
                Prev
              </button>
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
              <button
                disabled={currentPage === lastPage}
                onClick={() => goToPage(currentPage + 1)}
                className="px-2.5 py-1 rounded border text-gray-600 hover:bg-gray-100 disabled:opacity-40 disabled:cursor-not-allowed"
              >
                Next
              </button>
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
            </div>
          </div>
        )}
      </div>
    </div>
  )
}
