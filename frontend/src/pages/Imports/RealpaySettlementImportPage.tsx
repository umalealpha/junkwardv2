import { useState, useRef } from 'react'
import { useSearchParams } from 'react-router-dom'
import { useQueryClient } from '@tanstack/react-query'
import { useSettlementImports, useConfirmSettlementImport } from '../../hooks/useRealpaySettlement'
import { uploadSettlement, type SettlementImport, type SettlementSummary } from '../../api/realpaySettlement'
import LoadingSpinner from '../../components/common/LoadingSpinner'
import EmptyState from '../../components/common/EmptyState'
import { fmtDate } from '../../utils/format'

const STATUS_STYLES: Record<string, string> = {
  uploaded:      'bg-surface-2 text-ink-muted',
  previewing:    'bg-blue-100 text-blue-700',
  preview_ready: 'bg-amber-100 text-amber-700',
  committing:    'bg-indigo-100 text-indigo-700',
  committed:     'bg-green-100 text-green-700',
  failed:        'bg-red-100 text-red-700',
}

function summaryText(s: SettlementSummary | null): string {
  if (!s) return '—'
  const parts = [
    `stored ${s.tx_stored ?? 0}`,
    `existing ${s.tx_existing ?? 0}`,
    `no-match ${s.no_matching_installment ?? 0}`,
    `no-contract ${s.no_realpay_contract ?? 0}`,
    `no-policy ${s.policy_not_found ?? 0}`,
    `errors ${s.errors ?? 0}`,
  ]
  return parts.join(' · ')
}

