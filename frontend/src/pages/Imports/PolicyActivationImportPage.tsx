import { useState, useRef } from 'react'
import { useSearchParams } from 'react-router-dom'
import { useQueryClient } from '@tanstack/react-query'
import { useImportActivities } from '../../hooks/useExcelImports'
import { uploadExcelImport } from '../../api/excelImports'
import LoadingSpinner from '../../components/common/LoadingSpinner'
import { fmtDate } from '../../utils/format'
import EmptyState from '../../components/common/EmptyState'

export default function PolicyActivationImportPage() {
  const [searchParams, setSearchParams] = useSearchParams()
  const [jumpPage, setJumpPage] = useState('')
  const [dragOver, setDragOver] = useState(false)
  const [uploading, setUploading] = useState(false)
  const [uploadSuccess, setUploadSuccess] = useState<string | null>(null)
  const [uploadError, setUploadError] = useState<string | null>(null)
  const fileRef = useRef<HTMLInputElement>(null)
  const queryClient = useQueryClient()

  const filters = {
    status:   1,
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

  async function handleFileUpload(file: File) {
    setUploading(true)
    setUploadError(null)
    setUploadSuccess(null)
    // Reset file input so the same file can be re-selected if needed
    if (fileRef.current) fileRef.current.value = ''
    try {
      const result = await uploadExcelImport(file, 'activation')
      setUploadSuccess(`"${result.fileName}" uploaded successfully. Processing queued (ID: ${result.id}).`)
      queryClient.invalidateQueries({ queryKey: ['import-activities'] })
    } catch (err: any) {
      const msg = err?.response?.data?.error || err?.response?.data?.message || 'Upload failed. Please try again.'
      setUploadError(msg)
    } finally {
      setUploading(false)
    }
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
        <h1 className="text-2xl font-bold text-gray-800">Policy Activation Import</h1>
      </div>

      {/* Upload zone */}
      <div
        className={`border-2 border-dashed rounded-lg p-8 text-center transition ${dragOver ? 'border-blue-500 bg-blue-50' : 'border-gray-300 bg-white'} ${uploading ? 'opacity-60 pointer-events-none' : ''}`}
        onDragOver={e => { e.preventDefault(); setDragOver(true) }}
        onDragLeave={() => setDragOver(false)}
        onDrop={handleFileDrop}
      >
        <div className="text-gray-400 mb-2">
          {uploading ? (
            <LoadingSpinner size="md" />
          ) : (
            <svg className="w-10 h-10 mx-auto" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={1.5} d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12" />
            </svg>
          )}
        </div>
        {uploading ? (
          <p className="text-sm text-blue-600 font-medium">Uploading and processing...</p>
        ) : (
          <>
            <p className="text-sm text-gray-500 mb-1">Drag and drop your Excel file here, or</p>
            <button
              onClick={() => fileRef.current?.click()}
              className="px-4 py-2 bg-blue-600 text-white rounded-md text-sm font-medium hover:bg-blue-700 transition"
            >
              Browse Files
            </button>
          </>
        )}
        <input
          ref={fileRef}
          type="file"
          accept=".xlsx,.xls,.csv"
          className="hidden"
          onChange={e => { if (e.target.files?.[0]) handleFileUpload(e.target.files[0]) }}
        />
        <p className="text-xs text-gray-400 mt-2">
          Accepts .xlsx, .xls, .csv — Column A: policy number. One policy per row.
        </p>
      </div>

      {/* Upload feedback */}
      {uploadSuccess && (
        <div className="flex items-start gap-2 bg-green-50 border border-green-200 text-green-800 rounded-lg px-4 py-3 text-sm">
          <svg className="w-4 h-4 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fillRule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clipRule="evenodd" /></svg>
          <span>{uploadSuccess}</span>
          <button onClick={() => setUploadSuccess(null)} className="ml-auto text-green-600 hover:text-green-800">✕</button>
        </div>
      )}
      {uploadError && (
        <div className="flex items-start gap-2 bg-red-50 border border-red-200 text-red-800 rounded-lg px-4 py-3 text-sm">
          <svg className="w-4 h-4 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fillRule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clipRule="evenodd" /></svg>
          <span>{uploadError}</span>
          <button onClick={() => setUploadError(null)} className="ml-auto text-red-600 hover:text-red-800">✕</button>
        </div>
      )}

      {/* Filters */}
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
                <tr><td colSpan={5} className="p-0"><EmptyState compact title="No activation imports found" description="Try adjusting your filters." /></td></tr>
              ) : (
                data?.data.map(item => (
                  <tr key={item.id} className="hover:bg-gray-50 transition">
                    <td className="px-4 py-2 font-medium text-gray-700">{item.id}</td>
                    <td className="px-4 py-2 text-blue-600 truncate max-w-[200px]" title={item.uploadedFile ?? ''}>{item.uploadedFile || '—'}</td>
                    <td className="px-4 py-2 truncate max-w-[200px]" title={item.performFile ?? ''}>
                      {item.performFile
                        ? <span className="text-green-600 font-medium">{item.performFile}</span>
                        : <span className="text-yellow-600 text-xs">Processing...</span>
                      }
                    </td>
                    <td className="px-4 py-2">{item.addedBy || '—'}</td>
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
            <span className="text-gray-500">Showing {meta.from}–{meta.to} of {meta.total}</span>
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
