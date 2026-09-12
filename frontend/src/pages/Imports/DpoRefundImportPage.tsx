import { useState, useRef } from 'react'
import { useSearchParams } from 'react-router-dom'
import { useImportActivities } from '../../hooks/useExcelImports'
import LoadingSpinner from '../../components/common/LoadingSpinner'
import { fmtDate } from '../../utils/format'
import EmptyState from '../../components/common/EmptyState'

export default function DpoRefundImportPage() {
  const [searchParams, setSearchParams] = useSearchParams()
  const [jumpPage, setJumpPage] = useState('')
  const [dragOver, setDragOver] = useState(false)
  const fileRef = useRef<HTMLInputElement>(null)

  const filters = {
    status:   3,
    search:   searchParams.get('search') || undefined,
    per_page: 25,
    page:     Number(searchParams.get('page') || '1'),
  }

  const { data, isLoading, isFetching } = useImportActivities(filters)

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

  function handleFileDrop(e: React.DragEvent) {
    e.preventDefault()
    setDragOver(false)
    const files = e.dataTransfer.files
    if (files.length > 0) handleFileUpload(files[0])
  }

  function handleFileUpload(_file: File) {
    alert(`File "${_file.name}" selected. Upload API integration pending.`)
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
        <h1 className="text-2xl font-bold text-gray-800">DPO Refund Import</h1>
      </div>

      {/* Upload zone */}
      <div
        className={`border-2 border-dashed rounded-lg p-8 text-center transition ${dragOver ? 'border-blue-500 bg-blue-50' : 'border-gray-300 bg-white'}`}
        onDragOver={e => { e.preventDefault(); setDragOver(true) }}
        onDragLeave={() => setDragOver(false)}
        onDrop={handleFileDrop}
      >
        <div className="text-gray-400 mb-2">
          <svg className="w-10 h-10 mx-auto" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={1.5} d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12" /></svg>
        </div>
        <p className="text-sm text-gray-500 mb-1">Drag and drop your DPO refund Excel file here, or</p>
        <button onClick={() => fileRef.current?.click()} className="px-4 py-2 bg-blue-600 text-white rounded-md text-sm font-medium hover:bg-blue-700 transition">Browse Files</button>
        <input ref={fileRef} type="file" accept=".xlsx,.xls,.csv" className="hidden" onChange={e => { if (e.target.files?.[0]) handleFileUpload(e.target.files[0]) }} />
        <p className="text-xs text-gray-400 mt-2">Accepts .xlsx, .xls, .csv files</p>
      </div>

      <div className="flex flex-wrap gap-3 items-end">
        <div>
          <label className="block text-xs font-medium text-gray-500 mb-1">Search</label>
          <input
            type="text"
            placeholder="File name, added by..."
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
                <th className="px-4 py-3 text-left">ID</th>
                <th className="px-4 py-3 text-left">Uploaded File</th>
                <th className="px-4 py-3 text-left">Perform File</th>
                <th className="px-4 py-3 text-left">Added By</th>
                <th className="px-4 py-3 text-left">Date</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-gray-100">
              {isLoading ? (
                <tr><td colSpan={5} className="px-4 py-12 text-center"><LoadingSpinner size="md" /></td></tr>
              ) : data?.data.length === 0 ? (
                <tr><td colSpan={5} className="p-0"><EmptyState compact title="No DPO refund imports found" description="Try adjusting your filters." /></td></tr>
              ) : (
                data?.data.map(item => (
                  <tr key={item.id} className="hover:bg-gray-50 transition">
                    <td className="px-4 py-2 font-medium text-gray-700">{item.id}</td>
                    <td className="px-4 py-2 text-blue-600 truncate max-w-[200px]" title={item.uploadedFile ?? ''}>{item.uploadedFile || '\u2014'}</td>
                    <td className="px-4 py-2 truncate max-w-[200px]" title={item.performFile ?? ''}>{item.performFile || '\u2014'}</td>
                    <td className="px-4 py-2">{item.addedBy || '\u2014'}</td>
                    <td className="px-4 py-2 text-gray-500">{fmtDate(item.createdAt)}</td>
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