export default function RealpaySettlementImportPage() {
  const [searchParams, setSearchParams] = useSearchParams()
  const [dragOver, setDragOver] = useState(false)
  const [uploading, setUploading] = useState(false)
  const [msg, setMsg] = useState<{ kind: 'ok' | 'err'; text: string } | null>(null)
  const fileRef = useRef<HTMLInputElement>(null)
  const queryClient = useQueryClient()
  const confirmImport = useConfirmSettlementImport()

  const filters = {
    search:   searchParams.get('search') || undefined,
    per_page: 25,
    page:     Number(searchParams.get('page') || '1'),
  }
  const { data, isLoading, isFetching } = useSettlementImports(filters)

  function updateFilter(key: string, value: string) {
    const next = new URLSearchParams(searchParams)
    if (value) next.set(key, value); else next.delete(key)
    next.delete('page')
    setSearchParams(next)
  }

  async function handleFileUpload(file: File) {
    setUploading(true); setMsg(null)
    if (fileRef.current) fileRef.current.value = ''
    try {
      const res = await uploadSettlement(file)
      setMsg({ kind: 'ok', text: res.message || `"${res.data.fileName}" uploaded — preview running.` })
      queryClient.invalidateQueries({ queryKey: ['realpay-settlement-imports'] })
    } catch (err: any) {
      setMsg({ kind: 'err', text: err?.response?.data?.error || err?.response?.data?.message || 'Upload failed.' })
    } finally {
      setUploading(false)
    }
  }

  async function handleConfirm(item: SettlementImport) {
    const p = item.previewSummary
    const willWrite = (p?.tx_stored ?? 0) + (p?.contract_stored ?? 0)
    const ok = window.confirm(
      `Commit import "${item.fileName}"?\n\n` +
      `This will WRITE ~${p?.tx_stored ?? 0} transactions and ${p?.contract_stored ?? 0} contracts to the live database.\n` +
      `(${willWrite} new records. Existing ones are skipped.)\n\nThis cannot be undone from here.`,
    )
    if (!ok) return
    try {
      await confirmImport.mutateAsync(item.id)
      setMsg({ kind: 'ok', text: `Import "${item.fileName}" is committing in the background.` })
    } catch (err: any) {
      setMsg({ kind: 'err', text: err?.response?.data?.error || 'Confirm failed.' })
    }
  }

  return (
    <div className="p-6 space-y-4">
      <div>
        <h1 className="text-2xl font-bold text-ink">RealPay Settlement Import</h1>
        <p className="text-sm text-ink-muted mt-1">
          Upload a RealPay settlement export. It runs a <strong>dry-run preview</strong> first — review the
          counts, then <strong>Confirm</strong> to write the contracts + transactions. Existing records are skipped.
        </p>
      </div>

      {/* Upload zone */}
      <div
        className={`border-2 border-dashed rounded-lg p-8 text-center transition ${dragOver ? 'border-blue-500 bg-blue-50' : 'border-line bg-surface'} ${uploading ? 'opacity-60 pointer-events-none' : ''}`}
        onDragOver={e => { e.preventDefault(); setDragOver(true) }}
        onDragLeave={() => setDragOver(false)}
        onDrop={e => { e.preventDefault(); setDragOver(false); if (e.dataTransfer.files[0]) handleFileUpload(e.dataTransfer.files[0]) }}
      >
        <div className="text-ink-faint mb-2">
          {uploading ? <LoadingSpinner size="md" /> : (
            <svg className="w-10 h-10 mx-auto" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={1.5} d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12" />
            </svg>
          )}
        </div>
        {uploading ? (
          <p className="text-sm text-blue-600 font-medium">Uploading...</p>
        ) : (
          <>
            <p className="text-sm text-ink-muted mb-1">Drag &amp; drop the settlement Excel here, or</p>
            <button onClick={() => fileRef.current?.click()} className="px-4 py-2 bg-blue-600 text-white rounded-md text-sm font-medium hover:bg-blue-700 transition">Browse Files</button>
          </>
        )}
        <input ref={fileRef} type="file" accept=".xlsx,.xls,.csv" className="hidden"
          onChange={e => { if (e.target.files?.[0]) handleFileUpload(e.target.files[0]) }} />
        <p className="text-xs text-ink-faint mt-2">Accepts .xlsx, .xls, .csv — columns incl. Graphite Policy Number, Contract Number, Amount, Date, Status.</p>
      </div>

      {msg && (
        <div className={`flex items-start gap-2 rounded-lg px-4 py-3 text-sm border ${msg.kind === 'ok' ? 'bg-green-50 border-green-200 text-green-800' : 'bg-red-50 border-red-200 text-red-800'}`}>
          <span>{msg.text}</span>
          <button onClick={() => setMsg(null)} className="ml-auto opacity-70 hover:opacity-100">✕</button>
        </div>
      )}

      {/* Filters */}
      <div className="flex flex-wrap gap-3 items-end">
        <div>
          <label className="block text-xs font-medium text-ink-muted mb-1">Search</label>
          <input type="text" placeholder="File name, uploaded by..." defaultValue={filters.search}
            onKeyDown={e => { if (e.key === 'Enter') updateFilter('search', (e.target as HTMLInputElement).value) }}
            onBlur={e => updateFilter('search', e.target.value)}
            className="px-3 py-1.5 border border-line rounded-md text-sm w-64 focus:ring-1 focus:ring-blue-500 focus:border-blue-500" />
        </div>
      </div>

      {/* Table */}
      <div className="bg-surface rounded-lg shadow-sm border overflow-hidden relative">
        {(isLoading || isFetching) && (
          <div className="absolute top-2 right-2 z-10"><LoadingSpinner size="sm" /></div>
        )}
        <div className="overflow-x-auto">
          <table className="w-full text-sm">
            <thead className="bg-surface-2 text-ink-muted uppercase text-xs tracking-wider">
              <tr>
                <th className="px-4 py-3 text-left">ID</th>
                <th className="px-4 py-3 text-left">File</th>
                <th className="px-4 py-3 text-left">Uploaded By</th>
                <th className="px-4 py-3 text-left">Status</th>
                <th className="px-4 py-3 text-left">Summary</th>
                <th className="px-4 py-3 text-left">Date</th>
                <th className="px-4 py-3 text-left">Action</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-line">
              {isLoading ? (
                <tr><td colSpan={7} className="px-4 py-12 text-center"><LoadingSpinner size="md" /></td></tr>
              ) : data?.data.length === 0 ? (
                <tr><td colSpan={7} className="p-0"><EmptyState compact title="No settlement imports yet" description="Upload a file to get started." /></td></tr>
              ) : (
                data?.data.map(item => {
                  const isPreview = item.status === 'preview_ready'
                  const shown = item.commitSummary ?? item.previewSummary
                  return (
                    <tr key={item.id} className="hover:bg-surface-2 transition align-top">
                      <td className="px-4 py-2 font-medium text-ink">{item.id}</td>
                      <td className="px-4 py-2 text-blue-600 truncate max-w-[200px]" title={item.fileName}>{item.fileName}</td>
                      <td className="px-4 py-2">{item.uploadedBy || '—'}</td>
                      <td className="px-4 py-2">
                        <span className={`px-2 py-0.5 rounded-full text-xs font-medium ${STATUS_STYLES[item.status] ?? 'bg-surface-2 text-ink-muted'}`}>
                          {item.status.replace('_', ' ')}
                        </span>
                        {item.error && <div className="text-xs text-red-600 mt-1 max-w-[240px]">{item.error}</div>}
                      </td>
                      <td className="px-4 py-2 text-xs text-ink-muted max-w-[320px]">
                        {shown ? <span title={item.commitSummary ? 'committed' : 'preview'}>{summaryText(shown)}</span> : '—'}
                      </td>
                      <td className="px-4 py-2 text-ink-muted">{fmtDate(item.createdAt)}</td>
                      <td className="px-4 py-2">
                        {isPreview ? (
                          <button
                            onClick={() => handleConfirm(item)}
                            disabled={confirmImport.isPending}
                            className="px-3 py-1.5 bg-green-600 text-white rounded-md text-xs font-semibold hover:bg-green-700 disabled:opacity-50"
                          >
                            Confirm import
                          </button>
                        ) : (
                          <span className="text-xs text-ink-faint">
                            {item.status === 'committed' ? 'Done' : item.status === 'failed' ? 'Failed' : 'Working…'}
                          </span>
                        )}
                      </td>
                    </tr>
                  )
                })
              )}
            </tbody>
          </table>
        </div>
      </div>
    </div>
  )
}
