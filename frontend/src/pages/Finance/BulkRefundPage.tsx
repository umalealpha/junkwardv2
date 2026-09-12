import { useState } from 'react'
import { Link, useNavigate } from 'react-router-dom'
import {
  useBulkBatches,
  useCreateBulkRefundFromRows,
  useCreateBulkRefundFromCsv,
} from '../../hooks/useRefunds'

type TabKey = 'new' | 'batches'

const REASON_CODES = [
  { value: 'wrong_customer',   label: 'Wrong customer debited' },
  { value: 'duplicate_charge', label: 'Duplicate charge' },
  { value: 'goodwill',         label: 'Goodwill / complaint resolution' },
  { value: 'policy_cancelled', label: 'Policy cancelled, refund due' },
  { value: 'other',            label: 'Other' },
]

/**
 * Bulk refund admin page.
 *
 * New batch tab:
 *   - Upload a CSV (header: payment_transaction_id,amount). Amount is
 *     optional; leave blank for full refund.
 *   - OR paste a list of payment_transaction_ids, one per line, with optional
 *     ",amount" suffix.
 *   - Set a reason + reason code that applies to every row.
 *   - "Create & run" queues the ProcessBulkRefundJob immediately.
 *   - "Create only" stages the batch — you can review before running.
 *
 * Batches tab:
 *   - List of all batches with live counters, link to detail.
 */
export default function BulkRefundPage() {
  const [tab, setTab] = useState<TabKey>('new')

  return (
    <div className="max-w-6xl mx-auto px-4 py-6 space-y-5">
      <div>
        <h1 className="text-xl font-bold text-gray-900">Bulk Refunds</h1>
        <p className="text-sm text-gray-500">Issue DPO refunds in bulk against already-settled payments.</p>
      </div>

      <div className="border-b flex gap-1 text-sm">
        <button
          className={`px-4 py-2 -mb-px border-b-2 ${tab==='new' ? 'border-indigo-600 text-indigo-600 font-medium' : 'border-transparent text-gray-600 hover:text-gray-800'}`}
          onClick={() => setTab('new')}
        >
          New batch
        </button>
        <button
          className={`px-4 py-2 -mb-px border-b-2 ${tab==='batches' ? 'border-indigo-600 text-indigo-600 font-medium' : 'border-transparent text-gray-600 hover:text-gray-800'}`}
          onClick={() => setTab('batches')}
        >
          Batches
        </button>
      </div>

      {tab === 'new' ? <NewBatchTab /> : <BatchesTab />}
    </div>
  )
}

// ─── New batch tab ────────────────────────────────────────────────────────────

