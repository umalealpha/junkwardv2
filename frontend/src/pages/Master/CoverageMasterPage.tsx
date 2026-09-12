import DualScrollTable from '../../components/common/DualScrollTable'
import { useState } from 'react'
import { useSearchParams } from 'react-router-dom'
import apiClient from '../../api/client'
import { useQuery } from '@tanstack/react-query'
import LoadingSpinner from '../../components/common/LoadingSpinner'
import EmptyState from '../../components/common/EmptyState'

export default function CoverageMasterPage() {
  const [searchParams, setSearchParams] = useSearchParams()
  const [jumpPage, setJumpPage] = useState('')

  const filters = {
    search: searchParams.get('search') || undefined,
    page: Number(searchParams.get('page') || '1'),
    per_page: 25,
  }

  const { data, isLoading, isFetching } = useQuery({
    queryKey: ['master-coverages', filters],
    queryFn: () =>
      apiClient.get('/master/coverages', { params: filters }).then((r) => r.data),
  })

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

  const meta = data?.meta || (data?.last_page ? data : undefined)
  const rows = data?.data || []
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
        <h1 className="text-2xl font-bold text-gray-800">Coverage Master</h1>
      </div>

      <div className="flex flex-wrap gap-3 items-end">
        <div>
          <label className="block text-xs font-medium text-gray-500 mb-1">Search</label>
          <input
            type="text"
            placeholder="Coverage code, name or group..."
            defaultValue={filters.search}
            onKeyDown={(e) => {
              if (e.key === 'Enter') updateFilter('search', (e.target as HTMLInputElement).value)
            }}
            onBlur={(e) => updateFilter('search', e.target.value)}
            className="px-3 py-1.5 border border-gray-300 rounded-md text-sm w-72 focus:ring-1 focus:ring-blue-500 focus:border-blue-500"
          />
        </div>
      </div>

      <div className="bg-white rounded-lg shadow-sm border overflow-hidden relative">
        {isFetching && !isLoading && (
          <div className="absolute inset-0 bg-white/50 z-10 flex items-center justify-center">
            <LoadingSpinner size="md" />
          </div>
        )}
        <DualScrollTable>
          <table className="w-full text-sm">
            <thead className="bg-gray-50 text-gray-600 uppercase text-xs tracking-wider">
              <tr>
                <th className="px-4 py-3 text-left">ID</th>
                <th className="px-4 py-3 text-left">Coverage Code</th>
                <th className="px-4 py-3 text-left">Coverage Name</th>
                <th className="px-4 py-3 text-left">Group</th>
                <th className="px-4 py-3 text-left">Rate</th>
                <th className="px-4 py-3 text-left">Has Risk Address</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-gray-100">
              {isLoading ? (
                <tr>
                  <td colSpan={6} className="px-4 py-12 text-center">
                    <LoadingSpinner size="md" />
                  </td>
                </tr>
              ) : rows.length === 0 ? (
                <tr>
                  <td colSpan={6} className="p-0">
                    <EmptyState compact title="No coverages found" description="Try adjusting your filters or search terms." />
                  </td>
                </tr>
              ) : (
                rows.map((r: any) => (
                  <tr key={r.id} className="hover:bg-gray-50 transition">
                    <td className="px-4 py-2 text-gray-500">{r.id}</td>
                    <td className="px-4 py-2 font-medium text-blue-700">{r.s_CoverageCode || '\u2014'}</td>
                    <td className="px-4 py-2">{r.s_CoverageName || '\u2014'}</td>
                    <td className="px-4 py-2 text-gray-600">{r.s_CoverageGroupName || '\u2014'}</td>
                    <td className="px-4 py-2 text-gray-600">
                      {r.rate != null ? r.rate : (r.master_rate != null ? r.master_rate : '\u2014')}
                    </td>
                    <td className="px-4 py-2">
                      {r.has_risk_address != null ? (
                        <span
                          className={`px-2 py-0.5 rounded-full text-xs font-medium ${
                            r.has_risk_address
                              ? 'bg-green-100 text-green-700'
                              : 'bg-gray-100 text-gray-500'
                          }`}
                        >
                          {r.has_risk_address ? 'Yes' : 'No'}
                        </span>
                      ) : (
                        '\u2014'
                      )}
                    </td>
                  </tr>
                ))
              )}
            </tbody>
          </table>
        </DualScrollTable>

        {meta && lastPage > 1 && (
          <div className="flex items-center justify-between px-4 py-3 border-t bg-gray-50 text-sm">
            <span className="text-gray-500">
              Showing {meta.from}&ndash;{meta.to} of {meta.total}
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
                  <span key={`e${i}`} className="px-1.5 text-gray-400">
                    ...
                  </span>
                ) : (
                  <button
                    key={p}
                    onClick={() => goToPage(p as number)}
                    className={`px-2.5 py-1 rounded border text-sm ${
                      p === currentPage
                        ? 'bg-blue-600 text-white border-blue-600'
                        : 'text-gray-600 hover:bg-gray-100'
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
                  onChange={(e) => setJumpPage(e.target.value)}
                  onKeyDown={(e) => {
                    if (e.key === 'Enter') {
                      const p = Number(jumpPage)
                      if (p >= 1 && p <= lastPage) {
                        goToPage(p)
                        setJumpPage('')
                      }
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
