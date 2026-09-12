import { useState } from 'react'
import { useSearchParams, Link } from 'react-router-dom'
import { useADGroupPolicies } from '../../hooks/useEmployerGroups'
import LoadingSpinner from '../../components/common/LoadingSpinner'
import { fmtPula, fmtDate } from '../../utils/format'
import type { ADGroupPolicyFilters } from '../../api/employerGroups'
import EmptyState from '../../components/common/EmptyState'

const STATUS_MAP: Record<number, { label: string; cls: string }> = {
  0: { label: 'Inactive', cls: 'bg-gray-100 text-gray-600' },
  1: { label: 'Active', cls: 'bg-green-100 text-green-700' },
  2: { label: 'Cancelled', cls: 'bg-red-100 text-red-700' },
  3: { label: 'Expired', cls: 'bg-yellow-100 text-yellow-700' },
}

export default function ADGroupPolicyPage() {
  const [searchParams, setSearchParams] = useSearchParams()
  const [jumpPage, setJumpPage] = useState('')

  const filters: ADGroupPolicyFilters = {
    employer_group_id: searchParams.get('employer_group_id') || undefined,
    search: searchParams.get('search') || undefined,
    per_page: 25,
    page: Number(searchParams.get('page') || '1'),
  }

  const { data, isLoading, isFetching } = useADGroupPolicies(filters)

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

  function fmtCurrency(v: number | null) {
    if (v == null) return '—'
    return fmtPula(v)
  }

  return (
    <div className="p-6 space-y-4">
      <div className="flex items-center justify-between">
        <h1 className="text-2xl font-bold text-gray-800">AD Group Policies</h1>
      </div>

      {/* Filters */}
      <div className="flex flex-wrap gap-3 items-end">
        <div>
          <label className="block text-xs font-medium text-gray-500 mb-1">Search</label>
          <input
            type="text"
            placeholder="Policy number, customer, employee ID..."
            defaultValue={filters.search}
            onKeyDown={e => { if (e.key === 'Enter') updateFilter('search', (e.target as HTMLInputElement).value) }}
            onBlur={e => updateFilter('search', e.target.value)}
            className="px-3 py-1.5 border border-gray-300 rounded-md text-sm w-64 focus:ring-1 focus:ring-blue-500 focus:border-blue-500"
          />
        </div>
        <div>
          <label className="block text-xs font-medium text-gray-500 mb-1">Employer Group ID</label>
          <input
            type="text"
            placeholder="Filter by group ID..."
            defaultValue={filters.employer_group_id}
            onKeyDown={e => { if (e.key === 'Enter') updateFilter('employer_group_id', (e.target as HTMLInputElement).value) }}
            onBlur={e => updateFilter('employer_group_id', e.target.value)}
            className="px-3 py-1.5 border border-gray-300 rounded-md text-sm w-48 focus:ring-1 focus:ring-blue-500 focus:border-blue-500"
          />
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
                <th className="px-4 py-3 text-left">Policy #</th>
                <th className="px-4 py-3 text-left">Employee ID</th>
                <th className="px-4 py-3 text-left">Customer</th>
                <th className="px-4 py-3 text-left">Group</th>
                <th className="px-4 py-3 text-left">Product</th>
                <th className="px-4 py-3 text-right">Premium</th>
                <th className="px-4 py-3 text-left">Status</th>
                <th className="px-4 py-3 text-left">Date</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-gray-100">
              {isLoading ? (
                <tr><td colSpan={8} className="px-4 py-12 text-center"><LoadingSpinner size="md" /></td></tr>
              ) : data?.data.length === 0 ? (
                <tr><td colSpan={8} className="p-0"><EmptyState compact title="No AD group policies found" description="Try adjusting your filters or search terms." /></td></tr>
              ) : (
                data?.data.map(item => {
                  const st = STATUS_MAP[item.status] ?? { label: String(item.status), cls: 'bg-gray-100 text-gray-600' }
                  return (
                    <tr key={item.id} className="hover:bg-gray-50 transition">
                      <td className="px-4 py-2 font-medium text-blue-600">
                        {item.policyNumber ? <Link to={`/policies/${item.id}`} className="hover:underline">{item.policyNumber}</Link> : '—'}
                      </td>
                      <td className="px-4 py-2">{item.employeeId || '—'}</td>
                      <td className="px-4 py-2 truncate max-w-[180px]" title={item.customerName ?? ''}>{item.customerName || '—'}</td>
                      <td className="px-4 py-2 truncate max-w-[150px]" title={item.groupName ?? ''}>{item.groupName || '—'}</td>
                      <td className="px-4 py-2">{item.productName || '—'}</td>
                      <td className="px-4 py-2 text-right">{fmtCurrency(item.premium)}</td>
                      <td className="px-4 py-2">
                        <span className={`px-2 py-0.5 rounded-full text-xs font-medium ${st.cls}`}>{st.label}</span>
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