function NewBatchTab() {
  const nav = useNavigate()
  const [inputMode, setInputMode] = useState<'paste' | 'csv'>('paste')
  const [csvFile, setCsvFile] = useState<File | null>(null)
  const [pasteText, setPasteText] = useState('')
  const [name, setName] = useState('')
  const [reasonCode, setReasonCode] = useState('wrong_customer')
  const [reason, setReason] = useState('')
  const [runNow, setRunNow] = useState(true)
  const [error, setError] = useState<string | null>(null)

  const fromRows = useCreateBulkRefundFromRows()
  const fromCsv  = useCreateBulkRefundFromCsv()

  function parsePaste(text: string) {
    const rows: Array<{ payment_transaction_id: number; amount?: number }> = []
    for (const rawLine of text.split(/\r?\n/)) {
      const line = rawLine.trim()
      if (!line || line.startsWith('#')) continue
      const [idStr, amtStr] = line.split(/[,\t;]/).map(p => p.trim())
      const id = parseInt(idStr, 10)
      if (!Number.isFinite(id) || id <= 0) continue
      const amt = amtStr ? parseFloat(amtStr) : undefined
      rows.push({ payment_transaction_id: id, amount: amt && Number.isFinite(amt) ? amt : undefined })
    }
    return rows
  }

  async function submit() {
    setError(null)
    try {
      if (inputMode === 'csv') {
        if (!csvFile) { setError('Choose a CSV file first'); return }
        const res = await fromCsv.mutateAsync({
          file: csvFile,
          name: name || undefined,
          reason: reason || undefined,
          reason_code: reasonCode,
          run_now: runNow,
        })
        nav(`/finance/bulk-refunds/${res.batch_id}`)
      } else {
        const rows = parsePaste(pasteText)
        if (rows.length === 0) { setError('No valid rows. Expect lines like "12345" or "12345,250.00"'); return }
        const res = await fromRows.mutateAsync({
          rows,
          name: name || undefined,
          reason: reason || undefined,
          reason_code: reasonCode,
          run_now: runNow,
        })
        nav(`/finance/bulk-refunds/${res.batch_id}`)
      }
    } catch (e: any) {
      setError(e?.response?.data?.error ?? e?.message ?? 'Failed to create batch')
    }
  }

  const pending = fromRows.isPending || fromCsv.isPending

  return (
    <div className="bg-white border border-gray-200 rounded-xl shadow-sm p-5 space-y-4">
      {/* Label */}
      <div>
        <label className="block text-xs font-medium text-gray-700 mb-1">Batch name</label>
        <input
          value={name} onChange={e => setName(e.target.value)}
          placeholder="e.g. Feb wrong-customer sweep"
          className="w-full border border-gray-300 rounded-md px-3 py-2 text-sm"
        />
      </div>

      {/* Input mode toggle */}
      <div className="flex gap-2">
        <button
          onClick={() => setInputMode('paste')}
          className={`px-3 py-1.5 text-xs rounded-md border ${inputMode==='paste' ? 'bg-indigo-50 border-indigo-300 text-indigo-700' : 'border-gray-300 text-gray-700'}`}
        >Paste list</button>
        <button
          onClick={() => setInputMode('csv')}
          className={`px-3 py-1.5 text-xs rounded-md border ${inputMode==='csv' ? 'bg-indigo-50 border-indigo-300 text-indigo-700' : 'border-gray-300 text-gray-700'}`}
        >Upload CSV</button>
      </div>

      {inputMode === 'paste' ? (
        <div>
          <label className="block text-xs font-medium text-gray-700 mb-1">Payment transaction IDs</label>
          <textarea
            value={pasteText}
            onChange={e => setPasteText(e.target.value)}
            rows={8}
            placeholder={'# One per line. Amount is optional — leave blank for full refund.\n12345\n12346,250.00\n12347'}
            className="w-full border border-gray-300 rounded-md px-3 py-2 text-sm font-mono"
          />
          <p className="text-xs text-gray-500 mt-1">One row per payment. Optional comma-separated amount for partial refunds.</p>
        </div>
      ) : (
        <div>
          <label className="block text-xs font-medium text-gray-700 mb-1">CSV file</label>
          <input
            type="file" accept=".csv,text/csv"
            onChange={e => setCsvFile(e.target.files?.[0] ?? null)}
            className="block w-full text-sm text-gray-500 file:mr-3 file:py-2 file:px-4 file:rounded-md file:border file:border-gray-300 file:text-sm file:font-medium file:bg-gray-50 file:text-gray-700 hover:file:bg-gray-100"
          />
          <p className="text-xs text-gray-500 mt-1">
            Header row: <code className="font-mono">payment_transaction_id,amount</code>.
            Amount column is optional.
          </p>
        </div>
      )}

      <div className="grid grid-cols-2 gap-3">
        <div>
          <label className="block text-xs font-medium text-gray-700 mb-1">Reason code (applies to all rows)</label>
          <select value={reasonCode} onChange={e => setReasonCode(e.target.value)}
                  className="w-full border border-gray-300 rounded-md px-3 py-2 text-sm">
            {REASON_CODES.map(r => <option key={r.value} value={r.value}>{r.label}</option>)}
          </select>
        </div>
        <div>
          <label className="block text-xs font-medium text-gray-700 mb-1">Notes / ticket ref</label>
          <input
            value={reason} onChange={e => setReason(e.target.value)}
            maxLength={500}
            className="w-full border border-gray-300 rounded-md px-3 py-2 text-sm"
            placeholder="JIRA-123, call 2026-04-18, etc"
          />
        </div>
      </div>

      <label className="flex items-center gap-2 text-sm">
        <input type="checkbox" checked={runNow} onChange={e => setRunNow(e.target.checked)} />
        <span>Run immediately after creating</span>
      </label>

      {error && <div className="bg-red-50 border border-red-200 rounded-md px-3 py-2 text-sm text-red-700">{error}</div>}

      <div className="flex justify-end gap-2">
        <Link to="/finance/bulk-refunds" className="px-4 py-2 text-sm border rounded hover:bg-gray-100">Cancel</Link>
        <button
          onClick={submit} disabled={pending}
          className="px-4 py-2 text-sm bg-indigo-600 text-white rounded hover:bg-indigo-700 disabled:opacity-50"
        >
          {pending ? 'Creating…' : (runNow ? 'Create & run' : 'Create batch')}
        </button>
      </div>
    </div>
  )
}

