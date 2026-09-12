import { useState } from 'react'
import { useSearchParams } from 'react-router-dom'
import { useDuplicateCustomers } from '../../hooks/useKyc'
import LoadingSpinner from '../../components/common/LoadingSpinner'
import type { DuplicateCustomerFilters } from '../../api/kyc'
import { fmtDate } from '../../utils/format'
import EmptyState from '../../components/common/EmptyState'

const STATUS_BADGE: Record<string, string> = {
  resolved:   'bg-green-100 text-green-700',
  pending:    'bg-yellow-100 text-yellow-700',
  unresolved: 'bg-red-100 text-red-700',
  merged:     'bg-blue-100 text-blue-700',
}

const TYPE_BADGE: Record<string, string> = {
  omang:    'bg-purple-100 text-purple-700',
  passport: 'bg-indigo-100 text-indigo-700',
  phone:    'bg-cyan-100 text-cyan-700',
  email:    'bg-teal-100 text-teal-700',
}

export default function DuplicateCustomersPage() {
  const [searchParams, setSearchParams] = useSearchParams()
  const [jumpPage, setJumpPage] = useState('')

  const filters: DuplicateCustomerFilters = {
    status:         searchParams.get('status') || undefined,
    duplicate_type: searchParams.get('duplicate_type') || undefined,
    search:         searchParams.get('search') || undefined,
    per_page:       25,
    page:           Number(searchParams.get('page') || '1'),
  }

  const { data, isLoading, isFetching } = useDuplicateCustomers(filters)

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
        <h1 className="text-2xl font-bold text-gray-800">Duplicate Customers</h1>
      </div>

      <div className="flex flex-wrap gap-3 items-end">
        <div>
          <label className="block text-xs font-medium text-gray-500 mb-1">Search</label>
          <input
            type="text"
            placeholder="Customer name, omang, passport..."
            defaultValue={filters.search}
            onKeyDown={e => { if (e.key === 'Enter') updateFilter('search', (e.target as HTMLInputElement).value) }}
            onBlur={e => updateFilter('search', e.target.value)}
            className="px-3 py-1.5 border border-gray-300 rounded-md text-sm w-64 focus:ring-1 focus:ring-blue-500 focus:border-blue-500"
          />
        </div>
        <div>
          <label className="block text-xs font-medium text-gray-500 mb-1">Status</label>
          <select
            value={filters.status ?? ''}
            onChange={e => updateFilter('status', e.target.value)}
            className="px-3 py-1.5 border border-gray-300 rounded-md text-sm focus:ring-1 focus:ring-blue-500"
          >
            <option value="">All Status</option>
            <option value="pending">Pending</option>
            <option value="resolved">Resolved</option>
            <option value="unresolved">Unresolved</option>
            <option value="merged">Merged</option>
          </select>
        </div>
        <div>
          <label className="block text-xs font-medium text-gray-500 mb-1">Duplicate Type</label>
          <select
            value={filters.duplicate_type ?? ''}
            onChange={e => updateFilter('duplicate_type', e.target.value)}
            className="px-3 py-1.5 border border-gray-300 rounded-md text-sm focus:ring-1 focus:ring-blue-500"
          >
            <option value="">All Types</option>
            <option value="omang">Omang</option>
            <option value="passport">Passport</option>
            <option value="phone">Phone</option>
            <option value="email">Email</option>
          </select>
        </div>
      </div>

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
                <th className="px-4 py-3 text-left">Customer</th>
                <th className="px-4 py-3 text-left">Contact</th>
                <th className="px-4 py-3 text-left">Omang / Passport</th>
                <th className="px-4 py-3 text-left">Type</th>
                <th className="px-4 py-3 text-left">Reason</th>
                <th className="px-4 py-3 text-left">Status</th>
                <th className="px-4 py-3 text-left">Date</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-gray-100">
              {isLoading ? (
                <tr><td colSpan={7} className="px-4 py-12 text-center"><LoadingSpinner size="md" /></td></tr>
              ) : data?.data.length === 0 ? (
                <tr><td colSpan={7} className="p-0"><EmptyState compact title="No duplicate customers found" description="Try adjusting your filters." /></td></tr>
              ) : (
                data?.data.map(item => (
                  <tr key={item.id} className="hover:bg-gray-50 transition">
                    <td className="px-4 py-2 font-medium text-blue-600">{item.customerName || '\u2014'}</td>
                    <td className="px-4 py-2">
                      <div>{item.cellphone || '\u2014'}</div>
                      <div className="text-xs text-gray-400">{item.email || ''}</div>
                    </td>
                    <td className="px-4 py-2">
                      <div>{item.omangNumber || '\u2014'}</div>
                      {item.passportNumber && <div className="text-xs text-gray-400">{item.passportNumber}</div>}
                    </td>
                    <td className="px-4 py-2">
                      {item.duplicateType ? (
                        <span className={`px-2 py-0.5 rounded-full text-xs font-medium capitalize ${TYPE_BADGE[item.duplicateType.toLowerCase()] ?? 'bg-gray-100 text-gray-600'}`}>
                          {item.duplicateType}
                        </span>
                      ) : '\u2014'}
                    </td>
                    <td className="px-4 py-2 truncate max-w-[160px]" title={item.duplicateReason ?? ''}>{item.duplicateReason || '\u2014'}</td>
                    <td className="px-4 py-2">
                      {item.status ? (
                        <span className={`px-2 py-0.5 rounded-full text-xs font-medium capitalize ${STATUS_BADGE[item.status.toLowerCase()] ?? 'bg-gray-100 text-gray-600'}`}>
                          {item.status}
                        </span>
                      ) : '\u2014'}
                    </td>
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
    </div>
  )
}
