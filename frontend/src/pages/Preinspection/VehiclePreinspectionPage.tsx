import { useState } from 'react'
import { useSearchParams, Link } from 'react-router-dom'
import { useVehicleInspections } from '../../hooks/usePreinspection'
import LoadingSpinner from '../../components/common/LoadingSpinner'
import { fmtDate } from '../../utils/format'
import EmptyState from '../../components/common/EmptyState'

const STATUS_BADGE: Record<string, string> = {
  Approved: 'bg-green-100 text-green-700',
  Unapproved: 'bg-red-100 text-red-700',
  Recheck: 'bg-orange-100 text-orange-700',
  Pending: 'bg-yellow-100 text-yellow-700',
}

function Thumbnail({ src, alt }: { src: string | null; alt: string }) {
  if (!src) return <span className="text-gray-300 text-xs">No image</span>
  return (
    <a href={src} target="_blank" rel="noopener noreferrer" className="inline-block">
      <img src={src} alt={alt} className="w-12 h-9 object-cover rounded border hover:opacity-80 transition" />
    </a>
  )
}

export default function VehiclePreinspectionPage() {
  const [searchParams, setSearchParams] = useSearchParams()
  const [jumpPage, setJumpPage] = useState('')

  const filters = {
    search:   searchParams.get('search') || undefined,
    per_page: 25,
    page:     Number(searchParams.get('page') || '1'),
  }

  const { data, isLoading, isFetching } = useVehicleInspections(filters)

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
        <h1 className="text-2xl font-bold text-gray-800">Vehicle Preinspection</h1>
      </div>

      <div className="flex flex-wrap gap-3 items-end">
        <div>
          <label className="block text-xs font-medium text-gray-500 mb-1">Search</label>
          <input
            type="text"
            placeholder="Plate number..."
            defaultValue={filters.search}
            onKeyDown={e => { if (e.key === 'Enter') updateFilter('search', (e.target as HTMLInputElement).value) }}
            onBlur={e => updateFilter('search', e.target.value)}
            className="px-3 py-1.5 border border-gray-300 rounded-md text-sm w-64 focus:ring-1 focus:ring-blue-500 focus:border-blue-500"
          />
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
                <th className="px-4 py-3 text-left">Policy #</th>
                <th className="px-4 py-3 text-left">Plate Number</th>
                <th className="px-4 py-3 text-left">Customer</th>
                <th className="px-4 py-3 text-left">Front</th>
                <th className="px-4 py-3 text-left">Back</th>
                <th className="px-4 py-3 text-left">Left</th>
                <th className="px-4 py-3 text-left">Right</th>
                <th className="px-4 py-3 text-left">Status</th>
                <th className="px-4 py-3 text-left">Date</th>
                <th className="px-4 py-3 text-right">Actions</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-gray-100">
              {isLoading ? (
                <tr><td colSpan={10} className="px-4 py-12 text-center"><LoadingSpinner size="md" /></td></tr>
              ) : data?.data.length === 0 ? (
                <tr><td colSpan={10} className="p-0"><EmptyState compact title="No vehicle inspections found" description="Try adjusting your filters." /></td></tr>
              ) : (
                data?.data.map(item => (
                  <tr key={item.id} className="hover:bg-gray-50 transition">
                    <td className="px-4 py-2 font-medium text-gray-700">
                      {item.policyNumber ? <Link to={`/policies?search=${item.policyNumber}`} className="text-blue-600 hover:underline">{item.policyNumber}</Link> : '\u2014'}
                    </td>
                    <td className="px-4 py-2 font-medium text-gray-700">{item.vehiclePlate || '\u2014'}</td>
                    <td className="px-4 py-2 text-gray-600">{item.customerName || '\u2014'}</td>
                    <td className="px-4 py-2"><Thumbnail src={item.frontImage} alt="Front" /></td>
                    <td className="px-4 py-2"><Thumbnail src={item.backImage} alt="Back" /></td>
                    <td className="px-4 py-2"><Thumbnail src={item.leftImage} alt="Left" /></td>
                    <td className="px-4 py-2"><Thumbnail src={item.rightImage} alt="Right" /></td>
                    <td className="px-4 py-2">
                      <span className={`px-2 py-0.5 rounded-full text-xs font-medium ${STATUS_BADGE[item.statusLabel] ?? 'bg-gray-100 text-gray-600'}`}>
                        {item.statusLabel}
                      </span>
                    </td>
                    <td className="px-4 py-2 text-gray-500">
                      {fmtDate(item.createdAt)}
                    </td>
                    <td className="px-4 py-2 text-right">
                      <Link to={`/preinspection/vehicle/${item.id}`} className="text-blue-600 hover:underline text-sm font-medium">View / Edit</Link>
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
