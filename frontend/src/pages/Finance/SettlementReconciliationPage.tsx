import { useEffect, useState } from 'react'
import { Link } from 'react-router-dom'
import apiClient from '../../api/client'
import { fmtPulaSigned } from '../../utils/format'

type Run = {
  id: number
  provider: 'dpo' | 'realpay'
  file_name: string
  status: string
  settlement_date_from: string | null
  settlement_date_to: string | null
  currency: string
  batch_count: number
  transaction_count: number
  matched_count: number
  unmatched_count: number
  drift_count: number
  drift_total: number
  total_dpo_amount: number
  total_local_amount: number
  uploaded_at: string
  reviewed_at: string | null
}

const STATUS_PILL: Record<string, string> = {
  uploaded: 'bg-gray-100 text-gray-700',
  parsing:  'bg-blue-100 text-blue-700',
  parsed:   'bg-blue-100 text-blue-700',
  matched:  'bg-yellow-100 text-yellow-700',
  reviewed: 'bg-indigo-100 text-indigo-700',
  closed:   'bg-green-100 text-green-700',
  failed:   'bg-red-100 text-red-700',
}

export default function SettlementReconciliationPage() {
  const [runs, setRuns] = useState<Run[]>([])
  const [loading, setLoading] = useState(true)
  const [providerFilter, setProviderFilter] = useState<'all' | 'dpo' | 'realpay'>('all')
  const [statusFilter, setStatusFilter] = useState<'all' | 'open' | 'closed'>('all')
  const [uploadOpen, setUploadOpen] = useState(false)
  const [uploading, setUploading] = useState(false)
  const [uploadError, setUploadError] = useState<string | null>(null)
  const [provider, setProvider] = useState<'dpo' | 'realpay'>('dpo')
  const [file, setFile] = useState<File | null>(null)

  function load() {
    setLoading(true)
    const params: any = { limit: 50 }
    if (providerFilter !== 'all') params.provider = providerFilter
    if (statusFilter !== 'all') params.status = statusFilter
    apiClient.get('/finance/settlement-reconciliation/runs', { params })
      .then(r => setRuns(r.data?.data ?? []))
      .catch(() => setRuns([]))
      .finally(() => setLoading(false))
  }
  useEffect(load, [providerFilter, statusFilter])

  async function handleUpload() {
    if (!file) return
    setUploading(true); setUploadError(null)
    try {
      const fd = new FormData()
      fd.append('file', file)
      fd.append('provider', provider)
      // Upload completes in <5s — server detaches and processes in background
      const r = await apiClient.post('/finance/settlement-reconciliation/upload', fd, {
        headers: { 'Content-Type': 'multipart/form-data' },
        timeout: 60000,
      })
      const runId = r.data.run_id
      if (r.data.duplicate) {
        alert(`This file was already uploaded — opening existing run #${runId}.`)
        setUploadOpen(false); setFile(null); load()
        window.location.hash = `#/finance/settlement-reconciliation/${runId}`
        return
      }
      // Poll the run until parse+match completes (status flips from "parsing")
      // Typical run on an 11k-row CSV: ~75s. Poll every 5s up to 5 min.
      let attempts = 0
      const maxAttempts = 60
      const poll = async (): Promise<void> => {
        attempts++
        try {
          const s = await apiClient.get(`/finance/settlement-reconciliation/runs/${runId}`)
          const status = s.data?.run?.status
          if (status === 'matched' || status === 'closed' || status === 'reviewed') {
            setUploadOpen(false); setFile(null); load()
            window.location.hash = `#/finance/settlement-reconciliation/${runId}`
            return
          }
          if (status === 'failed') {
            setUploadError(`Processing failed: ${s.data?.run?.parse_error || 'see backend logs'}`)
            return
          }
          if (attempts >= maxAttempts) {
            setUploadError('Processing is taking longer than expected — check the runs list shortly.')
            return
          }
        } catch {
          // network blip, keep polling
        }
        await new Promise(res => setTimeout(res, 5000))
        return poll()
      }
      await poll()
    } catch (e: any) {
      setUploadError(e.response?.data?.message || 'Upload failed')
    } finally {
      setUploading(false)
    }
  }

  return (
    <div className="p-6 space-y-4">
      <div className="flex items-center justify-between">
        <div>
          <h1 className="text-2xl font-bold text-gray-800">Settlement Reconciliation</h1>
          <p className="text-sm text-gray-500 mt-1">
            Upload daily settlement CSV from DPO or RealPay. Matcher runs immediately and flags every transaction that doesn't match
            our payment records — zero tolerance. Each finding must be accepted or disputed before a run can be closed.
          </p>
        </div>
        <button
          onClick={() => setUploadOpen(true)}
          className="px-4 py-2 bg-blue-600 text-white text-sm rounded-md hover:bg-blue-700"
        >+ Upload Settlement File</button>
      </div>

      <div className="flex gap-2 items-center flex-wrap">
        <label className="text-xs font-medium text-gray-500">Provider:</label>
        {(['all', 'dpo', 'realpay'] as const).map(p => (
          <button key={p} onClick={() => setProviderFilter(p)}
            className={`px-3 py-1 text-xs rounded-md font-medium uppercase ${providerFilter === p ? 'bg-blue-600 text-white' : 'bg-gray-100 text-gray-600'}`}>{p}</button>
        ))}
        <span className="mx-3 text-gray-300">|</span>
        <label className="text-xs font-medium text-gray-500">Status:</label>
        {(['all', 'open', 'closed'] as const).map(s => (
          <button key={s} onClick={() => setStatusFilter(s)}
            className={`px-3 py-1 text-xs rounded-md font-medium uppercase ${statusFilter === s ? 'bg-blue-600 text-white' : 'bg-gray-100 text-gray-600'}`}>{s}</button>
        ))}
      </div>

      <div className="bg-white shadow rounded-lg overflow-hidden">
        <table className="min-w-full divide-y divide-gray-200 text-sm">
          <thead className="bg-gray-50">
            <tr>
              <th className="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">#</th>
              <th className="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Provider</th>
              <th className="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Period</th>
              <th className="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">File</th>
              <th className="px-3 py-2 text-right text-xs font-medium text-gray-500 uppercase">Tx</th>
              <th className="px-3 py-2 text-right text-xs font-medium text-gray-500 uppercase">Matched</th>
              <th className="px-3 py-2 text-right text-xs font-medium text-gray-500 uppercase">Findings</th>
              <th className="px-3 py-2 text-right text-xs font-medium text-gray-500 uppercase">Drift</th>
              <th className="px-3 py-2 text-center text-xs font-medium text-gray-500 uppercase">Status</th>
              <th className="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Uploaded</th>
              <th className="px-3 py-2 text-right text-xs font-medium text-gray-500 uppercase">Actions</th>
            </tr>
          </thead>
          <tbody className="divide-y divide-gray-200">
            {loading && <tr><td colSpan={11} className="px-3 py-8 text-center text-gray-400">Loading…</td></tr>}
            {!loading && runs.length === 0 && (
              <tr><td colSpan={11} className="px-3 py-8 text-center text-gray-400">
                No reconciliation runs yet. Upload your first settlement CSV to begin.
              </td></tr>
            )}
            {runs.map(r => {
              const findings = r.unmatched_count
              const driftBad = Math.abs(r.drift_total) > 0.005
              return (
                <tr key={r.id} className="hover:bg-gray-50">
                  <td className="px-3 py-2 font-mono text-xs">{r.id}</td>
                  <td className="px-3 py-2 text-xs uppercase font-medium">{r.provider}</td>
                  <td className="px-3 py-2 text-xs text-gray-700">
                    {r.settlement_date_from} {r.settlement_date_to && r.settlement_date_from !== r.settlement_date_to ? `→ ${r.settlement_date_to}` : ''}
                  </td>
                  <td className="px-3 py-2 text-xs text-gray-500 max-w-[260px] truncate" title={r.file_name}>{r.file_name}</td>
                  <td className="px-3 py-2 text-right text-xs">{r.transaction_count}</td>
                  <td className="px-3 py-2 text-right text-xs text-green-700">{r.matched_count}</td>
                  <td className={`px-3 py-2 text-right text-xs font-semibold ${findings ? 'text-red-700' : 'text-gray-400'}`}>
                    {findings || '—'}
                  </td>
                  <td className={`px-3 py-2 text-right text-xs font-mono ${driftBad ? 'text-red-700 font-semibold' : 'text-gray-500'}`}>
                    {r.drift_total == null ? '—' : fmtPulaSigned(r.drift_total)}
                  </td>
                  <td className="px-3 py-2 text-center">
                    <span className={`inline-block px-2 py-0.5 rounded-full text-xs font-medium ${STATUS_PILL[r.status] || 'bg-gray-100 text-gray-600'}`}>
                      {r.status}
                    </span>
                  </td>
                  <td className="px-3 py-2 text-xs text-gray-500">{r.uploaded_at?.replace('T', ' ').slice(0, 16)}</td>
                  <td className="px-3 py-2 text-right">
                    <Link to={`/finance/settlement-reconciliation/${r.id}`} className="text-sm text-blue-600 hover:underline">View</Link>
                  </td>
                </tr>
              )
            })}
          </tbody>
        </table>
      </div>

      {uploadOpen && (
        <div className="fixed inset-0 z-50 flex items-start justify-center bg-black/40 p-4 pt-10 overflow-y-auto" onClick={(e) => { if (e.target === e.currentTarget) { setUploadOpen(false); setFile(null); setUploadError(null) } }}>
          <div className="bg-white rounded-lg shadow-xl w-full max-w-lg p-5 space-y-3 max-h-[85vh] overflow-y-auto">
            <h2 className="text-lg font-bold">Upload Settlement File</h2>
            <p className="text-xs text-gray-500">
              Re-uploading the same file is safe — the system detects identical files by SHA-256 hash and returns the existing run instead of creating a duplicate.
            </p>
            <div>
              <label className="block text-xs font-medium text-gray-500 mb-1">Provider</label>
              <select value={provider} onChange={e => setProvider(e.target.value as any)}
                className="w-full px-3 py-2 border rounded-md text-sm">
                <option value="dpo">DPO Pay</option>
                <option value="realpay">RealPay</option>
              </select>
              {provider === 'realpay' && (
                <div className="text-[11px] text-yellow-700 bg-yellow-50 border border-yellow-200 rounded mt-2 p-2">
                  RealPay parser is not yet wired — upload will be rejected. Send a sample RealPay CSV to development to enable.
                </div>
              )}
            </div>
            <div>
              <label className="block text-xs font-medium text-gray-500 mb-1">CSV file</label>
              <input type="file" accept=".csv,text/csv" onChange={e => setFile(e.target.files?.[0] ?? null)}
                className="w-full text-sm" />
              {file && <div className="text-[11px] text-gray-500 mt-1">{file.name} · {(file.size / 1024).toFixed(1)} KB</div>}
            </div>
            {uploadError && (
              <div className="text-xs text-red-700 bg-red-50 border border-red-200 rounded p-2">{uploadError}</div>
            )}
            <div className="flex justify-end gap-2 pt-2">
              <button onClick={() => { setUploadOpen(false); setFile(null); setUploadError(null) }}
                className="px-4 py-2 text-sm rounded-md bg-gray-100 hover:bg-gray-200">Cancel</button>
              <button onClick={handleUpload} disabled={!file || uploading}
                className="px-4 py-2 text-sm rounded-md bg-blue-600 text-white hover:bg-blue-700 disabled:bg-gray-300">
                {uploading ? 'Parsing & matching…' : 'Upload & match'}
              </button>
            </div>
          </div>
        </div>
      )}
    </div>
  )
}