// ─── Batches list tab ─────────────────────────────────────────────────────────

function BatchesTab() {
  const { data, isLoading, error } = useBulkBatches({ per_page: 25 })

  if (isLoading) return <div className="text-gray-500">Loading batches…</div>
  if (error)     return <div className="text-red-600">Failed to load batches</div>
  if (!data || data.data.length === 0) {
    return (
      <div className="bg-white border border-gray-200 rounded-xl p-8 text-center text-sm text-gray-500">
        No bulk refund batches yet. Use the <strong>New batch</strong> tab to start one.
      </div>
    )
  }

  return (
    <div className="bg-white border border-gray-200 rounded-xl shadow-sm overflow-hidden">
      <table className="w-full text-sm">
        <thead className="bg-gray-50 border-b">
          <tr className="text-left text-xs text-gray-600">
            <th className="px-4 py-2">#</th>
            <th className="px-4 py-2">Name</th>
            <th className="px-4 py-2">Status</th>
            <th className="px-4 py-2 text-right">Total / OK / Failed / Pending</th>
            <th className="px-4 py-2 text-right">Amount refunded</th>
            <th className="px-4 py-2">Created</th>
            <th className="px-4 py-2"></th>
          </tr>
        </thead>
        <tbody className="divide-y">
          {data.data.map(b => (
            <tr key={b.id}>
              <td className="px-4 py-2 font-mono text-xs">{b.id}</td>
              <td className="px-4 py-2">
                <div className="font-medium">{b.name}</div>
                {b.reason_code && <div className="text-xs text-gray-500">{b.reason_code}</div>}
              </td>
              <td className="px-4 py-2"><StatusPill status={b.status} /></td>
              <td className="px-4 py-2 text-right text-xs">
                <div>{b.total_count} / <span className="text-green-700">{b.success_count}</span> / <span className="text-red-700">{b.failed_count}</span> / <span className="text-amber-700">{b.pending_count}</span></div>
              </td>
              <td className="px-4 py-2 text-right text-xs">P{Number(b.refunded_amount ?? 0).toFixed(2)} / P{Number(b.total_amount ?? 0).toFixed(2)}</td>
              <td className="px-4 py-2 text-xs text-gray-500">{b.created_at?.slice(0, 16).replace('T', ' ')}</td>
              <td className="px-4 py-2 text-right">
                <Link to={`/finance/bulk-refunds/${b.id}`} className="text-indigo-600 hover:text-indigo-800 text-sm">Open →</Link>
              </td>
            </tr>
          ))}
        </tbody>
      </table>
    </div>
  )
}

function StatusPill({ status }: { status: string }) {
  const map: Record<string, string> = {
    draft:      'bg-gray-100 text-gray-700',
    queued:     'bg-blue-100 text-blue-700',
    processing: 'bg-amber-100 text-amber-700 animate-pulse',
    completed:  'bg-green-100 text-green-700',
    failed:     'bg-red-100 text-red-700',
    cancelled:  'bg-gray-200 text-gray-600',
  }
  return <span className={`px-2 py-0.5 rounded text-xs font-medium ${map[status] ?? 'bg-gray-100 text-gray-700'}`}>{status}</span>
}
